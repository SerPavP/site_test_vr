<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

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

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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

function appendFileLog(string $fileName, array $payload): void
{
    $dir = ensureLogDirectory();
    file_put_contents(
        $dir . '/' . $fileName,
        json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND
    );
}

function demoAccounts(): array
{
    return [
        'users' => [
            [
                'username' => 'admin',
                'password' => 'admin12345',
                'role' => 'admin',
                'display_name' => 'Главный администратор',
            ],
            [
                'username' => 'teacher1',
                'password' => 'teacher12345',
                'role' => 'teacher',
                'display_name' => 'Преподаватель 1',
            ],
        ],
        'groups' => [
            'group-a' => 'Группа A',
            'group-b' => 'Группа B',
            'group-c' => 'Группа C',
        ],
        'students' => [
            ['username' => 'user1', 'display_name' => 'Студент user1', 'group_key' => 'group-a'],
            ['username' => 'user2', 'display_name' => 'Студент user2', 'group_key' => 'group-a'],
            ['username' => 'user3', 'display_name' => 'Студент user3', 'group_key' => 'group-a'],
            ['username' => 'user4', 'display_name' => 'Студент user4', 'group_key' => 'group-a'],
            ['username' => 'user5', 'display_name' => 'Студент user5', 'group_key' => 'group-b'],
            ['username' => 'user6', 'display_name' => 'Студент user6', 'group_key' => 'group-b'],
            ['username' => 'user7', 'display_name' => 'Студент user7', 'group_key' => 'group-b'],
            ['username' => 'user8', 'display_name' => 'Студент user8', 'group_key' => 'group-b'],
            ['username' => 'user9', 'display_name' => 'Студент user9', 'group_key' => 'group-b'],
            ['username' => 'user10', 'display_name' => 'Студент user10', 'group_key' => 'group-b'],
            ['username' => 'user11', 'display_name' => 'Студент user11', 'group_key' => 'group-c'],
            ['username' => 'user12', 'display_name' => 'Студент user12', 'group_key' => 'group-c'],
        ],
        'teacher_group_access' => [
            ['teacher_username' => 'teacher1', 'group_key' => 'group-a'],
            ['teacher_username' => 'teacher1', 'group_key' => 'group-b'],
            ['teacher_username' => 'teacher1', 'group_key' => 'group-c'],
        ],
    ];
}

function db(): PDO
{
    static $pdo = null;
    static $initialized = false;
    global $config;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = $config['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['database'],
        $db['charset']
    );

    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    if (!$initialized) {
        ensureApplicationSchema($pdo);
        $initialized = true;
    }

    return $pdo;
}

function ensureApplicationSchema(PDO $pdo): void
{
    $statements = [
        'CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(120) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(30) NOT NULL,
            display_name VARCHAR(120) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE IF NOT EXISTS student_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL UNIQUE
        )',
        'CREATE TABLE IF NOT EXISTS teacher_group_access (
            user_id INT NOT NULL,
            group_id INT NOT NULL,
            PRIMARY KEY (user_id, group_id)
        )',
        'CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(120) NOT NULL UNIQUE,
            display_name VARCHAR(120) NOT NULL,
            group_id INT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1
        )',
        'CREATE TABLE IF NOT EXISTS results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NULL,
            name VARCHAR(120) NOT NULL,
            score INT NOT NULL,
            total_questions INT NOT NULL DEFAULT 20,
            percent DECIMAL(5,2) NOT NULL,
            passed TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip VARCHAR(64) NULL
        )',
        'CREATE TABLE IF NOT EXISTS question_stats (
            question_id INT PRIMARY KEY,
            wrong_count INT NOT NULL DEFAULT 0,
            total_count INT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )',
        'CREATE TABLE IF NOT EXISTS question_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            result_id INT NOT NULL,
            student_id INT NULL,
            question_id INT NOT NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            actor_user_id INT NULL,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(100) NULL,
            entity_id INT NULL,
            ip VARCHAR(64) NULL,
            details_json JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )',
    ];

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    if (!schemaColumnExists($pdo, 'results', 'student_id')) {
        $pdo->exec('ALTER TABLE results ADD COLUMN student_id INT NULL AFTER id');
    }

    if (!schemaColumnExists($pdo, 'students', 'is_active')) {
        $pdo->exec('ALTER TABLE students ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1');
    }

    if (!schemaIndexExists($pdo, 'results', 'idx_results_student_id')) {
        $pdo->exec('ALTER TABLE results ADD INDEX idx_results_student_id (student_id)');
    }

    if (!schemaIndexExists($pdo, 'students', 'idx_students_group_id')) {
        $pdo->exec('ALTER TABLE students ADD INDEX idx_students_group_id (group_id)');
    }

    if (!schemaIndexExists($pdo, 'question_attempts', 'idx_question_attempts_question_id')) {
        $pdo->exec('ALTER TABLE question_attempts ADD INDEX idx_question_attempts_question_id (question_id)');
    }

    if (!schemaIndexExists($pdo, 'question_attempts', 'idx_question_attempts_student_id')) {
        $pdo->exec('ALTER TABLE question_attempts ADD INDEX idx_question_attempts_student_id (student_id)');
    }

    if (!schemaIndexExists($pdo, 'audit_logs', 'idx_audit_logs_created_at')) {
        $pdo->exec('ALTER TABLE audit_logs ADD INDEX idx_audit_logs_created_at (created_at)');
    }

    seedDemoData($pdo);
}

