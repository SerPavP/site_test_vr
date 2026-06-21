<?php
require_once __DIR__ . '/bootstrap.php';

function isLoggedIn(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

function attemptLogin(string $username, string $password): bool
{
    global $config;

    if ($username === $config['admin']['username'] && $password === $config['admin']['password']) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        return true;
    }

    return false;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . appUrl('index.php'));
        exit;
    }
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}
