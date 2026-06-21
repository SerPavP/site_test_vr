<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireTeacherOrAdmin();

$search = trim($_GET['search'] ?? '');
$rows = [];
$error = '';

try {
    [$scopeCondition, $scopeParams] = buildResultsScopeCondition('s');

    $sql = 'SELECT
                r.id,
                r.name AS unity_name,
                r.score,
                r.total_questions,
                r.percent,
                r.passed,
                r.created_at,
                r.ip,
                s.id AS student_id,
                s.username AS student_username,
                s.display_name AS student_display_name,
                g.name AS group_name
            FROM results r
            LEFT JOIN students s ON s.id = r.student_id
            LEFT JOIN student_groups g ON g.id = s.group_id
            WHERE ' . $scopeCondition;

    $params = $scopeParams;
    if ($search !== '') {
        $sql .= ' AND (
            r.name LIKE :search
            OR COALESCE(s.username, \'\') LIKE :search
            OR COALESCE(s.display_name, \'\') LIKE :search
            OR COALESCE(g.name, \'\') LIKE :search
        )';
        $params['search'] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY r.created_at DESC LIMIT 100';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    $error = 'Не удалось получить данные. Проверьте настройки БД.';
}

renderHeader('Результаты');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-0">Результаты тестов</h1>
        <small class="text-muted">
            <?= isAdmin() ? 'Администратор видит все результаты' : 'Преподаватель видит только свои группы' ?>
        </small>
    </div>
    <a href="analytics.php" class="btn btn-outline-primary">Открыть аналитику</a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md-10">
                <input
                    type="text"
                    name="search"
                    value="<?= e($search) ?>"
                    class="form-control"
                    placeholder="Поиск по студенту, группе или нику из Unity"
                >
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-primary" type="submit">Найти</button>
            </div>
        </form>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-warning mb-3"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($rows): ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Студент</th>
                        <th>Группа</th>
                        <th>Ник из Unity</th>
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
                        <td><?= e((string) $row['id']) ?></td>
                        <td>
                            <?php if (!empty($row['student_username'])): ?>
                                <div class="fw-semibold"><?= e($row['student_display_name']) ?></div>
                                <div class="small text-muted"><?= e($row['student_username']) ?></div>
                            <?php else: ?>
                                <span class="text-muted">Не привязан</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) ($row['group_name'] ?? '-')) ?></td>
                        <td><?= e($row['unity_name']) ?></td>
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
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-4 text-muted">Подходящих данных пока нет</div>
    </div>
<?php endif; ?>
<?php renderFooter();
