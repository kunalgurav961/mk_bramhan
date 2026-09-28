-- =====================================================================
-- MK Brahman — Complete Database Reset & Recreate Script
-- Database: mk_brahman
-- Character Set: utf8mb4 (Full Marathi / Devanagari Unicode support)
-- =====================================================================

-- 1. DROP EXISTING DATABASE (DELETES ALL EXISTING DATA)
DROP DATABASE IF EXISTS `mk_brahman`;

-- 2. RECREATE DATABASE
CREATE DATABASE `mk_brahman`
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE `mk_brahman`;

-- ---------------------------------------------------------------------
-- Table: profiles
-- Contains all matrimonial profile records
-- ---------------------------------------------------------------------
CREATE TABLE `profiles` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `registration_no`   VARCHAR(50)       NOT NULL,
    `registration_year` VARCHAR(4)        DEFAULT NULL COMMENT 'Registration year for nondani kramank',
    `gender`            TINYINT           NOT NULL COMMENT '1=Mulaga, 2=Mulagi',
    `birth_year`        VARCHAR(4)        NOT NULL,
    `name`              VARCHAR(150)      NOT NULL,
    `jaat`              VARCHAR(80)       DEFAULT NULL COMMENT 'जात (e.g. देशस्थ, कोकणस्थ)',
    `gotra`             VARCHAR(80)       NOT NULL,
    `rashi`             VARCHAR(50)       DEFAULT NULL COMMENT 'राशी',
    `nadi`              VARCHAR(50)       DEFAULT NULL COMMENT 'नाडी: प्रथम / मध्य / अंत्य',
    `height_ft`         TINYINT UNSIGNED  NOT NULL DEFAULT 5,
    `height_in`         TINYINT UNSIGNED  NOT NULL DEFAULT 0,
    `salary`            INT UNSIGNED      NOT NULL DEFAULT 0,
    `weight`            SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Weight in kg',
    `varn`              VARCHAR(50)       DEFAULT NULL COMMENT 'Skin color / वर्ण (गोरा, गव्हाळ, सावळा)',
    `chashma`           TINYINT(1)        NOT NULL DEFAULT 0 COMMENT 'Glasses: 0=No, 1=Yes',
    `aahar`             VARCHAR(50)       DEFAULT NULL COMMENT 'Diet / आहार (शाकाहारी, मांसाहारी)',
    `education`         VARCHAR(150)      DEFAULT NULL,
    `occupation`        VARCHAR(150)      DEFAULT NULL,
    `city`              VARCHAR(100)      NOT NULL,
    `mobile_no`         VARCHAR(30)       DEFAULT NULL,
    `mobile`            VARCHAR(30)       DEFAULT NULL,
    `mobile_2`          VARCHAR(30)       DEFAULT NULL,
    `mobile_3`          VARCHAR(30)       DEFAULT NULL,
    `mobile_4`          VARCHAR(30)       DEFAULT NULL,
    `father_name`       VARCHAR(150)      DEFAULT NULL,
    `mother_name`       VARCHAR(150)      DEFAULT NULL,
    `family_details`    TEXT              DEFAULT NULL,
    `about_me`          TEXT              DEFAULT NULL,
    `profile_image`     VARCHAR(255)      DEFAULT NULL,
    `profile_photo`     VARCHAR(255)      DEFAULT NULL,
    `sheet_img_1`       VARCHAR(500)      DEFAULT NULL,
    `sheet_img_2`       VARCHAR(500)      DEFAULT NULL,
    `sheet_img_3`       VARCHAR(500)      DEFAULT NULL,
    `sheet_img_4`       VARCHAR(500)      DEFAULT NULL,
    `shortlisted`       TINYINT(1)        NOT NULL DEFAULT 0,
    `status`            VARCHAR(20)       NOT NULL DEFAULT 'Active' COMMENT 'Active / Inactive',
    `sheets_synced_at`  DATETIME          DEFAULT NULL,
    `created_at`        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY `uq_reg` (`registration_no`),
    INDEX `idx_name`       (`name`),
    INDEX `idx_birth_year` (`birth_year`),
    INDEX `idx_gender`     (`gender`),
    INDEX `idx_city`       (`city`),
    INDEX `idx_gotra`      (`gotra`),
    INDEX `idx_rashi`      (`rashi`),
    INDEX `idx_nadi`       (`nadi`),
    INDEX `idx_varn`       (`varn`),
    INDEX `idx_short`      (`shortlisted`),
    INDEX `idx_status`     (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: profile_images
-- Supports multiple photos/gallery per profile
-- ---------------------------------------------------------------------
CREATE TABLE `profile_images` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `profile_id` INT UNSIGNED NOT NULL,
    `filename`   VARCHAR(255) NOT NULL,
    `sort_order` INT          NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_profile_id` (`profile_id`),
    CONSTRAINT `fk_profile_images_profile` 
        FOREIGN KEY (`profile_id`) REFERENCES `profiles` (`id`) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: admins
-- Admin login credentials
-- ---------------------------------------------------------------------
CREATE TABLE `admins` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username`   VARCHAR(50)  NOT NULL UNIQUE,
    `password`   VARCHAR(255) NOT NULL,
    `full_name`  VARCHAR(100) NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Default Admin Accounts:
-- 1) Username: admin      Password: admin123
-- 2) Username: mk_braman  Password: mkbramhan@123
-- ---------------------------------------------------------------------
INSERT INTO `admins` (`username`, `password`, `full_name`) VALUES
('admin', '$2y$10$o.UBhzaA6sRnwn8gbRol1.2ri1KCOxnqsBpjeu51WluL65Gux0Mqi', 'MK Brahman Admin'),
('mk_braman', '$2y$10$oNtXR.luYTgVFekYFAMu/ewX6zVc5vE3H2IGQF578u.4CGYj3Gcoi', 'MK Brahman Superadmin');
