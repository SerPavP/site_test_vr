#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT_NAME="${PROJECT_NAME:-pdd-test-site}"
SERVICE_NAME="${SERVICE_NAME:-pdd-test-site}"
APP_USER="${APP_USER:-www-data}"
APP_GROUP="${APP_GROUP:-www-data}"
REPO_URL="${REPO_URL:-}"
INSTALL_DIR="${INSTALL_DIR:-/var/www/pdd-test-site}"
SERVER_IP="${SERVER_IP:-35.254.178.48}"
SERVER_NAME="${SERVER_NAME:-_}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ -f "$SCRIPT_DIR/config/config.sample.php" && -d "$SCRIPT_DIR/public" && -d "$SCRIPT_DIR/api" ]]; then
    PROJECT_DIR="${PROJECT_DIR:-$SCRIPT_DIR}"
else
    PROJECT_DIR="${PROJECT_DIR:-$INSTALL_DIR}"
fi

DB_NAME="${DB_NAME:-pdd_test}"
DB_USER="${DB_USER:-pdd_user}"
DB_PASSWORD="${DB_PASSWORD:-pdd_password_change_me}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

DEMO_ADMIN_USERNAME="${DEMO_ADMIN_USERNAME:-admin}"
DEMO_ADMIN_PASSWORD="${DEMO_ADMIN_PASSWORD:-admin12345}"
DEMO_TEACHER_USERNAME="${DEMO_TEACHER_USERNAME:-teacher1}"
DEMO_TEACHER_PASSWORD="${DEMO_TEACHER_PASSWORD:-teacher12345}"

CONFIG_SAMPLE_FILE="$PROJECT_DIR/config/config.sample.php"
CONFIG_FILE="$PROJECT_DIR/config/config.php"
SCHEMA_FILE="$PROJECT_DIR/database/schema.sql"
BOOTSTRAP_FILE="$PROJECT_DIR/includes/bootstrap.php"
PHP_BIN=""
PHP_FPM_SERVICE=""
PHP_FPM_SOCKET=""
PHP_VERSION=""
API_KEY=""

if [[ "$(id -u)" -eq 0 ]]; then
    SUDO=""
else
    SUDO="sudo"
fi

info() {
    printf '\033[0;34m[INFO]\033[0m %s\n' "$1"
}

ok() {
    printf '\033[0;32m[OK]\033[0m %s\n' "$1"
}

warn() {
    printf '\033[1;33m[WARN]\033[0m %s\n' "$1"
}

fail() {
    printf '\033[0;31m[ERROR]\033[0m %s\n' "$1" >&2
    exit 1
}

on_error() {
    local exit_code=$?
    fail "Deploy failed on line $1. Exit code: $exit_code"
}

trap 'on_error $LINENO' ERR

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "Required command not found: $1"
}

require_project_root() {
    [[ -f "$CONFIG_SAMPLE_FILE" ]] || fail "config/config.sample.php not found in $PROJECT_DIR"
    [[ -f "$SCHEMA_FILE" ]] || fail "database/schema.sql not found in $PROJECT_DIR"
    [[ -f "$BOOTSTRAP_FILE" ]] || fail "includes/bootstrap.php not found in $PROJECT_DIR"
    [[ -d "$PROJECT_DIR/public" ]] || fail "public directory not found in $PROJECT_DIR"
    [[ -d "$PROJECT_DIR/api" ]] || fail "api directory not found in $PROJECT_DIR"
}

reset_runtime_files_before_pull() {
    if [[ ! -d "$PROJECT_DIR/.git" ]]; then
        return
    fi

    info "Resetting generated runtime files before git update"
    $SUDO git -C "$PROJECT_DIR" restore --worktree --staged -- \
        config/config.php \
        logs/api.log \
        logs/api.log.jsonl \
        logs/audit_fallback.jsonl \
        logs/ratelimit.json \
        logs/results_fallback.jsonl 2>/dev/null || true
}

install_system_packages() {
    info "Installing Debian packages"
    $SUDO apt-get update
    $SUDO env DEBIAN_FRONTEND=noninteractive apt-get install -y \
        nginx \
        mariadb-server \
        php-fpm \
        php-mysql \
        php-cli \
        git \
        curl
    ok "System packages installed"
}

