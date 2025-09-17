<?php
session_start();
require_once '../config/database.php';

// Authentication & Authorization
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get database connection
try {
    $pdo_avr = getDBConnection('avr');
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

// Check if user has permission to access this page
if ($user_profile['role'] !== 'citizen' && $user_profile['role'] !== 'employee') {
    header("Location: ../unauthorized.php");
    exit();
}

// Upload functionality for users
if (isset($_POST['upload'])) {
    $report_id = $_POST['report_id'];
    if (!empty($_FILES['evidence_file']['name'])) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) { 
            mkdir($targetDir, 0777, true); 
        }
        
        $fileName = time() . "_" . basename($_FILES['evidence_file']['name']);
        $targetFilePath = $targetDir . $fileName;
        
        if (move_uploaded_file($_FILES['evidence_file']['tmp_name'], $targetFilePath)) {
            $fileType = pathinfo($fileName, PATHINFO_EXTENSION);
            $uploaded_by = ($user_profile['role'] === 'employee') ? 'Employee' : 'Citizen';
            
            $stmt = $pdo_avr->prepare("INSERT INTO evidence (report_id, file_path, file_type, uploaded_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$report_id, $targetFilePath, $fileType, $uploaded_by]);
            
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'Evidence uploaded successfully!',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Upload Failed',
                    text: 'There was an error uploading your file.',
                });
            </script>";
        }
    }
}

