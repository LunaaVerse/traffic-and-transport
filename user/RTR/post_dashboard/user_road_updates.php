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

// Fetch all updates (users can only view, not edit/delete)
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
                <img src="img/ttm.png" alt="Logo">
                <div class="text">
                    Quezon City<br>
                    <small>Traffic Management System</small>
                </div>
            </div>
            
            <div class="sidebar-menu">
                <div class="sidebar-section">Main Navigation</div>
                <a href="../dashboard/dashboard.php" class="sidebar-link ">
                    <i class='bx bx-home'></i>
                    <span class="text">Dashboard</span>
                </a>

                <!-- Traffic Reporting -->
                <div class="sidebar-section mt-4">Traffic Services</div>
                <a href="TM/manual_logs/user_traffic_log.php" class="sidebar-link ">
                    <i class='bx bx-traffic-cone'></i>
                    <span class="text">Report Traffic</span>
                </a>
                
                <a href="TM/daily_monitoring/user_daily_monitoring.php" class="sidebar-link">
                    <i class='bx bx-road'></i>
                    <span class="text">Daily Monitoring</span>
                </a>
                
                <a href="RTR/post_dashboard/user_road_updates.php" class="sidebar-link active">
                    <i class='bx bx-error-circle'></i>
                    <span class="text">Post Dashboard</span>
                </a>
                  <a href="../../RTR/road_condition_map/road_condition_map.php" class="sidebar-link">
                    <i class='bx bx-error-circle'></i>
                    <span class="text">Road Condition</span>
                </a>
                  <a href="../../AVR/report_management/submit_citizen_report.php" class="sidebar-link">
                    <i class='bx bx-error-circle'></i>
                    <span class="text">Report Management</span>
                </a>
                  <a href="../../AVR/evidence_handling/evidence_user.php" class="sidebar-link">
                    <i class='bx bx-error-circle'></i>
                    <span class="text">Evidence Handling</span>
                </a>
                  <a href="../../VRD/routing_analytics/routing_analytics.php" class="sidebar-link">
                    <i class='bx bx-error-circle'></i>
                    <span class="text">Routing Analysis</span>
                </a>
                  <a href="../../PT/real_time_tracking/real_time_tracking.php" class="sidebar-link">
                    <i class='bx bx-error-circle'></i>
                    <span class="text">Real-Time Tracking</span>
                </a>
                  <a href="../../PTS/permit_application_processing/permit_application_processing.php" class="sidebar-link">
                    <i class='bx bx-error-circle'></i>
                    <span class="text">Permit Application</span>
                </a>
                  <a href="../../PTS/payment_settlement_management/payment_settlement_management.php" class="sidebar-link">
                    <i class='bx bx-error-circle'></i>
                    <span class="text">Payment & Settlement</span>
                </a>
           
                
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
          <p>View road status updates across Quezon City</p>
        </div>
        
        <div class="header-actions">
          <div class="user-info">
            <span class="fw-medium"><?= htmlspecialchars($user_profile['full_name']) ?></span>
            <span class="text-muted d-block small"><?= htmlspecialchars($user_profile['role']) ?></span>
          </div>
        </div>
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
        <div class="text-center">
          <strong>${barangay}</strong><br>
          Status: ${status}<br>
          <small>${lat.toFixed(6)}, ${lng.toFixed(6)}</small>
        </div>
      `);
      
      markers.push(marker);
    }
    
    // Directions functionality
    $(document).on('click', '.directions-btn', function() {
      const barangay = $(this).data('barangay');
      const latitude = $(this).data('latitude');
      const longitude = $(this).data('longitude');
      
      // Show the directions panel
      $('#directions-panel').slideDown();
      
      // Set the destination
      $('#end-point').val(`${barangay} (${latitude.toFixed(6)}, ${longitude.toFixed(6)})`);
      $('#end-point').data('latitude', latitude);
      $('#end-point').data('longitude', longitude);
      
      // Scroll to directions panel
      $('html, body').animate({
        scrollTop: $('#directions-panel').offset().top - 20
      }, 500);
    });
    
    // Get directions
    $('#get-directions-btn').click(function() {
      const startPoint = $('#start-point').val().trim();
      const endPointLat = $('#end-point').data('latitude');
      const endPointLng = $('#end-point').data('longitude');
      
      if (!startPoint) {
        Swal.fire({
          icon: 'warning',
          title: 'Missing Information',
          text: 'Please enter a starting point',
          confirmButtonColor: '#0d6efd'
        });
        return;
      }
      
      // Remove existing routing control if any
      if (routingControl) {
        map.removeControl(routingControl);
      }
      
      // Initialize routing control
      routingControl = L.Routing.control({
        waypoints: [
          L.latLng(0, 0), // Placeholder, will be updated by geocoding
          L.latLng(endPointLat, endPointLng)
        ],
        routeWhileDragging: false,
        lineOptions: {
          styles: [{color: '#0d6efd', opacity: 0.7, weight: 5}]
        },
        showAlternatives: false
      }).addTo(map);
      
      // Geocode the start point
      fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(startPoint)}`)
        .then(response => response.json())
        .then(data => {
          if (data && data.length > 0) {
            const startLat = parseFloat(data[0].lat);
            const startLng = parseFloat(data[0].lon);
            
            // Update the waypoints with the geocoded coordinates
            routingControl.setWaypoints([
              L.latLng(startLat, startLng),
              L.latLng(endPointLat, endPointLng)
            ]);
            
            // Listen for routes found event
            routingControl.on('routesfound', function(e) {
              const routes = e.routes;
              const instructions = $('#directions-instructions');
              instructions.empty();
              
              if (routes && routes.length > 0) {
                const route = routes[0];
                
                // Display summary
                instructions.append(`
                  <div class="alert alert-primary">
                    <strong>Route Summary</strong><br>
                    Distance: ${(route.summary.totalDistance / 1000).toFixed(2)} km<br>
                    Estimated Time: ${Math.round(route.summary.totalTime / 60)} minutes
                  </div>
                `);
                
                // Display instructions
                instructions.append('<h6 class="mt-3">Turn-by-Turn Directions:</h6>');
                const ol = $('<ol></ol>');
                
                route.instructions.forEach(function(instruction, index) {
                  ol.append(`<li>${instruction.text} (${(instruction.distance / 1000).toFixed(2)} km)</li>`);
                });
                
                instructions.append(ol);
              }
            });
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Location Not Found',
              text: 'Could not find the starting location. Please try a different address.',
              confirmButtonColor: '#0d6efd'
            });
          }
        })
        .catch(error => {
          console.error('Geocoding error:', error);
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while processing your request. Please try again.',
            confirmButtonColor: '#0d6efd'
          });
        });
    });
    
    // SweetAlert for successful directions request
    $(document).on('click', '#get-directions-btn', function() {
      Swal.fire({
        icon: 'success',
        title: 'Directions Requested',
        text: 'Your route is being calculated. Please wait...',
        showConfirmButton: false,
        timer: 2000
      });
    });
  </script>
</body>
</html>