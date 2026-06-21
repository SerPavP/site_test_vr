<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireTeacherOrAdmin();

$stats = ['total_tests' => 0, 'avg_score' => 0, 'pass_rate' => 0];
$topQuestions = [];
$topStudents = [];
$groupAverages = [];
$chartLabels = [];
$chartTotals = [];
$error = '';

try {
    [$scopeCondition, $scopeParams] = buildResultsScopeCondition('s');

    $statsStmt = db()->prepare(
        'SELECT
            COUNT(*) AS total_tests,
            COALESCE(AVG(r.score), 0) AS avg_score,
            COALESCE(AVG(r.passed) * 100, 0) AS pass_rate
         FROM results r
         LEFT JOIN students s ON s.id = r.student_id
         WHERE ' . $scopeCondition
    );
    $statsStmt->execute($scopeParams);
    $stats = $statsStmt->fetch() ?: $stats;

    $chartStmt = db()->prepare(
        'SELECT DATE(r.created_at) AS day, COUNT(*) AS total
         FROM results r
         LEFT JOIN students s ON s.id = r.student_id
         WHERE ' . $scopeCondition . '
         GROUP BY DATE(r.created_at)
         ORDER BY day ASC
         LIMIT 14'
    );
    $chartStmt->execute($scopeParams);
    foreach ($chartStmt->fetchAll() as $row) {
        $chartLabels[] = $row['day'];
        $chartTotals[] = (int) $row['total'];
    }

    $topStudentsStmt = db()->prepare(
        'SELECT
            COALESCE(s.display_name, r.name) AS student_name,
            COUNT(*) AS attempts,
            ROUND(AVG(r.score), 2) AS avg_score,
            ROUND(AVG(r.passed) * 100, 2) AS pass_rate
         FROM results r
         LEFT JOIN students s ON s.id = r.student_id
         WHERE ' . $scopeCondition . '
         GROUP BY COALESCE(s.id, CONCAT(\'guest:\', r.name)), COALESCE(s.display_name, r.name)
         ORDER BY avg_score DESC, attempts DESC
         LIMIT 5'
    );
    $topStudentsStmt->execute($scopeParams);
    $topStudents = $topStudentsStmt->fetchAll();

    $groupAveragesStmt = db()->prepare(
        'SELECT
            COALESCE(g.name, \'Без группы\') AS group_name,
            ROUND(AVG(r.score), 2) AS avg_score,
            COUNT(*) AS attempts
         FROM results r
         LEFT JOIN students s ON s.id = r.student_id
         LEFT JOIN student_groups g ON g.id = s.group_id
         WHERE ' . $scopeCondition . '
         GROUP BY COALESCE(g.id, 0), COALESCE(g.name, \'Без группы\')
         ORDER BY avg_score DESC, attempts DESC'
    );
    $groupAveragesStmt->execute($scopeParams);
    $groupAverages = $groupAveragesStmt->fetchAll();

    $topQuestionsStmt = db()->prepare(
        'SELECT
            qa.question_id,
            SUM(CASE WHEN qa.is_correct = 0 THEN 1 ELSE 0 END) AS wrong_count,
            COUNT(*) AS total_count
         FROM question_attempts qa
         LEFT JOIN students s ON s.id = qa.student_id
         WHERE ' . $scopeCondition . '
         GROUP BY qa.question_id
         ORDER BY wrong_count DESC, total_count DESC
         LIMIT 5'
    );
    $topQuestionsStmt->execute($scopeParams);
    $topQuestions = $topQuestionsStmt->fetchAll();
} catch (Throwable $e) {
    $error = 'Аналитика пока недоступна. Сначала настройте базу данных.';
}

renderHeader('Аналитика');
?>
<h1 class="h3 mb-3">Аналитика</h1>

<?php if ($error): ?>
    <div class="alert alert-warning"><?= e($error) ?></div>
<?php else: ?>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted">Всего тестов</div><div class="display-6"><?= (int) $stats['total_tests'] ?></div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted">Средний балл</div><div class="display-6"><?= number_format((float) $stats['avg_score'], 1) ?></div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted">Процент сдачи</div><div class="display-6"><?= number_format((float) $stats['pass_rate'], 1) ?>%</div></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5">График по дням</h2>
                <canvas id="resultsChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5">Топ студентов</h2>
                <ul class="list-group list-group-flush">
                    <?php foreach ($topStudents as $item): ?>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span><?= e($item['student_name']) ?></span>
                            <span class="text-muted">ср. <?= e((string) $item['avg_score']) ?></span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$topStudents): ?>
                        <li class="list-group-item px-0 text-muted">Недостаточно данных</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5">Средний балл по группам</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Группа</th>
                                <th>Средний балл</th>
                                <th>Попытки</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($groupAverages as $item): ?>
                            <tr>
                                <td><?= e($item['group_name']) ?></td>
                                <td><?= e((string) $item['avg_score']) ?></td>
                                <td><?= (int) $item['attempts'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$groupAverages): ?>
                            <tr><td colspan="3" class="text-muted">Данных пока нет</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5">Топ-5 сложных вопросов</h2>
                <ul class="list-group list-group-flush">
                    <?php foreach ($topQuestions as $item): ?>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span>Вопрос #<?= (int) $item['question_id'] ?></span>
                            <span class="badge text-bg-danger"><?= (int) $item['wrong_count'] ?> ошибок</span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$topQuestions): ?>
                        <li class="list-group-item px-0 text-muted">Недостаточно данных</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
new Chart(document.getElementById('resultsChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: 'Количество тестов',
            data: <?= json_encode($chartTotals) ?>,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13,110,253,0.15)',
            fill: true,
            tension: 0.3
        }]
    },
    options: { responsive: true, plugins: { legend: { display: true } } }
});
</script>
<?php endif; ?>
<?php renderFooter();
