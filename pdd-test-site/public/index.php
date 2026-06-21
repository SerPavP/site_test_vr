<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

if (isLoggedIn()) {
    header('Location: ' . appUrl('dashboard.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (attemptLogin($username, $password)) {
        header('Location: ' . appUrl('dashboard.php'));
        exit;
    }

    $error = 'Неверный логин или пароль';
}

renderHeader('Вход');
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h1 class="h3 mb-3">Вход администратора</h1>
                <p class="text-muted">Панель управления результатами тестов Unity-приложения.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Логин</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Пароль</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Войти</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php renderFooter();
