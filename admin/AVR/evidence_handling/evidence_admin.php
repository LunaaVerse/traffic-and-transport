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

// Set active tab
$active_tab = 'accident_violation';

// Fetch user profile
$user_profile = getUserProfile($pdo_ttm, $_SESSION['user_id']);

// Upload Evidence
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
            
            try {
                $stmt = $pdo_avr->prepare("INSERT INTO evidence (report_id, file_path, file_type, uploaded_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$report_id, $targetFilePath, $fileType, $user_profile['role']]);
                
                $_SESSION['alert'] = [
                    'type' => 'success',
                    'title' => 'Success',
                    'message' => 'Evidence uploaded successfully!'
                ];
            } catch (PDOException $e) {
                $_SESSION['alert'] = [
                    'type' => 'error',
                    'title' => 'Error',
                    'message' => 'Failed to upload evidence: ' . $e->getMessage()
                ];
            }
        } else {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Error',
                'message' => 'Failed to move uploaded file.'
            ];
        }
        
        header("Location: evidence_admin.php");
        exit();
    }
}

// Delete Evidence
if (isset($_POST['delete'])) {
    $id = $_POST['id'];
    
    try {
        // Get file path first
        $stmt = $pdo_avr->prepare("SELECT file_path FROM evidence WHERE id = ?");
        $stmt->execute([$id]);
        $file_path = $stmt->fetchColumn();
        
        // Delete file from server
        if ($file_path && file_exists($file_path)) { 
            unlink($file_path); 
        }
        
        // Delete record from database
        $stmt = $pdo_avr->prepare("DELETE FROM evidence WHERE id = ?");
        $stmt->execute([$id]);
        
        $_SESSION['alert'] = [
            'type' => 'success',
            'title' => 'Deleted',
            'message' => 'Evidence removed successfully!'
        ];
    } catch (PDOException $e) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'title' => 'Error',
            'message' => 'Failed to delete evidence: ' . $e->getMessage()
        ];
    }
    
    header("Location: evidence_admin.php");
    exit();
}

