<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'aurora_db');

// Create Connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check Connection
if (!$conn) {
    die(json_encode([
        'status'  => 'error',
        'message' => 'Database connection failed: ' . mysqli_connect_error()
    ]));
}

// Set charset
mysqli_set_charset($conn, 'utf8');
?>