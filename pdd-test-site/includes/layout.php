<?php
require_once __DIR__ . '/bootstrap.php';

function renderHeader(string $title): void
{
    global $config;
    $appName = $config['app_name'] ?? 'Тест ПДД';
    echo '<!doctype html>';
    echo '<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' — ' . e($appName) . '</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
    echo '</head><body class="bg-light">';
    echo '<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4"><div class="container">';
    echo '<a class="navbar-brand" href="' . e(appUrl('dashboard.php')) . '">' . e($appName) . '</a>';
    if (!empty($_SESSION['admin_logged_in'])) {
        echo '<div class="navbar-nav ms-auto">';
        echo '<a class="nav-link" href="' . e(appUrl('dashboard.php')) . '">Результаты</a>';
        echo '<a class="nav-link" href="' . e(appUrl('analytics.php')) . '">Аналитика</a>';
        echo '<a class="nav-link" href="' . e(appUrl('export.php')) . '">Экспорт CSV</a>';
        echo '<a class="nav-link" href="' . e(appUrl('logout.php')) . '">Выход</a>';
        echo '</div>';
    }
    echo '</div></nav><main class="container">';
}

function renderFooter(): void
{
    echo '</main></body></html>';
}
