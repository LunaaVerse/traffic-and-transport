<?php
// Database configuration for all modules
$databases = [
    'ttm' => [
        'host' => 'localhost',
        'name' => 'ttm_ttm',
        'user' => 'ttm_ttm',
        'pass' => 'Admin123'
    ],
    'avr' => [
        'host' => 'localhost',
        'name' => 'ttm_avr',
        'user' => 'ttm_avr',
        'pass' => 'Admin123'
    ],
    'pts' => [
        'host' => 'localhost',
        'name' => 'ttm_pts',
        'user' => 'ttm_pts',
        'pass' => 'Admin123'
    ],
    'pt' => [
        'host' => 'localhost', 
        'name' => 'ttm_pt',
        'user' => 'ttm_pt',
        'pass' => 'Admin123'
    ],
    'rtr' => [
        'host' => 'localhost',
        'name' => 'ttm_rtr',
        'user' => 'rottm_rtr',
        'pass' => 'Admin123'
    ],
    'tm' => [
        'host' => 'localhost',
        'name' => 'ttm_tm',
        'user' => 'ttm_tm',
        'pass' => 'Admin123'
    ],
    'tsc' => [
        'host' => 'localhost',
        'name' => 'ttm_tsc',
        'user' => 'ttm_tsc',
        'pass' => 'Admin123'
    ],
    'vrd' => [
        'host' => 'localhost',
        'name' => 'ttm_vrd',
        'user' => 'ttm_vrd',
        'pass' => 'Admin123'
    ]
];

// Function to get database connection
function getDBConnection($dbName) {
    global $databases;
    
    if (!isset($databases[$dbName])) {
        throw new Exception("Database configuration for '$dbName' not found");
    }
    
    $dbConfig = $databases[$dbName];
    
    try {
        $pdo = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4", 
            $dbConfig['user'], 
            $dbConfig['pass']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch(PDOException $e) {
        die("ERROR: Could not connect to database '$dbName'. " . $e->getMessage());
    }
}

// For backward compatibility
define('DB_HOST', 'localhost');
define('DB_NAME', 'ttm');
define('DB_USER', 'root');
define('DB_PASS', '');
?>