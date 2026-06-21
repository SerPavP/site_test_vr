<?php
require_once __DIR__ . '/bootstrap.php';

function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function currentUsername(): ?string
{
    return isset($_SESSION['username']) ? (string) $_SESSION['username'] : null;
}

function currentUserRole(): ?string
{
    return isset($_SESSION['role']) ? (string) $_SESSION['role'] : null;
}

function currentUserDisplayName(): ?string
{
    return isset($_SESSION['display_name']) ? (string) $_SESSION['display_name'] : null;
}

function isLoggedIn(): bool
{
    return currentUserId() !== null;
}

function isAdmin(): bool
{
    return currentUserRole() === 'admin';
}

function isTeacher(): bool
{
    return currentUserRole() === 'teacher';
}

function attemptLogin(string $username, string $password): bool
{
    $normalizedUsername = trim($username);
    $ip = clientIp();

    try {
        $stmt = db()->prepare(
            'SELECT id, username, password_hash, role, display_name
             FROM users
             WHERE username = :username AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['username' => $normalizedUsername]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            auditEvent('login_failed', 'user', null, ['username' => $normalizedUsername], null, $ip);
            return false;
        }

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = (string) $user['username'];
        $_SESSION['role'] = (string) $user['role'];
        $_SESSION['display_name'] = (string) $user['display_name'];

        auditEvent(
            'login_success',
            'user',
            (int) $user['id'],
            ['username' => $user['username'], 'role' => $user['role']],
            (int) $user['id'],
            $ip
        );

        return true;
    } catch (Throwable $e) {
        auditEvent('login_error', 'user', null, ['username' => $normalizedUsername, 'error' => $e->getMessage()], null, $ip);
        return false;
    }
}

function requireAuth(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . appUrl('index.php'));
        exit;
    }
}

function requireTeacherOrAdmin(): void
{
    requireAuth();
    if (!isTeacher() && !isAdmin()) {
        http_response_code(403);
        exit('Доступ запрещен');
    }
}

function requireAdmin(): void
{
    requireAuth();
    if (!isAdmin()) {
        http_response_code(403);
        exit('Доступ запрещен');
    }
}

function logout(): void
{
    if (isLoggedIn()) {
        auditEvent(
            'logout',
            'user',
            currentUserId(),
            ['username' => currentUsername(), 'role' => currentUserRole()],
            currentUserId(),
            clientIp()
        );
    }

    $_SESSION = [];
    session_destroy();
}

function currentTeacherGroupIds(): array
{
    if (!isTeacher() || !currentUserId()) {
        return [];
    }

    $stmt = db()->prepare('SELECT group_id FROM teacher_group_access WHERE user_id = :user_id ORDER BY group_id ASC');
    $stmt->execute(['user_id' => currentUserId()]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function buildResultsScopeCondition(string $studentAlias = 's'): array
{
    if (isAdmin()) {
        return ['1=1', []];
    }

    if (!isTeacher()) {
        return ['1=0', []];
    }

    $groupIds = currentTeacherGroupIds();
    if (!$groupIds) {
        return ['1=0', []];
    }

    $placeholders = [];
    $params = [];
    foreach ($groupIds as $index => $groupId) {
        $key = 'group_' . $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $groupId;
    }

    return [$studentAlias . '.group_id IN (' . implode(', ', $placeholders) . ')', $params];
}
