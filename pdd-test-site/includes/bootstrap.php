<?php
$sessionPath = __DIR__ . '/../logs/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0777, true);
}

session_save_path($sessionPath);
session_start();

$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/../config/config.sample.php';
}

$config = require $configPath;

function appUrl(string $path = ''): string
{
    global $config;

    $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
    $path = ltrim($path, '/');

    if ($path === '') {
        return $baseUrl;
    }

    return $baseUrl . '/' . $path;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = dbConfig();
    $driver = $db['driver'] ?? 'mysql';

    if ($driver === 'sqlite') {
        $sqlitePath = sqlitePath($db);
        $sqliteDir = dirname($sqlitePath);
        if (!is_dir($sqliteDir)) {
            mkdir($sqliteDir, 0777, true);
        }

        $pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        return $pdo;
    }

    $mysql = mysqlConfig($db);
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $mysql['host'],
        $mysql['port'],
        $mysql['database'],
        $mysql['charset']
    );

    $pdo = new PDO($dsn, $mysql['username'], $mysql['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function dbConfig(): array
{
    global $config;

    $db = $config['db'] ?? [];
    if (!is_array($db)) {
        return [
            'driver' => 'sqlite',
            'sqlite_path' => __DIR__ . '/../database/pdd.sqlite',
        ];
    }

    if (!isset($db['driver'])) {
        if (isset($db['host'], $db['database'])) {
            $db['driver'] = 'mysql';
        } elseif (isset($db['sqlite_path'])) {
            $db['driver'] = 'sqlite';
        } else {
            $db['driver'] = 'sqlite';
            $db['sqlite_path'] = __DIR__ . '/../database/pdd.sqlite';
        }
    }

    return $db;
}

function mysqlConfig(array $db): array
{
    if (isset($db['mysql']) && is_array($db['mysql'])) {
        return $db['mysql'];
    }

    return [
        'host' => $db['host'] ?? '127.0.0.1',
        'port' => $db['port'] ?? '3306',
        'database' => $db['database'] ?? 'pdd_test',
        'username' => $db['username'] ?? 'root',
        'password' => $db['password'] ?? '',
        'charset' => $db['charset'] ?? 'utf8mb4',
    ];
}

function sqlitePath(?array $db = null): string
{
    $db ??= dbConfig();

    $path = (string) ($db['sqlite_path'] ?? (__DIR__ . '/../database/pdd.sqlite'));
    if (preg_match('/^[A-Za-z]:\\\\|^\//', $path) === 1) {
        return $path;
    }

    return __DIR__ . '/../' . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function fallbackResultsPath(): string
{
    return __DIR__ . '/../logs/results_fallback.jsonl';
}

function loadFallbackResults(): array
{
    $path = fallbackResultsPath();
    if (!is_file($path)) {
        return [];
    }

    $rows = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    foreach ($lines as $line) {
        $item = json_decode($line, true);
        if (!is_array($item)) {
            continue;
        }

        $rows[] = [
            'id' => count($rows) + 1,
            'name' => (string) ($item['name'] ?? 'Без имени'),
            'score' => (int) ($item['score'] ?? 0),
            'total_questions' => max(1, (int) ($item['total_questions'] ?? 20)),
            'percent' => (float) ($item['percent'] ?? 0),
            'passed' => !empty($item['passed']) ? 1 : 0,
            'created_at' => fallbackDisplayDate((string) ($item['created_at'] ?? '')),
            'ip' => (string) ($item['ip'] ?? '-'),
            'answers' => is_array($item['answers'] ?? null) ? $item['answers'] : [],
        ];
    }

    usort($rows, static fn (array $a, array $b) => strcmp($b['created_at'], $a['created_at']));

    foreach ($rows as $index => &$row) {
        $row['id'] = $index + 1;
    }
    unset($row);

    return $rows;
}

function fallbackDisplayDate(string $value): string
{
    if ($value === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return $value;
    }
}

function filterFallbackResults(array $rows, string $search): array
{
    if ($search === '') {
        return $rows;
    }

    return array_values(array_filter($rows, static function (array $row) use ($search): bool {
        return mb_stripos((string) $row['name'], $search) !== false;
    }));
}

function fallbackStats(array $rows): array
{
    $totalTests = count($rows);
    if ($totalTests === 0) {
        return ['total_tests' => 0, 'avg_score' => 0, 'pass_rate' => 0];
    }

    $scoreSum = 0;
    $passCount = 0;
    foreach ($rows as $row) {
        $scoreSum += (int) $row['score'];
        $passCount += (int) $row['passed'] === 1 ? 1 : 0;
    }

    return [
        'total_tests' => $totalTests,
        'avg_score' => $scoreSum / $totalTests,
        'pass_rate' => ($passCount / $totalTests) * 100,
    ];
}

function fallbackQuestionStats(array $rows): array
{
    $stats = [];

    foreach ($rows as $row) {
        foreach ($row['answers'] as $answer) {
            $questionId = (int) ($answer['question_id'] ?? 0);
            if ($questionId <= 0) {
                continue;
            }

            if (!isset($stats[$questionId])) {
                $stats[$questionId] = [
                    'question_id' => $questionId,
                    'wrong_count' => 0,
                    'total_count' => 0,
                ];
            }

            $stats[$questionId]['total_count']++;
            if (empty($answer['is_correct'])) {
                $stats[$questionId]['wrong_count']++;
            }
        }
    }

    $stats = array_values($stats);
    usort($stats, static function (array $a, array $b): int {
        return [$b['wrong_count'], $b['total_count'], $a['question_id']]
            <=>
            [$a['wrong_count'], $a['total_count'], $b['question_id']];
    });

    return array_slice($stats, 0, 5);
}

function fallbackChartRows(array $rows): array
{
    $grouped = [];

    foreach ($rows as $row) {
        $day = substr((string) $row['created_at'], 0, 10);
        if ($day === '') {
            continue;
        }
        $grouped[$day] = ($grouped[$day] ?? 0) + 1;
    }

    ksort($grouped);
    if (count($grouped) > 14) {
        $grouped = array_slice($grouped, -14, null, true);
    }

    return $grouped;
}
