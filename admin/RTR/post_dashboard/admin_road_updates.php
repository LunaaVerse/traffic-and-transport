<?php
session_start();
require_once 'config/database.php'; // Adjust path as needed

// Authentication & Authorization
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get database connection
try {
    $pdo_rtr = getDBConnection('rtr');
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle Delete with SweetAlert confirmation
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo_rtr->prepare("DELETE FROM road_updates WHERE update_id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Road update deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Error deleting road update.";
    }
    header("Location: admin_road_updates.php");
    exit;
}

// Handle Update via Modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_id'])) {
    $update_id = intval($_POST['update_id']);
    $district = $_POST['district'];
    $barangay = $_POST['barangay'];
    $status = $_POST['status'];
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];
    
    $stmt = $pdo_rtr->prepare("UPDATE road_updates SET district = :district, barangay = :barangay, status = :status, latitude = :latitude, longitude = :longitude WHERE update_id = :id");
    $stmt->bindParam(':district', $district);
    $stmt->bindParam(':barangay', $barangay);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':latitude', $latitude);
    $stmt->bindParam(':longitude', $longitude);
    $stmt->bindParam(':id', $update_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Road update updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating road update.";
    }
    header("Location: admin_road_updates.php");
    exit;
}

// Fetch all updates
$result = $pdo_rtr->query("SELECT * FROM road_updates ORDER BY created_at DESC");

// Set active tab and submodule
$current_page = basename($_SERVER['PHP_SELF']);
$active_tab = 'road_update';

// Get user profile (assuming similar function exists)
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

