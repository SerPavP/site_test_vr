<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$dataset = ['rows' => [], 'summary' => []];
$error = '';

try {
    $dataset = installTestDataset();
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Установка тестовых данных</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 mb-3">Установка тестовых данных</h1>
                    <p class="text-muted">
                        Скрипт пересоздает тестовый набор результатов: 12 записей, 3 группы для преподавателя,
                        распределение учеников 4 / 6 / 2.
                    </p>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger">
                            <div class="fw-semibold mb-2">Не удалось установить тестовые данные.</div>
                            <div class="small">Ошибка БД: <code><?= e($error) ?></code></div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success">
                            Тестовые данные установлены: 12 результатов, преподавателю доступны 3 группы.
                        </div>

                        <div class="row g-3 mb-4">
                            <?php foreach ($dataset['summary'] as $groupName => $count): ?>
                                <div class="col-md-4">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body">
                                            <div class="text-muted small"><?= e($groupName) ?></div>
                                            <div class="display-6"><?= (int) $count ?></div>
                                            <div class="small text-muted">ученика</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Студент</th>
                                        <th>Группа</th>
                                        <th>Балл</th>
                                        <th>Процент</th>
                                        <th>Статус</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($dataset['rows'] as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= e($row['display_name']) ?></div>
                                            <div class="small text-muted"><?= e($row['username']) ?></div>
                                        </td>
                                        <td><?= e($row['group_name']) ?></td>
                                        <td><?= (int) $row['score'] ?>/20</td>
                                        <td><?= e((string) $row['percent']) ?>%</td>
                                        <td>
                                            <?php if ($row['passed']): ?>
                                                <span class="badge text-bg-success">Сдал</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-danger">Не сдал</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
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
