<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get database connection for TTM (user data)
try {
    $conn = getDBConnection('ttm'); // Use the function from database.php
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get user info using PDO
$user_id = $_SESSION['user_id'];
$user_query = $conn->prepare("SELECT * FROM users WHERE user_id = :user_id");
$user_query->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$user_query->execute();
$user_profile = $user_query->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Database connection for AVR using the function from database.php
    try {
        $avr_conn = getDBConnection('avr');
    } catch (Exception $e) {
        die("AVR database connection failed: " . $e->getMessage());
    }
    
    // Process form data using PDO
    $reporter_type = $_POST['reporter_type'];
    $report_type = $_POST['report_type'];
    $location = $_POST['location'];
    $description = $_POST['description'];
    $status = 'Pending';
    
    // Handle file upload
    $evidence_filename = null;
    if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../AVR/uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['evidence']['name'], PATHINFO_EXTENSION);
        $evidence_filename = time() . '_' . uniqid() . '.' . $file_extension;
        $upload_path = $upload_dir . $evidence_filename;
        
        // Move uploaded file
        if (move_uploaded_file($_FILES['evidence']['tmp_name'], $upload_path)) {
            // File uploaded successfully
        } else {
            $evidence_filename = null;
        }
    }
    
    // Insert into database using PDO
    $stmt = $avr_conn->prepare("INSERT INTO reports (reporter_type, report_type, location, description, status, evidence) VALUES (?, ?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$reporter_type, $report_type, $location, $description, $status, $evidence_filename])) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Report Submitted!',
                text: 'Your report has been successfully submitted and is now pending review.',
                confirmButtonText: 'OK'
            }).then(() => {
                window.location.href = 'submit_report.php';
            });
        </script>";
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Submission Failed',
                text: 'There was an error submitting your report. Please try again.',
                confirmButtonText: 'OK'
            });
        </script>";
    }
    
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Submit Report - Quezon City Traffic Management</title>
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
    
    /* Form Styling */
    .form-container {
      background: white;
      border-radius: var(--card-radius);
      padding: 2rem;
      box-shadow: var(--card-shadow);
      border: 1px solid rgba(13, 110, 253, 0.1);
      margin-bottom: 2rem;
    }
    
    .form-header {
      text-align: center;
      margin-bottom: 2rem;
    }
    
    .form-header h2 {
      color: var(--dark);
      margin-bottom: 0.5rem;
      font-weight: 700;
    }
    
    .form-header p {
      color: var(--secondary);
    }
    
    .form-label {
      font-weight: 600;
      color: var(--dark);
      margin-bottom: 0.5rem;
    }
    
    .form-control, .form-select {
      border-radius: 8px;
      padding: 0.75rem 1rem;
      border: 1px solid var(--gray-300);
      transition: var(--transition);
    }
    
    .form-control:focus, .form-select:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    
    .btn-primary {
      background: var(--primary-gradient);
      border: none;
      padding: 0.75rem 2rem;
      border-radius: 8px;
      font-weight: 600;
      transition: var(--transition);
    }
    
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
    }
    
    /* File Upload */
    .file-upload-container {
      position: relative;
    }
    
    .file-upload-label {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      border: 2px dashed var(--gray-300);
      border-radius: 8px;
      padding: 2rem;
      text-align: center;
      cursor: pointer;
      transition: var(--transition);
    }
    
    .file-upload-label:hover {
      border-color: var(--primary);
    }
    
    .file-upload-icon {
      font-size: 2.5rem;
      color: var(--primary);
      margin-bottom: 1rem;
    }
    
    .file-upload-text {
      color: var(--secondary);
    }
    
    .file-upload-text strong {
      color: var(--primary);
    }
    
    .file-input {
      position: absolute;
      width: 0;
      height: 0;
      opacity: 0;
    }
    
    .file-preview {
      margin-top: 1rem;
      display: none;
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

      <div class="sidebar-section mt-4">Traffic Modules</div>
      
      <!-- Accident & Violation Report with Dropdown -->
      <div class="dropdown-toggle sidebar-link active" data-bs-toggle="collapse" data-bs-target="#avrDropdown" aria-expanded="true">
        <i class='bx bx-error-circle'></i>
        <span class="text">Accident & Violation Report</span>
      </div>
      <div class="sidebar-dropdown collapse show" id="avrDropdown">
        <a href="submit_report.php" class="sidebar-dropdown-link active">
          <i class='bx bx-plus-circle'></i> Submit Report
        </a>
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
        <h1>Accident & Violation Reporting</h1>
        <p>Submit reports for traffic incidents and violations</p>
      </div>
      <div class="header-actions">
        <div class="user-info">
          <span class="fw-medium"><?= htmlspecialchars($user_profile['full_name'] ?? 'User') ?></span>
          <span class="text-muted d-block small"><?= htmlspecialchars($user_profile['role'] ?? 'User') ?></span>
        </div>
      </div>
    </div>
    
    <!-- Form Container -->
    <div class="form-container">
      <div class="form-header">
        <h2>📌 Submit Incident Report</h2>
        <p>For Employees & Citizens</p>
      </div>

      <form id="reportForm" method="POST" enctype="multipart/form-data">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Reporter Type</label>
            <select name="reporter_type" class="form-select" required>
              <option value="">-- Select --</option>
              <option value="Citizen">Citizen</option>
              <option value="Employee">Employee</option>
            </select>
          </div>
          
          <div class="col-md-6 mb-3">
            <label class="form-label">Report Type</label>
            <select name="report_type" class="form-select" required>
              <option value="">-- Select --</option>
              <option value="Accident">Accident</option>
              <option value="Violation">Violation</option>
            </select>
          </div>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Location</label>
          <input type="text" name="location" class="form-control" placeholder="Enter exact location" required>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="4" placeholder="Provide detailed description of the incident" required></textarea>
        </div>
        
        <div class="mb-4">
          <label class="form-label">Upload Evidence (Optional)</label>
          <div class="file-upload-container">
            <label class="file-upload-label" id="fileUploadLabel">
              <i class='bx bx-cloud-upload file-upload-icon'></i>
              <span class="file-upload-text">Drag & drop or <strong>click to upload</strong></span>
              <span class="text-muted small mt-2">Supports JPG, PNG, PDF (Max 5MB)</span>
            </label>
            <input type="file" name="evidence" id="fileInput" class="file-input" accept="image/*,.pdf">
          </div>
          <div class="file-preview mt-3" id="filePreview"></div>
        </div>
        
        <div class="text-center">
          <button type="button" class="btn btn-primary btn-lg" onclick="validateAndSubmit()">
            <i class='bx bx-paper-plane me-2'></i> Submit Report
          </button>
        </div>
      </form>
    </div>
  </div>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // File upload preview
    const fileInput = document.getElementById('fileInput');
    const filePreview = document.getElementById('filePreview');
    const fileUploadLabel = document.getElementById('fileUploadLabel');
    
    fileInput.addEventListener('change', function() {
      if (this.files && this.files[0]) {
        const file = this.files[0];
        const fileType = file.type;
        
        filePreview.style.display = 'block';
        
        if (fileType.startsWith('image/')) {
          const reader = new FileReader();
          
          reader.onload = function(e) {
            filePreview.innerHTML = `
              <div class="alert alert-info d-flex align-items-center">
                <img src="${e.target.result}" class="me-3" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                <div>
                  <strong>${file.name}</strong>
                  <div class="text-muted small">${(file.size / 1024).toFixed(2)} KB</div>
                </div>
                <button type="button" class="btn-close ms-auto" onclick="clearFileInput()"></button>
              </div>
            `;
          }
          
          reader.readAsDataURL(file);
        } else {
          filePreview.innerHTML = `
            <div class="alert alert-info d-flex align-items-center">
              <i class='bx bx-file me-3' style="font-size: 2rem;"></i>
              <div>
                <strong>${file.name}</strong>
                <div class="text-muted small">${(file.size / 1024).toFixed(2)} KB</div>
              </div>
              <button type="button" class="btn-close ms-auto" onclick="clearFileInput()"></button>
            </div>
          `;
        }
        
        fileUploadLabel.style.borderColor = 'var(--primary)';
      }
    });
    
    function clearFileInput() {
      fileInput.value = '';
      filePreview.style.display = 'none';
      filePreview.innerHTML = '';
      fileUploadLabel.style.borderColor = 'var(--gray-300)';
    }
    
    // Form validation and submission with SweetAlert
    function validateAndSubmit() {
      const form = document.getElementById('reportForm');
      const formData = new FormData(form);
      
      // Basic validation
      if (!formData.get('reporter_type') || !formData.get('report_type') || 
          !formData.get('location') || !formData.get('description')) {
        Swal.fire({
          icon: 'warning',
          title: 'Missing Information',
          text: 'Please fill in all required fields.',
          confirmButtonText: 'OK'
        });
        return;
      }
      
      // Show confirmation dialog
      Swal.fire({
        title: 'Submit Report?',
        text: 'Are you sure you want to submit this report?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, submit it!',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          // Submit the form
          form.submit();
        }
      });
    }
  </script>
</body>
</html>