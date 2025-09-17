<?php
session_start();
require_once 'config/database.php';

// Only allow authenticated users
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit();
}

// Get report ID from query string
$report_id = isset($_GET['report_id']) ? (int)$_GET['report_id'] : 0;

if ($report_id <= 0) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid report ID']);
    exit();
}

try {
    $pdo_avr = getDBConnection('avr');
    $stmt = $pdo_avr->prepare("SELECT * FROM evidence WHERE report_id = ? ORDER BY uploaded_at DESC");
    $stmt->execute([$report_id]);
    $evidence = $stmt->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode($evidence);
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}