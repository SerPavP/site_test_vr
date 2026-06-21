<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

if (isLoggedIn()) {
    header('Location: ' . appUrl('dashboard.php'));
    exit;
}

$error = '';
$setupMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (attemptLogin($username, $password)) {
        header('Location: ' . appUrl('dashboard.php'));
        exit;
    }

    $error = 'Неверный логин, пароль или база пользователей недоступна.';
}

renderHeader('Вход');
?>
<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h1 class="h3 mb-3">Вход в систему</h1>
                <p class="text-muted">Админ видит все результаты. Преподаватель видит только назначенные ему группы.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Логин</label>
                        <input type="text" name="username" class="form-control" required autocomplete="username">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Пароль</label>
                        <input type="password" name="password" class="form-control" required autocomplete="current-password">
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Войти</button>
                </form>

                <hr class="my-4">

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h5 mb-0">Демо-учетные записи</h2>
                    <div class="d-flex gap-2">
                        <a href="install_demo_users.php" class="btn btn-sm btn-outline-primary">Установить пользователей</a>
                        <a href="install_test_data.php" class="btn btn-sm btn-outline-secondary">Установить тестовые данные</a>
                    </div>
                </div>
                <div class="small text-muted mb-3">
                    Если вход не работает, сначала откройте установочный скрипт. Он заново создаст `admin` и `teacher1` в базе.
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Роль</th>
                                <th>Логин</th>
                                <th>Пароль</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Админ</td>
                                <td><code>admin</code></td>
                                <td><code>admin12345</code></td>
                            </tr>
                            <tr>
                                <td>Преподаватель</td>
                                <td><code>teacher1</code></td>
                                <td><code>teacher12345</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php renderFooter();
