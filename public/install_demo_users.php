<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$installedUsers = [];
$error = '';

try {
    $installedUsers = installDemoAccounts();
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Установка demo-пользователей</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 mb-3">Установка demo-пользователей</h1>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger">
                            <div class="fw-semibold mb-2">Не удалось установить пользователей.</div>
                            <div class="small">Ошибка БД: <code><?= e($error) ?></code></div>
                            <div class="small mt-2">Проверьте настройки в <code>config/config.php</code> и доступ к MySQL.</div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success">
                            Пользователи `admin` и `teacher1` установлены или обновлены.
                        </div>
                        <div class="table-responsive mb-3">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Логин</th>
                                        <th>Роль</th>
                                        <th>Имя</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($installedUsers as $user): ?>
                                    <tr>
                                        <td><code><?= e($user['username']) ?></code></td>
                                        <td><?= e($user['role']) ?></td>
                                        <td><?= e($user['display_name']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="small text-muted">
                            Логины: <code>admin</code> / <code>admin12345</code> и <code>teacher1</code> / <code>teacher12345</code>.
                        </div>
                    <?php endif; ?>

                    <div class="mt-4 d-flex gap-2">
                        <a href="index.php" class="btn btn-primary">Вернуться на главную</a>
                        <a href="dashboard.php" class="btn btn-outline-secondary">Открыть dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
