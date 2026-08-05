-- =============================================
-- MK Brahman — Database Schema & Seed
-- Run this once in phpMyAdmin or MySQL CLI
-- =============================================

CREATE DATABASE IF NOT EXISTS mk_brahman
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE mk_brahman;

-- -----------------------------------------------
-- PROFILES TABLE
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS profiles (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_no VARCHAR(30)  NOT NULL UNIQUE,
    gender          TINYINT      NOT NULL COMMENT '1=Mulaga (Boy), 2=Mulagi (Girl)',
    birth_year      VARCHAR(4)   NOT NULL COMMENT 'Short year: 78, 95, 01',
    name            VARCHAR(100) NOT NULL COMMENT 'Full name in Marathi',
    gotra           VARCHAR(80)  NOT NULL COMMENT 'Gotra in Marathi',
    height_ft       TINYINT UNSIGNED NOT NULL DEFAULT 5,
    height_in       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    salary          SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Salary in Lakhs per year',
    education       VARCHAR(100) DEFAULT NULL,
    occupation      VARCHAR(100) DEFAULT NULL,
    city            VARCHAR(80)  NOT NULL,
    shortlisted     TINYINT(1)   NOT NULL DEFAULT 0,
    status          TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=Active, 0=Inactive',
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_name   (name),
    INDEX idx_city   (city),
    INDEX idx_gotra  (gotra),
    INDEX idx_short  (shortlisted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------
-- ADMINS TABLE
-- -----------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL COMMENT 'bcrypt hashed password',
    full_name   VARCHAR(100) NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------
-- DEFAULT ADMIN USER
-- username: admin
-- password: admin123
-- (hash generated with password_hash('admin123', PASSWORD_BCRYPT))
-- CHANGE THIS PASSWORD AFTER FIRST LOGIN!
-- -----------------------------------------------
INSERT IGNORE INTO admins (username, password, full_name)
VALUES (
    'admin',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.',
    'MK Brahman Admin'
);
