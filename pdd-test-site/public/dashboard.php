<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();

$search = trim($_GET['search'] ?? '');
$rows = [];
$error = '';
$storage = 'database';

try {
    if ($search !== '') {
        $stmt = db()->prepare('SELECT * FROM results WHERE name LIKE :search ORDER BY created_at DESC LIMIT 100');
        $stmt->execute(['search' => '%' . $search . '%']);
        $rows = $stmt->fetchAll();
    } else {
        $stmt = db()->query('SELECT * FROM results ORDER BY created_at DESC LIMIT 100');
        $rows = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    $storage = 'fallback-file';
    $rows = array_slice(filterFallbackResults(loadFallbackResults(), $search), 0, 100);

    if (!$rows && !is_file(fallbackResultsPath())) {
        $error = 'Не удалось получить данные. Проверьте настройки БД.';
    }
}

renderHeader('Результаты');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-0">Результаты тестов</h1>
        <small class="text-muted">Последние 100 попыток</small>
    </div>
    <a href="analytics.php" class="btn btn-outline-primary">Открыть аналитику</a>
</div>

<?php if ($storage !== 'database'): ?>
    <div class="alert alert-info">Показаны данные из резервного файла, потому что подключение к БД недоступно.</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md-10">
                <input type="text" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Поиск по имени пользователя">
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-primary" type="submit">Найти</button>
            </div>
        </form>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-warning"><?= e($error) ?></div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Имя</th>
                        <th>Балл</th>
                        <th>Процент</th>
                        <th>Статус</th>
                        <th>Дата</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= (int) $row['id'] ?></td>
                        <td><?= e($row['name']) ?></td>
                        <td><?= (int) $row['score'] ?>/<?= (int) $row['total_questions'] ?></td>
                        <td><?= e((string) $row['percent']) ?>%</td>
                        <td>
                            <?php if ((int) $row['passed'] === 1): ?>
                                <span class="badge text-bg-success">Сдал</span>
                            <?php else: ?>
                                <span class="badge text-bg-danger">Не сдал</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($row['created_at']) ?></td>
                        <td><?= e((string) ($row['ip'] ?? '-')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">Данных пока нет</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php renderFooter();