// Get user profile (you'll need to adjust this based on your database structure)
try {
    $pdo_ttm = getDBConnection('ttm');
    $user_profile = getUserProfile($pdo_ttm, $_SESSION['user_id']);
} catch (Exception $e) {
    $user_profile = ['full_name' => 'User', 'role' => 'Unknown'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Real-Time Road Update Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.css" />
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
      display: flex;
      align-items: center;
      padding: 0.75rem 1rem;
      border-radius: 8px;
      color: #cbd5e1;
      transition: var(--transition);
      margin-bottom: 0.25rem;
      position: relative;
      overflow: hidden;
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
    
    /* Form Styles */
    .form-card {
      background: white;
      border-radius: var(--card-radius);
      padding: 1.5rem;
      box-shadow: var(--card-shadow);
      border: 1px solid rgba(13, 110, 253, 0.1);
      margin-bottom: 1.5rem;
    }
    
    .form-card h3 {
      font-size: 1.25rem;
      font-weight: 600;
      margin-bottom: 1.5rem;
      color: var(--dark);
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .form-card h3 i {
      color: var(--primary);
    }
    
    .form-label {
      font-weight: 500;
      margin-bottom: 0.5rem;
      color: var(--dark);
    }
    
    .btn-primary {
      background: var(--primary-gradient);
      border: none;
      padding: 0.75rem 1.5rem;
      font-weight: 500;
      transition: var(--transition);
    }
    
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
    }
    
    /* Map Styles */
    #map {
      height: 400px;
      border-radius: var(--card-radius);
      box-shadow: var(--card-shadow);
      margin-bottom: 1.5rem;
      border: 1px solid rgba(13, 110, 253, 0.1);
    }
    
    /* Directions Panel */
    .directions-panel {
      background: white;
      border-radius: var(--card-radius);
      padding: 1.5rem;
      box-shadow: var(--card-shadow);
      border: 1px solid rgba(13, 110, 253, 0.1);
      margin-bottom: 1.5rem;
      display: none;
    }
    
    .directions-panel h3 {
      font-size: 1.25rem;
      font-weight: 600;
      margin-bottom: 1.5rem;
      color: var(--dark);
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .directions-panel h3 i {
      color: var(--primary);
    }
    
    #directions-instructions {
      max-height: 300px;
      overflow-y: auto;
      padding: 10px;
      background-color: var(--gray-100);
      border-radius: 8px;
    }
    
    /* Table Styles */
    .data-card {
      background: white;
      border-radius: var(--card-radius);
      box-shadow: var(--card-shadow);
      border: 1px solid rgba(13, 110, 253, 0.1);
      overflow: hidden;
    }
    
    .data-card .card-header {
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid var(--gray-200);
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: rgba(248, 249, 250, 0.5);
    }
    
    .data-card .card-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--dark);
      margin: 0;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .data-card .card-title i {
      color: var(--primary);
    }
    
    .table-container {
      overflow-x: auto;
    }
    
    .table {
      width: 100%;
      border-collapse: collapse;
    }
    
    .table th {
      background-color: var(--gray-100);
      padding: 0.75rem;
      text-align: left;
      font-weight: 600;
      color: var(--dark);
      border-bottom: 2px solid var(--gray-300);
    }
    
    .table td {
      padding: 0.75rem;
      border-bottom: 1px solid var(--gray-200);
      vertical-align: middle;
    }
    
    .table tr:hover {
      background-color: var(--gray-100);
    }
    
    .badge {
      padding: 0.35rem 0.65rem;
      border-radius: 50rem;
      font-weight: 500;
      font-size: 0.75rem;
    }
    
    .badge-open {
      background-color: rgba(25, 135, 84, 0.1);
      color: var(--success);
    }
    
    .badge-blocked {
      background-color: rgba(220, 53, 69, 0.1);
      color: var(--danger);
    }
    
    .badge-maintenance {
      background-color: rgba(255, 193, 7, 0.1);
      color: var(--warning);
    }
    
    .action-buttons {
      display: flex;
      gap: 0.5rem;
    }
    
    .btn-icon {
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
      padding: 0;
    }
    
    /* Modal Styles */
    .modal-content {
      border-radius: var(--card-radius);
      border: none;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    }
    
    .modal-header {
      background: var(--primary-gradient);
      color: white;
      border-top-left-radius: var(--card-radius);
      border-top-right-radius: var(--card-radius);
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
    }
    
    @media (max-width: 576px) {
      .main-content {
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
                <a href="../dashboard/dashboard.php" class="sidebar-link active">
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
                    <a href="../../TM/manual_logs/admin_traffic_log.php" class="sidebar-dropdown-link">
                        <i class='bx bx-clipboard'></i> Manual Traffic Logs
                    </a>
                    <a href="../../TM/traffic_volume/tv.php" class="sidebar-dropdown-link ">
                        <i class='bx bx-signal-4'></i> Traffic Volume Status
                    </a>
                    <a href="../../TM/daily_monitoring/daily_monitoring.php" class="sidebar-dropdown-link">
                        <i class='bx bx-report'></i> Daily Monitoring Reports
                    </a>
                     <a href="../../TM/cctv_integration/admin_cctv.php" class="sidebar-dropdown-link">
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
                        <i class='bx bx-stats'></i> Status Management
                    </a>
                <a href="../../RTR/real_time_road/admin_road_updates.php" class="sidebar-dropdown-link">
                        <i class='bx bx-bell'></i> Real Time Road
                    </a>
                      </a>
                <a href="../../RTR/road_condition_map/road_condition_map.php" class="sidebar-dropdown-link">
                        <i class='bx bx-bell'></i> Road Condition Map
                    </a>
                </div>
                <!-- Accident & Violation Report with Dropdown -->
                <div class="dropdown-toggle sidebar-link <?= $active_tab == 'accident_violation' ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#avrDropdown" aria-expanded="<?= $active_tab == 'accident_violation' ? 'true' : 'false' ?>">
                    <i class='bx bx-error-circle'></i>
                    <span class="text">Accident & Violation Report</span>
                </div>
               <div class="sidebar-dropdown collapse <?= $active_tab == 'routing_diversion' ? 'show' : '' ?>" id="vrdDropdown">
                    <a href="../../AVR/report_management/report_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-directions'></i> Report Management
                    </a>
                    <a href="../../AVR/violation_categorization/violation_categorization.php" class="sidebar-dropdown-link">
                        <i class='bx bx-notification'></i> Violation Categorization
                    </a>
                    <a href="../../AVR/evidence_handling/evidence_admin.php" class="sidebar-dropdown-link">
                        <i class='bx bx-edit-alt'></i> Evidence Handling
                    </a>
                    <a href="../../AVR/violation_records/admin_violation_records.php" class="sidebar-dropdown-link">
                        <i class='bx bx-check-circle'></i> Violation Record 
                    </a>
                     <a href="../../AVR/escalation_assignment/admin_escalation_assignment.php" class="sidebar-dropdown-link">
                        <i class='bx bx-check-circle'></i> Escalation & Assignment  
                    </a>
                </div>

                  <!-- Vehicle Routing & Diversion with Dropdown -->
                <div class="dropdown-toggle sidebar-link <?= $active_tab == 'routing_diversion' ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#vrdDropdown" aria-expanded="<?= $active_tab == 'routing_diversion' ? 'true' : 'false' ?>">
                    <i class='bx bx-map-alt'></i>
                    <span class="text">Vehicle Routing & Diversion</span>
                </div>
                <div class="sidebar-dropdown collapse <?= $active_tab == 'routing_diversion' ? 'show' : '' ?>" id="vrdDropdown">
                    <a href="../../VRD/route_configuration_panel/route_configuration_panel.php" class="sidebar-dropdown-link">
                        <i class='bx bx-directions'></i> Route Configuration Panel
                    </a>
                    <a href="../../VRD/diversion_planning/diversion_planning.php" class="sidebar-dropdown-link">
                        <i class='bx bx-notification'></i> Diversion Planning
                    </a>
                    <a href="../../VRD/ai_rule_management/rule_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-edit-alt'></i> AI Rule Management
                    </a>
                    <a href="../../VRD/osm/osm_integration.php" class="sidebar-dropdown-link">
                        <i class='bx bx-check-circle'></i> OSM (Leaflet) Integration
                    </a>
                     <a href="../../VRD/routing_analytics/routing_analytics.php" class="sidebar-dropdown-link">
                        <i class='bx bx-check-circle'></i> Routing Analytics 
                    </a>
                </div>

                <!-- Traffic Signal Control with Dropdown -->
                <div class="dropdown-toggle sidebar-link <?= $active_tab == 'signal_control' ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#tscDropdown" aria-expanded="<?= $active_tab == 'signal_control' ? 'true' : 'false' ?>">
                    <i class='bx bx-traffic-light'></i>
                    <span class="text">Traffic Signal Control</span>
                </div>
                <div class="sidebar-dropdown collapse <?= $active_tab == 'signal_control' ? 'show' : '' ?>" id="tscDropdown">
                    <a href="../../TSC/signal_timing_management/signal_timing_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-slider-alt'></i> Signal Timing Management
                    </a>
                    <a href="../../TSC/real_time_signal_override/real_time_signal_override.php" class="sidebar-dropdown-link">
                        <i class='bx bx-time-five'></i> Real-Time Signal Override
                    </a>
                    <a href="../../TSC/automation_settings/admin_traffic_log.php" class="sidebar-dropdown-link">
                        <i class='bx bx-calendar'></i> Automation Settings
                    </a>
                       <a href="../../TSC/performance_logs/performance_logs.php" class="sidebar-dropdown-link">
                        <i class='bx bx-calendar'></i> Performance Logs 
                    </a>
                </div>

                <!-- Public Transport with Dropdown -->
                <div class="dropdown-toggle sidebar-link <?= $active_tab == 'public_transport' ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#ptsDropdown" aria-expanded="<?= $active_tab == 'public_transport' ? 'true' : 'false' ?>">
                    <i class='bx bx-bus'></i>
                    <span class="text">Public Transport</span>
                </div>
                <div class="sidebar-dropdown collapse <?= $active_tab == 'public_transport' ? 'show' : '' ?>" id="ptsDropdown">
                    <a href="../../PT/fleet_management/fleet_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-table'></i> Fleet Management
                    </a>
                    <a href="../../PT/route_and_schedule/route_and_schedule.php" class="sidebar-dropdown-link">
                        <i class='bx bx-time'></i> Route & Schedule Management
                    </a>
                    <a href="../../PT/real_time_tracking/real_time_tracking.php" class="sidebar-dropdown-link">
                        <i class='bx bx-info-circle'></i> Real-Time Tracking
                    </a>
                     <a href="../../PT/passenger_capacity_compliance/passenger_capacity_compliance.php" class="sidebar-dropdown-link">
                        <i class='bx bx-info-circle'></i> Passenger Capacity Compliance 
                    </a>
                </div>

                <!-- Permit & Ticketing System with Dropdown -->
                <div class="dropdown-toggle sidebar-link <?= $active_tab == 'permit_ticketing' ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#patsDropdown" aria-expanded="<?= $active_tab == 'permit_ticketing' ? 'true' : 'false' ?>">
                    <i class='bx bx-receipt'></i>
                    <span class="text">Permit & Ticketing System</span>
                </div>
                <div class="sidebar-dropdown collapse <?= $active_tab == 'permit_ticketing' ? 'show' : '' ?>" id="patsDropdown">
                    <a href="../../PTS/permit_application_processing/permit_application_processing.php" class="sidebar-dropdown-link">
                        <i class='bx bx-file'></i> Permit Application Processing
                    </a>
                    <a href="../../PTS/ticket_issuance_control/ticket_issuance_control.php" class="sidebar-dropdown-link">
                        <i class='bx bx-list-check'></i> Ticket Issuance Control          
                    </a>
                    <a href="../../PTS/payment_settlement_management/payment_settlement_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-show'></i> Payment & Settlement Management
                    </a>
                      <a href="../../PTS/offender_management/offender_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-show'></i> Database of Offenders
                    </a>
                      <a href="../../PTS/compliance_revenue_reports/compliance_revenue_reports.php" class="sidebar-dropdown-link">
                        <i class='bx bx-show'></i> Compliance & Revenue Reports
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
          <h1>Real-Time Road Update Dashboard</h1>
          <p>Manage road status updates across Quezon City</p>
        </div>
        
        <div class="header-actions">
          <div class="user-info">
            <span class="fw-medium"><?= htmlspecialchars($user_profile['full_name']) ?></span>
            <span class="text-muted d-block small"><?= htmlspecialchars($user_profile['role']) ?></span>
          </div>
        </div>
      </div>
      
      <!-- CREATE POST -->
      <div class="form-card">
        <h3><i class='bx bx-plus-circle'></i> Create New Road Update</h3>
        <form method="POST" action="save_update.php">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">District</label>
              <select id="district" name="district" class="form-select" required>
                <option value="">-- Select District --</option>
                <option value="District 1">District 1</option>
                <option value="District 2">District 2</option>
                <option value="District 3">District 3</option>
                <option value="District 4">District 4</option>
                <option value="District 5">District 5</option>
                <option value="District 6">District 6</option>
              </select>
            </div>
            
            <div class="col-md-6 mb-3">
              <label class="form-label">Barangay</label>
              <select id="barangay" name="barangay" class="form-select" required>
                <option value="">-- Select Barangay --</option>
              </select>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Road Condition</label>
              <select name="status" class="form-select" required>
                <option value="Open">Open</option>
                <option value="Blocked">Blocked</option>
                <option value="Maintenance">Maintenance</option>
              </select>
            </div>
            
            <div class="col-md-6 mb-3 d-flex align-items-end">
              <button type="button" class="btn btn-outline-primary w-100 me-2" onclick="getCoordinates()">
                <i class='bx bx-map'></i> Get Location
              </button>
              <button type="submit" class="btn btn-primary w-100">
                <i class='bx bx-save'></i> Save Update
              </button>
            </div>
          </div>
          
          <input type="hidden" name="latitude" id="latitude">
          <input type="hidden" name="longitude" id="longitude">
        </form>
      </div>
      
      <!-- Map -->
      <div id="map"></div>
      
      <!-- Directions Panel -->
      <div class="directions-panel" id="directions-panel">
        <h3><i class='bx bx-directions'></i> Directions</h3>
        <div class="row mb-3">
          <div class="col-md-5">
            <label class="form-label">From (Starting Point)</label>
            <input type="text" class="form-control" id="start-point" placeholder="Enter starting location">
          </div>
          <div class="col-md-5">
            <label class="form-label">To (Destination)</label>
            <input type="text" class="form-control" id="end-point" readonly>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="button" class="btn btn-primary w-100" id="get-directions-btn">
              <i class='bx bx-navigation'></i> Get Directions
            </button>
          </div>
        </div>
        <div id="directions-instructions"></div>
      </div>
      
      <!-- Road Updates Table -->
      <div class="data-card">
        <div class="card-header">
          <h5 class="card-title"><i class='bx bx-list-ul'></i> Road Updates</h5>
        </div>
        
        <div class="table-container">
          <table class="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>District</th>
                <th>Barangay</th>
                <th>Status</th>
                <th>Coordinates</th>
                <th>Date Posted</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $result->fetch(PDO::FETCH_ASSOC)): ?>
              <tr>
                <td><?= $row['update_id'] ?></td>
                <td><?= htmlspecialchars($row['district']) ?></td>
                <td><?= htmlspecialchars($row['barangay']) ?></td>
                <td>
                  <?php
                  $badge_class = '';
                  switch ($row['status']) {
                    case 'Open': $badge_class = 'badge-open'; break;
                    case 'Blocked': $badge_class = 'badge-blocked'; break;
                    case 'Maintenance': $badge_class = 'badge-maintenance'; break;
                  }
                  ?>
                  <span class="badge <?= $badge_class ?>"><?= $row['status'] ?></span>
                </td>
                <td><?= $row['latitude'] ?>, <?= $row['longitude'] ?></td>
                <td><?= date('M d, Y h:i A', strtotime($row['created_at'])) ?></td>
                <td>
                  <div class="action-buttons">
                    <button class="btn btn-sm btn-outline-primary btn-icon" data-bs-toggle="modal" data-bs-target="#editModal" 
                      data-id="<?= $row['update_id'] ?>" 
                      data-district="<?= htmlspecialchars($row['district']) ?>" 
                      data-barangay="<?= htmlspecialchars($row['barangay']) ?>" 
                      data-status="<?= htmlspecialchars($row['status']) ?>" 
                      data-latitude="<?= $row['latitude'] ?>" 
                      data-longitude="<?= $row['longitude'] ?>">
                      <i class='bx bx-edit'></i>
                    </button>
                    <a href="?delete=<?= $row['update_id'] ?>" class="btn btn-sm btn-outline-danger btn-icon delete-btn">
                      <i class='bx bx-trash'></i>
                    </a>
                    <button class="btn btn-sm btn-outline-info btn-icon directions-btn" 
                      data-barangay="<?= htmlspecialchars($row['barangay']) ?>" 
                      data-latitude="<?= $row['latitude'] ?>" 
                      data-longitude="<?= $row['longitude'] ?>">
                      <i class='bx bx-directions'></i>
                    </button>
                  </div>
                </td>
              </tr>
              <?php endwhile; ?>
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
          <h5 class="modal-title" id="editModalLabel">Edit Road Update</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" action="">
          <div class="modal-body">
            <input type="hidden" name="update_id" id="edit_id">
            
            <div class="mb-3">
              <label class="form-label">District</label>
              <select name="district" id="edit_district" class="form-select" required>
                <option value="District 1">District 1</option>
                <option value="District 2">District 2</option>
                <option value="District 3">District 3</option>
                <option value="District 4">District 4</option>
                <option value="District 5">District 5</option>
                <option value="District 6">District 6</option>
              </select>
            </div>
            
            <div class="mb-3">
              <label class="form-label">Barangay</label>
              <input type="text" name="barangay" id="edit_barangay" class="form-control" required>
            </div>
            
            <div class="mb-3">
              <label class="form-label">Status</label>
              <select name="status" id="edit_status" class="form-select" required>
                <option value="Open">Open</option>
                <option value="Blocked">Blocked</option>
                <option value="Maintenance">Maintenance</option>
              </select>
            </div>
            
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Latitude</label>
                <input type="text" name="latitude" id="edit_latitude" class="form-control" required>
              </div>
              
              <div class="col-md-6 mb-3">
                <label class="form-label">Longitude</label>
                <input type="text" name="longitude" id="edit_longitude" class="form-control" required>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Initialize map
    let map = L.map('map').setView([14.6760, 121.0437], 12); // Quezon City coordinates
    let markers = [];
    let routingControl = null;
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    // Add existing markers from database
    <?php 
    $result->execute(); // Re-execute to reset the cursor
    while ($row = $result->fetch(PDO::FETCH_ASSOC)): 
      $markerColor = '';
      switch ($row['status']) {
        case 'Open': $markerColor = 'green'; break;
        case 'Blocked': $markerColor = 'red'; break;
        case 'Maintenance': $markerColor = 'orange'; break;
      }
    ?>
      addMarker(<?= $row['latitude'] ?>, <?= $row['longitude'] ?>, '<?= $row['status'] ?>', '<?= $row['barangay'] ?>', '<?= $markerColor ?>');
    <?php endwhile; ?>
    
    // Function to add a marker to the map
    function addMarker(lat, lng, status, barangay, color = 'blue') {
      const marker = L.marker([lat, lng], {
        icon: L.icon({
          iconUrl: `https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-${color}.png`,
          shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
          iconSize: [25, 41],
          iconAnchor: [12, 41],
          popupAnchor: [1, -34],
          shadowSize: [41, 41]
        })
      }).addTo(map);
      
      marker.bindPopup(`
        <strong>${barangay}</strong><br>
        Status: ${status}<br>
        Coordinates: ${lat.toFixed(6)}, ${lng.toFixed(6)}
      `);
      
      markers.push(marker);
      return marker;
    }
    
    // Get user's current location
    function getCoordinates() {
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
          function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            
            // Add marker for current location
            addMarker(lat, lng, 'Current Location', 'Your Location', 'blue');
            
            // Center map on current location
            map.setView([lat, lng], 15);
            
            // Reverse geocode to get address
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
              .then(response => response.json())
              .then(data => {
                const address = data.display_name;
                alert(`Coordinates captured successfully!\n\nLatitude: ${lat}\nLongitude: ${lng}\n\nAddress: ${address}`);
              })
              .catch(error => {
                alert(`Coordinates captured successfully!\n\nLatitude: ${lat}\nLongitude: ${lng}`);
              });
          },
          function(error) {
            alert('Unable to get your location. Please enable location services or enter coordinates manually.');
            console.error('Geolocation error:', error);
          }
        );
      } else {
        alert('Geolocation is not supported by this browser. Please enter coordinates manually.');
      }
    }
    
    // Get directions to a location
    function getDirections(lat, lng, barangay) {
      // Show directions panel
      document.getElementById('directions-panel').style.display = 'block';
      document.getElementById('end-point').value = barangay;
      
      // Store the destination coordinates
      window.destinationCoords = { lat, lng };
      
      // Scroll to directions panel
      document.getElementById('directions-panel').scrollIntoView({ behavior: 'smooth' });
    }
    
    // Calculate and display route
    document.getElementById('get-directions-btn').addEventListener('click', function() {
      const startPoint = document.getElementById('start-point').value;
      const endPoint = document.getElementById('end-point').value;
      
      if (!startPoint) {
        alert('Please enter a starting point.');
        return;
      }
      
      // Clear previous route if exists
      if (routingControl) {
        map.removeControl(routingControl);
      }
      
      // Use OSM Nominatim to geocode the start point
      fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(startPoint)}`)
        .then(response => response.json())
        .then(data => {
          if (data.length > 0) {
            const startLat = data[0].lat;
            const startLon = data[0].lon;
            
            // Create routing control
            routingControl = L.Routing.control({
              waypoints: [
                L.latLng(startLat, startLon),
                L.latLng(window.destinationCoords.lat, window.destinationCoords.lng)
              ],
              routeWhileDragging: false,
              lineOptions: {
                styles: [{color: '#0d6efd', opacity: 0.7, weight: 5}]
              },
              showAlternatives: false,
              addWaypoints: false,
              draggableWaypoints: false
            }).addTo(map);
            
            routingControl.on('routesfound', function(e) {
              const routes = e.routes;
              const instructions = document.getElementById('directions-instructions');
              instructions.innerHTML = '';
              
              if (routes && routes[0]) {
                const steps = routes[0].instructions;
                
                steps.forEach(step => {
                  const div = document.createElement('div');
                  div.className = 'direction-step mb-2';
                  div.innerHTML = `<i class='bx bx-right-arrow'></i> ${step.text} (${step.distance}m)`;
                  instructions.appendChild(div);
                });
              }
            });
          } else {
            alert('Could not find the starting location. Please try a different address.');
          }
        })
        .catch(error => {
          console.error('Error geocoding start point:', error);
          alert('Error calculating route. Please try again.');
        });
    });
    
    // District to Barangay mapping
    const barangaysByDistrict = {
      'District 1': ['Alicia', 'Baesa', 'Bagbag', 'Capri', 'Fairview', 'Greater Lagro', 'Gulod', 'Kaligayahan', 'Nagkaisang Nayon', 'North Fairview', 'Novaliches Proper', 'Pasong Putik', 'San Agustin', 'San Bartolome', 'Sta. Lucia', 'Sta. Monica'],
      'District 2': ['Bagong Silangan', 'Batasan Hills', 'Commonwealth', 'Holy Spirit', 'Payatas'],
      'District 3': ['Amihan', 'Bagumbayan', 'Bayanihan', 'Blue Ridge A', 'Blue Ridge B', 'Camp Aguinaldo', 'Dioquino Zobel', 'E. Rodriguez', 'East Kamias', 'Escopa I', 'Escopa II', 'Escopa III', 'Escopa IV', 'Libis', 'Loyola Heights', 'Mangga', 'Marilag', 'Masagana', 'Matandang Balara', 'Milagrosa', 'Pansol', 'Quirino 2-A', 'Quirino 2-B', 'Quirino 2-C', 'Quirino 3-A', 'San Roque', 'St. Ignatius', 'Ugong Norte', 'Villa Maria Clara', 'West Kamias', 'White Plains'],
      'District 4': ['Bagong Lipunan ng Crame', 'Botocan', 'Central', 'Damayan', 'Damayang Lagi', 'Doña Aurora', 'Doña Imelda', 'Doña Josefa', 'Horseshoe', 'Immaculate Conception', 'Kaunlaran', 'Mariana', 'Obrero', 'Old Capitol Site', 'Paligsahan', 'Pinyahan', 'Roxas', 'San Isidro', 'San Martin de Porres', 'San Vicente', 'Santo Niño', 'Sikatuna Village', 'South Triangle', 'Tatalon', 'Teachers Village East', 'Teachers Village West', 'U.P. Campus', 'U.P. Village', 'Valencia'],
      'District 5': ['Bagumbuhay', 'Balingasa', 'Bungad', 'Damar', 'Del Monte', 'Katipunan', 'Lourdes', 'Maharlika', 'Manresa', 'N.S. Amoranto', 'Paang Bundok', 'Paltok', 'Paraiso', 'Salvacion', 'San Antonio', 'San Jose', 'San Isidro Labrador', 'Santa Cruz', 'Santa Teresita', 'Santo Domingo', 'Siena', 'Talayan', 'Veterans Village', 'West Triangle'],
      'District 6': ['Addition Hills', 'Balong-Bato', 'Batis', 'Corazon de Jesus', 'Ermitaño', 'Greenhills', 'Isabelita', 'Kabayanan', 'Little Baguio', 'Maytunas', 'Onse', 'Pasadena', 'Pedro Cruz', 'Progreso', 'Rivera', 'Salapan', 'San Perfecto', 'Santa Lucia', 'St. Joseph', 'Tibagan', 'Wack-Wack']
    };
    
    // Populate barangays based on district selection
    document.getElementById('district').addEventListener('change', function() {
      const district = this.value;
      const barangaySelect = document.getElementById('barangay');
      
      barangaySelect.innerHTML = '<option value="">-- Select Barangay --</option>';
      
      if (district && barangaysByDistrict[district]) {
        barangaysByDistrict[district].forEach(barangay => {
          const option = document.createElement('option');
          option.value = barangay;
          option.textContent = barangay;
          barangaySelect.appendChild(option);
        });
      }
    });
    
    // Edit Modal functionality
    const editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      const id = button.getAttribute('data-id');
      const district = button.getAttribute('data-district');
      const barangay = button.getAttribute('data-barangay');
      const status = button.getAttribute('data-status');
      const latitude = button.getAttribute('data-latitude');
      const longitude = button.getAttribute('data-longitude');
      
      document.getElementById('edit_id').value = id;
      document.getElementById('edit_district').value = district;
      document.getElementById('edit_barangay').value = barangay;
      document.getElementById('edit_status').value = status;
      document.getElementById('edit_latitude').value = latitude;
      document.getElementById('edit_longitude').value = longitude;
    });
    
    // Delete confirmation with SweetAlert
    document.querySelectorAll('.delete-btn').forEach(button => {
      button.addEventListener('click', function(e) {
        e.preventDefault();
        const url = this.getAttribute('href');
        
        Swal.fire({
          title: 'Are you sure?',
          text: "You won't be able to revert this!",
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#0d6efd',
          cancelButtonColor: '#6c757d',
          confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
          if (result.isConfirmed) {
            window.location.href = url;
          }
        });
      });
    });
    
    // Directions button functionality
    document.querySelectorAll('.directions-btn').forEach(button => {
      button.addEventListener('click', function() {
        const barangay = this.getAttribute('data-barangay');
        const latitude = parseFloat(this.getAttribute('data-latitude'));
        const longitude = parseFloat(this.getAttribute('data-longitude'));
        
        getDirections(latitude, longitude, barangay);
      });
    });
    
    // Display any success/error messages
    <?php if (isset($_SESSION['success_message'])): ?>
      Swal.fire({
        title: 'Success!',
        text: '<?= $_SESSION['success_message'] ?>',
        icon: 'success',
        confirmButtonColor: '#0d6efd'
      });
      <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
      Swal.fire({
        title: 'Error!',
        text: '<?= $_SESSION['error_message'] ?>',
        icon: 'error',
        confirmButtonColor: '#0d6efd'
      });
      <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
  </script>
</body>
</html>