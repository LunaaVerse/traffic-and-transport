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
    $pdo_pt = getDBConnection('pt');
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

// Fetch all vehicles with last location
try {
    $sql = "SELECT v.vehicle_id, v.plate_number, v.route_assigned,
                   t.latitude, t.longitude, t.speed, t.direction, t.timestamp
            FROM vehicles v
            LEFT JOIN vehicle_tracking t ON v.vehicle_id = t.vehicle_id
            AND t.id = (SELECT MAX(id) FROM vehicle_tracking WHERE vehicle_id = v.vehicle_id)
            ORDER BY v.vehicle_id DESC";
    $stmt = $pdo_pt->prepare($sql);
    $stmt->execute();
    $vehicles = $stmt->fetchAll();
} catch (PDOException $e) {
    $vehicles = [];
    $message = "❌ Error fetching vehicles: " . $e->getMessage();
    $message_type = "error";
}

// Fetch user profile
$user_profile = getUserProfile($pdo_ttm, $_SESSION['user_id']);

// Set active tab
$active_tab = 'public_transport';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real-Time Vehicle Tracking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .map-container {
            height: 500px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .vehicle-marker {
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }
        
        .vehicle-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .vehicle-table th {
            background: var(--primary);
            color: white;
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .stats-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }
        
        .stats-card p {
            color: var(--secondary);
            margin-bottom: 0;
        }
        
        .refresh-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
            background: white;
            border-radius: 4px;
            padding: 5px 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            cursor: pointer;
        }
        
        .direction-form {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .route-info {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .route-step {
            padding: 0.5rem;
            border-left: 3px solid var(--primary);
            margin-bottom: 0.5rem;
            background: rgba(13, 110, 253, 0.05);
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

                <!-- Public Transport with Dropdown -->
                <div class="dropdown-toggle sidebar-link active" data-bs-toggle="collapse" data-bs-target="#ptsDropdown" aria-expanded="true">
                    <i class='bx bx-bus'></i>
                    <span class="text">Public Transport</span>
                </div>
                <div class="sidebar-dropdown collapse show" id="ptsDropdown">
                    <a href="PT/real_time_tracking/user_tracking.php" class="sidebar-dropdown-link active">
                        <i class='bx bx-info-circle'></i> Real-Time Tracking
                    </a>
                    <a href="PT/route_planner/route_planner.php" class="sidebar-dropdown-link">
                        <i class='bx bx-map'></i> Route Planner
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
                    <h1>Real-Time Vehicle Tracking</h1>
                    <p>Track public transport vehicles in real-time</p>
                </div>
                
                <div class="header-actions">
                    <div class="user-info">
                        <span class="fw-medium"><?= htmlspecialchars($user_profile['full_name']) ?></span>
                        <span class="text-muted d-block small"><?= htmlspecialchars($user_profile['role']) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="row">
                <div class="col-md-3">
                    <div class="stats-card">
                        <h3 id="total-vehicles"><?= count($vehicles) ?></h3>
                        <p>Total Vehicles</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h3 id="active-trackers"><?= count(array_filter($vehicles, function($v) { return !empty($v['latitude']); })) ?></h3>
                        <p>Active Trackers</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h3 id="active-routes"><?= count(array_unique(array_column($vehicles, 'route_assigned'))) ?></h3>
                        <p>Active Routes</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h3>24/7</h3>
                        <p>Monitoring</p>
                    </div>
                </div>
            </div>
            
            <!-- Get Directions Form -->
            <div class="direction-form">
                <h4 class="mb-4"><i class='bx bx-directions'></i> Get Directions</h4>
                <form id="directionForm">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label class="form-label">Starting Point</label>
                                <input type="text" class="form-control" id="startPoint" placeholder="Enter your location" required>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label class="form-label">Destination</label>
                                <input type="text" class="form-control" id="endPoint" placeholder="Enter your destination" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class='bx bx-navigation'></i> Get Route
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Route Information (initially hidden) -->
            <div class="route-info" id="routeInfo" style="display: none;">
                <h4 class="mb-4"><i class='bx bx-map-alt'></i> Route Information</h4>
                <div id="routeSummary"></div>
                <div id="routeSteps" class="mt-3"></div>
            </div>
            
            <!-- Map Container -->
            <div class="map-container">
                <button class="refresh-btn" id="refreshMap">
                    <i class='bx bx-refresh'></i> Refresh
                </button>
                <div id="map" style="height: 100%; width: 100%;"></div>
            </div>
            
            <!-- Vehicles Table -->
            <div class="vehicle-table">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Vehicle ID</th>
                            <th>Plate Number</th>
                            <th>Route</th>
                            <th>Latitude</th>
                            <th>Longitude</th>
                            <th>Speed</th>
                            <th>Direction</th>
                            <th>Last Update</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="vehicles-table-body">
                        <?php if (!empty($vehicles)): ?>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <tr data-vehicle-id="<?= $vehicle['vehicle_id'] ?>">
                                    <td><?= htmlspecialchars($vehicle['vehicle_id']) ?></td>
                                    <td><?= htmlspecialchars($vehicle['plate_number']) ?></td>
                                    <td><?= htmlspecialchars($vehicle['route_assigned']) ?></td>
                                    <td><?= htmlspecialchars($vehicle['latitude'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($vehicle['longitude'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($vehicle['speed'] ?? '0') ?> km/h</td>
                                    <td><?= htmlspecialchars($vehicle['direction'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($vehicle['timestamp'] ?? 'N/A') ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info set-destination" data-route="<?= htmlspecialchars($vehicle['route_assigned']) ?>">
                                            <i class='bx bx-map'></i> Set as Destination
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">No vehicles found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet-routing-machine/dist/leaflet-routing-machine.min.js"></script>
    <script>
        // Initialize map
        var map = L.map('map').setView([14.6760, 121.0437], 13);
        var markers = {};
        var routingControl = null;
        
        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Function to update markers on the map
        function updateMapMarkers(vehicles) {
            // Clear existing markers
            for (let id in markers) {
                map.removeLayer(markers[id]);
            }
            markers = {};
            
            // Add new markers
            vehicles.forEach(vehicle => {
                if (vehicle.latitude && vehicle.longitude) {
                    // Create a custom icon with bus symbol
                    const busIcon = L.divIcon({
                        className: 'vehicle-marker',
                        html: '<i class="bx bx-bus" style="font-size: 16px;"></i>',
                        iconSize: [24, 24],
                        iconAnchor: [12, 12]
                    });
                    
                    const marker = L.marker([vehicle.latitude, vehicle.longitude], {icon: busIcon}).addTo(map);
                    marker.bindPopup(
                        "<b>Vehicle #" + vehicle.vehicle_id + "</b><br>" +
                        "Plate: " + vehicle.plate_number + "<br>" +
                        "Route: " + vehicle.route_assigned + "<br>" +
                        "Speed: " + (vehicle.speed || 0) + " km/h<br>" +
                        "Direction: " + (vehicle.direction || 'N/A') + "<br>" +
                        "Last Update: " + (vehicle.timestamp || 'N/A')
                    );
                    markers[vehicle.vehicle_id] = marker;
                }
            });
            
            // If we have vehicles, fit map to show all of them
            if (Object.keys(markers).length > 0) {
                const group = new L.featureGroup(Object.values(markers));
                map.fitBounds(group.getBounds().pad(0.1));
            }
        }

        // Function to update the vehicles table
        function updateVehiclesTable(vehicles) {
            const tbody = document.getElementById('vehicles-table-body');
            tbody.innerHTML = '';
            
            if (vehicles.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4">No vehicles found.</td></tr>';
                return;
            }
            
            vehicles.forEach(vehicle => {
                const row = document.createElement('tr');
                row.setAttribute('data-vehicle-id', vehicle.vehicle_id);
                row.innerHTML = `
                    <td>${vehicle.vehicle_id}</td>
                    <td>${vehicle.plate_number}</td>
                    <td>${vehicle.route_assigned}</td>
                    <td>${vehicle.latitude || 'N/A'}</td>
                    <td>${vehicle.longitude || 'N/A'}</td>
                    <td>${vehicle.speed || '0'} km/h</td>
                    <td>${vehicle.direction || 'N/A'}</td>
                    <td>${vehicle.timestamp || 'N/A'}</td>
                    <td>
                        <button class="btn btn-sm btn-info set-destination" data-route="${vehicle.route_assigned}">
                            <i class='bx bx-map'></i> Set as Destination
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
            
            // Add event listeners to the new buttons
            document.querySelectorAll('.set-destination').forEach(button => {
                button.addEventListener('click', function() {
                    const route = this.getAttribute('data-route');
                    document.getElementById('endPoint').value = route + ' Route';
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Destination Set',
                        text: 'Route ' + route + ' has been set as your destination',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                });
            });
        }

        // Function to fetch updated vehicle data
        function fetchVehicleData() {
            fetch('ajax/get_vehicles.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update stats
                        document.getElementById('total-vehicles').textContent = data.vehicles.length;
                        document.getElementById('active-trackers').textContent = data.vehicles.filter(v => v.latitude && v.longitude).length;
                        
                        const routes = [...new Set(data.vehicles.map(v => v.route_assigned))].filter(r => r);
                        document.getElementById('active-routes').textContent = routes.length;
                        
                        // Update map and table
                        updateMapMarkers(data.vehicles);
                        updateVehiclesTable(data.vehicles);
                    }
                })
                .catch(error => console.error('Error fetching vehicle data:', error));
        }

        // Function to calculate and display route
        function calculateRoute(start, end) {
            // Remove existing route if any
            if (routingControl) {
                map.removeControl(routingControl);
            }
            
            // Show loading message
            Swal.fire({
                title: 'Calculating Route',
                text: 'Finding the best route...',
                icon: 'info',
                showConfirmButton: false,
                allowOutsideClick: false
            });
            
            // Use Leaflet Routing Machine to calculate route
            routingControl = L.Routing.control({
                waypoints: [
                    L.latLng(14.6760, 121.0437), // Default starting point (Quezon City)
                    L.latLng(14.6760, 121.0437)  // Default ending point
                ],
                routeWhileDragging: false,
                showAlternatives: false,
                lineOptions: {
                    styles: [{color: '#0d6efd', opacity: 0.7, weight: 5}]
                }
            }).addTo(map);
            
            // Simulate API call (in a real app, you would use a geocoding service)
            setTimeout(() => {
                Swal.close();
                
                // Show route information
                document.getElementById('routeInfo').style.display = 'block';
                document.getElementById('routeSummary').innerHTML = `
                    <div class="alert alert-info">
                        <strong>Route from ${start} to ${end}</strong><br>
                        Estimated distance: 12.5 km<br>
                        Estimated time: 35 minutes
                    </div>
                `;
                
                // Show route steps
                const steps = [
                    'Start from ' + start,
                    'Head northeast on Main Street toward 1st Avenue (0.2 km)',
                    'Turn right onto 1st Avenue (1.5 km)',
                    'Turn left onto Quezon Boulevard (3.2 km)',
                    'Continue straight onto Commonwealth Avenue (5.8 km)',
                    'Turn right onto Elliptical Road (1.2 km)',
                    'Arrive at ' + end
                ];
                
                let stepsHtml = '<h5>Directions:</h5>';
                steps.forEach((step, index) => {
                    stepsHtml += `<div class="route-step">${index + 1}. ${step}</div>`;
                });
                
                document.getElementById('routeSteps').innerHTML = stepsHtml;
                
                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Route Found',
                    text: 'Your route has been calculated successfully',
                    timer: 2000,
                    showConfirmButton: false
                });
            }, 2000);
        }

        // Initial map markers
        updateMapMarkers(<?= json_encode($vehicles) ?>);

        // Form submission for directions
        document.getElementById('directionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const startPoint = document.getElementById('startPoint').value;
            const endPoint = document.getElementById('endPoint').value;
            
            if (!startPoint || !endPoint) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'Please enter both starting point and destination',
                    timer: 2000,
                    showConfirmButton: false
                });
                return;
            }
            
            calculateRoute(startPoint, endPoint);
        });

        // Set destination buttons
        document.querySelectorAll('.set-destination').forEach(button => {
            button.addEventListener('click', function() {
                const route = this.getAttribute('data-route');
                document.getElementById('endPoint').value = route + ' Route';
                
                Swal.fire({
                    icon: 'success',
                    title: 'Destination Set',
                    text: 'Route ' + route + ' has been set as your destination',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            });
        });

        // Refresh map button
        document.getElementById('refreshMap').addEventListener('click', function() {
            fetchVehicleData();
            Swal.fire({
                title: 'Refreshing',
                text: 'Updating vehicle data...',
                icon: 'info',
                timer: 1000,
                showConfirmButton: false
            });
        });

        // Auto refresh data every 10 seconds
        setInterval(fetchVehicleData, 10000);
    </script>
</body>
</html>