<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'u239038253_govindvarunkar');
define('DB_PASS', 'n+7@y^jkbSA');
define('DB_NAME', 'u239038253_mk_brahman');

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

// this is means the sftp working 