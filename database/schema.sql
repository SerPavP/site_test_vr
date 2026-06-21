CREATE DATABASE IF NOT EXISTS pdd_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pdd_test;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(30) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS student_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS teacher_group_access (
    user_id INT NOT NULL,
    group_id INT NOT NULL,
    PRIMARY KEY (user_id, group_id)
);

CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(120) NOT NULL UNIQUE,
    display_name VARCHAR(120) NOT NULL,
    group_id INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_students_group_id (group_id)
);

CREATE TABLE IF NOT EXISTS results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NULL,
    name VARCHAR(120) NOT NULL,
    score INT NOT NULL,
    total_questions INT NOT NULL DEFAULT 20,
    percent DECIMAL(5,2) NOT NULL,
    passed TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip VARCHAR(64) NULL,
    INDEX idx_results_student_id (student_id)
);

CREATE TABLE IF NOT EXISTS question_stats (
    question_id INT PRIMARY KEY,
    wrong_count INT NOT NULL DEFAULT 0,
    total_count INT NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS question_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    result_id INT NOT NULL,
    student_id INT NULL,
    question_id INT NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_question_attempts_question_id (question_id),
    INDEX idx_question_attempts_student_id (student_id)
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NULL,
    entity_id INT NULL,
    ip VARCHAR(64) NULL,
    details_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_logs_created_at (created_at)
);

-- Demo data. Password hashes are created automatically by the application
-- during the first successful connection to the database.
