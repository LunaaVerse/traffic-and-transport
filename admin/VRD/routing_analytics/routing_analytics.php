
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
    $pdo_ttm = getDBConnection('ttm');
    $pdo_vrd = getDBConnection('vrd');
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

// Handle AJAX CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $id = $_POST['id'] ?? null;
    $route_name = $_POST['route_name'] ?? null;
    $start_lat = $_POST['start_lat'] ?? null;
    $start_lng = $_POST['start_lng'] ?? null;
    $end_lat = $_POST['end_lat'] ?? null;
    $end_lng = $_POST['end_lng'] ?? null;
    $average_time = $_POST['average_time'] ?? null;
    $vehicle_count = $_POST['vehicle_count'] ?? null;
    $description = $_POST['description'] ?? null;

    $response = ['status' => 'error', 'message' => 'Unknown action'];

    try {
        if ($action === 'add') {
            $stmt = $pdo_vrd->prepare("INSERT INTO routing_analytics (route_name, start_lat, start_lng, end_lat, end_lng, average_time, vehicle_count, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$route_name, $start_lat, $start_lng, $end_lat, $end_lng, $average_time, $vehicle_count, $description]);
            $response = ['status' => 'success', 'id' => $pdo_vrd->lastInsertId()];
        } 
        elseif ($action === 'update') {
            $stmt = $pdo_vrd->prepare("UPDATE routing_analytics SET route_name=?, start_lat=?, start_lng=?, end_lat=?, end_lng=?, average_time=?, vehicle_count=?, description=? WHERE id=?");
            $stmt->execute([$route_name, $start_lat, $start_lng, $end_lat, $end_lng, $average_time, $vehicle_count, $description, $id]);
            $response = ['status' => 'success'];
        } 
        elseif ($action === 'delete') {
            $stmt = $pdo_vrd->prepare("DELETE FROM routing_analytics WHERE id=?");
            $stmt->execute([$id]);
            $response = ['status' => 'success'];
        } 
        elseif ($action === 'drag') {
            $marker_type = $_POST['marker_type']; // 'start' or 'end'
            if ($marker_type === 'start') {
                $stmt = $pdo_vrd->prepare("UPDATE routing_analytics SET start_lat=?, start_lng=? WHERE id=?");
                $stmt->execute([$start_lat, $start_lng, $id]);
            } else {
                $stmt = $pdo_vrd->prepare("UPDATE routing_analytics SET end_lat=?, end_lng=? WHERE id=?");
                $stmt->execute([$end_lat, $end_lng, $id]);
            }
            $response = ['status' => 'success'];
        }
    } catch (PDOException $e) {
        $response = ['status' => 'error', 'message' => $e->getMessage()];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Fetch all routes
$routes = [];
try {
    $stmt = $pdo_vrd->query("SELECT * FROM routing_analytics");
    $routes = $stmt->fetchAll();
} catch (PDOException $e) {
    // Error handling - routes will remain empty
}

// Fetch user profile
$user_profile = getUserProfile($pdo_ttm, $_SESSION['user_id']);

// Set active tab
$active_tab = 'routing_diversion';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Routing Analytics - Quezon City Traffic Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        #map { height: 500px; margin-bottom: 20px; border-radius: var(--card-radius); }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        input, textarea { width: 100%; padding: 5px; margin: 5px 0; }
        .route-form { background: white; padding: 20px; border-radius: var(--card-radius); margin-bottom: 20px; box-shadow: var(--card-shadow); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; }
        .chart-container { background: white; padding: 20px; border-radius: var(--card-radius); margin-top: 20px; box-shadow: var(--card-shadow); }
        
        /* User info styling from dashboard.php */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info {
            text-align: right;
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .user-info span.fw-medium {
            color: #fff;
            font-weight: 500;
        }
        
        .user-info span.text-muted {
            color: rgba(255, 255, 255, 0.7) !important;
            font-size: 0.85rem;
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
                <div class="dropdown-toggle sidebar-link" data-bs-toggle="collapse" data-bs-target="#avrDropdown" aria-expanded="false">
                    <i class='bx bx-error-circle'></i>
                    <span class="text">Accident & Violation Report</span>
                </div>
                <div class="sidebar-dropdown collapse" id="avrDropdown">
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
                </div>

                <!-- Vehicle Routing & Diversion with Dropdown -->
                <div class="dropdown-toggle sidebar-link active" data-bs-toggle="collapse" data-bs-target="#vrdDropdown" aria-expanded="true">
                    <i class='bx bx-map-alt'></i>
                    <span class="text">Vehicle Routing & Diversion</span>
                </div>
                <div class="sidebar-dropdown collapse show" id="vrdDropdown">
                    <a href="VRD/route_configuration_panel/route_configuration_panel.php" class="sidebar-dropdown-link">
                        <i class='bx bx-directions'></i> Route Configuration Panel
                    </a>
                    <a href="VRD/diversion_planning/diversion_planning.php" class="sidebar-dropdown-link">
                        <i class='bx bx-notification'></i> Diversion Planning
                    </a>
                    <a href="VRD/ai_rule_management/rule_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-edit-alt'></i> AI Rule Management
                    </a>
                    <a href="VRD/osm/osm_integration.php" class="sidebar-dropdown-link">
                        <i class='bx bx-check-circle'></i> OSM (Leaflet) Integration
                    </a>
                    <a href="VRD/routing_analytics/routing_analytics.php" class="sidebar-dropdown-link active">
                        <i class='bx bx-check-circle'></i> Routing Analytics 
                    </a>
                </div>

                <!-- Traffic Signal Control with Dropdown -->
                <div class="dropdown-toggle sidebar-link" data-bs-toggle="collapse" data-bs-target="#tscDropdown" aria-expanded="false">
                    <i class='bx bx-traffic-light'></i>
                    <span class="text">Traffic Signal Control</span>
                </div>
                <div class="sidebar-dropdown collapse" id="tscDropdown">
                    <a href="TSC/signal_timing_management/signal_timing_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-slider-alt'></i> Signal Timing Management
                    </a>
                    <a href="TSC/real_time_signal_override/real_time_signal_override.php" class="sidebar-dropdown-link">
                        <i class='bx bx-time-five'></i> Real-Time Signal Override
                    </a>
                    <a href="TSC/automation_settings/admin_traffic_log.php" class="sidebar-dropdown-link">
                        <i class='bx bx-calendar'></i> Automation Settings
                    </a>
                    <a href="TSC/performance_logs/performance_logs.php" class="sidebar-dropdown-link">
                        <i class='bx bx-calendar'></i> Performance Logs 
                    </a>
                </div>

                <!-- Public Transport with Dropdown -->
                <div class="dropdown-toggle sidebar-link" data-bs-toggle="collapse" data-bs-target="#ptsDropdown" aria-expanded="false">
                    <i class='bx bx-bus'></i>
                    <span class="text">Public Transport</span>
                </div>
                <div class="sidebar-dropdown collapse" id="ptsDropdown">
                    <a href="PT/fleet_management/fleet_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-table'></i> Fleet Management
                    </a>
                    <a href="PT/route_and_schedule/route_and_schedule.php" class="sidebar-dropdown-link">
                        <i class='bx bx-time'></i> Route & Schedule Management
                    </a>
                    <a href="PT/real_time_tracking/real_time_tracking.php" class="sidebar-dropdown-link">
                        <i class='bx bx-info-circle'></i> Real-Time Tracking
                    </a>
                    <a href="PT/passenger_capacity_compliance/passenger_capacity_compliance.php" class="sidebar-dropdown-link">
                        <i class='bx bx-info-circle'></i> Passenger Capacity Compliance 
                    </a>
                </div>

                <!-- Permit & Ticketing System with Dropdown -->
                <div class="dropdown-toggle sidebar-link" data-bs-toggle="collapse" data-bs-target="#patsDropdown" aria-expanded="false">
                    <i class='bx bx-receipt'></i>
                    <span class="text">Permit & Ticketing System</span>
                </div>
                <div class="sidebar-dropdown collapse" id="patsDropdown">
                    <a href="PTS/permit_application_processing/permit_application_processing.php" class="sidebar-dropdown-link">
                        <i class='bx bx-file'></i> Permit Application Processing
                    </a>
                    <a href="PTS/ticket_issuance_control/ticket_issuance_control.php" class="sidebar-dropdown-link">
                        <i class='bx bx-list-check'></i> Ticket Issuance Control          
                    </a>
                    <a href="PTS/payment_settlement_management/payment_settlement_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-show'></i> Payment & Settlement Management
                    </a>
                    <a href="PTS/offender_management/offender_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-show'></i> Database of Offenders
                    </a>
                    <a href="PTS/compliance_revenue_reports/compliance_revenue_reports.php" class="sidebar-dropdown-link">
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
                    <h1>Routing Analytics</h1>
                    <p>Monitor and analyze vehicle routing data</p>
                </div>
                
                <!-- User Info from dashboard.php -->
                <div class="header-actions">
                    <div class="user-info">
                        <span class="fw-medium"><?= htmlspecialchars($user_profile['full_name']) ?></span>
                        <span class="text-muted d-block small"><?= htmlspecialchars($user_profile['role']) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Map Section -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class='bx bx-map'></i> Route Visualization</h5>
                </div>
                <div class="card-body p-0">
                    <div id="map"></div>
                </div>
            </div>
            
            <!-- Add/Update Route Form -->
            <div class="route-form">
                <h3>Add / Update Route</h3>
                <form id="routeForm">
                    <input type="hidden" id="id" name="id">
                    <div class="form-grid">
                        <div>
                            <label for="route_name" class="form-label">Route Name</label>
                            <input type="text" class="form-control" id="route_name" name="route_name" placeholder="Route Name" required>
                        </div>
                        <div>
                            <label for="start_lat" class="form-label">Start Latitude</label>
                            <input type="number" step="0.0000001" class="form-control" id="start_lat" name="start_lat" placeholder="Start Latitude" required>
                        </div>
                        <div>
                            <label for="start_lng" class="form-label">Start Longitude</label>
                            <input type="number" step="0.0000001" class="form-control" id="start_lng" name="start_lng" placeholder="Start Longitude" required>
                        </div>
                        <div>
                            <label for="end_lat" class="form-label">End Latitude</label>
                            <input type="number" step="0.0000001" class="form-control" id="end_lat" name="end_lat" placeholder="End Latitude" required>
                        </div>
                        <div>
                            <label for="end_lng" class="form-label">End Longitude</label>
                            <input type="number" step="0.0000001" class="form-control" id="end_lng" name="end_lng" placeholder="End Longitude" required>
                        </div>
                        <div>
                            <label for="average_time" class="form-label">Average Time (min)</label>
                            <input type="number" step="0.01" class="form-control" id="average_time" name="average_time" placeholder="Average Time (min)" required>
                        </div>
                        <div>
                            <label for="vehicle_count" class="form-label">Vehicle Count</label>
                            <input type="number" class="form-control" id="vehicle_count" name="vehicle_count" placeholder="Vehicle Count" required>
                        </div>
                        <div class="grid-column-span-2">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" placeholder="Description" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary" data-action="add">Add Route</button>
                        <button type="submit" class="btn btn-success" data-action="update">Update Route</button>
                        <button type="button" class="btn btn-secondary" onclick="clearForm()">Clear Form</button>
                    </div>
                </form>
            </div>
            
            <!-- Routes Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class='bx bx-table'></i> Routes Table</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>Avg Time</th>
                                    <th>Vehicles</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($routes as $r): ?>
                                <tr data-id="<?= $r['id'] ?>">
                                    <td><?= $r['id'] ?></td>
                                    <td><?= htmlspecialchars($r['route_name']) ?></td>
                                    <td><?= $r['start_lat'].','.$r['start_lng'] ?></td>
                                    <td><?= $r['end_lat'].','.$r['end_lng'] ?></td>
                                    <td><?= $r['average_time'] ?></td>
                                    <td><?= $r['vehicle_count'] ?></td>
                                    <td><?= htmlspecialchars($r['description']) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editRoute(<?= $r['id'] ?>, '<?= htmlspecialchars($r['route_name']) ?>', <?= $r['start_lat'] ?>, <?= $r['start_lng'] ?>, <?= $r['end_lat'] ?>, <?= $r['end_lng'] ?>, <?= $r['average_time'] ?>, <?= $r['vehicle_count'] ?>, '<?= htmlspecialchars($r['description']) ?>')">Edit</button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteRoute(<?= $r['id'] ?>)">Delete</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Analytics Chart -->
            <div class="chart-container">
                <h3>Analytics Chart</h3>
                <canvas id="analyticsChart" width="600" height="300"></canvas>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Initialize map
        var map = L.map('map').setView([14.6760, 121.0437], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        var routes = <?= json_encode($routes) ?>;
        var markers = {}, polylines = {};
        var selectedLine = null;

        // Add routes to map
        routes.forEach(function(r) {
            var startMarker = L.marker([r.start_lat, r.start_lng], {draggable: true})
                .addTo(map)
                .bindPopup('Start: ' + r.route_name + '<br>Vehicles: ' + r.vehicle_count);
                
            var endMarker = L.marker([r.end_lat, r.end_lng], {draggable: true})
                .addTo(map)
                .bindPopup('End: ' + r.route_name + '<br>Vehicles: ' + r.vehicle_count);
                
            var line = L.polyline([[r.start_lat, r.start_lng], [r.end_lat, r.end_lng]], {
                color: 'blue',
                weight: 3
            }).addTo(map);

            // Drag event handlers
            startMarker.on('dragend', function(e) {
                var pos = e.target.getLatLng();
                updateMarkerPosition(r.id, 'start', pos.lat, pos.lng);
                line.setLatLngs([[pos.lat, pos.lng], [endMarker.getLatLng().lat, endMarker.getLatLng().lng]]);
            });

            endMarker.on('dragend', function(e) {
                var pos = e.target.getLatLng();
                updateMarkerPosition(r.id, 'end', pos.lat, pos.lng);
                line.setLatLngs([[startMarker.getLatLng().lat, startMarker.getLatLng().lng], [pos.lat, pos.lng]]);
            });

            // Click event handlers
            startMarker.on('click', function() {
                showAnalytics(r);
            });

            endMarker.on('click', function() {
                showAnalytics(r);
            });

            // Store references
            markers[r.id] = { start: startMarker, end: endMarker };
            polylines[r.id] = line;
        });

        // Table row click handler
        document.querySelectorAll('table tbody tr').forEach(function(row) {
            row.addEventListener('click', function() {
                var routeId = this.getAttribute('data-id');
                var line = polylines[routeId];
                
                if (selectedLine) {
                    selectedLine.setStyle({color: 'blue', weight: 3});
                }
                
                line.setStyle({color: 'red', weight: 6});
                selectedLine = line;
                
                map.fitBounds(line.getBounds(), {padding: [50, 50]});
                
                var r = routes.find(function(route) {
                    return route.id == routeId;
                });
                
                showAnalytics(r);
            });
        });

        // Form submission
        document.getElementById('routeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            var action = e.submitter.getAttribute('data-action');
            var formData = new FormData(this);
            formData.append('action', action);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        title: 'Success',
                        text: 'Route ' + (action === 'add' ? 'added' : 'updated') + ' successfully',
                        icon: 'success'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Operation failed',
                        icon: 'error'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: 'Error',
                    text: 'An error occurred: ' + error,
                    icon: 'error'
                });
            });
        });

        // Functions
        function updateMarkerPosition(id, type, lat, lng) {
            var formData = new FormData();
            formData.append('action', 'drag');
            formData.append('id', id);
            formData.append('marker_type', type);
            formData.append(type + '_lat', lat);
            formData.append(type + '_lng', lng);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        title: 'Updated!',
                        text: 'Marker position updated',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        }

        function showAnalytics(r) {
            Swal.fire({
                title: r.route_name,
                html: `<b>Average Time:</b> ${r.average_time} min<br>
                      <b>Vehicle Count:</b> ${r.vehicle_count}<br>
                      <b>Description:</b> ${r.description}`,
                icon: 'info'
            });
        }

        function editRoute(id, name, start_lat, start_lng, end_lat, end_lng, avg_time, vehicle_count, desc) {
            document.getElementById('id').value = id;
            document.getElementById('route_name').value = name;
            document.getElementById('start_lat').value = start_lat;
            document.getElementById('start_lng').value = start_lng;
            document.getElementById('end_lat').value = end_lat;
            document.getElementById('end_lng').value = end_lng;
            document.getElementById('average_time').value = avg_time;
            document.getElementById('vehicle_count').value = vehicle_count;
            document.getElementById('description').value = desc;
            
            Swal.fire({
                title: 'Editing',
                text: 'You are now editing this route',
                icon: 'info',
                timer: 1500,
                showConfirmButton: false
            });
        }

        function deleteRoute(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'This will delete the route permanently!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    var formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('id', id);
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire({
                                title: 'Deleted!',
                                text: 'Route has been deleted',
                                icon: 'success'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: data.message || 'Failed to delete route',
                                icon: 'error'
                            });
                        }
                    });
                }
            });
        }

        function clearForm() {
            document.getElementById('routeForm').reset();
            document.getElementById('id').value = '';
        }

        // Initialize chart
        var ctx = document.getElementById('analyticsChart').getContext('2d');
        var chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: routes.map(r => r.route_name),
                datasets: [
                    {
                        label: 'Average Time (min)',
                        data: routes.map(r => r.average_time),
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Vehicle Count',
                        data: routes.map(r => r.vehicle_count),
                        backgroundColor: 'rgba(255, 99, 132, 0.7)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Simulate real-time vehicle count updates every 5 seconds
        function updateVehicleCounts() {
            routes.forEach(r => {
                // Simulate change (+/- 5 vehicles)
                r.vehicle_count = Math.max(0, r.vehicle_count + Math.floor(Math.random() * 11 - 5));
                
                // Update marker popup
                var startMarker = markers[r.id].start;
                var endMarker = markers[r.id].end;
                startMarker.bindPopup('Start: ' + r.route_name + '<br>Vehicles: ' + r.vehicle_count);
                endMarker.bindPopup('End: ' + r.route_name + '<br>Vehicles: ' + r.vehicle_count);

                // Update table
                var row = document.querySelector('#routesTable tbody tr[data-id="' + r.id + '"]');
                if (row) row.cells[5].innerText = r.vehicle_count;
            });

            // Update Chart
            chart.data.datasets[1].data = routes.map(r => r.vehicle_count);
            chart.update();
        }

        // Start simulation
        setInterval(updateVehicleCounts, 5000);
    </script>
</body>
</html>
