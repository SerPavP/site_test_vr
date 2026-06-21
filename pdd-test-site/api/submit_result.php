<?php
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');
header('Access-Control-Allow-Methods: POST, OPTIONS');

function jsonResponse(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function clientIp(): string
{
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function ensureLogDirectory(): string
{
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir;
}

function saveFallbackResult(array $payload): void
{
    $dir = ensureLogDirectory();
    file_put_contents(
        $dir . '/results_fallback.jsonl',
        json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND
    );
}

function checkRateLimit(string $ip, int $limit): bool
{
    $dir = ensureLogDirectory();
    $file = $dir . '/ratelimit.json';
    $data = file_exists($file) ? json_decode((string) file_get_contents($file), true) : [];
    $now = time();
    $windowStart = $now - 300;

    foreach ($data as $storedIp => $timestamps) {
        $data[$storedIp] = array_values(array_filter($timestamps, static fn ($ts) => $ts >= $windowStart));
        if (!$data[$storedIp]) {
            unset($data[$storedIp]);
        }
    }

    $data[$ip] = $data[$ip] ?? [];
    if (count($data[$ip]) >= $limit) {
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return false;
    }

    $data[$ip][] = $now;
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['success' => false, 'message' => 'Разрешён только POST']);
}

$ip = clientIp();
$providedApiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($_POST['api_key'] ?? '');
$expectedApiKey = $config['security']['api_key'] ?? '';

if (!$expectedApiKey || !hash_equals($expectedApiKey, $providedApiKey)) {
    jsonResponse(401, ['success' => false, 'message' => 'Неверный API-ключ']);
}

if (!checkRateLimit($ip, (int) ($config['security']['rate_limit_per_5_min'] ?? 30))) {
    jsonResponse(429, ['success' => false, 'message' => 'Слишком много запросов']);
}

$raw = (string) file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$name = trim((string) ($data['name'] ?? $data['playerName'] ?? 'Без имени'));
$score = max(0, (int) ($data['score'] ?? 0));
$totalQuestions = max(1, (int) ($data['total_questions'] ?? 20));
$percent = round(($score / $totalQuestions) * 100, 2);
$passed = $score >= 18 ? 1 : 0;
$answers = $data['answers'] ?? [];

$logDir = ensureLogDirectory();
file_put_contents(
    $logDir . '/api.log',
    sprintf("[%s] IP=%s NAME=%s SCORE=%d/%d\n", date('Y-m-d H:i:s'), $ip, $name, $score, $totalQuestions),
    FILE_APPEND
);

$storage = 'database';

try {
    $pdo = db();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    $stmt = $pdo->prepare(
        'INSERT INTO results (name, score, total_questions, percent, passed, ip) VALUES (:name, :score, :total_questions, :percent, :passed, :ip)'
    );
    $stmt->execute([
        'name' => $name,
        'score' => $score,
        'total_questions' => $totalQuestions,
        'percent' => $percent,
        'passed' => $passed,
        'ip' => $ip,
    ]);

    if (is_array($answers)) {
        $statsSql = $driver === 'sqlite'
            ? 'INSERT INTO question_stats (question_id, wrong_count, total_count, updated_at)
               VALUES (:question_id, :wrong_count, 1, CURRENT_TIMESTAMP)
               ON CONFLICT(question_id) DO UPDATE SET
                   wrong_count = question_stats.wrong_count + excluded.wrong_count,
                   total_count = question_stats.total_count + 1,
                   updated_at = CURRENT_TIMESTAMP'
            : 'INSERT INTO question_stats (question_id, wrong_count, total_count) VALUES (:question_id, :wrong_count, 1)
               ON DUPLICATE KEY UPDATE wrong_count = wrong_count + VALUES(wrong_count), total_count = total_count + 1';
        $statsStmt = $pdo->prepare($statsSql);

        foreach ($answers as $answer) {
            $questionId = (int) ($answer['question_id'] ?? 0);
            if ($questionId <= 0) {
                continue;
            }

            $isCorrect = !empty($answer['is_correct']);
            $statsStmt->execute([
                'question_id' => $questionId,
                'wrong_count' => $isCorrect ? 0 : 1,
            ]);
        }
    }
} catch (Throwable $e) {
    $storage = 'fallback-file';
    saveFallbackResult([
        'created_at' => date('c'),
        'name' => $name,
        'score' => $score,
        'total_questions' => $totalQuestions,
        'percent' => $percent,
        'passed' => (bool) $passed,
        'ip' => $ip,
        'answers' => $answers,
        'error' => $e->getMessage(),
    ]);
}

jsonResponse(200, [
    'success' => true,
    'message' => $storage === 'database' ? 'Результат успешно сохранён' : 'Результат сохранён во временный лог',
    'percent' => $percent,
    'passed' => (bool) $passed,
    'storage' => $storage,
]);
