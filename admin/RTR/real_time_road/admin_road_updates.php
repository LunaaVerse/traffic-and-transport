
<?php
// admin_road_updates.php
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

// Get user profile
try {
    $pdo_ttm = getDBConnection('ttm');
    $user_profile = getUserProfile($pdo_ttm, $_SESSION['user_id']);
} catch (Exception $e) {
    $user_profile = ['full_name' => 'User', 'role' => 'Unknown'];
}

// Check if user is Admin
if ($user_profile['role'] !== 'admin') {
    die("Access Denied. Admins only.");
}

// SweetAlert messages
$alert = "";

// CREATE
if (isset($_POST['add'])) {
    $road_name = $_POST['road_name'];
    $status = $_POST['status'];
    $description = $_POST['description'];

    $sql = "INSERT INTO road_updates (road_name, status, description) 
            VALUES (:road_name, :status, :description)";
    $stmt = $pdo_rtr->prepare($sql);
    $stmt->bindParam(':road_name', $road_name);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':description', $description);
    
    if ($stmt->execute()) {
        $_SESSION['alert'] = "created";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// UPDATE
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $road_name = $_POST['road_name'];
    $status = $_POST['status'];
    $description = $_POST['description'];

    $sql = "UPDATE road_updates 
            SET road_name=:road_name, status=:status, description=:description 
            WHERE id=:id";
    $stmt = $pdo_rtr->prepare($sql);
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':road_name', $road_name);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':description', $description);
    
    if ($stmt->execute()) {
        $_SESSION['alert'] = "updated";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// DELETE
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    $sql = "DELETE FROM road_updates WHERE id=:id";
    $stmt = $pdo_rtr->prepare($sql);
    $stmt->bindParam(':id', $id);
    
    if ($stmt->execute()) {
        $_SESSION['alert'] = "deleted";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Check for alert message from session
if (isset($_SESSION['alert'])) {
    $alert = $_SESSION['alert'];
    unset($_SESSION['alert']);
}

// FETCH DATA
$stmt = $pdo_rtr->prepare("SELECT * FROM road_updates ORDER BY updated_at DESC");
$stmt->execute();
$result = $stmt->fetchAll();

// Set active tab
$active_tab = 'road_update';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Road Updates - Admin Only</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                    <h1>Road Updates Management</h1>
                    <p>Manage road status updates and notifications</p>
                </div>
                
                <div class="header-actions">
                    <div class="user-info">
                        <span class="fw-medium"><?= htmlspecialchars($user_profile['full_name']) ?></span>
                        <span class="text-muted d-block small"><?= htmlspecialchars($user_profile['role']) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Dashboard Content -->
            <div class="stats-grid mb-4">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class='bx bx-road'></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= count($result) ?></h3>
                        <p>Total Road Updates</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(25, 135, 84, 0.1); color: #198754;">
                        <i class='bx bx-check-circle'></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= count(array_filter($result, function($item) { return $item['status'] === 'Open'; })) ?></h3>
                        <p>Open Roads</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                        <i class='bx bx-x-circle'></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= count(array_filter($result, function($item) { return $item['status'] === 'Blocked'; })) ?></h3>
                        <p>Blocked Roads</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                        <i class='bx bx-wrench'></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= count(array_filter($result, function($item) { return $item['status'] === 'Maintenance'; })) ?></h3>
                        <p>Under Maintenance</p>
                    </div>
                </div>
            </div>

            <!-- Add Road Update Form -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class='bx bx-plus'></i> Add New Road Update</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="roadForm">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="road_name" class="form-label">Road Name</label>
                                <input type="text" name="road_name" class="form-control" placeholder="Enter road name" required>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select name="status" class="form-control" required>
                                    <option value="">Select Status</option>
                                    <option value="Open">Open</option>
                                    <option value="Blocked">Blocked</option>
                                    <option value="Maintenance">Maintenance</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="description" class="form-label">Description</label>
                                <input type="text" name="description" class="form-control" placeholder="Enter description">
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" name="add" class="btn btn-primary w-100">
                                    <i class='bx bx-plus'></i> Add
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Road Updates Table -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class='bx bx-list-ul'></i> Road Updates List</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Road Name</th>
                                    <th>Status</th>
                                    <th>Description</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (count($result) > 0): ?>
                                <?php foreach($result as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['id']); ?></td>
                                        <td><?= htmlspecialchars($row['road_name']); ?></td>
                                        <td>
                                            <?php 
                                            $status_class = '';
                                            if ($row['status'] === 'Open') $status_class = 'status-active';
                                            if ($row['status'] === 'Blocked') $status_class = 'status-inactive';
                                            if ($row['status'] === 'Maintenance') $status_class = 'status-warning';
                                            ?>
                                            <span class="status-badge <?= $status_class ?>"><?= htmlspecialchars($row['status']); ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($row['description']); ?></td>
                                        <td><?= htmlspecialchars($row['updated_at']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-warning" 
                                                onclick="editRoad('<?= $row['id']; ?>','<?= htmlspecialchars(addslashes($row['road_name'])); ?>','<?= $row['status']; ?>','<?= htmlspecialchars(addslashes($row['description'])); ?>')">
                                                <i class='bx bx-edit'></i> Edit
                                            </button>
                                            <a href="?delete=<?= $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirmDelete()">
                                                <i class='bx bx-trash'></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No road updates found.</td>
                                </tr>
                            <?php endif; ?>
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
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title"><i class='bx bx-edit'></i> Edit Road Update</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
              <input type="hidden" name="id" id="edit_id">
              <div class="mb-3">
                  <label class="form-label">Road Name</label>
                  <input type="text" name="road_name" id="edit_road_name" class="form-control" required>
              </div>
              <div class="mb-3">
                  <label class="form-label">Status</label>
                  <select name="status" id="edit_status" class="form-control" required>
                      <option value="Open">Open</option>
                      <option value="Blocked">Blocked</option>
                      <option value="Maintenance">Maintenance</option>
                  </select>
              </div>
              <div class="mb-3">
                  <label class="form-label">Description</label>
                  <input type="text" name="description" id="edit_description" class="form-control">
              </div>
          </div>
          <div class="modal-footer">
            <button type="submit" name="update" class="btn btn-success">
                <i class='bx bx-save'></i> Save Changes
            </button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class='bx bx-x'></i> Cancel
            </button>
          </div>
        </form>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function editRoad(id, road_name, status, description) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_road_name').value = road_name;
        document.getElementById('edit_status').value = status;
        document.getElementById('edit_description').value = description;
        
        // Initialize and show the modal
        var editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
    }
    
    function confirmDelete() {
        return confirm('Are you sure you want to delete this road update?');
    }

    // SweetAlert Feedback
    <?php if ($alert == "created"): ?>
    Swal.fire({
        title: "Success!",
        text: "Road update has been added.",
        icon: "success",
        timer: 3000,
        showConfirmButton: false
    });
    <?php elseif ($alert == "updated"): ?>
    Swal.fire({
        title: "Updated!",
        text: "Road update has been modified.",
        icon: "info",
        timer: 3000,
        showConfirmButton: false
    });
    <?php elseif ($alert == "deleted"): ?>
    Swal.fire({
        title: "Deleted!",
        text: "Road update has been removed.",
        icon: "success",
        timer: 3000,
        showConfirmButton: false
    });
    <?php endif; ?>
    </script>
</body>
</html>
