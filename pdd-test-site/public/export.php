<?php
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=results_export.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Имя', 'Балл', 'Всего вопросов', 'Процент', 'Статус', 'Дата', 'IP'], ';');

try {
    $rows = db()->query('SELECT * FROM results ORDER BY created_at DESC')->fetchAll();
} catch (Throwable $e) {
    $rows = loadFallbackResults();
}

foreach ($rows as $row) {
    fputcsv($output, [
        $row['id'],
        $row['name'],
        $row['score'],
        $row['total_questions'],
        $row['percent'],
        ((int) $row['passed'] === 1 ? 'Сдал' : 'Не сдал'),
        $row['created_at'],
        $row['ip'],
    ], ';');
}

fclose($output);
exit;
