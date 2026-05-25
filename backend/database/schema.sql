CREATE DATABASE IF NOT EXISTS lumbalgia_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE lumbalgia_db;

CREATE TABLE users (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    name         VARCHAR(100)  NOT NULL,
    email        VARCHAR(150)  UNIQUE NOT NULL,
    password     VARCHAR(255)  NOT NULL,
    role         ENUM('admin','student') DEFAULT 'student',
    semester     VARCHAR(20),
    group_type   ENUM('control','experimental') DEFAULT 'experimental',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    description TEXT
);

CREATE TABLE questions (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    category_id    INT,
    difficulty     ENUM('easy','medium','hard') NOT NULL,
    question_text  TEXT NOT NULL,
    option_a       VARCHAR(255),
    option_b       VARCHAR(255),
    option_c       VARCHAR(255),
    option_d       VARCHAR(255),
    correct_answer CHAR(1),
    feedback_text  TEXT,
    is_active      BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE game_sessions (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    user_id             INT NOT NULL,
    session_type        ENUM('pretest','game','posttest') NOT NULL,
    current_difficulty  ENUM('easy','medium','hard') DEFAULT 'easy',
    score               INT DEFAULT 0,
    total_questions     INT DEFAULT 0,
    started_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at            TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE session_answers (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    session_id          INT NOT NULL,
    question_id         INT NOT NULL,
    selected_answer     CHAR(1),
    is_correct          BOOLEAN,
    response_time_ms    INT,
    difficulty_at_time  ENUM('easy','medium','hard'),
    answered_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES game_sessions(id),
    FOREIGN KEY (question_id) REFERENCES questions(id)
);

CREATE TABLE test_results (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    user_id         INT NOT NULL,
    test_type       ENUM('pretest','posttest') NOT NULL,
    score           DECIMAL(5,2),
    knowledge_gain  DECIMAL(5,2) NULL,
    applied_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);