<?php
require_once __DIR__ . '/../includes/auth.php';

requireTeacherOrAdmin();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=results_export.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Студент', 'Группа', 'Ник из Unity', 'Балл', 'Всего вопросов', 'Процент', 'Статус', 'Дата', 'IP'], ';');

try {
    [$scopeCondition, $scopeParams] = buildResultsScopeCondition('s');
    $stmt = db()->prepare(
        'SELECT
            r.id,
            r.name AS unity_name,
            r.score,
            r.total_questions,
            r.percent,
            r.passed,
            r.created_at,
            r.ip,
            s.display_name AS student_display_name,
            s.username AS student_username,
            g.name AS group_name
         FROM results r
         LEFT JOIN students s ON s.id = r.student_id
         LEFT JOIN student_groups g ON g.id = s.group_id
         WHERE ' . $scopeCondition . '
         ORDER BY r.created_at DESC'
    );
    $stmt->execute($scopeParams);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        fputcsv($output, [
            $row['id'],
            $row['student_display_name'] ? $row['student_display_name'] . ' (' . $row['student_username'] . ')' : 'Не привязан',
            $row['group_name'] ?? '-',
            $row['unity_name'],
            $row['score'],
            $row['total_questions'],
            $row['percent'],
            ((int) $row['passed'] === 1 ? 'Сдал' : 'Не сдал'),
            $row['created_at'],
            $row['ip'],
        ], ';');
    }
} catch (Throwable $e) {
    fputcsv($output, ['Ошибка подключения к базе данных'], ';');
}

fclose($output);
exit;