// Fetch reports and their evidence
try {
    $reports = $pdo_avr->query("SELECT * FROM reports ORDER BY created_at DESC")->fetchAll();
    
    // Pre-fetch evidence for all reports
    $evidenceByReport = [];
    foreach ($reports as $report) {
        $stmt = $pdo_avr->prepare("SELECT * FROM evidence WHERE report_id = ? ORDER BY uploaded_at DESC");
        $stmt->execute([$report['id']]);
        $evidenceByReport[$report['id']] = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Evidence Handling</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/style.css">
    <style>
        .evidence-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .evidence-table th, .evidence-table td { border: 1px solid #dee2e6; padding: 12px; text-align: left; }
        .evidence-table th { background-color: #f8f9fa; font-weight: 600; }
        .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
        .modal-content { border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
        .modal-header { background: var(--primary-gradient); color: white; border-radius: 12px 12px 0 0; }
        .badge-pending { background-color: #fff3cd; color: #856404; }
        .badge-under-review { background-color: #cce5ff; color: #004085; }
        .badge-resolved { background-color: #d4edda; color: #155724; }
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
                    <a href="../report_management/report_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-clipboard'></i> Report Management
                    </a>
                    <a href="../violation_categorization/violation_categorization.php" class="sidebar-dropdown-link">
                        <i class='bx bx-category'></i> Violation Categorization
                    </a>
                    <a href="../evidence_handling/evidence_admin.php" class="sidebar-dropdown-link active">
                        <i class='bx bx-file'></i> Evidence Handling
                    </a>
                    <a href="../violation_records/admin_violation_records.php" class="sidebar-dropdown-link">
                        <i class='bx bx-list-check'></i> Violation Record 
                    </a>
                    <a href="../escalation_assignment/admin_escalation_assignment.php" class="sidebar-dropdown-link">
                        <i class='bx bx-transfer-alt'></i> Escalation & Assignment  
                    </a>
                </div>
                
                <div class="sidebar-section mt-4">User</div>
                <a href="../profile.php" class="sidebar-link">
                    <i class='bx bx-user'></i>
                    <span class="text">Profile</span>
                </a>
                <a href="../../logout.php" class="sidebar-link">
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
                    <p>Manage evidence files for accident and violation reports</p>
                </div>
                
                <div class="header-actions">
                    <div class="user-info">
                        <span class="fw-medium"><?= htmlspecialchars($user_profile['full_name']) ?></span>
                        <span class="text-muted d-block small"><?= htmlspecialchars($user_profile['role']) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Reports Table -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Reports List</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($reports)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">No reports found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($reports as $report): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($report['id']) ?></td>
                                            <td><?= htmlspecialchars($report['report_type']) ?></td>
                                            <td><?= htmlspecialchars($report['location']) ?></td>
                                            <td>
                                                <?php 
                                                $statusClass = 'badge-pending';
                                                if ($report['status'] === 'Under Review') $statusClass = 'badge-under-review';
                                                if ($report['status'] === 'Resolved') $statusClass = 'badge-resolved';
                                                ?>
                                                <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($report['status']) ?></span>
                                            </td>
                                            <td><?= date('M j, Y g:i A', strtotime($report['created_at'])) ?></td>
                                            <td>
                                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#evidenceModal" 
                                                        data-report-id="<?= $report['id'] ?>" 
                                                        data-report-type="<?= htmlspecialchars($report['report_type']) ?>"
                                                        data-report-location="<?= htmlspecialchars($report['location']) ?>">
                                                    Manage Evidence
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Evidence Modal -->
    <div class="modal fade" id="evidenceModal" tabindex="-1" aria-labelledby="evidenceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="evidenceModalLabel">Manage Evidence</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6 class="mb-3">Report Details: <span id="modalReportType" class="fw-normal"></span> at <span id="modalReportLocation" class="fw-normal"></span></h6>
                    
                    <!-- Upload Form -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Upload New Evidence</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                                <input type="hidden" name="report_id" id="modalReportId">
                                <div class="mb-3">
                                    <label for="evidenceFile" class="form-label">Evidence File</label>
                                    <input class="form-control" type="file" name="evidence_file" id="evidenceFile" required>
                                </div>
                                <button type="submit" name="upload" class="btn btn-primary">Upload</button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Evidence List -->
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Evidence Files</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>File</th>
                                            <th>Type</th>
                                            <th>Uploaded By</th>
                                            <th>Uploaded At</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="evidenceList">
                                        <!-- Evidence will be loaded here via JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Show SweetAlert notifications
        <?php if (isset($_SESSION['alert'])): ?>
            Swal.fire({
                icon: '<?= $_SESSION['alert']['type'] ?>',
                title: '<?= $_SESSION['alert']['title'] ?>',
                text: '<?= $_SESSION['alert']['message'] ?>',
                timer: 3000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['alert']); ?>
        <?php endif; ?>

        // Handle evidence modal
        const evidenceModal = document.getElementById('evidenceModal');
        evidenceModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const reportId = button.getAttribute('data-report-id');
            const reportType = button.getAttribute('data-report-type');
            const reportLocation = button.getAttribute('data-report-location');
            
            const modalTitle = evidenceModal.querySelector('.modal-title');
            const modalReportId = evidenceModal.querySelector('#modalReportId');
            const modalReportType = evidenceModal.querySelector('#modalReportType');
            const modalReportLocation = evidenceModal.querySelector('#modalReportLocation');
            
            modalTitle.textContent = `Manage Evidence - Report #${reportId}`;
            modalReportId.value = reportId;
            modalReportType.textContent = reportType;
            modalReportLocation.textContent = reportLocation;
            
            // Load evidence for this report
            loadEvidence(reportId);
        });
        
        // Function to load evidence via AJAX
        function loadEvidence(reportId) {
            fetch(`load_evidence.php?report_id=${reportId}`)
                .then(response => response.json())
                .then(data => {
                    const evidenceList = document.getElementById('evidenceList');
                    evidenceList.innerHTML = '';
                    
                    if (data.length === 0) {
                        evidenceList.innerHTML = '<tr><td colspan="5" class="text-center py-3">No evidence files found</td></tr>';
                        return;
                    }
                    
                    data.forEach(evidence => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td><a href="${evidence.file_path}" target="_blank">View File</a></td>
                            <td>${evidence.file_type}</td>
                            <td>${evidence.uploaded_by}</td>
                            <td>${new Date(evidence.uploaded_at).toLocaleString()}</td>
                            <td>
                                <form method="POST" onsubmit="return confirmDelete(this);">
                                    <input type="hidden" name="id" value="${evidence.id}">
                                    <button type="submit" name="delete" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        `;
                        evidenceList.appendChild(row);
                    });
                })
                .catch(error => {
                    console.error('Error loading evidence:', error);
                    document.getElementById('evidenceList').innerHTML = '<tr><td colspan="5" class="text-center py-3 text-danger">Error loading evidence</td></tr>';
                });
        }
        
        // Confirm delete function
        function confirmDelete(form) {
            event.preventDefault();
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
                    form.submit();
                }
            });
            return false;
        }
    </script>
</body>
</html>