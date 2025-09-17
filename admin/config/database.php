<?php
// Database configuration for all modules
$databases = [
    'ttm' => [
        'host' => 'localhost',
        'name' => 'your_ttm_database',
        'user' => 'your_database_user',
        'pass' => 'your_database_password'
    ],
    'avr' => [
        'host' => 'localhost',
        'name' => 'your_avr_database',
        'user' => 'your_database_user',
        'pass' => 'your_database_password'
    ],
    'pats' => [
        'host' => 'localhost',
        'name' => 'your_pats_database',
        'user' => 'your_database_user',
        'pass' => 'your_database_password'
    ],
    'pts' => [
        'host' => 'localhost',
        'name' => 'your_pts_database',
        'user' => 'your_database_user',
        'pass' => 'your_database_password'
    ],
    'rtr' => [
        'host' => 'localhost',
        'name' => 'your_rtr_database',
        'user' => 'your_database_user',
        'pass' => 'your_database_password'
    ],
    'tm' => [
        'host' => 'localhost',
        'name' => 'your_tm_database',
        'user' => 'your_database_user',
        'pass' => 'your_database_password'
    ],
    'tsc' => [
        'host' => 'localhost',
        'name' => 'your_tsc_database',
        'user' => 'your_database_user',
        'pass' => 'your_database_password'
    ],
    'vrd' => [
        'host' => 'localhost',
        'name' => 'your_vrd_database',
        'user' => 'your_database_user',
        'pass' => 'your_database_password'
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
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw new Exception("Unable to connect to database: " . $dbName);
    }
}

// Main TTM database connection (default)
try {
    $pdo = getDBConnection('ttm');
} catch (Exception $e) {
    // Log error but don't display details to users
    error_log($e->getMessage());
    die("Database connection error. Please try again later.");
}
?>