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

// Handle vehicle addition
$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_vehicle'])) {
    $plate_number = $_POST['plate_number'];
    $route        = $_POST['route'];
    $latitude     = $_POST['latitude'];
    $longitude    = $_POST['longitude'];
    $speed        = $_POST['speed'];
    $direction    = $_POST['direction'];

    if (!empty($plate_number) && !empty($route) && !empty($latitude) && !empty($longitude)) {
        try {
            // Check if vehicle already exists
            $check_sql = "SELECT vehicle_id FROM vehicles WHERE plate_number = :plate_number";
            $check_stmt = $pdo_pt->prepare($check_sql);
            $check_stmt->bindParam(':plate_number', $plate_number);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $vehicle_id = $check_stmt->fetch()['vehicle_id'];
                
                // Update existing vehicle
                $update_sql = "UPDATE vehicles SET route_assigned = :route WHERE vehicle_id = :vehicle_id";
                $update_stmt = $pdo_pt->prepare($update_sql);
                $update_stmt->bindParam(':route', $route);
                $update_stmt->bindParam(':vehicle_id', $vehicle_id);
                $update_stmt->execute();
            } else {
                // Insert new vehicle
                $sql = "INSERT INTO vehicles (plate_number, route_assigned) VALUES (:plate_number, :route)";
                $stmt = $pdo_pt->prepare($sql);
                $stmt->bindParam(':plate_number', $plate_number);
                $stmt->bindParam(':route', $route);
                
                if ($stmt->execute()) {
                    $vehicle_id = $pdo_pt->lastInsertId();
                } else {
                    $message = "❌ Error adding vehicle";
                    $message_type = "error";
                }
            }

            // Insert tracking record
            $sql2 = "INSERT INTO vehicle_tracking (vehicle_id, latitude, longitude, speed, direction)
                     VALUES (:vehicle_id, :latitude, :longitude, :speed, :direction)";
            $stmt2 = $pdo_pt->prepare($sql2);
            $stmt2->bindParam(':vehicle_id', $vehicle_id);
            $stmt2->bindParam(':latitude', $latitude);
            $stmt2->bindParam(':longitude', $longitude);
            $stmt2->bindParam(':speed', $speed);
            $stmt2->bindParam(':direction', $direction);
            
            if ($stmt2->execute()) {
                $message = "✅ Vehicle successfully added/updated with tracking!";
                $message_type = "success";
            } else {
                $message = "❌ Error adding tracking data";
                $message_type = "error";
            }
        } catch (PDOException $e) {
            $message = "❌ Database error: " . $e->getMessage();
            $message_type = "error";
        }
    } else {
        $message = "⚠️ Please fill in all required fields.";
        $message_type = "warning";
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
        
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
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
                    <a href="PT/fleet_management/fleet_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-table'></i> Fleet Management
                    </a>
                    <a href="PT/route_and_schedule/route_and_schedule.php" class="sidebar-dropdown-link">
                        <i class='bx bx-time'></i> Route & Schedule Management
                    </a>
                    <a href="PT/real_time_tracking/real_time_tracking.php" class="sidebar-dropdown-link active">
                        <i class='bx bx-info-circle'></i> Real-Time Tracking
                    </a>
                     <a href="PT/passenger_capacity_compliance/passenger_capacity_compliance.php" class="sidebar-dropdown-link">
                        <i class='bx bx-user-voice'></i> Passenger Capacity Compliance 
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
                    <p>Monitor and manage vehicle locations in real-time</p>
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
            
            <!-- Add Vehicle Form -->
            <div class="form-container">
                <h4 class="mb-4"><i class='bx bx-plus'></i> Add New Vehicle</h4>
                <form method="POST" action="" id="vehicleForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Plate Number</label>
                                <input type="text" class="form-control" name="plate_number" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Route Assigned</label>
                                <textarea class="form-control" name="route" rows="1" required></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Initial Latitude</label>
                                <input type="text" class="form-control" name="latitude" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Initial Longitude</label>
                                <input type="text" class="form-control" name="longitude" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Speed (km/h)</label>
                                <input type="number" class="form-control" name="speed" value="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Direction</label>
                                <input type="text" class="form-control" name="direction" placeholder="e.g., Northbound">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_vehicle" class="btn btn-primary">
                        <i class='bx bx-plus'></i> Add Vehicle
                    </button>
                </form>
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
                                        <button class="btn btn-sm btn-info update-location" data-id="<?= $vehicle['vehicle_id'] ?>">
                                            <i class='bx bx-edit'></i> Update Location
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

    <!-- Update Location Modal -->
    <div class="modal fade" id="updateLocationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Vehicle Location</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="updateLocationForm">
                        <input type="hidden" id="update_vehicle_id" name="vehicle_id">
                        <div class="mb-3">
                            <label class="form-label">Latitude</label>
                            <input type="text" class="form-control" name="latitude" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Longitude</label>
                            <input type="text" class="form-control" name="longitude" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Speed (km/h)</label>
                            <input type="number" class="form-control" name="speed" value="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Direction</label>
                            <input type="text" class="form-control" name="direction" placeholder="e.g., Northbound">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="submitUpdate">Update Location</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Initialize map
        var map = L.map('map').setView([14.6760, 121.0437], 13);
        var markers = {};
        
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
                    const marker = L.marker([vehicle.latitude, vehicle.longitude]).addTo(map);
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
                        <button class="btn btn-sm btn-info update-location" data-id="${vehicle.vehicle_id}">
                            <i class='bx bx-edit'></i> Update Location
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
            
            // Add event listeners to the new buttons
            document.querySelectorAll('.update-location').forEach(button => {
                button.addEventListener('click', function() {
                    const vehicleId = this.getAttribute('data-id');
                    document.getElementById('update_vehicle_id').value = vehicleId;
                    
                    // Pre-fill the form with current values if available
                    const vehicle = vehicles.find(v => v.vehicle_id == vehicleId);
                    if (vehicle) {
                        document.querySelector('#updateLocationForm input[name="latitude"]').value = vehicle.latitude || '';
                        document.querySelector('#updateLocationForm input[name="longitude"]').value = vehicle.longitude || '';
                        document.querySelector('#updateLocationForm input[name="speed"]').value = vehicle.speed || '0';
                        document.querySelector('#updateLocationForm input[name="direction"]').value = vehicle.direction || '';
                    }
                    
                    // Show the modal
                    const modal = new bootstrap.Modal(document.getElementById('updateLocationModal'));
                    modal.show();
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

        // Show SweetAlert notification if there's a message
        <?php if (!empty($message)): ?>
            Swal.fire({
                icon: '<?= $message_type ?>',
                title: '<?= addslashes($message) ?>',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        <?php endif; ?>

        // Initial map markers
        updateMapMarkers(<?= json_encode($vehicles) ?>);

        // Form validation
        document.getElementById('vehicleForm').addEventListener('submit', function(e) {
            let valid = true;
            const requiredFields = this.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    valid = false;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!valid) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Please fill all required fields',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            }
        });

        // Handle update location form submission
        document.getElementById('submitUpdate').addEventListener('click', function() {
            const formData = new FormData(document.getElementById('updateLocationForm'));
            
            fetch('ajax/update_location.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Location Updated',
                        text: 'Vehicle location has been updated successfully',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    // Close the modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('updateLocationModal'));
                    modal.hide();
                    
                    // Refresh the data
                    fetchVehicleData();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Update Failed',
                        text: data.message || 'Failed to update vehicle location'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while updating the location'
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