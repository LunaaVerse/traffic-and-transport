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
    $pdo_vrd = getDBConnection('vrd');
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

// Fetch all routes for display (read-only for users)
$query = "SELECT * FROM routing_analytics";
$stmt = $pdo_vrd->prepare($query);
$stmt->execute();
$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set active tab
$active_tab = 'routing_diversion';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Routing Analytics - User View</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        /* Map Styles */
        #map { 
            height: 500px; 
            margin-bottom: 20px; 
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
        }
        
        /* Table Styles */
        .table-card {
            background: white;
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(13, 110, 253, 0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .table-card .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(248, 249, 250, 0.5);
        }
        
        .table-card .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .table-card .card-title i {
            color: var(--primary);
        }
        
        .table-card .card-body {
            padding: 1.5rem;
            overflow-x: auto;
        }
        
        table { 
            border-collapse: collapse; 
            width: 100%; 
            margin-top: 10px; 
        }
        
        th, td { 
            border: 1px solid #ccc; 
            padding: 12px; 
            text-align: left; 
        }
        
        th {
            background-color: var(--gray-100);
            font-weight: 600;
        }
        
        tr:hover {
            background-color: var(--gray-100);
        }
        
        /* Chart Styles */
        .chart-card {
            background: white;
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(13, 110, 253, 0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .chart-card .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(248, 249, 250, 0.5);
        }
        
        .chart-card .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .chart-card .card-title i {
            color: var(--primary);
        }
        
        .chart-card .card-body {
            padding: 1.5rem;
            height: 300px;
        }
        
        /* User Info */
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            background: rgba(13, 110, 253, 0.05);
            border-radius: 8px;
            border: 1px solid rgba(13, 110, 253, 0.1);
        }
        
        .user-info .fw-medium {
            font-weight: 500;
            color: var(--dark);
        }
        
        .user-info .text-muted {
            font-size: 0.85rem;
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
            
            .chart-card .card-body {
                height: 250px;
            }
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

                <!-- Vehicle Routing & Diversion with Dropdown -->
                <div class="dropdown-toggle sidebar-link active" data-bs-toggle="collapse" data-bs-target="#vrdDropdown" aria-expanded="true">
                    <i class='bx bx-map-alt'></i>
                    <span class="text">Vehicle Routing & Diversion</span>
                </div>
                <div class="sidebar-dropdown collapse show" id="vrdDropdown">
                    <a href="route_configuration_panel/route_configuration_panel.php" class="sidebar-dropdown-link">
                        <i class='bx bx-directions'></i> Route Configuration Panel
                    </a>
                    <a href="diversion_planning/diversion_planning.php" class="sidebar-dropdown-link">
                        <i class='bx bx-notification'></i> Diversion Planning
                    </a>
                    <a href="ai_rule_management/rule_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-edit-alt'></i> AI Rule Management
                    </a>
                    <a href="osm/osm_integration.php" class="sidebar-dropdown-link">
                        <i class='bx bx-check-circle'></i> OSM (Leaflet) Integration
                    </a>
                    <a href="routing_analytics/routing_analytics_user.php" class="sidebar-dropdown-link active">
                        <i class='bx bx-check-circle'></i> Routing Analytics 
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
                    <h1>Routing Analytics</h1>
                    <p>View real-time route information and analytics</p>
                </div>
                
                <div class="header-actions">
                    <div class="user-info">
                        <span class="fw-medium"><?= htmlspecialchars($user_profile['full_name']) ?></span>
                        <span class="text-muted d-block small"><?= htmlspecialchars($user_profile['role']) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Map -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class='bx bx-map'></i> Route Map</h5>
                </div>
                <div class="card-body p-0">
                    <div id="map"></div>
                </div>
            </div>
            
            <!-- Routes Table -->
            <div class="table-card">
                <div class="card-header">
                    <h5 class="card-title"><i class='bx bx-table'></i> Routes Information</h5>
                </div>
                <div class="card-body">
                    <table id="routesTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Avg Time (min)</th>
                                <th>Vehicles</th>
                                <th>Description</th>
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
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Analytics Chart -->
            <div class="chart-card">
                <div class="card-header">
                    <h5 class="card-title"><i class='bx bx-bar-chart-alt'></i> Route Analytics</h5>
                </div>
                <div class="card-body">
                    <canvas id="analyticsChart" width="600" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize map
        var map = L.map('map').setView([14.6760,121.0437],13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap'}).addTo(map);

        var routes = <?= json_encode($routes) ?>;
        var markers = {}, polylines = {};
        var selectedLine = null;

        // Add routes to map
        routes.forEach(function(r){
            var startMarker = L.marker([r.start_lat,r.start_lng],{draggable:false}).addTo(map).bindPopup('Start: '+r.route_name+'<br>Vehicles: '+r.vehicle_count);
            var endMarker = L.marker([r.end_lat,r.end_lng],{draggable:false}).addTo(map).bindPopup('End: '+r.route_name+'<br>Vehicles: '+r.vehicle_count);
            var line = L.polyline([[r.start_lat,r.start_lng],[r.end_lat,r.end_lng]],{color:'blue',weight:3}).addTo(map);

            // Add click events to markers
            startMarker.on('click',()=> showAnalytics(r));
            endMarker.on('click',()=> showAnalytics(r));

            markers[r.id] = {start:startMarker,end:endMarker};
            polylines[r.id] = line;
        });

        // Table row click -> zoom & highlight
        document.querySelectorAll('#routesTable tbody tr').forEach(function(row){
            row.addEventListener('click', function(){
                var routeId = this.getAttribute('data-id');
                var route = routes.find(r => r.id == routeId);
                
                if(selectedLine) selectedLine.setStyle({color:'blue', weight:3});
                
                var line = polylines[routeId];
                line.setStyle({color:'red', weight:6});
                selectedLine = line;
                
                map.fitBounds(line.getBounds(),{padding:[50,50]});
                showAnalytics(route);
            });
        });

        // Show analytics in SweetAlert
        function showAnalytics(r){
            Swal.fire({
                title: r.route_name,
                html: `<b>Average Time:</b> ${r.average_time} min<br>
                      <b>Vehicle Count:</b> ${r.vehicle_count}<br>
                      <b>Description:</b> ${r.description}`,
                icon: 'info',
                confirmButtonText: 'OK'
            });
        }

        // Initialize chart
        var ctx = document.getElementById('analyticsChart').getContext('2d');
        var chart = new Chart(ctx,{
            type:'bar',
            data:{
                labels: routes.map(r=>r.route_name),
                datasets:[
                    {
                        label:'Average Time (min)',
                        data:routes.map(r=>r.average_time),
                        backgroundColor:'rgba(54,162,235,0.7)',
                        borderColor: 'rgba(54,162,235,1)',
                        borderWidth: 1
                    },
                    {
                        label:'Vehicle Count',
                        data:routes.map(r=>r.vehicle_count),
                        backgroundColor:'rgba(255,99,132,0.7)',
                        borderColor: 'rgba(255,99,132,1)',
                        borderWidth: 1
                    }
                ]
            },
            options:{
                responsive:true,
                maintainAspectRatio: false,
                scales:{
                    y:{
                        beginAtZero:true,
                        title: {
                            display: true,
                            text: 'Value'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Routes'
                        }
                    }
                }
            }
        });

        // Simulate real-time vehicle count updates every 5 seconds
        function updateVehicleCounts() {
            routes.forEach(r => {
                // Simulate change (+/- 5 vehicles)
                r.vehicle_count = Math.max(0, r.vehicle_count + Math.floor(Math.random()*11 - 5));
                
                // Update marker popup
                var startMarker = markers[r.id].start;
                var endMarker = markers[r.id].end;
                startMarker.bindPopup('Start: '+r.route_name+'<br>Vehicles: '+r.vehicle_count);
                endMarker.bindPopup('End: '+r.route_name+'<br>Vehicles: '+r.vehicle_count);

                // Update table
                var row = document.querySelector('#routesTable tbody tr[data-id="'+r.id+'"]');
                if(row) row.cells[5].innerText = r.vehicle_count;
            });

            // Update Chart
            chart.data.datasets[1].data = routes.map(r=>r.vehicle_count);
            chart.update('none'); // 'none' prevents animation for smoother update
        }

        // Start simulation
        setInterval(updateVehicleCounts, 5000);
    </script>
</body>
</html>