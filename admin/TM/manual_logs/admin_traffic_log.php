<?php
session_start();
require_once 'config/database.php';

// Authentication & Authorization
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get database connections
try {
    $pdo_tm = getDBConnection('tm');
    $pdo_ttm = getDBConnection('ttm');
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Function to get user profile
function getUserProfile($pdo_ttm, $user_id) {
    try {
        $query = "SELECT user_id, username, full_name, email, role FROM users WHERE user_id = :user_id";
        $stmt = $pdo_ttm->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    } catch (PDOException $e) {
        return ['full_name' => 'User', 'role' => 'Unknown'];
    }
}

// Function to determine volume status
function getVolumeStatus($volume) {
    if ($volume <= 20) {
        return 'Low';
    } elseif ($volume <= 50) {
        return 'Moderate';
    } else {
        return 'Heavy';
    }
}

// Initialize message variables
$message = '';
$message_type = '';

// Insert new log
if (isset($_POST['add'])) {
    $employee_id = $_SESSION['user_id'];
    $location = $_POST['location'];
    $district = $_POST['district'];
    $barangay = $_POST['barangay'];
    $volume = $_POST['volume'];
    $remarks = $_POST['remarks'];
    $volume_status = getVolumeStatus($volume);

    // Validate inputs
    if (empty($location) || empty($volume) || empty($district) || empty($barangay)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } else {
        try {
            // Start transaction
            $pdo_tm->beginTransaction();
            
            // Insert into traffic_logs
            $stmt = $pdo_tm->prepare("INSERT INTO traffic_logs (employee_id, location, volume, remarks, district, barangay) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$employee_id, $location, $volume, $remarks, $district, $barangay]);
            $log_id = $pdo_tm->lastInsertId();
            
            // Insert into traffic_volume with reference to the log_id
            $stmt2 = $pdo_tm->prepare("INSERT INTO traffic_volume (log_id, log_date, district, barangay, volume_status) VALUES (?, CURDATE(), ?, ?, ?)");
            $stmt2->execute([$log_id, $district, $barangay, $volume_status]);
            
            // Commit transaction
            $pdo_tm->commit();
            
            $message = 'Traffic log added successfully!';
            $message_type = 'success';
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo_tm->rollBack();
            $message = 'Error adding traffic log: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Update log
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $location = $_POST['location'];
    $district = $_POST['district'];
    $barangay = $_POST['barangay'];
    $volume = $_POST['volume'];
    $remarks = $_POST['remarks'];
    $volume_status = getVolumeStatus($volume);

    // Validate inputs
    if (empty($location) || empty($volume) || empty($district) || empty($barangay)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } else {
        try {
            // Start transaction
            $pdo_tm->beginTransaction();
            
            // Update traffic_logs
            $stmt = $pdo_tm->prepare("UPDATE traffic_logs SET location=?, volume=?, remarks=?, district=?, barangay=? WHERE id=?");
            $stmt->execute([$location, $volume, $remarks, $district, $barangay, $id]);
            
            // Update the corresponding traffic_volume record using log_id
            $stmt2 = $pdo_tm->prepare("UPDATE traffic_volume SET district=?, barangay=?, volume_status=? WHERE log_id=?");
            $stmt2->execute([$district, $barangay, $volume_status, $id]);
            
            // Commit transaction
            $pdo_tm->commit();
            
            $message = 'Traffic log updated successfully!';
            $message_type = 'success';
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo_tm->rollBack();
            $message = 'Error updating traffic log: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // Redirect to avoid resubmission on refresh
    header("Location: admin_traffic_log.php?message=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

// Delete log
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Validate ID
    if (!is_numeric($id)) {
        $message = 'Invalid log ID';
        $message_type = 'error';
    } else {
        try {
            // Start transaction
            $pdo_tm->beginTransaction();
            
            // First delete from traffic_volume where log_id matches
            $stmt_volume = $pdo_tm->prepare("DELETE FROM traffic_volume WHERE log_id = ?");
            $stmt_volume->execute([$id]);
            
            // Then delete from traffic_logs
            $stmt_log = $pdo_tm->prepare("DELETE FROM traffic_logs WHERE id = ?");
            $stmt_log->execute([$id]);
            
            // Commit transaction
            $pdo_tm->commit();
            
            $message = 'Traffic log deleted successfully!';
            $message_type = 'success';
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo_tm->rollBack();
            $message = 'Error deleting traffic log: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // Redirect to avoid resubmission on refresh
    header("Location: admin_traffic_log.php?message=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

// Check for message in URL (for delete operations)
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = $_GET['message'];
    $message_type = $_GET['type'];
}

// Fetch logs with additional details
$result = $pdo_tm->query("
    SELECT tl.*, u.full_name, u.role
    FROM traffic_logs tl
    LEFT JOIN ttm.users u ON tl.employee_id = u.user_id
    ORDER BY tl.log_date DESC
");
$logs = $result->fetchAll();

// Fetch user profile
$user_profile = getUserProfile($pdo_ttm, $_SESSION['user_id']);

// Set active tab
$current_page = basename($_SERVER['PHP_SELF']);
$active_tab = 'traffic_monitoring';

// Define barangays by district
$barangays_by_district = [
    'District 1' => ['Alicia', 'Bagong Pag-asa', 'Bahay Toro', 'Balingasa', 'Bungad',
        'Damar', 'Damayan', 'Del Monte', 'Katipunan', 'Lourdes',
        'Maharlika', 'Manresa', 'Mariblo', 'Masambong', 'N.S. Amoranto (Gintong Silahis)',
        'Nayong Kanluran', 'Paang Bundok', 'Pag-ibig sa Nayon', 'Paltok', 'Paraiso',
        'Phil-Am', 'Project 6', 'Ramon Magsaysay', 'Salvacion', 'San Antonio',
        'San Isidro Labrador', 'San Jose', 'Santa Cruz', 'Santa Teresita',
        'Sto. Cristo', 'Veterans Village', 'West Triangle'],
    'District 2' => [
        'Bagong Silangan', 'Batasan Hills', 'Commonwealth',
        'Holy Spirit', 'Payatas'
    ],
    'District 3' => [
        'Amihan', 'Bagumbayan', 'Bagumbuhay', 'Bayanihan',
        'Blue Ridge A', 'Blue Ridge B', 'Camp Aguinaldo',
        'Claro (Quirino 3-B)', 'Dioquino Zobel', 'Duyan-Duyan',
        'E. Rodriguez', 'East Kamias', 'Escopa I', 'Escopa II',
        'Escopa III', 'Escopa IV', 'Libis', 'Loyola Heights',
        'Mangga', 'Marilag', 'Masagana', 'Matandang Balara',
        'Milagrosa', 'Pansol', 'Quirino 2A', 'Quirino 2B',
        'Quirino 2C', 'Quirino 3A', 'St. Ignatius', 'San Roque',
        'Silangan', 'Socorro', 'Tagumpay', 'Ugong Norte',
        'Villa Maria Clara', 'White Plains', 'West Kamias'
    ],
    'District 4' => [
        'Bagong Lipunan ng Crame', 'Botocan', 'Central',
        'Damayang Lagi', 'Don Manuel', 'Doña Aurora',
        'Doña Imelda', 'Doña Josefa', 'Horseshoe',
        'Immaculate Conception', 'Kalusugan', 'Kamuning',
        'Kaunlaran', 'Kristong Hari', 'Krus na Ligas',
        'Laging Handa', 'Malaya', 'Mariana', 'Obrero',
        'Old Capitol Site', 'Paligsahan', 'Pinagkaisahan',
        'Pinyahan', 'Roxas', 'Sacred Heart', 'San Isidro Galas',
        'San Martin de Porres', 'San Vicente', 'Santol',
        'Sikatuna Village', 'South Triangle', 'Santo Niño',
        'Tatalon', 'Teacher’s Village East', 'Teacher’s Village West',
        'U.P. Campus', 'U.P. Village', 'Valencia'
    ],
    'District 5' => [
        'Bagbag', 'Capri', 'Fairview', 'Greater Lagro',
        'Gulod', 'Kaligayahan', 'Nagkaisang Nayon',
        'North Fairview', 'Novaliches Proper',
        'Pasong Putik Proper', 'San Agustin', 'San Bartolome',
        'Santa Lucia', 'Santa Monica'
    ],
    'District 6' => [
        'Apolonio Samson', 'Baesa', 'Balon Bato', 'Culiat',
        'New Era', 'Pasong Tamo', 'Sangandaan', 'Sauyo',
        'Talipapa', 'Tandang Sora', 'Unang Sigaw'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manual Traffic Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* User Info Styles - Enhanced */
.user-info-card {
    background: var(--primary-gradient);
    border-radius: var(--card-radius);
    padding: 1rem;
    color: white;
    box-shadow: var(--card-shadow);
    border: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 280px;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.user-info-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
    z-index: 1;
}

.user-info-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 35px rgba(13, 110, 253, 0.2);
}

.user-avatar {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
    z-index: 2;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.user-details {
    flex: 1;
    z-index: 2;
}

.user-name {
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 0.25rem;
    font-family: 'Montserrat', sans-serif;
}

.user-role-badge {
    background-color: rgba(255, 255, 255, 0.2);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-block;
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.user-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    margin-top: 0.5rem;
    opacity: 0.9;
}

.status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #28a745;
    position: relative;
}

.status-indicator::after {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background-color: #28a745;
    opacity: 0.5;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(1);
        opacity: 0.5;
    }
    50% {
        transform: scale(2);
        opacity: 0;
    }
    100% {
        transform: scale(1);
        opacity: 0.5;
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .user-info-card {
        min-width: auto;
        width: 100%;
    }
    
    .header-actions {
        width: 100%;
    }
}
        /* Additional styles specific to this page */
        .user-info {
            background: var(--primary-gradient);
            border-radius: var(--card-radius);
            padding: 15px;
            color: white;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .user-info h5 {
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .user-info p {
            margin-bottom: 3px;
            font-size: 0.9rem;
        }
        
        .user-role {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            display: inline-block;
            margin-top: 5px;
        }
        
        .card {
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
            border: 1px solid rgba(13, 110, 253, 0.1);
            transition: var(--transition);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(13, 110, 253, 0.15);
        }
        
        .card-header {
            background: rgba(248, 249, 250, 0.5);
            border-bottom: 1px solid var(--gray-200);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-header i {
            color: var(--primary);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            transition: var(--transition);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
        }
        
        .btn {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            box-shadow: 0 4px 10px rgba(13, 110, 253, 0.25);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #0b5ed7 0%, #2b75e3 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(13, 110, 253, 0.35);
        }
        
        .btn-outline-primary {
            border: 1px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #198754 0%, #2d9f6a 100%);
            border: none;
            box-shadow: 0 4px 10px rgba(25, 135, 84, 0.25);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #e34f5d 100%);
            border: none;
            box-shadow: 0 4px 10px rgba(220, 53, 69, 0.25);
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .table-container {
            border-radius: var(--card-radius);
            overflow: hidden;
            box-shadow: var(--card-shadow);
            background: white;
        }
        
        .table {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table thead th {
            background: var(--primary-gradient);
            color: white;
            font-weight: 600;
            padding: 1rem;
            border: none;
            text-align: left;
        }
        
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .table tbody tr {
            transition: var(--transition);
        }
        
        .table tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.05);
        }
        
        .badge {
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.75rem;
        }
        
        .badge.bg-success {
            background: linear-gradient(135deg, #198754 0%, #2d9f6a 100%);
        }
        
        .badge.bg-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ffce3a 100%);
            color: #000;
        }
        
        .badge.bg-danger {
            background: linear-gradient(135deg, #dc3545 0%, #e34f5d 100%);
        }
        
        .modal-content {
            border-radius: var(--card-radius);
            border: none;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
            background: var(--primary-gradient);
            color: white;
            border-top-left-radius: var(--card-radius);
            border-top-right-radius: var(--card-radius);
            padding: 1.25rem 1.5rem;
        }
        
        .modal-title {
            font-weight: 600;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--gray-200);
        }
        
        .alert {
            border-radius: var(--card-radius);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border: none;
            box-shadow: var(--card-shadow);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #e2f0e6 100%);
            color: #0f5132;
            border-left: 4px solid var(--success);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5e1e3 100%);
            color: #842029;
            border-left: 4px solid var(--danger);
        }
        
        .text-gradient {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .bg-gradient-primary {
            background: var(--primary-gradient);
        }
        
        .icon-box {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(13, 110, 253, 0.1);
            color: var(--primary);
            font-size: 1.5rem;
            margin-right: 15px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }
        
        .spinner-border {
            width: 1.5rem;
            height: 1.5rem;
            border-width: 0.2em;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 250px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block;
            }
            /* Add these to your existing style.css file */

/* User Info Styles */
.user-info {
    background: var(--primary-gradient);
    border-radius: var(--card-radius);
    padding: 15px;
    color: white;
    box-shadow: var(--card-shadow);
    border: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.user-info h5 {
    margin-bottom: 5px;
    font-weight: 600;
    font-size: 1.1rem;
}

.user-info p {
    margin-bottom: 3px;
    font-size: 0.9rem;
}

.user-role {
    background-color: rgba(255, 255, 255, 0.2);
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    display: inline-block;
    margin-top: 5px;
}

/* Table Styles */
.table-container {
    border-radius: var(--card-radius);
    overflow: hidden;
    box-shadow: var(--card-shadow);
    background: white;
}

.table {
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
}

.table thead th {
    background: var(--primary-gradient);
    color: white;
    font-weight: 600;
    padding: 1rem;
    border: none;
    text-align: left;
}

.table tbody td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--gray-200);
}

.table tbody tr:last-child td {
    border-bottom: none;
}

.table tbody tr {
    transition: var(--transition);
}

.table tbody tr:hover {
    background-color: rgba(13, 110, 253, 0.05);
}

/* Badge Styles */
.badge {
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    font-weight: 500;
    font-size: 0.75rem;
}

.badge.bg-success {
    background: linear-gradient(135deg, #198754 0%, #2d9f6a 100%);
}

.badge.bg-warning {
    background: linear-gradient(135deg, #ffc107 0%, #ffce3a 100%);
    color: #000;
}

.badge.bg-danger {
    background: linear-gradient(135deg, #dc3545 0%, #e34f5d 100%);
}
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
   <!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <img src="img/ttm.png" alt="Logo">
        <div class="text">
            Quezon City<br>
            <small>Traffic Management System</small>
        </div>
    </div>
    
    <div class="sidebar-menu">
        <div class="sidebar-section">Main Navigation</div>
        <a href="../dashboard/dashboard.php" class="sidebar-link">
            <i class='bx bx-home'></i>
            <span class="text">Dashboard</span>
        </a>

        <!-- Traffic Monitoring with Dropdown -->
        <div class="sidebar-section mt-4">Traffic Modules</div>
        <div class="dropdown-toggle sidebar-link active" data-bs-toggle="collapse" data-bs-target="#tmDropdown" aria-expanded="true">
            <i class='bx bx-traffic-cone'></i>
            <span class="text">Traffic Monitoring</span>
        </div>
        <div class="sidebar-dropdown collapse show" id="tmDropdown">
            <a href="../dashboard.php" class="sidebar-dropdown-link">
                <i class='bx bx-bar-chart'></i> Dashboard
            </a>
            <a href="../../TM/manual_logs/admin_traffic_log.php" class="sidebar-dropdown-link">
                <i class='bx bx-notepad'></i> Manual Traffic Logs
            </a>
            <a href="../../TM/traffic_volume/tv.php" class="sidebar-dropdown-link">
                <i class='bx bx-pulse'></i> Traffic Volume Status
            </a>
            <a href="../../TM/daily_monitoring/daily_monitoring.php" class="sidebar-dropdown-link">
                <i class='bx bx-file'></i> Daily Monitoring Reports
            </a>
            <a href="../../TM/cctv_integration/admin_cctv.php" class="sidebar-dropdown-link active">
                <i class='bx bx-cctv'></i> CCTV Integration
            </a>
        </div>

        <!-- Real-time Road Update with Dropdown -->
        <div class="dropdown-toggle sidebar-link" data-bs-toggle="collapse" data-bs-target="#rruDropdown" aria-expanded="false">
            <i class='bx bx-radar'></i>
            <span class="text">Real-time Road Update</span>
        </div>
        <div class="sidebar-dropdown collapse" id="rruDropdown">
            <a href="../../RTR/post_dashboard/admin_road_updates.php" class="sidebar-dropdown-link">
                <i class='bx bx-news'></i> Post Dashboard
            </a>
            <a href="../../RTR/status_management/status_management.php" class="sidebar-dropdown-link">
                <i class='bx bx-bar-chart-alt'></i> Status Management
            </a>
            <a href="../../RTR/real_time_road/admin_road_updates.php" class="sidebar-dropdown-link">
                <i class='bx bx-timer'></i> Real Time Road
            </a>
            <a href="../../RTR/road_condition_map/road_condition_map.php" class="sidebar-dropdown-link">
                <i class='bx bx-map'></i> Road Condition Map
            </a>
        </div>

        <!-- Accident & Violation Report with Dropdown -->
        <div class="dropdown-toggle sidebar-link" data-bs-toggle="collapse" data-bs-target="#avrDropdown" aria-expanded="false">
            <i class='bx bx-shield-x'></i>
            <span class="text">Accident & Violation Report</span>
        </div>
        <div class="sidebar-dropdown collapse" id="avrDropdown">
            <a href="../../AVR/report_management/report_management.php" class="sidebar-dropdown-link">
                <i class='bx bx-file'></i> Report Management
            </a>
            <a href="../../AVR/violation_categorization/violation_categorization.php" class="sidebar-dropdown-link">
                <i class='bx bx-category'></i> Violation Categorization
            </a>
            <a href="../../AVR/evidence_handling/evidence_admin.php" class="sidebar-dropdown-link">
                <i class='bx bx-folder'></i> Evidence Handling
            </a>
            <a href="../../AVR/violation_records/admin_violation_records.php" class="sidebar-dropdown-link">
                <i class='bx bx-book'></i> Violation Record 
            </a>
            <a href="../../AVR/escalation_assignment/admin_escalation_assignment.php" class="sidebar-dropdown-link">
                <i class='bx bx-transfer'></i> Escalation & Assignment  
            </a>
        </div>

        <!-- Vehicle Routing & Diversion with Dropdown -->
        <div class="dropdown-toggle sidebar-link" data-bs-toggle="collapse" data-bs-target="#vrdDropdown" aria-expanded="false">
            <i class='bx bx-map-alt'></i>
            <span class="text">Vehicle Routing & Diversion</span>
        </div>
        <div class="sidebar-dropdown collapse" id="vrdDropdown">
            <a href="../../VRD/route_configuration_panel/route_configuration_panel.php" class="sidebar-dropdown-link">
                <i class='bx bx-map-alt'></i> Route Configuration Panel
            </a>
            <a href="../../VRD/diversion_planning/diversion_planning.php" class="sidebar-dropdown-link">
                <i class='bx bx-git-branch'></i> Diversion Planning
            </a>
            <a href="../../VRD/ai_rule_management/rule_management.php" class="sidebar-dropdown-link">
                <i class='bx bx-cog'></i> AI Rule Management
            </a>
            <a href="../../VRD/osm/osm_integration.php" class="sidebar-dropdown-link">
                <i class='bx bx-globe'></i> OSM (Leaflet) Integration
            </a>
            <a href="../../VRD/routing_analytics/routing_analytics.php" class="sidebar-dropdown-link">
                <i class='bx bx-analyse'></i> Routing Analytics 
            </a>
        </div>

        <!-- Traffic Signal Control with Dropdown -->
        <div class="dropdown-toggle sidebar-link" data-bs-toggle="collapse" data-bs-target="#tscDropdown" aria-expanded="false">
            <i class='bx bx-time-five'></i>
            <span class="text">Traffic Signal Control</span>
        </div>
        <div class="sidebar-dropdown collapse" id="tscDropdown">
            <a href="../../TSC/signal_timing_management/signal_timing_management.php" class="sidebar-dropdown-link">
                <i class='bx bx-timer'></i> Signal Timing Management
            </a>
            <a href="../../TSC/real_time_signal_override/real_time_signal_override.php" class="sidebar-dropdown-link">
                <i class='bx bx-play-circle'></i> Real-Time Signal Override
            </a>
            <a href="../../TSC/automation_settings/admin_traffic_log.php" class="sidebar-dropdown-link">
                <i class='bx bx-cog'></i> Automation Settings
            </a>
            <a href="../../TSC/performance_logs/performance_logs.php" class="sidebar-dropdown-link">
                <i class='bx bx-line-chart'></i> Performance Logs 
            </a>
        </div>

        <!-- Public Transport with Dropdown -->
        <div class="dropdown-toggle sidebar-link" data-bs-toggle="collapse" data-bs-target="#ptsDropdown" aria-expanded="false">
            <i class='bx bx-bus'></i>
            <span class="text">Public Transport</span>
        </div>
        <div class="sidebar-dropdown collapse" id="ptsDropdown">
            <a href="../../PT/fleet_management/fleet_management.php" class="sidebar-dropdown-link">
                <i class='bx bx-car'></i> Fleet Management
            </a>
            <a href="../../PT/route_and_schedule/route_and_schedule.php" class="sidebar-dropdown-link">
                <i class='bx bx-time-five'></i> Route & Schedule Management
            </a>
            <a href="../../PT/real_time_tracking/real_time_tracking.php" class="sidebar-dropdown-link">
                <i class='bx bx-map-pin'></i> Real-Time Tracking
            </a>
            <a href="../../PT/passenger_capacity_compliance/passenger_capacity_compliance.php" class="sidebar-dropdown-link">
                <i class='bx bx-group'></i> Passenger Capacity Compliance 
            </a>
        </div>

        <!-- Permit & Ticketing System with Dropdown -->
        <div class="dropdown-toggle sidebar-link" data-bs-toggle="collapse" data-bs-target="#patsDropdown" aria-expanded="false">
            <i class='bx bx-receipt'></i>
            <span class="text">Permit & Ticketing System</span>
        </div>
        <div class="sidebar-dropdown collapse" id="patsDropdown">
            <a href="../../PTS/permit_application_processing/permit_application_processing.php" class="sidebar-dropdown-link">
                <i class='bx bx-file'></i> Permit Application Processing
            </a>
            <a href="../../PTS/ticket_issuance_control/ticket_issuance_control.php" class="sidebar-dropdown-link">
                <i class='bx bx-list-check'></i> Ticket Issuance Control          
            </a>
            <a href="../../PTS/payment_settlement_management/payment_settlement_management.php" class="sidebar-dropdown-link">
                <i class='bx bx-credit-card'></i> Payment & Settlement Management
            </a>
            <a href="../../PTS/offender_management/offender_management.php" class="sidebar-dropdown-link">
                <i class='bx bx-user-x'></i> Database of Offenders
            </a>
            <a href="../../PTS/compliance_revenue_reports/compliance_revenue_reports.php" class="sidebar-dropdown-link">
                <i class='bx bx-bar-chart-square'></i> Compliance & Revenue Reports
            </a>
        </div>
        
        <div class="sidebar-section mt-4">User</div>
        <a href="../profile.php" class="sidebar-link">
            <i class='bx bx-user'></i>
            <span class="text">Profile</span>
        </a>
        <a href="../logout.php" class="sidebar-link">
            <i class='bx bx-log-out'></i>
            <span class="text">Logout</span>
        </a>
    </div>
</div>
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="dashboard-header fade-in">
                <div class="page-title">
                    <h1>Traffic Monitoring</h1>
                    <p>Manage and monitor traffic logs and volume data</p>
                </div>
                
                <div class="header-actions">
                    <div class="user-info">
                        <h5><?php echo htmlspecialchars($user_profile['full_name']); ?></h5>
                        <p class="user-role"><?php echo htmlspecialchars($user_profile['role']); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'danger'; ?> fade-in" role="alert">
                    <i class='bx <?php echo $message_type == 'success' ? 'bx-check-circle' : 'bx-error-circle'; ?>'></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Add Log Form -->
            <div class="card fade-in">
                <div class="card-header">
                    <i class='bx bx-plus'></i>
                    Add New Traffic Log
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="district" class="form-label">District</label>
                                <select class="form-select" id="district" name="district" required>
                                    <option value="" selected disabled>Select District</option>
                                    <option value="District 1">District 1</option>
                                    <option value="District 2">District 2</option>
                                    <option value="District 3">District 3</option>
                                    <option value="District 4">District 4</option>
                                    <option value="District 5">District 5</option>
                                    <option value="District 6">District 6</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="barangay" class="form-label">Barangay</label>
                                <select class="form-select" id="barangay" name="barangay" required>
                                    <option value="" selected disabled>Select Barangay</option>
                                    <!-- Barangay options will be populated by JavaScript based on district selection -->
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="volume" class="form-label">Traffic Volume</label>
                                <input type="number" class="form-control" id="volume" name="volume" min="1" required>
                                <div class="form-text">Enter the number of vehicles per hour</div>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="3"></textarea>
                            </div>
                        </div>
                        <button type="submit" name="add" class="btn btn-primary">
                            <i class='bx bx-plus'></i> Add Log
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Traffic Logs History -->
            <div class="card fade-in">
                <div class="card-header">
                    <i class='bx bx-history'></i>
                    Traffic Logs History
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Location</th>
                                    <th>District</th>
                                    <th>Barangay</th>
                                    
                                    <th>Volume Status</th>
                                    <th>Employee</th>
                                    <th>Remarks</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($logs) > 0): ?>
                                    <?php foreach ($logs as $log): ?>
                                        <?php
                                        $volume_status = getVolumeStatus($log['volume']);
                                        $badge_class = '';
                                        if ($volume_status == 'Low') {
                                            $badge_class = 'bg-success';
                                        } elseif ($volume_status == 'Moderate') {
                                            $badge_class = 'bg-warning';
                                        } else {
                                            $badge_class = 'bg-danger';
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo date('M j, Y g:i A', strtotime($log['log_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($log['location']); ?></td>
                                            <td><?php echo htmlspecialchars($log['district']); ?></td>
                                            <td><?php echo htmlspecialchars($log['barangay']); ?></td>
                                           
                                            <td><span class="badge <?php echo $badge_class; ?>"><?php echo $volume_status; ?></span></td>
                                            <td><?php echo htmlspecialchars($log['full_name'] ?? 'Unknown'); ?></td>
                                            <td><?php echo htmlspecialchars($log['remarks']); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $log['id']; ?>">
                                                        <i class='bx bx-edit'></i>
                                                    </button>
                                                    <a href="admin_traffic_log.php?delete=<?php echo $log['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this log?')">
                                                        <i class='bx bx-trash'></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        
                                        <!-- Edit Modal -->
                                        <div class="modal fade" id="editModal<?php echo $log['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $log['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="editModalLabel<?php echo $log['id']; ?>">Edit Traffic Log</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <form method="POST" action="">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="id" value="<?php echo $log['id']; ?>">
                                                            <div class="row">
                                                                <div class="col-md-6 mb-3">
                                                                    <label for="edit_location<?php echo $log['id']; ?>" class="form-label">Location</label>
                                                                    <input type="text" class="form-control" id="edit_location<?php echo $log['id']; ?>" name="location" value="<?php echo htmlspecialchars($log['location']); ?>" required>
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label for="edit_district<?php echo $log['id']; ?>" class="form-label">District</label>
                                                                    <select class="form-select" id="edit_district<?php echo $log['id']; ?>" name="district" required>
                                                                        <option value="District 1" <?php echo $log['district'] == 'District 1' ? 'selected' : ''; ?>>District 1</option>
                                                                        <option value="District 2" <?php echo $log['district'] == 'District 2' ? 'selected' : ''; ?>>District 2</option>
                                                                        <option value="District 3" <?php echo $log['district'] == 'District 3' ? 'selected' : ''; ?>>District 3</option>
                                                                        <option value="District 4" <?php echo $log['district'] == 'District 4' ? 'selected' : ''; ?>>District 4</option>
                                                                        <option value="District 5" <?php echo $log['district'] == 'District 5' ? 'selected' : ''; ?>>District 5</option>
                                                                        <option value="District 6" <?php echo $log['district'] == 'District 6' ? 'selected' : ''; ?>>District 6</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label for="edit_barangay<?php echo $log['id']; ?>" class="form-label">Barangay</label>
                                                                    <select class="form-select" id="edit_barangay<?php echo $log['id']; ?>" name="barangay" required>
                                                                        <?php
                                                                        $district_barangays = $barangays_by_district[$log['district']] ?? [];
                                                                        foreach ($district_barangays as $barangay_option):
                                                                        ?>
                                                                            <option value="<?php echo $barangay_option; ?>" <?php echo $log['barangay'] == $barangay_option ? 'selected' : ''; ?>>
                                                                                <?php echo $barangay_option; ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label for="edit_volume<?php echo $log['id']; ?>" class="form-label">Traffic Volume</label>
                                                                    <input type="number" class="form-control" id="edit_volume<?php echo $log['id']; ?>" name="volume" value="<?php echo htmlspecialchars($log['volume']); ?>" min="1" required>
                                                                </div>
                                                                <div class="col-12 mb-3">
                                                                    <label for="edit_remarks<?php echo $log['id']; ?>" class="form-label">Remarks</label>
                                                                    <textarea class="form-control" id="edit_remarks<?php echo $log['id']; ?>" name="remarks" rows="3"><?php echo htmlspecialchars($log['remarks']); ?></textarea>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="update" class="btn btn-primary">
                                                                <i class='bx bx-save'></i> Update Log
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">No traffic logs found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // JavaScript for dynamic barangay selection
        document.addEventListener('DOMContentLoaded', function() {
            const barangaysByDistrict = <?php echo json_encode($barangays_by_district); ?>;
            
            // For add form
            const districtSelect = document.getElementById('district');
            const barangaySelect = document.getElementById('barangay');
            
            if (districtSelect && barangaySelect) {
                districtSelect.addEventListener('change', function() {
                    const selectedDistrict = this.value;
                    barangaySelect.innerHTML = '<option value="" selected disabled>Select Barangay</option>';
                    
                    if (selectedDistrict && barangaysByDistrict[selectedDistrict]) {
                        barangaysByDistrict[selectedDistrict].forEach(barangay => {
                            const option = document.createElement('option');
                            option.value = barangay;
                            option.textContent = barangay;
                            barangaySelect.appendChild(option);
                        });
                    }
                });
            }
            
            // For edit modals
            document.querySelectorAll('[id^="edit_district"]').forEach(districtSelect => {
                districtSelect.addEventListener('change', function() {
                    const modalId = this.id.replace('edit_district', '');
                    const barangaySelect = document.getElementById('edit_barangay' + modalId);
                    const selectedDistrict = this.value;
                    
                    if (barangaySelect && selectedDistrict && barangaysByDistrict[selectedDistrict]) {
                        barangaySelect.innerHTML = '';
                        
                        barangaysByDistrict[selectedDistrict].forEach(barangay => {
                            const option = document.createElement('option');
                            option.value = barangay;
                            option.textContent = barangay;
                            barangaySelect.appendChild(option);
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>