function schemaColumnExists(PDO $pdo, string $tableName, string $columnName): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name'
    );
    $stmt->execute([
        'table_name' => $tableName,
        'column_name' => $columnName,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function schemaIndexExists(PDO $pdo, string $tableName, string $indexName): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = :table_name AND index_name = :index_name'
    );
    $stmt->execute([
        'table_name' => $tableName,
        'index_name' => $indexName,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function seedDemoData(PDO $pdo): void
{
    $demo = demoAccounts();

    $groupStatement = $pdo->prepare('INSERT INTO student_groups (name) VALUES (:name)');
    foreach ($demo['groups'] as $groupName) {
        if (!findGroupIdByName($pdo, $groupName)) {
            $groupStatement->execute(['name' => $groupName]);
        }
    }

    $userStatement = $pdo->prepare(
        'INSERT INTO users (username, password_hash, role, display_name, is_active)
         VALUES (:username, :password_hash, :role, :display_name, 1)'
    );
    $userUpdateStatement = $pdo->prepare(
        'UPDATE users
         SET password_hash = :password_hash, role = :role, display_name = :display_name, is_active = 1
         WHERE username = :username'
    );
    foreach ($demo['users'] as $user) {
        $payload = [
            'username' => $user['username'],
            'password_hash' => password_hash($user['password'], PASSWORD_BCRYPT),
            'role' => $user['role'],
            'display_name' => $user['display_name'],
        ];

        if (!findUserIdByUsername($pdo, $user['username'])) {
            $userStatement->execute($payload);
            continue;
        }

        $userUpdateStatement->execute($payload);
    }

    $studentStatement = $pdo->prepare(
        'INSERT INTO students (username, display_name, group_id, is_active)
         VALUES (:username, :display_name, :group_id, 1)'
    );
    $studentUpdateStatement = $pdo->prepare(
        'UPDATE students
         SET display_name = :display_name, group_id = :group_id, is_active = 1
         WHERE username = :username'
    );
    foreach ($demo['students'] as $student) {
        $groupId = findGroupIdByName($pdo, $demo['groups'][$student['group_key']]);
        if (!$groupId) {
            continue;
        }

        $payload = [
            'username' => $student['username'],
            'display_name' => $student['display_name'],
            'group_id' => $groupId,
        ];

        if (!findStudentIdByUsername($pdo, $student['username'])) {
            $studentStatement->execute($payload);
            continue;
        }

        $studentUpdateStatement->execute($payload);
    }

    $teacherAccessStatement = $pdo->prepare(
        'INSERT IGNORE INTO teacher_group_access (user_id, group_id) VALUES (:user_id, :group_id)'
    );
    foreach ($demo['teacher_group_access'] as $access) {
        $userId = findUserIdByUsername($pdo, $access['teacher_username']);
        $groupId = findGroupIdByName($pdo, $demo['groups'][$access['group_key']] ?? '');
        if (!$userId || !$groupId) {
            continue;
        }

        $teacherAccessStatement->execute([
            'user_id' => $userId,
            'group_id' => $groupId,
        ]);
    }
}

function installDemoAccounts(): array
{
    $pdo = db();
    seedDemoData($pdo);

    $usernames = array_map(
        static fn (array $user): string => (string) $user['username'],
        demoAccounts()['users']
    );

    $placeholders = [];
    $params = [];
    foreach ($usernames as $index => $username) {
        $key = 'username_' . $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $username;
    }

    $stmt = $pdo->prepare(
        'SELECT username, role, display_name
         FROM users
         WHERE username IN (' . implode(', ', $placeholders) . ')
         ORDER BY username ASC'
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function installTestDataset(): array
{
    $pdo = db();
    seedDemoData($pdo);

    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM question_attempts');
        $pdo->exec('DELETE FROM question_stats');
        $pdo->exec('DELETE FROM results');

        $studentLookupStmt = $pdo->query(
            'SELECT s.id, s.username, s.display_name, g.name AS group_name
             FROM students s
             LEFT JOIN student_groups g ON g.id = s.group_id
             ORDER BY s.id ASC'
        );
        $students = $studentLookupStmt->fetchAll();

        $resultStmt = $pdo->prepare(
            'INSERT INTO results (student_id, name, score, total_questions, percent, passed, created_at, ip)
             VALUES (:student_id, :name, :score, :total_questions, :percent, :passed, :created_at, :ip)'
        );
        $attemptStmt = $pdo->prepare(
            'INSERT INTO question_attempts (result_id, student_id, question_id, is_correct, created_at)
             VALUES (:result_id, :student_id, :question_id, :is_correct, :created_at)'
        );
        $statsStmt = $pdo->prepare(
            'INSERT INTO question_stats (question_id, wrong_count, total_count)
             VALUES (:question_id, :wrong_count, 1)
             ON DUPLICATE KEY UPDATE wrong_count = wrong_count + VALUES(wrong_count), total_count = total_count + 1'
        );

        $scorePattern = [19, 16, 20, 14, 18, 17, 13, 20, 15, 18, 12, 19];
        $datePattern = [
            '2026-06-01 09:00:00',
            '2026-06-01 14:00:00',
            '2026-06-02 10:00:00',
            '2026-06-02 15:00:00',
            '2026-06-03 09:30:00',
            '2026-06-03 16:00:00',
            '2026-06-04 11:00:00',
            '2026-06-04 17:00:00',
            '2026-06-05 10:30:00',
            '2026-06-05 15:30:00',
            '2026-06-06 09:15:00',
            '2026-06-06 13:45:00',
        ];
        $insertedRows = [];

        foreach ($students as $index => $student) {
            if ($index >= 12) {
                break;
            }

            $score = $scorePattern[$index];
            $totalQuestions = 20;
            $percent = round(($score / $totalQuestions) * 100, 2);
            $passed = $score >= 18 ? 1 : 0;
            $createdAt = $datePattern[$index];
            $ip = '127.0.0.' . ($index + 10);

            $resultStmt->execute([
                'student_id' => (int) $student['id'],
                'name' => $student['username'],
                'score' => $score,
                'total_questions' => $totalQuestions,
                'percent' => $percent,
                'passed' => $passed,
                'created_at' => $createdAt,
                'ip' => $ip,
            ]);

            $resultId = (int) $pdo->lastInsertId();

            for ($question = 1; $question <= $totalQuestions; $question++) {
                $isCorrect = $question <= $score ? 1 : 0;
                $attemptStmt->execute([
                    'result_id' => $resultId,
                    'student_id' => (int) $student['id'],
                    'question_id' => $question,
                    'is_correct' => $isCorrect,
                    'created_at' => $createdAt,
                ]);
                $statsStmt->execute([
                    'question_id' => $question,
                    'wrong_count' => $isCorrect ? 0 : 1,
                ]);
            }

            $insertedRows[] = [
                'username' => $student['username'],
                'display_name' => $student['display_name'],
                'group_name' => $student['group_name'],
                'score' => $score,
                'percent' => $percent,
                'passed' => (bool) $passed,
            ];
        }

        $pdo->commit();

        auditEvent('test_dataset_installed', 'result', null, [
            'rows' => count($insertedRows),
            'groups' => ['Группа A' => 4, 'Группа B' => 6, 'Группа C' => 2],
        ]);

        return [
            'rows' => $insertedRows,
            'summary' => [
                'Группа A' => 4,
                'Группа B' => 6,
                'Группа C' => 2,
            ],
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function findUserIdByUsername(PDO $pdo, string $username): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $result = $stmt->fetchColumn();

    return $result === false ? null : (int) $result;
}

function findStudentIdByUsername(PDO $pdo, string $username): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM students WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $result = $stmt->fetchColumn();

    return $result === false ? null : (int) $result;
}

function findGroupIdByName(PDO $pdo, string $name): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM student_groups WHERE name = :name LIMIT 1');
    $stmt->execute(['name' => $name]);
    $result = $stmt->fetchColumn();

    return $result === false ? null : (int) $result;
}

function auditEvent(
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    array $details = [],
    ?int $actorUserId = null,
    ?string $ip = null
): void {
    $record = [
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'actor_user_id' => $actorUserId,
        'ip' => $ip ?? clientIp(),
        'details' => $details,
        'created_at' => date('c'),
    ];

    try {
        $stmt = db()->prepare(
            'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, ip, details_json)
             VALUES (:actor_user_id, :action, :entity_type, :entity_id, :ip, :details_json)'
        );
        $stmt->execute([
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip' => $record['ip'],
            'details_json' => json_encode($details, JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $e) {
        $record['database_error'] = $e->getMessage();
        appendFileLog('audit_fallback.jsonl', $record);
    }
}
