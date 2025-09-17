<?php
session_start();
require_once 'config/database.php';

// Authentication - Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get database connections
try {
    $pdo_ttm = getDBConnection('ttm');
    $pdo_avr = getDBConnection('avr');
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

// Fetch user data for display
$user_profile = getUserProfile($pdo_ttm, $_SESSION['user_id']);

// Set active tab
$current_page = basename($_SERVER['PHP_SELF']);
$active_tab = 'accident_violation';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_type = $_POST['report_type'];
    $location = $_POST['location'];
    $description = $_POST['description'];
    $reporter_type = "Citizen";
    $status = "Pending";
    $evidenceFile = null;

    // Handle file upload
    if (!empty($_FILES['evidence']['name'])) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) { 
            mkdir($targetDir, 0777, true); 
        }
        
        // Generate unique filename
        $fileExtension = pathinfo($_FILES['evidence']['name'], PATHINFO_EXTENSION);
        $evidenceFile = time() . "_" . uniqid() . "." . $fileExtension;
        
        // Move uploaded file
        move_uploaded_file($_FILES['evidence']['tmp_name'], $targetDir . $evidenceFile);
    }

    try {
        // Insert report into database
        $query = "INSERT INTO reports (reporter_type, report_type, location, description, status, evidence) 
                  VALUES (:reporter_type, :report_type, :location, :description, :status, :evidence)";
        $stmt = $pdo_avr->prepare($query);
        $stmt->bindParam(':reporter_type', $reporter_type);
        $stmt->bindParam(':report_type', $report_type);
        $stmt->bindParam(':location', $location);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':evidence', $evidenceFile);
        $stmt->execute();

        // Success message with SweetAlert
        $success_message = "✅ Your report has been submitted successfully!";
    } catch (PDOException $e) {
        $error_message = "❌ Error submitting report: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Report Submission - Quezon City Traffic Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
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
                <a href="../dashboard/dashboard.php" class="sidebar-link">
                    <i class='bx bx-home'></i>
                    <span class="text">Dashboard</span>
                </a>

                <!-- Traffic Monitoring with Dropdown -->
                <div class="sidebar-section mt-4">Traffic Modules</div>
                <div class="dropdown-toggle sidebar-link" data-bs-toggle="collapse" data-bs-target="#tmDropdown" aria-expanded="false">
                    <i class='bx bx-traffic-cone'></i>
                    <span class="text">Traffic Monitoring</span>
                </div>
                <div class="sidebar-dropdown collapse" id="tmDropdown">
                    <a href="../dashboard.php" class="sidebar-dropdown-link">
                        <i class='bx bx-clipboard'></i> Dashboard
                    </a>
                    <a href="TM/manual_logs/admin_traffic_log.php" class="sidebar-dropdown-link">
                        <i class='bx bx-clipboard'></i> Manual Traffic Logs
                    </a>
                    <a href="TM/traffic_volume/tv.php" class="sidebar-dropdown-link">
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
                    <a href="RTR/post_dashboard/admin_road_updates.php" class="sidebar-dropdown-link">
                        <i class='bx bx-edit'></i> Post Dashboard
                    </a>
                    <a href="RTR/status_management/status_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-stats'></i> Status Management
                    </a>
                    <a href="RTR/real_time_road/admin_road_updates.php" class="sidebar-dropdown-link">
                        <i class='bx bx-bell'></i> Real Time Road
                    </a>
                    <a href="RTR/road_condition_map/road_condition_map.php" class="sidebar-dropdown-link">
                        <i class='bx bx-bell'></i> Road Condition Map
                    </a>
                </div>

                <!-- Accident & Violation Report with Dropdown -->
                <div class="dropdown-toggle sidebar-link active" data-bs-toggle="collapse" data-bs-target="#avrDropdown" aria-expanded="true">
                    <i class='bx bx-error-circle'></i>
                    <span class="text">Accident & Violation Report</span>
                </div>
                <div class="sidebar-dropdown collapse show" id="avrDropdown">
                    <a href="AVR/report_management/report_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-directions'></i> Report Management
                    </a>
                    <a href="AVR/violation_categorization/violation_categorization.php" class="sidebar-dropdown-link">
                        <i class='bx bx-notification'></i> Violation Categorization
                    </a>
                    <a href="AVR/evidence_handling/evidence_admin.php" class="sidebar-dropdown-link">
                        <i class='bx bx-edit-alt'></i> Evidence Handling
                    </a>
                    <a href="AVR/violation_records/admin_violation_records.php" class="sidebar-dropdown-link">
                        <i class='bx bx-check-circle'></i> Violation Record 
                    </a>
                     <a href="AVR/escalation_assignment/admin_escalation_assignment.php" class="sidebar-dropdown-link">
                        <i class='bx bx-check-circle'></i> Escalation & Assignment  
                    </a>
                    <a href="submit_citizen_report.php" class="sidebar-dropdown-link active">
                        <i class='bx bx-user-voice'></i> Citizen Report Submission
                    </a>
                </div>

                <!-- Other modules would follow the same pattern -->
                
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
                    <h1>Citizen Report Submission</h1>
                    <p>Submit accident or violation reports</p>
                </div>
                
                <div class="header-actions">
                    <div class="user-info">
                        <span class="fw-medium"><?= htmlspecialchars($user_profile['full_name']) ?></span>
                        <span class="text-muted d-block small"><?= htmlspecialchars($user_profile['role']) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Report Submission Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class='bx bx-edit'></i> Submit a Report</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success" role="alert">
                            <?= $success_message ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= $error_message ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="report_type" class="form-label">Report Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="report_type" name="report_type" required>
                                        <option value="">-- Select Report Type --</option>
                                        <option value="Accident" <?= (isset($_POST['report_type']) && $_POST['report_type'] == 'Accident') ? 'selected' : '' ?>>Accident</option>
                                        <option value="Violation" <?= (isset($_POST['report_type']) && $_POST['report_type'] == 'Violation') ? 'selected' : '' ?>>Violation</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="location" class="form-label">Location <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="location" name="location" 
                                           value="<?= isset($_POST['location']) ? htmlspecialchars($_POST['location']) : '' ?>" 
                                           placeholder="Enter the location" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="4" 
                                      placeholder="Please provide a detailed description of the incident" required><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="evidence" class="form-label">Evidence (Optional)</label>
                            <input type="file" class="form-control" id="evidence" name="evidence" 
                                   accept="image/*,video/*,.pdf">
                            <div class="form-text">Upload photos, videos, or documents related to the incident. Maximum file size: 5MB</div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="reset" class="btn btn-secondary me-md-2">Clear Form</button>
                            <button type="submit" class="btn btn-primary">Submit Report</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Recent Reports (if any) -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title"><i class='bx bx-history'></i> Your Recent Reports</h5>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        // Get recent reports by this user
                        $query = "SELECT * FROM reports WHERE reporter_type = 'Citizen' ORDER BY created_at DESC LIMIT 5";
                        $stmt = $pdo_avr->prepare($query);
                        $stmt->execute();
                        $recent_reports = $stmt->fetchAll();
                        
                        if (count($recent_reports) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Location</th>
                                            <th>Status</th>
                                            <th>Date Submitted</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_reports as $report): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($report['report_type']) ?></td>
                                                <td><?= htmlspecialchars($report['location']) ?></td>
                                                <td>
                                                    <span class="badge 
                                                        <?= $report['status'] == 'Resolved' ? 'bg-success' : 
                                                           ($report['status'] == 'Under Review' ? 'bg-warning' : 'bg-secondary') ?>">
                                                        <?= htmlspecialchars($report['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M j, Y g:i A', strtotime($report['created_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center py-3">You haven't submitted any reports yet.</p>
                        <?php endif;
                    } catch (PDOException $e) {
                        echo '<p class="text-danger">Error loading recent reports.</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Initialize sidebar dropdowns
        document.addEventListener('DOMContentLoaded', function() {
            // Set active dropdowns based on current page
            const activeTab = '<?= $active_tab ?>';
            if (activeTab) {
                const dropdown = document.getElementById(activeTab + 'Dropdown');
                if (dropdown) {
                    dropdown.classList.add('show');
                }
            }
            
            // Show SweetAlert on successful submission
            <?php if (isset($success_message)): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Report Submitted',
                    text: 'Your report has been submitted successfully!',
                    confirmButtonColor: '#0d6efd'
                });
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Submission Failed',
                    text: 'There was an error submitting your report. Please try again.',
                    confirmButtonColor: '#0d6efd'
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>