prepare_project_code() {
    if [[ -f "$CONFIG_SAMPLE_FILE" && -d "$PROJECT_DIR/.git" ]]; then
        reset_runtime_files_before_pull
        info "Updating existing git checkout in $PROJECT_DIR"
        $SUDO git -C "$PROJECT_DIR" pull --ff-only
        ok "Repository updated"
        return
    fi

    if [[ -f "$CONFIG_SAMPLE_FILE" ]]; then
        info "Using existing project directory: $PROJECT_DIR"
        return
    fi

    [[ -n "$REPO_URL" ]] || fail "REPO_URL must be provided when project is not already present on the server."

    info "Cloning project from $REPO_URL to $PROJECT_DIR"
    $SUDO mkdir -p "$(dirname "$PROJECT_DIR")"
    $SUDO git clone "$REPO_URL" "$PROJECT_DIR"
    ok "Project cloned"
}

detect_php_runtime() {
    PHP_BIN="$(command -v php || true)"
    [[ -n "$PHP_BIN" ]] || fail "php executable was not found after package installation."

    PHP_FPM_SERVICE="$(
        systemctl list-unit-files --type=service --no-legend \
            | awk '/^php[0-9]+\.[0-9]+-fpm\.service/ {print $1}' \
            | head -n 1
    )"
    [[ -n "$PHP_FPM_SERVICE" ]] || fail "Could not detect php-fpm systemd service."

    PHP_VERSION="${PHP_FPM_SERVICE#php}"
    PHP_VERSION="${PHP_VERSION%-fpm.service}"
    PHP_FPM_SOCKET="/run/php/php${PHP_VERSION}-fpm.sock"

    [[ -S "$PHP_FPM_SOCKET" || ! -e "$PHP_FPM_SOCKET" ]] || fail "Unexpected php-fpm socket path: $PHP_FPM_SOCKET"
    ok "Detected PHP CLI: $PHP_BIN and service: $PHP_FPM_SERVICE"
}

prepare_runtime_directories() {
    info "Preparing writable runtime directories"
    $SUDO mkdir -p "$PROJECT_DIR/logs" "$PROJECT_DIR/logs/sessions"
    $SUDO chown -R "$APP_USER:$APP_GROUP" "$PROJECT_DIR/logs"
    $SUDO find "$PROJECT_DIR/logs" -type d -exec chmod 775 {} \;
    $SUDO find "$PROJECT_DIR/logs" -type f -exec chmod 664 {} \; 2>/dev/null || true
    ok "Runtime directories are ready"
}

setup_mariadb() {
    info "Configuring MariaDB"
    $SUDO systemctl enable --now mariadb

    $SUDO mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

    sed -e '/^CREATE DATABASE IF NOT EXISTS /d' -e '/^USE /d' "$SCHEMA_FILE" \
        | $SUDO mysql --protocol=TCP -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "--password=$DB_PASSWORD" "$DB_NAME"

    ok "MariaDB database is ready"
}

write_config_file() {
    info "Writing production config.php"

    API_KEY="$("$PHP_BIN" -r 'echo "pdd_live_" . bin2hex(random_bytes(32));')"

    $SUDO "$PHP_BIN" -r '
        $sample = $argv[1];
        $target = $argv[2];
        $baseUrl = $argv[3];
        $dbHost = $argv[4];
        $dbPort = $argv[5];
        $dbName = $argv[6];
        $dbUser = $argv[7];
        $dbPassword = $argv[8];
        $apiKey = $argv[9];

        $config = require $sample;
        $config["base_url"] = $baseUrl;
        $config["db"]["host"] = $dbHost;
        $config["db"]["port"] = $dbPort;
        $config["db"]["database"] = $dbName;
        $config["db"]["username"] = $dbUser;
        $config["db"]["password"] = $dbPassword;
        $config["security"]["api_key"] = $apiKey;

        $content = "<?php\nreturn " . var_export($config, true) . ";\n";
        file_put_contents($target, $content);
    ' \
        "$CONFIG_SAMPLE_FILE" \
        "$CONFIG_FILE" \
        "http://${SERVER_IP}" \
        "$DB_HOST" \
        "$DB_PORT" \
        "$DB_NAME" \
        "$DB_USER" \
        "$DB_PASSWORD" \
        "$API_KEY"

    $SUDO chown "$APP_USER:$APP_GROUP" "$CONFIG_FILE"
    $SUDO chmod 640 "$CONFIG_FILE"
    ok "Production config generated"
}

