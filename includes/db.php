<?php
/**
 * MK Brahman — Database Connection
 *
 * Credentials are loaded from the root .env.php file.
 * Never hard-code credentials here — keep them in .env.php only.
 */

$envFile = dirname(__DIR__) . '/.env.php';
if (file_exists($envFile)) {
    require_once $envFile;
} else {
    // Fallback: throw a clear error so we notice immediately
    error_log('CRITICAL: .env.php not found at ' . $envFile);
    http_response_code(500);
    die('Configuration error. Please contact the administrator.');
}

function getDB(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            error_log('DB Connection Failed: ' . $conn->connect_error);
            http_response_code(500);
            die('Database connection error. Please contact administrator.');
        }
        $conn->set_charset('utf8mb4');

        // Auto-migration: ensure 'weight' column exists in profiles table
        $chk = $conn->query("SHOW COLUMNS FROM `profiles` LIKE 'weight'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("ALTER TABLE `profiles` ADD COLUMN `weight` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Weight in kg' AFTER `salary`");
        }

        // Auto-migration: ensure 'varn' column exists
        $chk = $conn->query("SHOW COLUMNS FROM `profiles` LIKE 'varn'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("ALTER TABLE `profiles` ADD COLUMN `varn` VARCHAR(50) DEFAULT NULL COMMENT 'Skin color / वर्ण' AFTER `weight`");
        }

        // Auto-migration: ensure 'chashma' column exists
        $chk = $conn->query("SHOW COLUMNS FROM `profiles` LIKE 'chashma'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("ALTER TABLE `profiles` ADD COLUMN `chashma` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Glasses yes/no' AFTER `varn`");
        }

        // Auto-migration: ensure 'registration_year' column exists
        $chk = $conn->query("SHOW COLUMNS FROM `profiles` LIKE 'registration_year'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("ALTER TABLE `profiles` ADD COLUMN `registration_year` VARCHAR(4) DEFAULT NULL COMMENT 'Registration year for nondani kramank' AFTER `registration_no`");
        }

        // Auto-migration: ensure 'aahar' column exists
        $chk = $conn->query("SHOW COLUMNS FROM `profiles` LIKE 'aahar'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("ALTER TABLE `profiles` ADD COLUMN `aahar` VARCHAR(50) DEFAULT NULL COMMENT 'Diet / आहार' AFTER `chashma`");
        }

    }
    return $conn;
}