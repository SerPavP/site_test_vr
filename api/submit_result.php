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

function saveFallbackResult(array $payload): void
{
    appendFileLog('results_fallback.jsonl', $payload);
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
    jsonResponse(405, ['success' => false, 'message' => 'Разрешен только POST']);
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

$name = trim((string) ($data['name'] ?? $data['playerName'] ?? ''));
if ($name === '') {
    $name = 'Без имени';
}

$score = max(0, (int) ($data['score'] ?? 0));
$totalQuestions = max(1, (int) ($data['total_questions'] ?? 20));
$percent = min(100, round(($score / $totalQuestions) * 100, 2));
$passed = $score >= 18 ? 1 : 0;
$answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];

appendFileLog('api.log.jsonl', [
    'created_at' => date('c'),
    'ip' => $ip,
    'name' => $name,
    'score' => $score,
    'total_questions' => $totalQuestions,
]);

$storage = 'database';
$studentUsername = null;
$studentId = null;

try {
    $studentStmt = db()->prepare(
        'SELECT id, username
         FROM students
         WHERE username = :username AND is_active = 1
         LIMIT 1'
    );
    $studentStmt->execute(['username' => $name]);
    $student = $studentStmt->fetch();
    if ($student) {
        $studentId = (int) $student['id'];
        $studentUsername = (string) $student['username'];
    }

    $stmt = db()->prepare(
        'INSERT INTO results (student_id, name, score, total_questions, percent, passed, ip)
         VALUES (:student_id, :name, :score, :total_questions, :percent, :passed, :ip)'
    );
    $stmt->bindValue('student_id', $studentId, $studentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue('name', $name, PDO::PARAM_STR);
    $stmt->bindValue('score', $score, PDO::PARAM_INT);
    $stmt->bindValue('total_questions', $totalQuestions, PDO::PARAM_INT);
    $stmt->bindValue('percent', $percent);
    $stmt->bindValue('passed', $passed, PDO::PARAM_INT);
    $stmt->bindValue('ip', $ip, PDO::PARAM_STR);
    $stmt->execute();

    $resultId = (int) db()->lastInsertId();

    if ($answers) {
        $statsStmt = db()->prepare(
            'INSERT INTO question_stats (question_id, wrong_count, total_count) VALUES (:question_id, :wrong_count, 1)
             ON DUPLICATE KEY UPDATE wrong_count = wrong_count + VALUES(wrong_count), total_count = total_count + 1'
        );
        $attemptStmt = db()->prepare(
            'INSERT INTO question_attempts (result_id, student_id, question_id, is_correct)
             VALUES (:result_id, :student_id, :question_id, :is_correct)'
        );

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

            $attemptStmt->bindValue('result_id', $resultId, PDO::PARAM_INT);
            $attemptStmt->bindValue('student_id', $studentId, $studentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $attemptStmt->bindValue('question_id', $questionId, PDO::PARAM_INT);
            $attemptStmt->bindValue('is_correct', $isCorrect ? 1 : 0, PDO::PARAM_INT);
            $attemptStmt->execute();
        }
    }

    auditEvent('unity_result_received', 'result', $resultId, [
        'unity_name' => $name,
        'student_username' => $studentUsername,
        'score' => $score,
        'total_questions' => $totalQuestions,
        'passed' => (bool) $passed,
    ], null, $ip);
} catch (Throwable $e) {
    $storage = 'fallback-file';
    saveFallbackResult([
        'created_at' => date('c'),
        'name' => $name,
        'student_username' => $studentUsername,
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
    'message' => $storage === 'database' ? 'Результат успешно сохранен' : 'Результат сохранен во временный лог',
    'percent' => $percent,
    'passed' => (bool) $passed,
    'storage' => $storage,
    'student_linked' => $studentUsername !== null,
    'student_username' => $studentUsername,
]);