// Get user's reports (only show reports they submitted)
$user_id = $_SESSION['user_id'];
$reports_query = $pdo_avr->prepare("
    SELECT r.* 
    FROM reports r 
    WHERE (r.reporter_type = 'Citizen' AND :user_role = 'citizen')
       OR (r.reporter_type = 'Employee' AND :user_role = 'employee')
    ORDER BY r.created_at DESC
");
$reports_query->bindParam(':user_role', $user_profile['role']);
$reports_query->execute();
$reports = $reports_query->fetchAll();

// Set active tab
$active_tab = 'accident_violation';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evidence Handling - User Access</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .evidence-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .evidence-table {
            width: 100%;
            border-collapse: collapse;
        }
        .evidence-table th, .evidence-table td {
            padding: 12px;
            border: 1px solid #dee2e6;
        }
        .evidence-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .btn-action {
            padding: 5px 10px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-view {
            background-color: #0d6efd;
            color: white;
        }
        .btn-upload {
            background-color: #198754;
            color: white;
        }
        .hidden-row {
            display: none;
        }
        .evidence-section {
            margin-top: 15px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
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
                <a href="../dashboard/dashboard.php" class="sidebar-link">
                    <i class='bx bx-home'></i>
                    <span class="text">Dashboard</span>
                </a>

                <!-- Accident & Violation Report with Dropdown -->
                <div class="dropdown-toggle sidebar-link active" data-bs-toggle="collapse" data-bs-target="#avrDropdown" aria-expanded="true">
                    <i class='bx bx-error-circle'></i>
                    <span class="text">Accident & Violation Report</span>
                </div>
                <div class="sidebar-dropdown collapse show" id="avrDropdown">
                    <a href="../AVR/report_management/report_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-edit'></i> Report Management
                    </a>
                    <a href="../AVR/evidence_handling/evidence_user.php" class="sidebar-dropdown-link active">
                        <i class='bx bx-file'></i> Evidence Handling
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
                    <h1>Evidence Handling</h1>
                    <p>Manage evidence for your accident and violation reports</p>
                </div>
                
                <div class="header-actions">
                    <div class="user-info">
                        <span class="fw-medium"><?= htmlspecialchars($user_profile['full_name']) ?></span>
                        <span class="text-muted d-block small"><?= htmlspecialchars(ucfirst($user_profile['role'])) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Evidence Content -->
            <div class="evidence-container">
                <h3>Your Reports</h3>
                <p class="text-muted">Click on a report to manage its evidence</p>
                
                <table class="evidence-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reports)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <p class="text-muted">You haven't submitted any reports yet.</p>
                                    <a href="../AVR/report_management/report_management.php" class="btn btn-primary">Submit a Report</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td><?= htmlspecialchars($report['id']) ?></td>
                                    <td><?= htmlspecialchars($report['report_type']) ?></td>
                                    <td><?= htmlspecialchars($report['location']) ?></td>
                                    <td>
                                        <span class="badge 
                                            <?= $report['status'] == 'Resolved' ? 'bg-success' : '' ?>
                                            <?= $report['status'] == 'Under Review' ? 'bg-warning' : '' ?>
                                            <?= $report['status'] == 'Pending' ? 'bg-secondary' : '' ?>">
                                            <?= htmlspecialchars($report['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M j, Y g:i A', strtotime($report['created_at'])) ?></td>
                                    <td>
                                        <button class="btn-action btn-view" onclick="toggleEvidence(<?= $report['id'] ?>)">
                                            Manage Evidence
                                        </button>
                                    </td>
                                </tr>
                                <tr id="evidence-<?= $report['id'] ?>" class="hidden-row">
                                    <td colspan="6">
                                        <div class="evidence-section">
                                            <h5>Add Evidence for Report #<?= $report['id'] ?></h5>
                                            <form method="POST" enctype="multipart/form-data" class="mb-4">
                                                <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <input type="file" name="evidence_file" class="form-control" required accept="image/*,video/*,.pdf,.doc,.docx">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <button type="submit" name="upload" class="btn-action btn-upload">
                                                            Upload Evidence
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                            
                                            <h5>Existing Evidence</h5>
                                            <?php
                                            $evidence_query = $pdo_avr->prepare("SELECT * FROM evidence WHERE report_id = ? ORDER BY uploaded_at DESC");
                                            $evidence_query->execute([$report['id']]);
                                            $evidence = $evidence_query->fetchAll();
                                            ?>
                                            
                                            <?php if (empty($evidence)): ?>
                                                <p class="text-muted">No evidence has been uploaded for this report yet.</p>
                                            <?php else: ?>
                                                <div class="table-responsive">
                                                    <table class="table table-bordered">
                                                        <thead>
                                                            <tr>
                                                                <th>File</th>
                                                                <th>Type</th>
                                                                <th>Uploaded By</th>
                                                                <th>Date</th>
                                                                <th>Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($evidence as $item): ?>
                                                                <tr>
                                                                    <td>
                                                                        <a href="<?= htmlspecialchars($item['file_path']) ?>" target="_blank" class="btn btn-sm btn-primary">
                                                                            View File
                                                                        </a>
                                                                    </td>
                                                                    <td><?= strtoupper(htmlspecialchars($item['file_type'])) ?></td>
                                                                    <td><?= htmlspecialchars($item['uploaded_by']) ?></td>
                                                                    <td><?= date('M j, Y g:i A', strtotime($item['uploaded_at'])) ?></td>
                                                                    <td>
                                                                        <?php if ($item['uploaded_by'] === 'Citizen' && $user_profile['role'] === 'citizen'): ?>
                                                                            <form method="POST" onsubmit="return confirmDeleteEvidence(event, <?= $item['id'] ?>)">
                                                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                                                <button type="submit" name="delete" class="btn btn-sm btn-danger">
                                                                                    Delete
                                                                                </button>
                                                                            </form>
                                                                        <?php elseif ($item['uploaded_by'] === 'Employee' && $user_profile['role'] === 'employee'): ?>
                                                                            <form method="POST" onsubmit="return confirmDeleteEvidence(event, <?= $item['id'] ?>)">
                                                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                                                <button type="submit" name="delete" class="btn btn-sm btn-danger">
                                                                                    Delete
                                                                                </button>
                                                                            </form>
                                                                        <?php else: ?>
                                                                            <span class="text-muted">Cannot delete</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleEvidence(reportId) {
            const evidenceRow = document.getElementById(`evidence-${reportId}`);
            evidenceRow.classList.toggle('hidden-row');
        }
        
        function confirmDeleteEvidence(event, evidenceId) {
            event.preventDefault();
            const form = event.target;
            
            Swal.fire({
                title: 'Are you sure?',
                text: "This evidence will be permanently deleted!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create a form and submit it programmatically
                    const deleteForm = document.createElement('form');
                    deleteForm.method = 'POST';
                    deleteForm.action = window.location.href;
                    
                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'id';
                    idInput.value = evidenceId;
                    deleteForm.appendChild(idInput);
                    
                    const deleteInput = document.createElement('input');
                    deleteInput.type = 'hidden';
                    deleteInput.name = 'delete';
                    deleteInput.value = '1';
                    deleteForm.appendChild(deleteInput);
                    
                    document.body.appendChild(deleteForm);
                    deleteForm.submit();
                }
            });
            
            return false;
        }
        
        // Handle delete functionality if coming from form submission
        <?php if (isset($_POST['delete'])): ?>
            <?php
            $id = $_POST['id'];
            try {
                // First get the file path
                $stmt = $pdo_avr->prepare("SELECT file_path, uploaded_by FROM evidence WHERE id = ?");
                $stmt->execute([$id]);
                $evidence = $stmt->fetch();
                
                if ($evidence) {
                    // Check if user has permission to delete this evidence
                    $canDelete = false;
                    if (($evidence['uploaded_by'] === 'Citizen' && $user_profile['role'] === 'citizen') || 
                        ($evidence['uploaded_by'] === 'Employee' && $user_profile['role'] === 'employee')) {
                        $canDelete = true;
                    }
                    
                    if ($canDelete) {
                        // Delete the file from server
                        if (file_exists($evidence['file_path'])) {
                            unlink($evidence['file_path']);
                        }
                        
                        // Delete the record from database
                        $stmt = $pdo_avr->prepare("DELETE FROM evidence WHERE id = ?");
                        $stmt->execute([$id]);
                        
                        echo "Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: 'Evidence has been deleted.',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });";
                    } else {
                        echo "Swal.fire({
                            icon: 'error',
                            title: 'Permission Denied',
                            text: 'You cannot delete this evidence.',
                        });";
                    }
                }
            } catch (Exception $e) {
                echo "Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'There was an error deleting the evidence.',
                });";
            }
            ?>
        <?php endif; ?>
    </script>
</body>
</html>