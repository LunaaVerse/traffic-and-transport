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
    $pdo_rtr = getDBConnection('rtr');
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

// ---------------- Handle AJAX Actions ----------------
if(isset($_POST['action'])) {
    $action = $_POST['action'];

    if($action == 'save') {
        $id = $_POST['id'] ?? '';
        $road_name = $_POST['road_name'];
        $status = $_POST['status'];
        $notes = $_POST['notes'];
        $lat = $_POST['lat'];
        $lng = $_POST['lng'];
        $severity = $_POST['severity'] ?? 1;

        if($id) {
            $stmt = $pdo_rtr->prepare("UPDATE road_status SET road_name=:road_name, status=:status, notes=:notes, lat=:lat, lng=:lng, severity=:severity WHERE id=:id");
            $stmt->execute([
                'road_name'=>$road_name,
                'status'=>$status,
                'notes'=>$notes,
                'lat'=>$lat,
                'lng'=>$lng,
                'severity'=>$severity,
                'id'=>$id
            ]);
        } else {
            $stmt = $pdo_rtr->prepare("INSERT INTO road_status (road_name, status, notes, lat, lng, severity) VALUES (:road_name, :status, :notes, :lat, :lng, :severity)");
            $stmt->execute([
                'road_name'=>$road_name,
                'status'=>$status,
                'notes'=>$notes,
                'lat'=>$lat,
                'lng'=>$lng,
                'severity'=>$severity
            ]);
        }
        echo 'success'; exit;
    }

    if($action == 'delete') {
        $id = $_POST['id'];
        $stmt = $pdo_rtr->prepare("DELETE FROM road_status WHERE id=:id");
        $stmt->execute(['id'=>$id]);
        echo 'success'; exit;
    }

    if($action == 'approve') {
        $id = $_POST['id'];
        $approved_by = $_SESSION['user_id'];
        $stmt = $pdo_rtr->prepare("UPDATE road_status SET approved_by=:approved_by, approved_at=NOW() WHERE id=:id");
        $stmt->execute(['approved_by'=>$approved_by, 'id'=>$id]);
        echo 'success'; exit;
    }
}

// ---------------- Filter & Search ----------------
$filter_status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$severity_filter = $_GET['severity'] ?? '';

$query = "SELECT rs.*, u.full_name as approver_name FROM road_status rs LEFT JOIN ttm.users u ON rs.approved_by = u.user_id WHERE 1";
if($filter_status) $query .= " AND rs.status = :status";
if($search) $query .= " AND rs.road_name LIKE :search";
if($severity_filter) $query .= " AND rs.severity = :severity";
$query .= " ORDER BY rs.updated_at DESC";

$stmt = $pdo_rtr->prepare($query);
if($filter_status) $stmt->bindValue(':status', $filter_status);
if($search) $stmt->bindValue(':search', "%$search%");
if($severity_filter) $stmt->bindValue(':severity', $severity_filter);
$stmt->execute();
$roads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user profile for display
$user_profile = getUserProfile($pdo_ttm, $_SESSION['user_id']);