seed_demo_users() {
    info "Installing demo users through application bootstrap"
    $SUDO -u "$APP_USER" "$PHP_BIN" -r '
        chdir($argv[1]);
        require $argv[2];
        installDemoAccounts();
    ' "$PROJECT_DIR" "$BOOTSTRAP_FILE"
    ok "Demo users ensured in database"
}

setup_php_fpm() {
    info "Ensuring php-fpm is enabled"
    $SUDO systemctl enable --now "$PHP_FPM_SERVICE"
    $SUDO systemctl restart "$PHP_FPM_SERVICE"
    ok "php-fpm is running"
}

setup_nginx() {
    info "Creating nginx site"

    $SUDO tee "/etc/nginx/sites-available/${SERVICE_NAME}" >/dev/null <<EOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name ${SERVER_NAME};

    root ${PROJECT_DIR}/public;
    index index.php index.html;

    access_log /var/log/nginx/${SERVICE_NAME}.access.log;
    error_log /var/log/nginx/${SERVICE_NAME}.error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location /api/ {
        alias ${PROJECT_DIR}/api/;
        try_files \$uri =404;
    }

    location ~ ^/api/(.+\.php)$ {
        alias ${PROJECT_DIR}/api/\$1;
        include snippets/fastcgi-php.conf;
        fastcgi_param SCRIPT_FILENAME ${PROJECT_DIR}/api/\$1;
        fastcgi_pass unix:${PHP_FPM_SOCKET};
    }

    location ~ \.php$ {
        try_files \$uri =404;
        include snippets/fastcgi-php.conf;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_pass unix:${PHP_FPM_SOCKET};
    }

    location ~ ^/(config|database|includes|logs)/ {
        deny all;
        return 404;
    }

    location ~ /\. {
        deny all;
        return 404;
    }
}
EOF

    $SUDO ln -sf "/etc/nginx/sites-available/${SERVICE_NAME}" "/etc/nginx/sites-enabled/${SERVICE_NAME}"
    $SUDO rm -f /etc/nginx/sites-enabled/default
    $SUDO nginx -t
    $SUDO systemctl enable --now nginx
    $SUDO systemctl reload nginx
    ok "Nginx is ready"
}

set_project_permissions() {
    info "Applying project permissions"
    $SUDO chown -R root:"$APP_GROUP" "$PROJECT_DIR"
    $SUDO find "$PROJECT_DIR" -type d -exec chmod 755 {} \;
    $SUDO find "$PROJECT_DIR" -type f -exec chmod 644 {} \;
    $SUDO chown -R "$APP_USER:$APP_GROUP" "$PROJECT_DIR/logs"
    $SUDO chmod 640 "$CONFIG_FILE"
    ok "Permissions updated"
}

print_summary() {
    cat <<EOF

Deploy completed.

Project: $PROJECT_DIR
URL: http://${SERVER_IP}/
API URL: http://${SERVER_IP}/api/submit_result.php
Database: mysql://${DB_USER}@${DB_HOST}:${DB_PORT}/${DB_NAME}
PHP-FPM: ${PHP_FPM_SERVICE}
Nginx site: /etc/nginx/sites-available/${SERVICE_NAME}
Config: ${CONFIG_FILE}
API key: ${API_KEY}

Demo credentials:
  ${DEMO_ADMIN_USERNAME} / ${DEMO_ADMIN_PASSWORD}
  ${DEMO_TEACHER_USERNAME} / ${DEMO_TEACHER_PASSWORD}

Useful commands:
  systemctl status nginx
  systemctl status mariadb
  systemctl status ${PHP_FPM_SERVICE}
  journalctl -u ${PHP_FPM_SERVICE} -f
  tail -f /var/log/nginx/${SERVICE_NAME}.error.log
EOF
}

main() {
    install_system_packages
    prepare_project_code
    require_project_root
    detect_php_runtime
    prepare_runtime_directories
    setup_mariadb
    write_config_file
    seed_demo_users
    setup_php_fpm
    setup_nginx
    set_project_permissions
    print_summary
}

main "$@"
