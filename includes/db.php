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
    }
    return $conn;
}