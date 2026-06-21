<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireAdmin();

$rows = [];
$error = '';

try {
    $stmt = db()->query(
        'SELECT
            al.id,
            al.action,
            al.entity_type,
            al.entity_id,
            al.ip,
            al.details_json,
            al.created_at,
            u.username AS actor_username,
            u.display_name AS actor_display_name
         FROM audit_logs al
         LEFT JOIN users u ON u.id = al.actor_user_id
         ORDER BY al.created_at DESC, al.id DESC
         LIMIT 100'
    );
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    $error = 'Не удалось загрузить аудит.';
}

renderHeader('Аудит');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-0">Журнал аудита</h1>
        <small class="text-muted">Последние 100 событий системы</small>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-warning"><?= e($error) ?></div>
<?php elseif ($rows): ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Действие</th>
                        <th>Кто</th>
                        <th>Сущность</th>
                        <th>IP</th>
                        <th>Детали</th>
                        <th>Дата</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= (int) $row['id'] ?></td>
                        <td><?= e($row['action']) ?></td>
                        <td>
                            <?php if (!empty($row['actor_username'])): ?>
                                <div class="fw-semibold"><?= e($row['actor_display_name']) ?></div>
                                <div class="small text-muted"><?= e($row['actor_username']) ?></div>
                            <?php else: ?>
                                <span class="text-muted">Система</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e((string) ($row['entity_type'] ?? '-')) ?>
                            <?php if (!empty($row['entity_id'])): ?>
                                #<?= (int) $row['entity_id'] ?>
                            <?php endif; ?>
                        </td>
                        <td><?= e((string) ($row['ip'] ?? '-')) ?></td>
                        <td><code><?= e((string) ($row['details_json'] ?? '{}')) ?></code></td>
                        <td><?= e($row['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-4 text-muted">Событий пока нет</div>
    </div>
<?php endif; ?>
<?php renderFooter();
