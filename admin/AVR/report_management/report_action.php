<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is admin
//if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
 //   header("Location: ../login.php");
  //  exit();
//}


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


// Database connection for AVR
$avr_conn = new mysqli("localhost:3307", "root", "", "avr");
if ($avr_conn->connect_error) {
    die("AVR database connection failed: " . $avr_conn->connect_error);
}

// Handle actions (approve, reject, delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action === 'approve') {
        $stmt = $avr_conn->prepare("UPDATE reports SET status = 'Approved' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Report Approved',
                text: 'The report has been approved successfully.',
                confirmButtonText: 'OK'
            }).then(() => {
                window.location.href = 'report_management.php';
            });
        </script>";
    } 
    elseif ($action === 'reject') {
        $stmt = $avr_conn->prepare("UPDATE reports SET status = 'Rejected' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        echo "<script>
            Swal.fire({
                icon: 'info',
                title: 'Report Rejected',
                text: 'The report has been rejected.',
                confirmButtonText: 'OK'
            }).then(() => {
                window.location.href = 'report_management.php';
            });
        </script>";
    }
    elseif ($action === 'delete') {
        // First get the evidence filename to delete the file
        $result = $avr_conn->query("SELECT evidence FROM reports WHERE id = $id");
        if ($result && $row = $result->fetch_assoc()) {
            if ($row['evidence']) {
                $file_path = "../AVR/uploads/" . $row['evidence'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
        }
        
        $stmt = $avr_conn->prepare("DELETE FROM reports WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Report Deleted',
                text: 'The report has been deleted successfully.',
                confirmButtonText: 'OK'
            }).then(() => {
                window.location.href = 'report_management.php';
            });
        </script>";
    }
}

// Get all reports
$reports = [];
$result = $avr_conn->query("SELECT * FROM reports ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
}

$avr_conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Report Management - Quezon City Traffic Management</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
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
    
    /* Main Content */
    .main-content {
      margin-left: var(--sidebar-width);
      padding: 2rem;
      transition: var(--transition);
      min-height: 100vh;
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
    
    /* Card Styling */
    .stats-card {
      background: white;
      border-radius: var(--card-radius);
      padding: 1.5rem;
      box-shadow: var(--card-shadow);
      border: 1px solid rgba(13, 110, 253, 0.1);
      margin-bottom: 1.5rem;
      transition: var(--transition);
    }
    
    .stats-card:hover {
      transform: translateY(-5px);
    }
    
    .stats-icon {
      width: 60px;
      height: 60px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      margin-bottom: 1rem;
    }
    
    .stats-icon.pending {
      background: rgba(255, 193, 7, 0.15);
      color: var(--warning);
    }
    
    .stats-icon.approved {
      background: rgba(25, 135, 84, 0.15);
      color: var(--success);
    }
    
    .stats-icon.rejected {
      background: rgba(220, 53, 69, 0.15);
      color: var(--danger);
    }
    
    .stats-icon.total {
      background: rgba(13, 110, 253, 0.15);
      color: var(--primary);
    }
    
    .stats-number {
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 0.25rem;
    }
    
    .stats-text {
      color: var(--secondary);
      font-size: 14px;
    }
    
    /* Table Styling */
    .table-container {
      background: white;
      border-radius: var(--card-radius);
      padding: 1.5rem;
      box-shadow: var(--card-shadow);
      border: 1px solid rgba(13, 110, 253, 0.1);
      margin-bottom: 2rem;
      overflow: hidden;
    }
    
    .table-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.5rem;
    }
    
    .table-title {
      font-size: 20px;
      font-weight: 700;
      color: var(--dark);
      margin-bottom: 0;
    }
    
    .table-responsive {
      border-radius: 8px;
      overflow: hidden;
    }
    
    .table {
      margin-bottom: 0;
    }
    
    .table thead th {
      background: var(--gray-100);
      color: var(--dark);
      font-weight: 600;
      border-top: none;
      padding: 1rem;
    }
    
    .table tbody td {
      padding: 1rem;
      vertical-align: middle;
    }
    
    .table tbody tr {
      transition: var(--transition);
    }
    
    .table tbody tr:hover {
      background: var(--gray-100);
    }
    
    /* Status Badges */
    .badge {
      padding: 0.5rem 1rem;
      border-radius: 50px;
      font-weight: 600;
      font-size: 12px;
    }
    
    .badge-pending {
      background: rgba(255, 193, 7, 0.15);
      color: var(--warning);
    }
    
    .badge-approved {
      background: rgba(25, 135, 84, 0.15);
      color: var(--success);
    }
    
    .badge-rejected {
      background: rgba(220, 53, 69, 0.15);
      color: var(--danger);
    }
    
    /* Action Buttons */
    .btn-action {
      padding: 0.25rem 0.5rem;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
    }
    
    .btn-view {
      background: rgba(13, 110, 253, 0.1);
      color: var(--primary);
      border: none;
    }
    
    .btn-view:hover {
      background: rgba(13, 110, 253, 0.2);
    }
    
    .btn-approve {
      background: rgba(25, 135, 84, 0.1);
      color: var(--success);
      border: none;
    }
    
    .btn-approve:hover {
      background: rgba(25, 135, 84, 0.2);
    }
    
    .btn-reject {
      background: rgba(220, 53, 69, 0.1);
      color: var(--danger);
      border: none;
    }
    
    .btn-reject:hover {
      background: rgba(220, 53, 69, 0.2);
    }
    
    .btn-delete {
      background: rgba(108, 117, 125, 0.1);
      color: var(--secondary);
      border: none;
    }
    
    .btn-delete:hover {
      background: rgba(108, 117, 125, 0.2);
    }
    
    /* Modal Styling */
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
      padding: 1.5rem;
    }
    
    .modal-title {
      font-weight: 700;
    }
    
    .modal-body {
      padding: 1.5rem;
    }
    
    .modal-footer {
      padding: 1rem 1.5rem;
      border-top: 1px solid var(--gray-200);
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
      
      .main-content {
        padding: 1rem;
      }
      
      .table-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
      }
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="sidebar-header">
      <img src="../img/FRSM.png" alt="Logo">
      <div class="text">
        Quezon City<br>
        <small>Traffic Management System</small>
      </div>
    </div>
    
    <div class="sidebar-menu">
      <div class="sidebar-section">Main Navigation</div>
      <a href="dashboard.php" class="sidebar-link">
        <i class='bx bx-home'></i>
        <span class="text">Dashboard</span>
      </a>

      <div class="sidebar-section mt-4">Admin Modules</div>
      
      <!-- Accident & Violation Report with Dropdown -->
      <div class="dropdown-toggle sidebar-link active" data-bs-toggle="collapse" data-bs-target="#avrDropdown" aria-expanded="true">
        <i class='bx bx-error-circle'></i>
        <span class="text">Accident & Violation Report</span>
      </div>
      <div class="sidebar-dropdown collapse show" id="avrDropdown">
        <a href="report_management.php" class="sidebar-dropdown-link active">
          <i class='bx bx-task'></i> Report Management
        </a>
        <a href="analytics.php" class="sidebar-dropdown-link">
          <i class='bx bx-stats'></i> Analytics
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
        <h1>Report Management</h1>
        <p>Review and manage all submitted reports</p>
      </div>
      <div class="header-actions">
        <div class="user-info text-end">
          <span class="fw-medium"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></span>
          <span class="text-muted d-block small">Administrator</span>
        </div>
      </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row">
      <div class="col-md-3">
        <div class="stats-card">
          <div class="stats-icon total">
            <i class='bx bx-file'></i>
          </div>
          <div class="stats-number"><?= count($reports) ?></div>
          <div class="stats-text">Total Reports</div>
        </div>
      </div>
      
      <div class="col-md-3">
        <div class="stats-card">
          <div class="stats-icon pending">
            <i class='bx bx-time'></i>
          </div>
          <div class="stats-number"><?= count(array_filter($reports, function($r) { return $r['status'] === 'Pending'; })) ?></div>
          <div class="stats-text">Pending Reports</div>
        </div>
      </div>
      
      <div class="col-md-3">
        <div class="stats-card">
          <div class="stats-icon approved">
            <i class='bx bx-check-circle'></i>
          </div>
          <div class="stats-number"><?= count(array_filter($reports, function($r) { return $r['status'] === 'Approved'; })) ?></div>
          <div class="stats-text">Approved Reports</div>
        </div>
      </div>
      
      <div class="col-md-3">
        <div class="stats-card">
          <div class="stats-icon rejected">
            <i class='bx bx-x-circle'></i>
          </div>
          <div class="stats-number"><?= count(array_filter($reports, function($r) { return $r['status'] === 'Rejected'; })) ?></div>
          <div class="stats-text">Rejected Reports</div>
        </div>
      </div>
    </div>
    
    <!-- Reports Table -->
    <div class="table-container">
      <div class="table-header">
        <h3 class="table-title">All Reports</h3>
        <div class="table-actions">
          <button class="btn btn-sm btn-outline-primary">
            <i class='bx bx-filter me-1'></i> Filter
          </button>
        </div>
      </div>
      
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>ID</th>
              <th>Reporter Type</th>
              <th>Report Type</th>
              <th>Location</th>
              <th>Evidence</th>
              <th>Status</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($reports) > 0): ?>
              <?php foreach ($reports as $report): ?>
                <tr>
                  <td><?= $report['id'] ?></td>
                  <td><?= htmlspecialchars($report['reporter_type']) ?></td>
                  <td><?= htmlspecialchars($report['report_type']) ?></td>
                  <td><?= htmlspecialchars($report['location']) ?></td>
                  <td>
                    <?php if ($report['evidence']): ?>
                      <span class="badge bg-light text-dark"><?= htmlspecialchars($report['evidence']) ?></span>
                    <?php else: ?>
                      <span class="text-muted">None</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($report['status'] === 'Pending'): ?>
                      <span class="badge badge-pending">Pending</span>
                    <?php elseif ($report['status'] === 'Approved'): ?>
                      <span class="badge badge-approved">Approved</span>
                    <?php else: ?>
                      <span class="badge badge-rejected">Rejected</span>
                    <?php endif; ?>
                  </td>
                  <td><?= date('M d, Y', strtotime($report['created_at'])) ?></td>
                  <td>
                    <div class="btn-group">
                      <button class="btn btn-sm btn-action btn-view" data-bs-toggle="modal" data-bs-target="#viewReportModal" 
                        data-id="<?= $report['id'] ?>"
                        data-reporter="<?= htmlspecialchars($report['reporter_type']) ?>"
                        data-type="<?= htmlspecialchars($report['report_type']) ?>"
                        data-location="<?= htmlspecialchars($report['location']) ?>"
                        data-description="<?= htmlspecialchars($report['description']) ?>"
                        data-evidence="<?= htmlspecialchars($report['evidence']) ?>"
                        data-status="<?= $report['status'] ?>"
                        data-date="<?= date('M d, Y h:i A', strtotime($report['created_at'])) ?>">
                        <i class='bx bx-show'></i> View
                      </button>
                      
                      <?php if ($report['status'] === 'Pending'): ?>
                        <a href="?action=approve&id=<?= $report['id'] ?>" class="btn btn-sm btn-action btn-approve">
                          <i class='bx bx-check'></i> Approve
                        </a>
                        <a href="?action=reject&id=<?= $report['id'] ?>" class="btn btn-sm btn-action btn-reject">
                          <i class='bx bx-x'></i> Reject
                        </a>
                      <?php endif; ?>
                      
                      <a href="?action=delete&id=<?= $report['id'] ?>" class="btn btn-sm btn-action btn-delete" onclick="return confirm('Are you sure you want to delete this report?')">
                        <i class='bx bx-trash'></i> Delete
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="8" class="text-center py-4">
                  <i class='bx bx-inbox' style="font-size: 3rem; color: #cbd5e1;"></i>
                  <p class="mt-2 text-muted">No reports found</p>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  
  <!-- View Report Modal -->
  <div class="modal fade" id="viewReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Report Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row mb-3">
            <div class="col-md-6">
              <strong>Report ID:</strong> <span id="modal-id"></span>
            </div>
            <div class="col-md-6">
              <strong>Date Submitted:</strong> <span id="modal-date"></span>
            </div>
          </div>
          
          <div class="row mb-3">
            <div class="col-md-6">
              <strong>Reporter Type:</strong> <span id="modal-reporter"></span>
            </div>
            <div class="col-md-6">
              <strong>Report Type:</strong> <span id="modal-type"></span>
            </div>
          </div>
          
          <div class="mb-3">
            <strong>Location:</strong> <span id="modal-location"></span>
          </div>
          
          <div class="mb-3">
            <strong>Description:</strong>
            <div class="p-3 bg-light rounded mt-1" id="modal-description"></div>
          </div>
          
          <div class="mb-3">
            <strong>Evidence File:</strong>
            <div id="modal-evidence" class="mt-1"></div>
          </div>
          
          <div class="mb-3">
            <strong>Status:</strong> <span id="modal-status"></span>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // View Report Modal
    const viewReportModal = document.getElementById('viewReportModal');
    viewReportModal.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      
      document.getElementById('modal-id').textContent = button.getAttribute('data-id');
      document.getElementById('modal-reporter').textContent = button.getAttribute('data-reporter');
      document.getElementById('modal-type').textContent = button.getAttribute('data-type');
      document.getElementById('modal-location').textContent = button.getAttribute('data-location');
      document.getElementById('modal-description').textContent = button.getAttribute('data-description');
      document.getElementById('modal-date').textContent = button.getAttribute('data-date');
      
      const evidence = button.getAttribute('data-evidence');
      const evidenceElement = document.getElementById('modal-evidence');
      
      if (evidence) {
        evidenceElement.innerHTML = `<span class="badge bg-light text-dark">${evidence}</span>`;
      } else {
        evidenceElement.innerHTML = '<span class="text-muted">No evidence file</span>';
      }
      
      const status = button.getAttribute('data-status');
      const statusElement = document.getElementById('modal-status');
      
      if (status === 'Pending') {
        statusElement.innerHTML = '<span class="badge badge-pending">Pending</span>';
      } else if (status === 'Approved') {
        statusElement.innerHTML = '<span class="badge badge-approved">Approved</span>';
      } else {
        statusElement.innerHTML = '<span class="badge badge-rejected">Rejected</span>';
      }
    });
  </script>
</body>
</html>