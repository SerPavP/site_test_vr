<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();

$stats = ['total_tests' => 0, 'avg_score' => 0, 'pass_rate' => 0];
$topQuestions = [];
$chartLabels = [];
$chartTotals = [];
$error = '';
$storage = 'database';

try {
    $stats = db()->query(
        'SELECT COUNT(*) AS total_tests, COALESCE(AVG(score), 0) AS avg_score, COALESCE(AVG(passed) * 100, 0) AS pass_rate FROM results'
    )->fetch();

    $topQuestions = db()->query(
        'SELECT question_id, wrong_count, total_count FROM question_stats ORDER BY wrong_count DESC, total_count DESC LIMIT 5'
    )->fetchAll();

    $chartRows = db()->query(
        'SELECT DATE(created_at) AS day, COUNT(*) AS total FROM results GROUP BY DATE(created_at) ORDER BY day ASC LIMIT 14'
    )->fetchAll();

    foreach ($chartRows as $row) {
        $chartLabels[] = $row['day'];
        $chartTotals[] = (int) $row['total'];
    }
} catch (Throwable $e) {
    $storage = 'fallback-file';
    $rows = loadFallbackResults();

    if (!$rows && !is_file(fallbackResultsPath())) {
        $error = 'Аналитика пока недоступна. Сначала настройте базу данных.';
    } else {
        $stats = fallbackStats($rows);
        $topQuestions = fallbackQuestionStats($rows);
        $chartRows = fallbackChartRows($rows);
        $chartLabels = array_keys($chartRows);
        $chartTotals = array_values($chartRows);
    }
}

renderHeader('Аналитика');
?>
<h1 class="h3 mb-3">Аналитика</h1>

<?php if ($storage !== 'database'): ?>
    <div class="alert alert-info">Аналитика построена по резервному файлу, потому что подключение к БД недоступно.</div>
<?php endif; ?>

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
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5">График по дням</h2>
                <canvas id="resultsChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
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
