<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ttm_ttm');
define('DB_USER', 'ttm_ttm');
define('DB_PASS', 'Admin123');

// Create connection
$conn = new mysqli(DB_HOST, DB_NAME, DB_USER, DB_PASS);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");
?>