<?php
// condition_map.php
session_start();

// DB connection for condition_map
$conn = new mysqli("localhost:3307", "root", "", "rtr");
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// DB connection for user info (using the same connection details as dashboard.php)
$db_host = "localhost:3307";
$db_user = "root";
$db_pass = "";
$db_name = "ttm"; // Assuming this is the database for user info

// Create connection for user info
$user_conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($user_conn->connect_error) {
    die("User database connection failed: " . $user_conn->connect_error);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user profile information
$user_id = $_SESSION['user_id'];
$user_query = "SELECT user_id, username, full_name, email, role FROM users WHERE user_id = ?";
$stmt = $user_conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();

if ($user_result->num_rows > 0) {
    $user_profile = $user_result->fetch_assoc();
} else {
    // Default values if user not found
    $user_profile = [
        'full_name' => 'Unknown User',
        'role' => 'Guest'
    ];
}

$alert = "";

// CREATE
if (isset($_POST['add'])) {
    $name = $_POST['location_name'];
    $lat = $_POST['latitude'];
    $lng = $_POST['longitude'];
    $status = $_POST['status'];
    $desc = $_POST['description'];

    $sql = "INSERT INTO condition_map (location_name, latitude, longitude, status, description)
            VALUES ('$name', '$lat', '$lng', '$status', '$desc')";
    if ($conn->query($sql)) {
        $alert = "created";
    }
}

// UPDATE
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $name = $_POST['location_name'];
    $lat = $_POST['latitude'];
    $lng = $_POST['longitude'];

    $status = $_POST['status'];
    $desc = $_POST['description'];

    $sql = "UPDATE condition_map 
            SET location_name='$name', latitude='$lat', longitude='$lng', status='$status', description='$desc'
            WHERE id=$id";
    if ($conn->query($sql)) {
        $alert = "updated";
    }
}

// DELETE
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql = "DELETE FROM condition_map WHERE id=$id";
    if ($conn->query($sql)) {
        $alert = "deleted";
    }
}

