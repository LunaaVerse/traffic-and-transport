<?php
session_start();
require_once 'config/database.php';

// Authentication & Authorization
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
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

// Fetch user profile
$user_profile = getUserProfile($pdo_ttm, $_SESSION['user_id']);

// Initialize messages
$success_message = '';
$error_message = '';

// CRUD Operations for CCTV Cameras
// Add new camera
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_camera'])) {
    $location = $_POST['location'];
    $stream_url = $_POST['stream_url'];
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];
    $status = $_POST['status'];
    $description = $_POST['description'];
    
    try {
        $query = "INSERT INTO cctv_cameras (location, stream_url, latitude, longitude, status, description) 
                  VALUES (:location, :stream_url, :latitude, :longitude, :status, :description)";
        $stmt = $pdo_tm->prepare($query);
        $stmt->bindParam(':location', $location);
        $stmt->bindParam(':stream_url', $stream_url);
        $stmt->bindParam(':latitude', $latitude);
        $stmt->bindParam(':longitude', $longitude);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':description', $description);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "CCTV camera added successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $error_message = "Failed to add CCTV camera.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Update camera
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_camera'])) {
    $cctv_id = $_POST['cctv_id'];
    $location = $_POST['location'];
    $stream_url = $_POST['stream_url'];
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];
    $status = $_POST['status'];
    $description = $_POST['description'];
    
    try {
        $query = "UPDATE cctv_cameras SET 
                  location = :location, 
                  stream_url = :stream_url, 
                  latitude = :latitude, 
                  longitude = :longitude, 
                  status = :status, 
                  description = :description 
                  WHERE cctv_id = :cctv_id";
        $stmt = $pdo_tm->prepare($query);
        $stmt->bindParam(':cctv_id', $cctv_id, PDO::PARAM_INT);
        $stmt->bindParam(':location', $location);
        $stmt->bindParam(':stream_url', $stream_url);
        $stmt->bindParam(':latitude', $latitude);
        $stmt->bindParam(':longitude', $longitude);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':description', $description);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "CCTV camera updated successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $error_message = "Failed to update CCTV camera.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Delete camera
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    try {
        $query = "DELETE FROM cctv_cameras WHERE cctv_id = :id";
        $stmt = $pdo_tm->prepare($query);
        $stmt->bindParam(':id', $delete_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "CCTV camera deleted successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $error_message = "Failed to delete CCTV camera.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Get camera for editing
$edit_camera = null;
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    
    try {
        $query = "SELECT * FROM cctv_cameras WHERE cctv_id = :id";
        $stmt = $pdo_tm->prepare($query);
        $stmt->bindParam(':id', $edit_id, PDO::PARAM_INT);
        $stmt->execute();
        $edit_camera = $stmt->fetch();
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Get all cameras
try {
    $query = "SELECT * FROM cctv_cameras ORDER BY created_at DESC";
    $stmt = $pdo_tm->prepare($query);
    $stmt->execute();
    $cameras = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Failed to fetch cameras: " . $e->getMessage();
    $cameras = [];
}

// Get camera statistics
try {
    $query = "SELECT 
        COUNT(*) as total_cameras,
        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_cameras,
        SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) as inactive_cameras,
        SUM(CASE WHEN status = 'Maintenance' THEN 1 ELSE 0 END) as maintenance_cameras
        FROM cctv_cameras";
    $stmt = $pdo_tm->prepare($query);
    $stmt->execute();
    $camera_stats = $stmt->fetch();
} catch (PDOException $e) {
    $camera_stats = [
        'total_cameras' => 0,
        'active_cameras' => 0,
        'inactive_cameras' => 0,
        'maintenance_cameras' => 0
    ];
}

// Check for success message from redirect
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CCTV Integration & Feed Control - Quezon City Traffic Management</title>
  
  <!-- CSS Libraries -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="../css/style.css" rel="stylesheet">
  
  <!-- SweetAlert CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

  <style>
    .live-feed-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    
    .live-feed-card {
      background: white;
      border-radius: var(--card-radius);
      overflow: hidden;
      box-shadow: var(--card-shadow);
      transition: var(--transition);
      border: 1px solid rgba(13, 110, 253, 0.1);
    }
    
    .live-feed-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 35px rgba(13, 110, 253, 0.15);
    }
    
    .live-feed-header {
      padding: 1rem;
      background: var(--primary-gradient);
      color: white;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    
    .live-feed-body {
      padding: 0;
      position: relative;
    }
    
    .live-feed-placeholder {
      height: 200px;
      background: var(--gray-200);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--secondary);
      flex-direction: column;
    }
    
    .live-feed-footer {
      padding: 1rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-top: 1px solid var(--gray-200);
    }
    
    .live-feed-actions {
      display: flex;
      gap: 0.5rem;
    }
    
    .live-feed-btn {
      padding: 0.4rem 0.75rem;
      border-radius: 6px;
      font-size: 0.875rem;
      display: flex;
      align-items: center;
      gap: 0.25rem;
      transition: var(--transition);
    }
    
    .live-feed-btn.primary {
      background: var(--primary);
      color: white;
    }
    
    .live-feed-btn.primary:hover {
      background: var(--primary-dark);
    }
    
    .live-feed-btn.secondary {
      background: var(--gray-200);
      color: var(--dark);
    }
    
    .live-feed-btn.secondary:hover {
      background: var(--gray-300);
    }
    
    /* Map Container */
    .map-container {
      height: 400px;
      border-radius: var(--card-radius);
      overflow: hidden;
      margin-top: 1rem;
    }
    
    .status-badge {
      padding: 0.5rem 0.75rem;
      border-radius: 6px;
      font-weight: 500;
    }
    
    .status-active {
      background-color: rgba(25, 135, 84, 0.15);
      color: #198754;
    }
    
    .status-inactive {
      background-color: rgba(220, 53, 69, 0.15);
      color: #dc3545;
    }
    
    .status-maintenance {
      background-color: rgba(255, 193, 7, 0.15);
      color: #ffc107;
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
    
    .form-group.required label:after {
      content: " *";
      color: #dc3545;
    }
    
    .cctv-form {
      background: white;
      padding: 1.5rem;
      border-radius: var(--card-radius);
      box-shadow: var(--card-shadow);
      margin-bottom: 2rem;
    }
    
    .cctv-table {
      background: white;
      padding: 1.5rem;
      border-radius: var(--card-radius);
      box-shadow: var(--card-shadow);
      margin-bottom: 2rem;
    }
    
    .map-card {
      background: white;
      padding: 1.5rem;
      border-radius: var(--card-radius);
      box-shadow: var(--card-shadow);
      margin-bottom: 2rem;
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
          <h1>CCTV Integration & Feed Control</h1>
          <p>Manage and monitor all CCTV cameras across Quezon City</p>
        </div>
        <div class="header-actions">
          <div class="user-info">
            <span class="fw-medium"><?= htmlspecialchars($user_profile['full_name']) ?></span>
            <span class="text-muted d-block small"><?= htmlspecialchars($user_profile['role']) ?></span>
          </div>
        </div>
      </div>
      
      <!-- Stats Overview -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon">
            <i class='bx bx-cctv'></i>
          </div>
          <div class="stat-content">
            <h3><?= $camera_stats['total_cameras'] ?></h3>
            <p>Total Cameras</p>
            <div class="stat-trend trend-up">
              <i class='bx bx-up-arrow-alt'></i>
              <span><?= $camera_stats['total_cameras'] ?> cameras installed</span>
            </div>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon" style="background: rgba(25, 135, 84, 0.1); color: #198754;">
            <i class='bx bx-check-circle'></i>
          </div>
          <div class="stat-content">
            <h3><?= $camera_stats['active_cameras'] ?></h3>
            <p>Active Cameras</p>
            <div class="stat-trend trend-up">
              <i class='bx bx-up-arrow-alt'></i>
              <span><?= $camera_stats['total_cameras'] > 0 ? round(($camera_stats['active_cameras'] / $camera_stats['total_cameras']) * 100, 1) : 0 ?>% active rate</span>
            </div>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
            <i class='bx bx-error-circle'></i>
          </div>
          <div class="stat-content">
            <h3><?= $camera_stats['inactive_cameras'] ?></h3>
            <p>Offline Cameras</p>
            <div class="stat-trend trend-down">
              <i class='bx bx-down-arrow-alt'></i>
              <span><?= $camera_stats['total_cameras'] > 0 ? round(($camera_stats['inactive_cameras'] / $camera_stats['total_cameras']) * 100, 1) : 0 ?>% offline rate</span>
            </div>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
            <i class='bx bx-time-five'></i>
          </div>
          <div class="stat-content">
            <h3><?= $camera_stats['maintenance_cameras'] ?></h3>
            <p>Under Maintenance</p>
            <div class="stat-trend trend-up">
              <i class='bx bx-time'></i>
              <span><?= $camera_stats['total_cameras'] > 0 ? round(($camera_stats['maintenance_cameras'] / $camera_stats['total_cameras']) * 100, 1) : 0 ?>% maintenance</span>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Live Feed Section -->
      <div class="live-feed-container">
        <?php if (!empty($cameras)): ?>
          <?php foreach ($cameras as $camera): ?>
            <div class="live-feed-card">
              <div class="live-feed-header">
                <h5><?= htmlspecialchars($camera['location']) ?></h5>
                <span class="status-badge status-<?= strtolower($camera['status']) ?>"><?= $camera['status'] ?></span>
              </div>
              <div class="live-feed-body">
                <div class="live-feed-placeholder">
                  <?php if ($camera['status'] == 'Active'): ?>
                    <i class='bx bx-video' style="font-size: 2rem;"></i>
                    <span>Live feed available</span>
                  <?php elseif ($camera['status'] == 'Maintenance'): ?>
                    <i class='bx bx-wrench' style="font-size: 2rem;"></i>
                    <span>Camera under maintenance</span>
                  <?php else: ?>
                    <i class='bx bx-video-off' style="font-size: 2rem;"></i>
                    <span>Camera offline</span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="live-feed-footer">
                <div>Camera ID: CCTV-<?= $camera['cctv_id'] ?></div>
                <div class="live-feed-actions">
                  <button class="live-feed-btn primary">
                    <i class='bx bx-fullscreen'></i>
                  </button>
                  <button class="live-feed-btn secondary">
                    <i class='bx bx-cog'></i>
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="col-12">
            <div class="alert alert-info">
              <i class='bx bx-info-circle'></i> No CCTV cameras found. Please add cameras to view live feeds.
            </div>
          </div>
        <?php endif; ?>
      </div>
      
      <!-- CCTV Management Form -->
      <div class="cctv-form">
        <h4><i class='bx <?= $edit_camera ? 'bx-edit' : 'bx-plus-circle' ?>'></i> <?= $edit_camera ? 'Edit CCTV Camera' : 'Add New CCTV Camera' ?></h4>
        <form method="POST" action="">
          <?php if ($edit_camera): ?>
            <input type="hidden" name="cctv_id" value="<?= $edit_camera['cctv_id'] ?>">
          <?php endif; ?>
          
          <div class="row">
            <div class="col-md-6">
              <div class="form-group required">
                <label for="location">Location</label>
                <input type="text" class="form-control" id="location" name="location" 
                       placeholder="Enter camera location" 
                       value="<?= $edit_camera ? htmlspecialchars($edit_camera['location']) : '' ?>" 
                       required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group required">
                <label for="stream_url">Stream URL</label>
                <input type="text" class="form-control" id="stream_url" name="stream_url" 
                       placeholder="Enter stream URL" 
                       value="<?= $edit_camera ? htmlspecialchars($edit_camera['stream_url']) : '' ?>" 
                       required>
              </div>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-4">
              <div class="form-group required">
                <label for="latitude">Latitude</label>
                <input type="number" step="any" class="form-control" id="latitude" name="latitude" 
                       placeholder="Enter latitude" 
                       value="<?= $edit_camera ? htmlspecialchars($edit_camera['latitude']) : '' ?>" 
                       required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group required">
                <label for="longitude">Longitude</label>
                <input type="number" step="any" class="form-control" id="longitude" name="longitude" 
                       placeholder="Enter longitude" 
                       value="<?= $edit_camera ? htmlspecialchars($edit_camera['longitude']) : '' ?>" 
                       required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label for="status">Status</label>
                <select class="form-control" id="status" name="status">
                  <option value="Active" <?= $edit_camera && $edit_camera['status'] == 'Active' ? 'selected' : '' ?>>Active</option>
                  <option value="Inactive" <?= $edit_camera && $edit_camera['status'] == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                  <option value="Maintenance" <?= $edit_camera && $edit_camera['status'] == 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                </select>
              </div>
            </div>
          </div>
          
          <div class="form-group">
            <label for="description">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3" 
                      placeholder="Enter camera description"><?= $edit_camera ? htmlspecialchars($edit_camera['description']) : '' ?></textarea>
          </div>
          
          <button type="submit" name="<?= $edit_camera ? 'update_camera' : 'add_camera' ?>" class="btn btn-primary">
            <i class='bx bx-save'></i> <?= $edit_camera ? 'Update Camera' : 'Save Camera' ?>
          </button>
          
          <?php if ($edit_camera): ?>
            <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">
              <i class='bx bx-x'></i> Cancel
            </a>
          <?php endif; ?>
        </form>
      </div>
      
      <!-- CCTV Table -->
      <div class="cctv-table">
        <h4><i class='bx bx-list-ul'></i> CCTV Camera List</h4>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Location</th>
                <th>Stream URL</th>
                <th>Coordinates</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($cameras)): ?>
                <?php foreach ($cameras as $camera): ?>
                  <tr>
                    <td><?= $camera['cctv_id'] ?></td>
                    <td><?= htmlspecialchars($camera['location']) ?></td>
                    <td><?= htmlspecialchars($camera['stream_url']) ?></td>
                    <td><?= $camera['latitude'] ?>, <?= $camera['longitude'] ?></td>
                    <td>
                      <?php if ($camera['status'] == 'Active'): ?>
                        <span class="status-badge status-active">Active</span>
                      <?php elseif ($camera['status'] == 'Inactive'): ?>
                        <span class="status-badge status-inactive">Inactive</span>
                      <?php else: ?>
                        <span class="status-badge status-maintenance">Maintenance</span>
                      <?php endif; ?>
                    </td>
                    <td><?= date('M d, Y', strtotime($camera['created_at'])) ?></td>
                    <td>
                      <a href="?edit_id=<?= $camera['cctv_id'] ?>" class="action-btn edit">
                        <i class='bx bx-edit'></i> Edit
                      </a>
                      <a href="#" class="action-btn delete" onclick="confirmDelete(<?= $camera['cctv_id'] ?>)">
                        <i class='bx bx-trash'></i> Delete
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" class="text-center">No CCTV cameras found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- Map Section -->
      <div class="map-card">
        <h4><i class='bx bx-map'></i> CCTV Camera Locations</h4>
        <div id="map" class="map-container"></div>
      </div>
    </div>
  </div>
  
  <!-- JavaScript Libraries -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  
  <script>
    // Initialize map
    function initMap() {
      // Default to Quezon City coordinates
      const map = L.map('map').setView([14.6760, 121.0437], 12);
      
      // Add OpenStreetMap tiles
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
      }).addTo(map);
      
      // Add markers for each camera
      <?php foreach ($cameras as $camera): ?>
        <?php if (!empty($camera['latitude']) && !empty($camera['longitude'])): ?>
          const marker<?= $camera['cctv_id'] ?> = L.marker([<?= $camera['latitude'] ?>, <?= $camera['longitude'] ?>]).addTo(map);
          marker<?= $camera['cctv_id'] ?>.bindPopup(`
            <strong><?= addslashes($camera['location']) ?></strong><br>
            Status: <?= $camera['status'] ?><br>
            <a href="<?= $camera['stream_url'] ?>" target="_blank">View Stream</a>
          `);
        <?php endif; ?>
      <?php endforeach; ?>
    }
    
    // Initialize map when page loads
    document.addEventListener('DOMContentLoaded', initMap);
    
    // SweetAlert for delete confirmation
    function confirmDelete(id) {
      Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = `?delete_id=${id}`;
        }
      });
    }
    
    // Show success/error messages
    <?php if ($success_message): ?>
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '<?= addslashes($success_message) ?>',
        timer: 3000,
        showConfirmButton: false
      });
    <?php endif; ?>
    
    <?php if ($error_message): ?>
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: '<?= addslashes($error_message) ?>',
        timer: 5000,
        showConfirmButton: true
      });
    <?php endif; ?>
  </script>
</body>
</html>