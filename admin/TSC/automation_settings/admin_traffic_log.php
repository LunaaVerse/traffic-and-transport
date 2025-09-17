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
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Function to get user profile
function getUserProfile($pdo_ttm, $user_id) {
    try {
        $pdo_ttm = getDBConnection('ttm');
        $query = "SELECT user_id, username, full_name, email, role FROM users WHERE user_id = :user_id";
        $stmt = $pdo_ttm->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    } catch (PDOException $e) {
        return ['full_name' => 'User', 'role' => 'Unknown'];
    }
}

// Initialize message variables
$message = '';
$message_type = '';

// Insert new log
if (isset($_POST['add'])) {
    $location = $_POST['location'];
    $volume = $_POST['volume'];
    $remarks = $_POST['remarks'];

    // Validate inputs
    if (empty($location) || empty($volume)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } else {
        try {
            $stmt = $pdo_tm->prepare("INSERT INTO traffic_logs (location, volume, remarks) VALUES (?, ?, ?)");
            $stmt->execute([$location, $volume, $remarks]);
            
            $message = 'Traffic log added successfully!';
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Error adding traffic log: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Update log
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $location = $_POST['location'];
    $volume = $_POST['volume'];
    $remarks = $_POST['remarks'];

    // Validate inputs
    if (empty($location) || empty($volume)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } else {
        try {
            $stmt = $pdo_tm->prepare("UPDATE traffic_logs SET location=?, volume=?, remarks=? WHERE id=?");
            $stmt->execute([$location, $volume, $remarks, $id]);
            
            $message = 'Traffic log updated successfully!';
            $message_type = 'success';
        } catch (PDOException $e) {
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
    try {
        $stmt = $pdo_tm->prepare("DELETE FROM traffic_logs WHERE id = ?");
        $stmt->execute([$id]);
        
        $message = 'Traffic log deleted successfully!';
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = 'Error deleting traffic log: ' . $e->getMessage();
        $message_type = 'error';
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

// Fetch logs
$result = $pdo_tm->query("SELECT * FROM traffic_logs ORDER BY log_date DESC");
$logs = $result->fetchAll();

// Fetch user profile
$user_profile = getUserProfile($pdo_tm, $_SESSION['user_id']);

// Set active tab
$current_page = basename($_SERVER['PHP_SELF']);
$active_tab = 'traffic_monitoring';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manual Traffic Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --primary: #0d6efd;
            --primary-dark: #0b5ed7;
            --primary-light: #3d85e4;
            --primary-gradient: linear-gradient(135deg, #0d6efd 0%, #3d85e4 100%);
            --secondary: #6c757d;
            --accent: #fd7e14;
            --success: #198754;
            --warning: #ffc107;
            --danger: #dc3545;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-800: #334155;
            --sidebar-width: 280px;
            --header-height: 80px;
            --card-radius: 16px;
            --card-shadow: 0 10px 30px rgba(13, 110, 253, 0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: #f9fafb;
            color: #334155;
            font-weight: 400;
            overflow-x: hidden;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            min-height: 100vh;
        }
        
        /* Layout */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--dark);
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding: 1.5rem 1rem;
            transition: var(--transition);
            z-index: 1000;
            overflow-y: auto;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-header {
            display: flex;
            align-items: center;
            padding: 0 0.5rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header img {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            margin-right: 12px;
            object-fit: cover;
        }
        
        .sidebar-header .text {
            font-weight: 600;
            font-size: 16px;
            line-height: 1.3;
            font-family: 'Montserrat', sans-serif;
        }
        
        .sidebar-header .text small {
            font-size: 12px;
            opacity: 0.7;
            font-weight: 400;
        }
        
        .sidebar-menu {
            margin-top: 2rem;
        }
        
        .sidebar-section {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0.75rem 0.5rem;
            color: #94a3b8;
            font-weight: 600;
        }
        
        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            color: #cbd5e1;
            text-decoration: none;
            transition: var(--transition);
            margin-bottom: 0.25rem;
            position: relative;
            overflow: hidden;
        }
        
        .sidebar-link::before {
            content: '';
            position: absolute;
            left: -10px;
            top: 0;
            height: 100%;
            width: 4px;
            background: var(--primary);
            opacity: 0;
            transition: var(--transition);
        }
        
        .sidebar-link:hover, .sidebar-link.active {
            background: rgba(255, 255, 255, 0.08);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar-link:hover::before, .sidebar-link.active::before {
            opacity: 1;
            left: 0;
        }
        
        .sidebar-link i {
            font-size: 1.25rem;
            margin-right: 12px;
            width: 24px;
            text-align: center;
            transition: var(--transition);
        }
        
        .sidebar-link:hover i, .sidebar-link.active i {
            color: var(--primary);
            transform: scale(1.1);
        }
        
        .sidebar-link .text {
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
        }
        
        /* Dropdown menu */
        .sidebar-dropdown {
            margin-left: 2rem;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .sidebar-dropdown.show {
            max-height: 500px;
        }
        
        .sidebar-dropdown-link {
            display: flex;
            align-items: center;
            padding: 0.6rem 1rem;
            border-radius: 6px;
            color: #94a3b8;
            text-decoration: none;
            transition: var(--transition);
            font-size: 13px;
            margin-bottom: 0.2rem;
        }
        
        .sidebar-dropdown-link:hover, .sidebar-dropdown-link.active {
            background: rgba(255, 255, 255, 0.05);
            color: white;
        }
        
        .sidebar-dropdown-link i {
            font-size: 1rem;
            margin-right: 10px;
            width: 20px;
        }
        
        .dropdown-toggle {
            cursor: pointer;
        }
        
        .dropdown-toggle::after {
            content: '\f282';
            font-family: 'boxicons';
            font-size: 1.2rem;
            border: none;
            transition: transform 0.3s ease;
            margin-left: auto;
        }
        
        .dropdown-toggle[aria-expanded="true"]::after {
            transform: rotate(90deg);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: var(--transition);
        }
        
        /* Header */
        .dashboard-header {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: var(--card-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid rgba(13, 110, 253, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-gradient);
        }
        
        .page-title h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
            font-family: 'Montserrat', sans-serif;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .page-title p {
            color: var(--secondary);
            margin-bottom: 0;
            font-size: 14px;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        /* Form and Table Cards */
        .form-card, .table-card {
            background: white;
            border-radius: var(--card-radius);
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            border: 1px solid rgba(13, 110, 253, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .form-card:hover, .table-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(13, 110, 253, 0.15);
        }
        
        .form-card h3, .table-card h3 {
            color: var(--dark);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-200);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1.25rem;
            position: relative;
        }
        
        .form-group label {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }
        
        .form-group label::after {
            content: '*';
            color: var(--danger);
            margin-left: 0.25rem;
            display: none;
        }
        
        .form-group.required label::after {
            display: inline;
        }
        
        .form-control {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--gray-300);
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
        }
        
        /* Table Styles */
        .table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            margin-top: 1rem;
        }
        
        .table th {
            background: var(--primary-gradient);
            color: white;
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            position: sticky;
            top: 0;
        }
        
        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .table tr:last-child td {
            border-bottom: none;
        }
        
        .table tr:hover {
            background-color: rgba(13, 110, 253, 0.03);
        }
        
        .badge {
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .badge-low {
            background-color: rgba(25, 135, 84, 0.15);
            color: #198754;
        }
        
        .badge-medium {
            background-color: rgba(255, 193, 7, 0.15);
            color: #ffc107;
        }
        
        .badge-high {
            background-color: rgba(220, 53, 69, 0.15);
            color: #dc3545;
        }
        
        .action-btn {
            padding: 0.4rem 0.75rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.875rem;
            margin-right: 0.5rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .action-btn.edit {
            background-color: rgba(255, 193, 7, 0.15);
            color: #ffc107;
        }
        
        .action-btn.edit:hover {
            background-color: #ffc107;
            color: white;
        }
        
        .action-btn.delete {
            background-color: rgba(220, 53, 69, 0.15);
            color: #dc3545;
        }
        
        .action-btn.delete:hover {
            background-color: #dc3545;
            color: white;
        }
        
        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1rem;
            border-radius: var(--card-radius);
        }
        
        .stat-card {
            background: white;
            border-radius: var(--card-radius);
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            display: flex;
            flex-direction: column;
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(13, 110, 253, 0.15);
        }
        
        .stat-card .icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .stat-card .icon.low {
            background: rgba(25, 135, 84, 0.15);
            color: #198754;
        }
        
        .stat-card .icon.medium {
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
        }
        
        .stat-card .icon.high {
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
        }
        
        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-family: 'Montserrat', sans-serif;
        }
        
        .stat-card .label {
            color: var(--secondary);
            font-size: 0.875rem;
        }
        
        /* Modal Styles */
        .modal-content {
            border-radius: var(--card-radius);
            border: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-close {
            filter: invert(1);
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .table-responsive {
                overflow-x: auto;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 1rem;
            }
            
            .form-card, .table-card {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
            <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <img src="../../img/FRSM.png" alt="Logo">
                <div class="text">
                    Quezon City<br>
                    <small>Traffic Management System</small>
                </div>
            </div>
            
            <div class="sidebar-menu">
                <div class="sidebar-section">Main Navigation</div>
                <a href="../dashboard/dashboard.php" class="sidebar-link ">
                    <i class='bx bx-home'></i>
                    <span class="text">Dashboard</span>
                </a>

                <!-- Traffic Monitoring with Dropdown -->
                <div class="sidebar-section mt-4">Traffic Modules</div>
                <div class="dropdown-toggle sidebar-link " data-bs-toggle="collapse" data-bs-target="#tmDropdown" aria-expanded="true">
                    <i class='bx bx-traffic-cone'></i>
                    <span class="text">Traffic Monitoring</span>
                </div>
                <div class="sidebar-dropdown collapse show" id="tmDropdown">
                    <a href="../dashboard.php" class="sidebar-dropdown-link">
                        <i class='bx bx-clipboard'></i> Dashboard
                    </a>
                    <a href="TM/manual_logs/admin_traffic_log.php" class="sidebar-dropdown-link active">
                        <i class='bx bx-clipboard'></i> Manual Traffic Logs
                    </a>
                    <a href="TM/traffic_volume/tv.php" class="sidebar-dropdown-link ">
                        <i class='bx bx-signal-4'></i> Traffic Volume Status
                    </a>
                    <a href="TM/daily_monitoring/daily_monitoring.php" class="sidebar-dropdown-link">
                        <i class='bx bx-report'></i> Daily Monitoring Reports
                    </a>
                     <a href="TM/cctv_integration/admin_cctv.php" class="sidebar-dropdown-link">
                        <i class='bx bx-report'></i> CCTV Integration
                    </a>
                </div>

                <!-- Real-time Road Update with Dropdown -->
                <div class="dropdown-toggle sidebar-link" data-bs-toggle="collapse" data-bs-target="#rruDropdown" aria-expanded="false">
                    <i class='bx bx-road'></i>
                    <span class="text">Real-time Road Update</span>
                </div>
                <div class="sidebar-dropdown collapse" id="rruDropdown">
                    <a href="../../RTR/post_dashboard/admin_road_updates.php" class="sidebar-dropdown-link">
                        <i class='bx bx-edit'></i> Post Dashboard
                    </a>

                <a href="../../RTR/status_management/status_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-stats'></i> Road Status Options
                    </a>
                <a href="../../RTR/real_time_road/admin_road_updates.php" class="sidebar-dropdown-link">
                        <i class='bx bx-bell'></i> Notification Panel
                    </a>
                </div>

                <!-- Accident & Violation Report with Dropdown -->
                <div class="dropdown-toggle sidebar-link <?= $active_tab == 'accident_violation' ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#avrDropdown" aria-expanded="<?= $active_tab == 'accident_violation' ? 'true' : 'false' ?>">
                    <i class='bx bx-error-circle'></i>
                    <span class="text">Accident & Violation Report</span>
                </div>
                <div class="sidebar-dropdown collapse <?= $active_tab == 'accident_violation' ? 'show' : '' ?>" id="avrDropdown">
                    <a href="AVR/report_review/rr.php" class="sidebar-dropdown-link">
                        <i class='bx bx-search-alt'></i> Report Review
                    </a>
                    <a href="AVR/status_update/su.php" class="sidebar-dropdown-link">
                        <i class='bx bx-task'></i> Status Update
                    </a>
                    <a href="AVR/violation_history/vh.php" class="sidebar-dropdown-link">
                        <i class='bx bx-history'></i> Violation History
                    </a>
                </div>

                <!-- Vehicle Routing & Diversion with Dropdown -->
                <div class="dropdown-toggle sidebar-link <?= $active_tab == 'routing_diversion' ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#vrdDropdown" aria-expanded="<?= $active_tab == 'routing_diversion' ? 'true' : 'false' ?>">
                    <i class='bx bx-map-alt'></i>
                    <span class="text">Vehicle Routing & Diversion</span>
                </div>
                <div class="sidebar-dropdown collapse <?= $active_tab == 'routing_diversion' ? 'show' : '' ?>" id="vrdDropdown">
                    <a href="VRD/route_suggestion/rs.php" class="sidebar-dropdown-link">
                        <i class='bx bx-directions'></i> Route Suggestions
                    </a>
                    <a href="VRD/diversion_notice/dn.php" class="sidebar-dropdown-link">
                        <i class='bx bx-notification'></i> Diversion Notice Board
                    </a>
                    <a href="VRD/route_update/ru.php" class="sidebar-dropdown-link">
                        <i class='bx bx-edit-alt'></i> Route Update Form
                    </a>
                    <a href="VRD/manage_route/mr.php" class="sidebar-dropdown-link">
                        <i class='bx bx-check-circle'></i> Manage Route Suggestions
                    </a>
                </div>

                <!-- Traffic Signal Control with Dropdown -->
                <div class="dropdown-toggle sidebar-link <?= $active_tab == 'signal_control' ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#tscDropdown" aria-expanded="<?= $active_tab == 'signal_control' ? 'true' : 'false' ?>">
                    <i class='bx bx-traffic-light'></i>
                    <span class="text">Traffic Signal Control</span>
                </div>
                <div class="sidebar-dropdown collapse <?= $active_tab == 'signal_control' ? 'show' : '' ?>" id="tscDropdown">
                    <a href="TSC/simulated_signal/ssl.php" class="sidebar-dropdown-link">
                        <i class='bx bx-slider-alt'></i> Signal Control Interface
                    </a>
                    <a href="TSC/manual_timing/mt.php" class="sidebar-dropdown-link">
                        <i class='bx bx-time-five'></i> Manual Timing Adjuster
                    </a>
                    <a href="TSC/simple_schedule/ss.php" class="sidebar-dropdown-link">
                        <i class='bx bx-calendar'></i> Schedule Viewer
                    </a>
                </div>

                <!-- Public Transport with Dropdown -->
                <div class="dropdown-toggle sidebar-link <?= $active_tab == 'public_transport' ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#ptsDropdown" aria-expanded="<?= $active_tab == 'public_transport' ? 'true' : 'false' ?>">
                    <i class='bx bx-bus'></i>
                    <span class="text">Public Transport</span>
                </div>
                <div class="sidebar-dropdown collapse <?= $active_tab == 'public_transport' ? 'show' : '' ?>" id="ptsDropdown">
                    <a href="PTS/vehicle_timetable/vt.php" class="sidebar-dropdown-link">
                        <i class='bx bx-table'></i> Vehicle Timetable
                    </a>
                    <a href="PTS/arrival_estimator/ae.php" class="sidebar-dropdown-link">
                        <i class='bx bx-time'></i> Arrival Estimator
                    </a>
                    <a href="PTS/commuter_info/ci.php" class="sidebar-dropdown-link">
                        <i class='bx bx-info-circle'></i> Commuter Info Page
                    </a>
                </div>

                <!-- Permit & Ticketing System with Dropdown -->
                <div class="dropdown-toggle sidebar-link <?= $active_tab == 'permit_ticketing' ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#patsDropdown" aria-expanded="<?= $active_tab == 'permit_ticketing' ? 'true' : 'false' ?>">
                    <i class='bx bx-receipt'></i>
                    <span class="text">Permit & Ticketing System</span>
                </div>
                <div class="sidebar-dropdown collapse <?= $active_tab == 'permit_ticketing' ? 'show' : '' ?>" id="patsDropdown">
                    <a href="PATS/permit_request/pr.php" class="sidebar-dropdown-link">
                        <i class='bx bx-file'></i> Permit Requests
                    </a>
                    <a href="PATS/ticket_log/tl.php" class="sidebar-dropdown-link">
                        <i class='bx bx-list-check'></i> Ticket Log System
                    </a>
                    <a href="PATS/status_view/sv.php" class="sidebar-dropdown-link">
                        <i class='bx bx-show'></i> Status View
                    </a>
                </div>
                
                <div class="sidebar-section mt-4">User</div>
                <a href="profile.php" class="sidebar-link">
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
            <div class="dashboard-header">
                <div class="page-title">
                    <h1>Manual Traffic Log</h1>
                    <p>Manage and monitor traffic data across Quezon City</p>
                </div>
                
                <div class="header-actions">
                    <div class="user-info">
                        <span class="fw-medium"><?= htmlspecialchars($user_profile['full_name']) ?></span>
                        <span class="text-muted d-block small"><?= htmlspecialchars($user_profile['role']) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-container">
                <?php
                // Calculate stats
                $low_count = 0;
                $medium_count = 0;
                $high_count = 0;
                
                foreach ($logs as $log) {
                    if ($log['volume'] == 'Low') $low_count++;
                    if ($log['volume'] == 'Medium') $medium_count++;
                    if ($log['volume'] == 'High') $high_count++;
                }
                ?>
                <div class="stat-card">
                    <div class="icon low">
                        <i class='bx bx-trending-down'></i>
                    </div>
                    <div class="value"><?= $low_count ?></div>
                    <div class="label">Low Traffic Reports</div>
                </div>
                
                <div class="stat-card">
                    <div class="icon medium">
                        <i class='bx bx-trending-up'></i>
                    </div>
                    <div class="value"><?= $medium_count ?></div>
                    <div class="label">Medium Traffic Reports</div>
                </div>
                
                <div class="stat-card">
                    <div class="icon high">
                        <i class='bx bx-trending-up'></i>
                    </div>
                    <div class="value"><?= $high_count ?></div>
                    <div class="label">High Traffic Reports</div>
                </div>
            </div>
            
            <!-- Form Card -->
            <div class="form-card">
                <h3><i class='bx bx-plus'></i> Add New Traffic Log</h3>
                <form method="POST" id="trafficForm">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group required">
                                <label for="location">Location:</label>
                                <select class="form-control" id="location" name="location" required>
                                    <option value="">-- Select Barangay --</option>
                                    <option value="Bagong Silangan" <?= isset($_POST['location']) && $_POST['location'] == 'Bagong Silangan' ? 'selected' : '' ?>>Bagong Silangan</option>
                                    <option value="Batasan Hills" <?= isset($_POST['location']) && $_POST['location'] == 'Batasan Hills' ? 'selected' : '' ?>>Batasan Hills</option>
                                    <option value="Commonwealth" <?= isset($_POST['location']) && $_POST['location'] == 'Commonwealth' ? 'selected' : '' ?>>Commonwealth</option>
                                    <option value="Holy Spirit" <?= isset($_POST['location']) && $_POST['location'] == 'Holy Spirit' ? 'selected' : '' ?>>Holy Spirit</option>
                                    <option value="Payatas" <?= isset($_POST['location']) && $_POST['location'] == 'Payatas' ? 'selected' : '' ?>>Payatas</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group required">
                                <label for="volume">Traffic Volume:</label>
                                <select class="form-control" id="volume" name="volume" required>
                                    <option value="">-- Select Volume --</option>
                                    <option value="Low" <?= isset($_POST['volume']) && $_POST['volume'] == 'Low' ? 'selected' : '' ?>>Low</option>
                                    <option value="Medium" <?= isset($_POST['volume']) && $_POST['volume'] == 'Medium' ? 'selected' : '' ?>>Medium</option>
                                    <option value="High" <?= isset($_POST['volume']) && $_POST['volume'] == 'High' ? 'selected' : '' ?>>High</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="remarks">Remarks:</label>
                                <input type="text" class="form-control" id="remarks" name="remarks" placeholder="Enter remarks" value="<?= isset($_POST['remarks']) ? htmlspecialchars($_POST['remarks']) : '' ?>">
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="add" class="btn btn-primary"><i class='bx bx-save'></i> Save Log</button>
                </form>
            </div>

            <!-- Logs Table -->
            <div class="table-card">
                <h3><i class='bx bx-history'></i> Traffic Logs</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Location</th>
                                <th>Volume</th>
                                <th>Remarks</th>
                                <th>Date & Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($logs) > 0): ?>
                            <?php foreach($logs as $row): ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td><?= $row['location'] ?></td>
                                <td>
                                    <?php 
                                    $badge_class = '';
                                    if ($row['volume'] == 'Low') $badge_class = 'badge-low';
                                    if ($row['volume'] == 'Medium') $badge_class = 'badge-medium';
                                    if ($row['volume'] == 'High') $badge_class = 'badge-high';
                                    ?>
                                    <span class="badge <?= $badge_class ?>"><?= $row['volume'] ?></span>
                                </td>
                                <td><?= $row['remarks'] ?></td>
                               <td><?= $row['log_date'] ?></td> 
                                <td>
                                    <a href="#" data-id="<?= $row['id'] ?>" class="action-btn edit edit-btn"><i class='bx bx-edit'></i> Edit</a>
                                    <a href="#" data-id="<?= $row['id'] ?>" class="action-btn delete delete-btn"><i class='bx bx-trash'></i> Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">No traffic logs found.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel"><i class='bx bx-edit'></i> Edit Traffic Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="editForm">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="form-group required">
                            <label for="edit_location">Location:</label>
                            <select class="form-control" id="edit_location" name="location" required>
                                <option value="">-- Select Barangay --</option>
                                <option value="Bagong Silangan">Bagong Silangan</option>
                                <option value="Batasan Hills">Batasan Hills</option>
                                <option value="Commonwealth">Commonwealth</option>
                                <option value="Holy Spirit">Holy Spirit</option>
                                <option value="Payatas">Payatas</option>
                            </select>
                        </div>
                        <div class="form-group required">
                            <label for="edit_volume">Traffic Volume:</label>
                            <select class="form-control" id="edit_volume" name="volume" required>
                                <option value="">-- Select Volume --</option>
                                <option value="Low">Low</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_remarks">Remarks:</label>
                            <input type="text" class="form-control" id="edit_remarks" name="remarks" placeholder="Enter remarks">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update" class="btn btn-primary">Update Log</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>
        // Initialize sidebar dropdowns
        document.addEventListener('DOMContentLoaded', function() {
            // Set active dropdowns based on current page
            const activeTab = '<?= $active_tab ?>';
            if (activeTab && activeTab !== 'dashboard') {
                const dropdown = document.getElementById(activeTab + 'Dropdown');
                if (dropdown) {
                    dropdown.classList.add('show');
                }
            }
            
            // Show SweetAlert notifications
            <?php if (!empty($message)): ?>
                const messageType = '<?= $message_type ?>';
                const messageText = '<?= addslashes($message) ?>';
                
                Swal.fire({
                    icon: messageType,
                    title: messageType === 'success' ? 'Success!' : 'Error!',
                    text: messageText,
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer)
                        toast.addEventListener('mouseleave', Swal.resumeTimer)
                    }
                });
            <?php endif; ?>
            
            // Form validation
            const form = document.getElementById('trafficForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    let isValid = true;
                    const location = document.getElementById('location');
                    const volume = document.getElementById('volume');
                    
                    if (!location.value) {
                        location.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        location.classList.remove('is-invalid');
                    }
                    
                    if (!volume.value) {
                        volume.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        volume.classList.remove('is-invalid');
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                        
                        // Show error message
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Please fill in all required fields.',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true
                        });
                    }
                });
            }
            
            // Edit button click handler
            const editButtons = document.querySelectorAll('.edit-btn');
            editButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const id = this.getAttribute('data-id');
                    const row = this.closest('tr');
                    
                    // Get data from the row
                    const location = row.cells[1].textContent;
                    const volume = row.cells[2].querySelector('.badge').textContent;
                    const remarks = row.cells[3].textContent;
                    
                    // Set values in the modal
                    document.getElementById('edit_id').value = id;
                    document.getElementById('edit_location').value = location;
                    document.getElementById('edit_volume').value = volume;
                    document.getElementById('edit_remarks').value = remarks;
                    
                    // Show the modal
                    const modal = new bootstrap.Modal(document.getElementById('editModal'));
                    modal.show();
                });
            });
            
            // Delete button click handler
            const deleteButtons = document.querySelectorAll('.delete-btn');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const id = this.getAttribute('data-id');
                    
                    // Show SweetAlert confirmation
                    Swal.fire({
                        title: 'Are you sure?',
                        text: "You won't be able to revert this!",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, delete it!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Redirect to delete the record
                            window.location.href = 'admin_traffic_log.php?delete=' + id;
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>