// Set active tab
$current_page = basename($_SERVER['PHP_SELF']);
$active_tab = 'road_update';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Road Status Management - Quezon City Traffic Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .status-Open { background-color: #d4edda; }
        .status-Blocked { background-color: #f8d7da; }
        .status-Maintenance { background-color: #fff3cd; }
        .status-Planned { background-color: #cce5ff; }
        
        .severity-1 { border-left: 4px solid #28a745; }
        .severity-2 { border-left: 4px solid #ffc107; }
        .severity-3 { border-left: 4px solid #fd7e14; }
        .severity-4 { border-left: 4px solid #dc3545; }
        .severity-5 { border-left: 4px solid #6f42c1; }
        
        .map-container { height: 500px; border-radius: 8px; overflow: hidden; }
        .road-table tr { cursor: pointer; }
        .road-table tr:hover { background-color: #f8f9fa; }
        
        .modal-content { border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
        .modal-header { background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; border-radius: 12px 12px 0 0; }
        
        .filter-card { background-color: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
        
        .action-buttons .btn { margin-right: 5px; }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-open { background-color: #d4edda; color: #155724; }
        .badge-blocked { background-color: #f8d7da; color: #721c24; }
        .badge-maintenance { background-color: #fff3cd; color: #856404; }
        .badge-planned { background-color: #cce5ff; color: #004085; }
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
                    <h1>Road Status Management</h1>
                    <p>Monitor and update road status across Quezon City</p>
                </div>
                
                <div class="header-actions">
                    <div class="user-info">
                        <span class="fw-medium"><?= htmlspecialchars($user_profile['full_name']) ?></span>
                        <span class="text-muted d-block small"><?= htmlspecialchars($user_profile['role']) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-card">
                <form id="filterForm" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="search" placeholder="Search road..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="Open" <?= $filter_status=='Open'?'selected':'' ?>>Open</option>
                            <option value="Blocked" <?= $filter_status=='Blocked'?'selected':'' ?>>Blocked</option>
                            <option value="Maintenance" <?= $filter_status=='Maintenance'?'selected':'' ?>>Maintenance</option>
                            <option value="Planned" <?= $filter_status=='Planned'?'selected':'' ?>>Planned</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="severity" class="form-select">
                            <option value="">All Severity</option>
                            <option value="1" <?= $severity_filter=='1'?'selected':'' ?>>Low (1)</option>
                            <option value="2" <?= $severity_filter=='2'?'selected':'' ?>>Moderate (2)</option>
                            <option value="3" <?= $severity_filter=='3'?'selected':'' ?>>High (3)</option>
                            <option value="4" <?= $severity_filter=='4'?'selected':'' ?>>Severe (4)</option>
                            <option value="5" <?= $severity_filter=='5'?'selected':'' ?>>Critical (5)</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-secondary w-100" onclick="clearFilters()">Clear Filters</button>
                    </div>
                </form>
            </div>
            
            <!-- Action Buttons -->
            <div class="d-flex justify-content-between mb-3">
                <h4>Road Status Overview</h4>
                <button class="btn btn-success" onclick="openModal()">
                    <i class='bx bx-plus'></i> Add New Status
                </button>
            </div>
            
            <!-- Road Status Table -->
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover road-table" id="roadTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Road Name</th>
                                    <th>Status</th>
                                    <th>Severity</th>
                                    <th>Last Updated</th>
                                    <th>Approved By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($roads)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">No road status records found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($roads as $road): ?>
                                    <tr class="status-<?= $road['status'] ?> severity-<?= $road['severity'] ?? 1 ?>" 
                                        data-id="<?= $road['id'] ?>"
                                        data-lat="<?= $road['lat'] ?>" 
                                        data-lng="<?= $road['lng'] ?>"
                                        data-road_name="<?= htmlspecialchars($road['road_name']) ?>"
                                        data-status="<?= $road['status'] ?>"
                                        data-notes="<?= htmlspecialchars($road['notes']) ?>"
                                        data-severity="<?= $road['severity'] ?? 1 ?>">
                                        <td><?= htmlspecialchars($road['road_name']) ?></td>
                                        <td>
                                            <span class="status-badge badge-<?= strtolower($road['status']) ?>">
                                                <?= $road['status'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $severity = $road['severity'] ?? 1;
                                            echo str_repeat('⭐', $severity);
                                            ?>
                                        </td>
                                        <td><?= date('M j, Y g:i A', strtotime($road['updated_at'])) ?></td>
                                        <td><?= $road['approver_name'] ?? ($road['approved_by'] ? 'User '.$road['approved_by'] : '-') ?></td>
                                        <td class="action-buttons">
                                            <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); openModal(
                                                '<?= $road['id'] ?>',
                                                '<?= htmlspecialchars(addslashes($road['road_name'])) ?>',
                                                '<?= $road['status'] ?>',
                                                '<?= htmlspecialchars(addslashes($road['notes'])) ?>',
                                                '<?= $road['lat'] ?>',
                                                '<?= $road['lng'] ?>',
                                                '<?= $road['severity'] ?? 1 ?>'
                                            )">
                                                <i class='bx bx-edit'></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger deleteBtn" data-id="<?= $road['id'] ?>">
                                                <i class='bx bx-trash'></i> Delete
                                            </button>
                                            <?php if (!$road['approved_by']): ?>
                                                <button class="btn btn-sm btn-outline-success approveBtn" data-id="<?= $road['id'] ?>">
                                                    <i class='bx bx-check'></i> Approve
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Map Section -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title"><i class='bx bx-map'></i> Road Status Map Overview</h5>
                </div>
                <div class="card-body">
                    <div id="map" class="map-container"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Road Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="statusForm">
                        <input type="hidden" name="id" id="roadId">
                        <div class="mb-3">
                            <label class="form-label">Road Name</label>
                            <input type="text" class="form-control" name="road_name" id="roadName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="roadStatus" required>
                                <option value="Open">Open</option>
                                <option value="Blocked">Blocked</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="Planned">Planned</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Severity Level</label>
                            <select class="form-select" name="severity" id="roadSeverity" required>
                                <option value="1">⭐ Low (1)</option>
                                <option value="2">⭐⭐ Moderate (2)</option>
                                <option value="3">⭐⭐⭐ High (3)</option>
                                <option value="4">⭐⭐⭐⭐ Severe (4)</option>
                                <option value="5">⭐⭐⭐⭐⭐ Critical (5)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" id="roadNotes" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Latitude</label>
                                    <input type="text" class="form-control" name="lat" id="roadLat" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Longitude</label>
                                    <input type="text" class="form-control" name="lng" id="roadLng" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-text">Click on the map below to set coordinates</div>
                        </div>
                        <div id="miniMap" style="height: 200px; border-radius: 8px;"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="$('#statusForm').submit()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    <script>
        // Initialize main map
        var map = L.map('map').setView([14.6760, 121.0437], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // Initialize mini map in modal
        var miniMap = L.map('miniMap').setView([14.6760, 121.0437], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(miniMap);
        
        var markers = {};
        var miniMarker = null;

        // Add click event to mini map
        miniMap.on('click', function(e) {
            if (miniMarker) {
                miniMap.removeLayer(miniMarker);
            }
            miniMarker = L.marker(e.latlng).addTo(miniMap);
            $('#roadLat').val(e.latlng.lat);
            $('#roadLng').val(e.latlng.lng);
        });

        function updateMap() {
            // Clear existing markers
            for(var key in markers) {
                map.removeLayer(markers[key]);
            }
            markers = {};
            
            // Add markers from table data
            $('#roadTable tbody tr').each(function(){
                var $row = $(this);
                var id = $row.data('id');
                var name = $row.data('road_name');
                var status = $row.data('status');
                var lat = parseFloat($row.data('lat'));
                var lng = parseFloat($row.data('lng'));
                
                if(isNaN(lat) || isNaN(lng)) return;
                
                var color = getColorForStatus(status);
                var marker = L.circleMarker([lat, lng], {
                    color: color,
                    fillColor: color,
                    fillOpacity: 0.7,
                    radius: 8
                }).addTo(map).bindPopup('<strong>' + name + '</strong><br>Status: ' + status);
                
                markers[id] = marker;
            });
        }
        
        function getColorForStatus(status) {
            switch(status) {
                case 'Open': return '#28a745';
                case 'Blocked': return '#dc3545';
                case 'Maintenance': return '#ffc107';
                case 'Planned': return '#17a2b8';
                default: return '#6c757d';
            }
        }

        function openModal(id='', name='', status='', notes='', lat='', lng='', severity=1) {
            $('#roadId').val(id);
            $('#roadName').val(name);
            $('#roadStatus').val(status);
            $('#roadNotes').val(notes);
            $('#roadLat').val(lat);
            $('#roadLng').val(lng);
            $('#roadSeverity').val(severity);
            $('#modalTitle').text(id ? 'Edit Road Status' : 'Add Road Status');
            
            // Set mini map view if coordinates are provided
            if (lat && lng) {
                var latLng = [parseFloat(lat), parseFloat(lng)];
                miniMap.setView(latLng, 15);
                
                if (miniMarker) {
                    miniMap.removeLayer(miniMarker);
                }
                miniMarker = L.marker(latLng).addTo(miniMap);
            } else {
                miniMap.setView([14.6760, 121.0437], 12);
                if (miniMarker) {
                    miniMap.removeLayer(miniMarker);
                    miniMarker = null;
                }
            }
            
            var modal = new bootstrap.Modal(document.getElementById('statusModal'));
            modal.show();
        }
        
        function clearFilters() {
            window.location.href = window.location.pathname;
        }

        // Function to show SweetAlert notifications
        function showAlert(icon, title, text, timer = 3000) {
            Swal.fire({
                icon: icon,
                title: title,
                text: text,
                timer: timer,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }

        $(document).ready(function(){
            // Initialize maps
            updateMap();
            
            // Form submission
            $('#statusForm').on('submit', function(e){
                e.preventDefault();
                $.post('', $(this).serialize()+'&action=save', function(resp){
                    if(resp=='success') { 
                        $('#statusModal').modal('hide');
                        showAlert('success', 'Success', 'Road status saved successfully');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showAlert('error', 'Error', 'Failed to save road status');
                    }
                }).fail(function() {
                    showAlert('error', 'Error', 'Failed to save road status');
                });
            });
            
            // Delete button
            $(document).on('click', '.deleteBtn', function(e){
                e.stopPropagation();
                var id = $(this).data('id');
                var roadName = $(this).closest('tr').data('road_name');
                
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You are about to delete the road status for " + roadName,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post('', {action:'delete', id:id}, function(resp){ 
                            if(resp=='success') {
                                showAlert('success', 'Deleted', 'Road status deleted successfully');
                                setTimeout(function() {
                                    window.location.reload();
                                }, 1500);
                            } else {
                                showAlert('error', 'Error', 'Failed to delete road status');
                            }
                        }).fail(function() {
                            showAlert('error', 'Error', 'Failed to delete road status');
                        });
                    }
                });
            });
            
            // Approve button
            $(document).on('click', '.approveBtn', function(e){
                e.stopPropagation();
                var id = $(this).data('id');
                var roadName = $(this).closest('tr').data('road_name');
                
                Swal.fire({
                    title: 'Approve Road Status',
                    text: "Are you sure you want to approve the status for " + roadName + "?",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, approve it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post('', {action:'approve', id:id}, function(resp){ 
                            if(resp=='success') {
                                showAlert('success', 'Approved', 'Road status approved successfully');
                                setTimeout(function() {
                                    window.location.reload();
                                }, 1500);
                            } else {
                                showAlert('error', 'Error', 'Failed to approve road status');
                            }
                        }).fail(function() {
                            showAlert('error', 'Error', 'Failed to approve road status');
                        });
                    }
                });
            });
            
            // Row click to view on map
            $(document).on('click', '#roadTable tbody tr', function(e){
                if($(e.target).is('button') || $(e.target).closest('button').length) return;
                
                var lat = parseFloat($(this).data('lat'));
                var lng = parseFloat($(this).data('lng'));
                var id = $(this).data('id');
                
                if(isNaN(lat) || isNaN(lng)) return;
                
                map.setView([lat, lng], 15);
                if(markers[id]) {
                    markers[id].openPopup();
                }
            });
            
            // Auto refresh every 30 seconds
            setInterval(function() {
                $.get(window.location.href, function(data) {
                    var html = $(data).find('#roadTable').html();
                    $('#roadTable').html(html);
                    updateMap();
                });
            }, 30000);
        });
    </script>
</body>
</html>