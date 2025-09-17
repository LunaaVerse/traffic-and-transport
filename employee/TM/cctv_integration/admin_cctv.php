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
  <link href="css/style.css" rel="stylesheet">

</head>
<body>
  <?php
  session_start();
  // Simulate user session data for demonstration
  if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['full_name'] = 'System Administrator';
    $_SESSION['role'] = 'Administrator';
  }
  ?>

  <div class="dashboard-container">
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
        <a href="../TM/dashboard/dashboard.php" class="sidebar-link">
          <i class='bx bx-traffic-cone'></i>
          <span class="text">Traffic Monitoring</span>
        </a>
        
        <a href="../RTR/admin_post/apd.php" class="sidebar-link">
          <i class='bx bx-road'></i>
          <span class="text">Real-time Road Update</span>
        </a>
        
        <a href="../AVR/report_review/rr.php" class="sidebar-link">
          <i class='bx bx-error-circle'></i>
          <span class="text">Accident & Violation Report</span>
        </a>
        
        <a href="../VRD/route_suggestion/rs.php" class="sidebar-link">
          <i class='bx bx-map-alt'></i>
          <span class="text">Vehicle Routing & Diversion</span>
        </a>
        
        <a href="../TSC/simulated_signal/ssl.php" class="sidebar-link">
          <i class='bx bx-traffic-light'></i>
          <span class="text">Traffic Signal Control</span>
        </a>
        
        <a href="../PTS/vehicle_timetable/vt.php" class="sidebar-link">
          <i class='bx bx-bus'></i>
          <span class="text">Public Transport</span>
        </a>
        
        <a href="../PATS/permit_request/pr.php" class="sidebar-link">
          <i class='bx bx-receipt'></i>
          <span class="text">Permit & Ticketing System</span>
        </a>
        
        <a href="admin_cctv.php" class="sidebar-link active">
          <i class='bx bx-cctv'></i>
          <span class="text">CCTV Management</span>
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
          <h1>CCTV Integration & Feed Control</h1>
          <p>Manage and monitor CCTV cameras across Quezon City</p>
        </div>
        
        <div class="header-actions">
          <div class="user-info">
            <span class="fw-medium"><?php echo $_SESSION['full_name']; ?></span>
            <span class="text-muted d-block small"><?php echo $_SESSION['role']; ?></span>
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
            <h3 id="total-cameras">0</h3>
            <p>Total Cameras</p>
            <div class="stat-trend trend-up">
              <i class='bx bx-up-arrow-alt'></i>
              <span>Loading...</span>
            </div>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon" style="background: rgba(25, 135, 84, 0.1); color: #198754;">
            <i class='bx bx-check-circle'></i>
          </div>
          <div class="stat-content">
            <h3 id="active-cameras">0</h3>
            <p>Active Cameras</p>
            <div class="stat-trend trend-up">
              <i class='bx bx-up-arrow-alt'></i>
              <span>Loading...</span>
            </div>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
            <i class='bx bx-error-circle'></i>
          </div>
          <div class="stat-content">
            <h3 id="inactive-cameras">0</h3>
            <p>Inactive Cameras</p>
            <div class="stat-trend trend-down">
              <i class='bx bx-down-arrow-alt'></i>
              <span>Loading...</span>
            </div>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
            <i class='bx bx-alarm'></i>
          </div>
          <div class="stat-content">
            <h3 id="alerts-today">0</h3>
            <p>Alerts Today</p>
            <div class="stat-trend trend-up">
              <i class='bx bx-up-arrow-alt'></i>
              <span>Loading...</span>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Add CCTV Form -->
      <div class="cctv-form">
        <h4 class="mb-4"><i class='bx bx-plus-circle'></i> Add New CCTV Camera</h4>
        <form method="POST" action="save_cctv.php" id="cctvForm">
          <div class="row">
            <div class="col-md-6">
              <label class="form-label">Location Name:</label>
              <input type="text" class="form-control" name="location" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Stream URL:</label>
              <input type="text" class="form-control" name="stream_url" required>
            </div>
          </div>
          
          <div class="row mt-3">
            <div class="col-md-4">
              <label class="form-label">Latitude:</label>
              <input type="text" class="form-control" name="latitude" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Longitude:</label>
              <input type="text" class="form-control" name="longitude" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Status:</label>
              <select class="form-select" name="status">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Maintenance">Maintenance</option>
              </select>
            </div>
          </div>
          
          <div class="row mt-3">
            <div class="col-md-12">
              <label class="form-label">Description (Optional):</label>
              <textarea class="form-control" name="description" rows="2"></textarea>
            </div>
          </div>
          
          <button type="submit" class="btn btn-primary mt-3" name="add_cctv">
            <i class='bx bx-plus'></i> Add CCTV Camera
          </button>
        </form>
      </div>
      
      <!-- CCTV List -->
      <div class="cctv-table">
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>ID</th>
                <th>Location</th>
                <th>Coordinates</th>
                <th>Status</th>
                <th>Stream</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="cctv-table-body">
              <!-- Data will be populated by JavaScript -->
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- Map View -->
      <div class="map-card">
        <h4 class="mb-4"><i class='bx bx-map'></i> CCTV Map View</h4>
        <div id="map"></div>
      </div>
    </div>
  </div>

  <!-- Modal for Alerts -->
  <div class="modal fade" id="cctvAlertModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-danger">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title"><i class='bx bx-alarm'></i> CCTV Alert</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="alertMessage">
          <!-- message injected by JS -->
        </div>
        <div class="modal-footer">
          <button class="btn btn-danger" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Alert Sound -->
  <audio id="alertSound" src="https://actions.google.com/sounds/v1/alarms/alarm_clock.ogg"></audio>

  <!-- JavaScript Libraries -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

  <script>
    // Initialize map
    var map = L.map('map').setView([14.6760, 121.0437], 12); // QC center
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '© OpenStreetMap'
    }).addTo(map);

    var markers = [];
    var lastInactive = [];
    var cctvData = [];

    // Load CCTV data
    function loadCCTV() {
      // In a real application, this would fetch from the server
      // For demo purposes, we'll use mock data
      const mockData = {
        feeds: [
          {
            cctv_id: 1,
            location: "Quezon City Hall",
            latitude: 14.6500,
            longitude: 121.0300,
            stream_url: "https://example.com/stream1",
            status: "Active",
            description: "Main entrance camera"
          },
          {
            cctv_id: 2,
            location: "East Avenue",
            latitude: 14.6510,
            longitude: 121.0400,
            stream_url: "https://example.com/stream2",
            status: "Active",
            description: "Traffic intersection"
          },
          {
            cctv_id: 3,
            location: "Cubao Intersection",
            latitude: 14.6180,
            longitude: 121.0520,
            stream_url: "https://example.com/stream3",
            status: "Inactive",
            description: "Under maintenance"
          },
          {
            cctv_id: 4,
            location: "Trinoma Mall",
            latitude: 14.6550,
            longitude: 121.0330,
            stream_url: "https://example.com/stream4",
            status: "Active",
            description: "North EDSA monitoring"
          }
        ]
      };
      
      // Process the data
      processCCTVData(mockData);
    }

    // Process CCTV data and update UI
    function processCCTVData(data) {
      cctvData = data.feeds;
      
      // Update stats
      updateStats(data.feeds);
      
      // Update table
      updateTable(data.feeds);
      
      // Update map
      updateMap(data.feeds);
    }

    // Update statistics
    function updateStats(feeds) {
      const total = feeds.length;
      const active = feeds.filter(cctv => cctv.status === "Active").length;
      const inactive = feeds.filter(cctv => cctv.status === "Inactive").length;
      
      document.getElementById('total-cameras').textContent = total;
      document.getElementById('active-cameras').textContent = active;
      document.getElementById('inactive-cameras').textContent = inactive;
      
      // Update trend indicators
      document.querySelectorAll('.stat-trend span').forEach(el => {
        el.textContent = "Updated just now";
      });
    }

    // Update table with CCTV data
    function updateTable(feeds) {
      const tableBody = document.getElementById('cctv-table-body');
      tableBody.innerHTML = '';
      
      feeds.forEach(cctv => {
        const statusClass = cctv.status === "Active" ? "status-active" : "status-inactive";
        
        const row = `
          <tr>
            <td>${cctv.cctv_id}</td>
            <td>${cctv.location}</td>
            <td>${cctv.latitude}, ${cctv.longitude}</td>
            <td><span class="status-badge ${statusClass}">${cctv.status}</span></td>
            <td>
              ${cctv.status === "Active" ? 
                `<button class="btn btn-sm btn-primary view-stream" data-url="${cctv.stream_url}">
                  <i class='bx bx-play-circle'></i> View Stream
                </button>` : 
                '<span class="text-muted">Not available</span>'
              }
            </td>
            <td>
              <button class="btn btn-sm btn-outline-primary action-btn edit-cctv" data-id="${cctv.cctv_id}">
                <i class='bx bx-edit'></i>
              </button>
              <button class="btn btn-sm btn-outline-danger action-btn delete-cctv" data-id="${cctv.cctv_id}">
                <i class='bx bx-trash'></i>
              </button>
            </td>
          </tr>
        `;
        
        tableBody.innerHTML += row;
      });
      
      // Add event listeners
      attachTableEventListeners();
    }

    // Update map with CCTV markers
    function updateMap(feeds) {
      // Clear old markers
      markers.forEach(m => map.removeLayer(m));
      markers = [];
      
      const newInactive = [];
      
      // Add markers
      feeds.forEach(cctv => {
        if (cctv.latitude && cctv.longitude) {
          const markerColor = cctv.status === "Active" ? "green" : "red";
          const markerIcon = L.divIcon({
            className: 'custom-marker',
            html: `<div style="background-color: ${markerColor}; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.5);"></div>`,
            iconSize: [20, 20],
            iconAnchor: [10, 10]
          });
          
          const marker = L.marker([cctv.latitude, cctv.longitude], {icon: markerIcon}).addTo(map);
          
          let popupContent = `<b>${cctv.location}</b><br>Status: ${cctv.status}`;
          if (cctv.description) {
            popupContent += `<br>Description: ${cctv.description}`;
          }
          if (cctv.status === "Active") {
            popupContent += `<br><button class="btn btn-sm btn-primary mt-2 view-stream" data-url="${cctv.stream_url}">View Live Feed</button>`;
          }
          
          marker.bindPopup(popupContent);
          markers.push(marker);
        }
        
        if (cctv.status === "Inactive") {
          newInactive.push(cctv.cctv_id);
        }
      });
      
      // Detect new inactive cameras and show alerts
      detectInactiveAlerts(newInactive);
      
      lastInactive = newInactive;
    }

    // Detect inactive cameras and show alerts
    function detectInactiveAlerts(newInactive) {
      newInactive.forEach(id => {
        if (!lastInactive.includes(id)) {
          // Play sound
          document.getElementById("alertSound").play();
          
          // Find the camera details
          const camera = cctvData.find(c => c.cctv_id === id);
          
          // Show modal
          const modal = new bootstrap.Modal(document.getElementById("cctvAlertModal"));
          document.getElementById("alertMessage").innerHTML = `
            <p>🚨 CCTV ID <b>${id}</b> (${camera.location}) has gone <b>INACTIVE</b>!</p>
            <p class="mb-0">Please check the equipment or network connection.</p>
          `;
          modal.show();
          
          // Update alerts count
          const alertsToday = parseInt(document.getElementById('alerts-today').textContent) || 0;
          document.getElementById('alerts-today').textContent = alertsToday + 1;
        }
      });
    }

    // Attach event listeners to table actions
    function attachTableEventListeners() {
      // View stream buttons
      document.querySelectorAll('.view-stream').forEach(btn => {
        btn.addEventListener('click', function() {
          const streamUrl = this.getAttribute('data-url');
          window.open(streamUrl, '_blank');
        });
      });
      
      // Edit buttons
      document.querySelectorAll('.edit-cctv').forEach(btn => {
        btn.addEventListener('click', function() {
          const cctvId = this.getAttribute('data-id');
          alert(`Edit functionality for CCTV ${cctvId} would open here.`);
          // In a real application, this would open a modal with a form to edit the CCTV details
        });
      });
      
      // Delete buttons
      document.querySelectorAll('.delete-cctv').forEach(btn => {
        btn.addEventListener('click', function() {
          const cctvId = this.getAttribute('data-id');
          if (confirm(`Are you sure you want to delete CCTV ${cctvId}?`)) {
            alert(`CCTV ${cctvId} would be deleted here.`);
            // In a real application, this would send a request to the server to delete the CCTV
          }
        });
      });
    }

    // Form submission handler
    document.getElementById('cctvForm').addEventListener('submit', function(e) {
      e.preventDefault();
      alert('CCTV would be saved to the database in a real application.');
      // In a real application, this would send the form data to the server
    });

    // Initial load
    document.addEventListener('DOMContentLoaded', function() {
      loadCCTV();
      
      // Set up periodic refresh (every 30 seconds)
      setInterval(loadCCTV, 30000);
    });
  </script>
</body>
</html>