// FETCH DATA
$result = $conn->query("SELECT * FROM condition_map ORDER BY updated_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Road Condition Map - Admin Only</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
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
    
    .main-content {
        flex: 1;
        margin-left: var(--sidebar-width);
        padding: 2rem;
        transition: var(--transition);
    }
    
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
    
    .user-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem 1rem;
        background: rgba(13, 110, 253, 0.05);
        border-radius: 8px;
        border: 1px solid rgba(13, 110, 253, 0.1);
    }
    
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--primary-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 16px;
    }
    
    .user-details {
        display: flex;
        flex-direction: column;
    }
    
    .user-name {
        font-weight: 600;
        color: var(--dark);
        font-size: 14px;
    }
    
    .user-role {
        font-size: 12px;
        color: var(--secondary);
    }
    
    .card {
        background: white;
        border-radius: var(--card-radius);
        box-shadow: var(--card-shadow);
        border: 1px solid rgba(13, 110, 253, 0.1);
        overflow: hidden;
        margin-bottom: 1.5rem;
    }
    
    .card-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: rgba(248, 249, 250, 0.5);
    }
    
    .card-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--dark);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .card-title i {
        color: var(--primary);
    }
    
    .card-body {
        padding: 1.5rem;
    }
    
    .form-control, .form-select {
        border-radius: 8px;
        border: 1px solid var(--gray-300);
        padding: 0.75rem 1rem;
        font-size: 14px;
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
    }
    
    .btn-primary {
        background: var(--primary-gradient);
        border: none;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
    }
    
    .btn-warning, .btn-danger {
        border: none;
    }
    
    .table {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
    }
    
    .table th {
        background-color: var(--gray-100);
        color: var(--gray-800);
        font-weight: 600;
        padding: 1rem;
        border-bottom: 2px solid var(--gray-300);
    }
    
    .table td {
        padding: 1rem;
        border-bottom: 1px solid var(--gray-200);
        vertical-align: middle;
    }
    
    .table tr:last-child td {
        border-bottom: none;
    }
    
    .table tr:hover {
        background-color: rgba(13, 110, 253, 0.03);
    }
    
    .badge {
        padding: 0.5rem 0.75rem;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .badge-open {
        background-color: rgba(25, 135, 84, 0.1);
        color: #198754;
    }
    
    .badge-blocked {
        background-color: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }
    
    .badge-maintenance {
        background-color: rgba(255, 193, 7, 0.1);
        color: #ffc107;
    }
    
    #map { 
        height: 500px; 
        border-radius: var(--card-radius);
        overflow: hidden;
        box-shadow: var(--card-shadow);
    }
    
    .modal-content { 
        border-radius: 12px; 
        border: none; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.15); 
    }
    
    .modal-header { 
        background-color: #f8f9fa; 
        border-bottom: 1px solid #dee2e6; 
        border-radius: 12px 12px 0 0; 
        padding: 1.25rem 1.5rem;
    }
    
    .modal-title {
        font-weight: 600;
        color: var(--dark);
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .modal-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid #dee2e6;
    }
    
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
        .main-content {
            padding: 1rem;
        }
        
        .dashboard-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .header-actions {
            width: 100%;
            justify-content: space-between;
        }
        
        .user-info {
            width: 100%;
            justify-content: center;
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
            <div class="dashboard-header">
                <div class="page-title">
                    <h1>Road Condition Map</h1>
                    <p>Manage and monitor road conditions across Quezon City</p>
                </div>
                
                <div class="header-actions">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php 
                            // Get initials from user's full name
                            $names = explode(' ', $user_profile['full_name']);
                            $initials = '';
                            foreach ($names as $name) {
                                $initials .= strtoupper(substr($name, 0, 1));
                                if (strlen($initials) >= 2) break;
                            }
                            echo $initials;
                            ?>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?= htmlspecialchars($user_profile['full_name']) ?></span>
                            <span class="user-role"><?= htmlspecialchars($user_profile['role']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Overlay Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class='bx bx-plus-circle'></i> Add New Overlay</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row mb-2">
                            <div class="col-md-3 mb-2">
                                <input type="text" name="location_name" class="form-control" placeholder="Location Name" required>
                            </div>
                            <div class="col-md-2 mb-2">
                                <input type="text" name="latitude" id="lat" class="form-control" placeholder="Latitude" required>
                            </div>
                            <div class="col-md-2 mb-2">
                                <input type="text" name="longitude" id="lng" class="form-control" placeholder="Longitude" required>
                            </div>
                            <div class="col-md-2 mb-2">
                                <select name="status" class="form-select">
                                    <option value="Open">Open</option>
                                    <option value="Blocked">Blocked</option>
                                    <option value="Maintenance">Maintenance</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <input type="text" name="description" class="form-control" placeholder="Description">
                            </div>
                            <div class="col-md-1 mb-2">
                                <button type="submit" name="add" class="btn btn-primary w-100">Add</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Map Display -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class='bx bx-map-alt'></i> Road Condition Map</h5>
                </div>
                <div class="card-body p-0">
                    <div id="map"></div>
                </div>
            </div>

            <!-- Overlay List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class='bx bx-list-ul'></i> Overlay Records</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Description</th>
                                    <th>Coordinates</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php while($row = $result->fetch_assoc()) { 
                                $status_class = "badge-open";
                                if ($row['status'] == "Blocked") $status_class = "badge-blocked";
                                if ($row['status'] == "Maintenance") $status_class = "badge-maintenance";
                            ?>
                                <tr>
                                    <td><?= $row['id']; ?></td>
                                    <td><?= $row['location_name']; ?></td>
                                    <td><span class="badge <?= $status_class ?>"><?= $row['status']; ?></span></td>
                                    <td><?= $row['description']; ?></td>
                                    <td><?= $row['latitude'] . ", " . $row['longitude']; ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning"
                                            onclick="editOverlay('<?= $row['id']; ?>','<?= $row['location_name']; ?>','<?= $row['latitude']; ?>','<?= $row['longitude']; ?>','<?= $row['status']; ?>','<?= $row['description']; ?>')">
                                            Edit
                                        </button>
                                        <a href="?delete=<?= $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this overlay?')">Delete</a>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Overlay</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Location Name</label>
                        <input type="text" name="location_name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Latitude</label>
                        <input type="text" name="latitude" id="edit_lat" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Longitude</label>
                        <input type="text" name="longitude" id="edit_lng" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="Open">Open</option>
                            <option value="Blocked">Blocked</option>
                            <option value="Maintenance">Maintenance</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" id="edit_desc" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update" class="btn btn-success">Save Changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Leaflet map setup
    var map = L.map('map').setView([14.6760, 121.0437], 12); // Default: QC

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);

   // Markers from DB
<?php
$res = $conn->query("SELECT * FROM condition_map");
while($r = $res->fetch_assoc()) {
    // Define icon based on status
    $iconColor = ($r['status']=="Open") ? "green" : (($r['status']=="Blocked") ? "red" : "orange");
?>
    var customIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-<?= $iconColor ?>.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });
    
    L.marker([<?= $r['latitude']; ?>, <?= $r['longitude']; ?>], {
        title: "<?= $r['location_name']; ?>",
        icon: customIcon
    })
    .bindPopup("<b><?= $r['location_name']; ?></b><br>Status: <?= $r['status']; ?><br><?= $r['description']; ?>")
    .addTo(map);
<?php } ?>

    // Click to autofill coordinates
    map.on('click', function(e) {
        document.getElementById('lat').value = e.latlng.lat.toFixed(6);
        document.getElementById('lng').value = e.latlng.lng.toFixed(6);
    });

    // Edit Overlay function
    function editOverlay(id, name, lat, lng, status, desc) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_lat').value = lat;
        document.getElementById('edit_lng').value = lng;
        document.getElementById('edit_status').value = status;
        document.getElementById('edit_desc').value = desc;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    // SweetAlerts
    <?php if ($alert == "created") { ?>
    Swal.fire("Added!", "Overlay has been created.", "success");
    <?php } elseif ($alert == "updated") { ?>
    Swal.fire("Updated!", "Overlay has been updated.", "info");
    <?php } elseif ($alert == "deleted") { ?>
    Swal.fire("Deleted!", "Overlay has been removed.", "error");
    <?php } ?>

    // Initialize sidebar dropdowns
    document.addEventListener('DOMContentLoaded', function() {
        // Set active dropdowns
        const dropdown = document.getElementById('rruDropdown');
        if (dropdown) {
            dropdown.classList.add('show');
        }
    });
    </script>

</body>
</html>