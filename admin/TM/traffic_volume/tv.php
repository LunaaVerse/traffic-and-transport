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
    $pdo_tm = getDBConnection('tm');
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

// Function to get traffic volume data
function getTrafficVolumeData($pdo_tm, $filter = []) {
    $data = [];
    
    // Default filter values
    $location_id = $filter['location_id'] ?? null;
    $date_range = $filter['date_range'] ?? 'today';
    $time_interval = $filter['time_interval'] ?? 'hourly';
    
    // Build query based on filters
    $query = "SELECT 
                tl.location_id,
                cl.location_name,
                DATE(tl.created_at) as date,
                HOUR(tl.created_at) as hour,
                AVG(tl.vehicle_count) as avg_vehicles,
                MAX(tl.vehicle_count) as max_vehicles,
                MIN(tl.vehicle_count) as min_vehicles,
                COUNT(tl.log_id) as record_count
              FROM traffic_logs tl
              LEFT JOIN camera_locations cl ON tl.location_id = cl.location_id
              WHERE 1=1";
    
    $params = [];
    
    // Apply location filter
    if ($location_id && $location_id != 'all') {
        $query .= " AND tl.location_id = :location_id";
        $params[':location_id'] = $location_id;
    }
    
    // Apply date range filter
    if ($date_range == 'today') {
        $query .= " AND DATE(tl.created_at) = CURDATE()";
    } elseif ($date_range == 'yesterday') {
        $query .= " AND DATE(tl.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
    } elseif ($date_range == 'week') {
        $query .= " AND tl.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($date_range == 'month') {
        $query .= " AND tl.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    }
    
    // Group by based on time interval
    if ($time_interval == 'hourly') {
        $query .= " GROUP BY tl.location_id, DATE(tl.created_at), HOUR(tl.created_at)
                   ORDER BY date DESC, hour DESC";
    } else {
        $query .= " GROUP BY tl.location_id, DATE(tl.created_at)
                   ORDER BY date DESC";
    }
    
    try {
        $stmt = $pdo_tm->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching traffic volume data: " . $e->getMessage());
    }
    
    return $data;
}

// Function to get location options
function getLocationOptions($pdo_tm) {
    $options = [];
    try {
        $query = "SELECT location_id, location_name FROM camera_locations ORDER BY location_name";
        $stmt = $pdo_tm->prepare($query);
        $stmt->execute();
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching location options: " . $e->getMessage());
    }
    return $options;
}

// Fetch data for display
$user_profile = getUserProfile($pdo_ttm, $_SESSION['user_id']);

// Process filters
$filters = [
    'location_id' => $_GET['location_id'] ?? 'all',
    'date_range' => $_GET['date_range'] ?? 'today',
    'time_interval' => $_GET['time_interval'] ?? 'hourly'
];

$traffic_data = getTrafficVolumeData($pdo_tm, $filters);
$location_options = getLocationOptions($pdo_tm);

// Set active tab and submodule
$current_page = basename($_SERVER['PHP_SELF']);
$active_tab = 'traffic_monitoring';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traffic Volume Dashboard - Quezon City Traffic Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        <a href="../dashboard/dashboard.php" class="sidebar-link">
            <i class='bx bx-home'></i>
            <span class="text">Dashboard</span>
        </a>

        <!-- Traffic Monitoring with Dropdown -->
        <div class="sidebar-section mt-4">Traffic Modules</div>
        <div class="dropdown-toggle sidebar-link active" data-bs-toggle="collapse" data-bs-target="#tmDropdown" aria-expanded="true">
            <i class='bx bx-traffic-cone'></i>
            <span class="text">Traffic Monitoring</span>
        </div>
        <div class="sidebar-dropdown collapse show" id="tmDropdown">
            <a href="../dashboard.php" class="sidebar-dropdown-link">
                <i class='bx bx-bar-chart'></i> Dashboard
            </a>
            <a href="../../TM/manual_logs/admin_traffic_log.php" class="sidebar-dropdown-link">
                <i class='bx bx-notepad'></i> Manual Traffic Logs
            </a>
            <a href="../../TM/traffic_volume/tv.php" class="sidebar-dropdown-link">
                <i class='bx bx-pulse'></i> Traffic Volume Status
            </a>
            <a href="../../TM/daily_monitoring/daily_monitoring.php" class="sidebar-dropdown-link">
                <i class='bx bx-file'></i> Daily Monitoring Reports
            </a>
            <a href="../../TM/cctv_integration/admin_cctv.php" class="sidebar-dropdown-link active">
                <i class='bx bx-cctv'></i> CCTV Integration
            </a>
        </div>

        <!-- Real-time Road Update with Dropdown -->
        <div class="dropdown-toggle sidebar-link" data-bs-toggle="collapse" data-bs-target="#rruDropdown" aria-expanded="false">
            <i class='bx bx-radar'></i>
            <span class="text">Real-time Road Update</span>
        </div>
        <div class="sidebar-dropdown collapse" id="rruDropdown">
            <a href="../../RTR/post_dashboard/admin_road_updates.php" class="sidebar-dropdown-link">
                <i class='bx bx-news'></i> Post Dashboard
            </a>
            <a href="../../RTR/status_management/status_management.php" class="sidebar-dropdown-link">
                <i class='bx bx-bar-chart-alt'></i> Status Management
            </a>
            <a href="../../RTR/real_time_road/admin_road_updates.php" class="sidebar-dropdown-link">
                <i class='bx bx-timer'></i> Real Time Road
            </a>
            <a href="../../RTR/road_condition_map/road_condition_map.php" class="sidebar-dropdown-link">
                <i class='bx bx-map'></i> Road Condition Map
            </a>
        </div>

        <!-- Accident & Violation Report with Dropdown -->
        <div class="dropdown-toggle sidebar-link" data-bs-toggle="collapse" data-bs-target="#avrDropdown" aria-expanded="false">
            <i class='bx bx-shield-x'></i>
            <span class="text">Accident & Violation Report</span>
        </div>
        <div class="sidebar-dropdown collapse" id="avrDropdown">
            <a href="../../AVR/report_management/report_management.php" class="sidebar-dropdown-link">
                <i class='bx bx-file'></i> Report Management
            </a>
            <a href="../../AVR/violation_categorization/violation_categorization.php" class="sidebar-dropdown-link">
                <i class='bx bx-category'></i> Violation Categorization
            </a>
            <a href="../../AVR/evidence_handling/evidence_admin.php" class="sidebar-dropdown-link">
                <i class='bx bx-folder'></i> Evidence Handling
            </a>
            <a href="../../AVR/violation_records/admin_violation_records.php" class="sidebar-dropdown-link">
                <i class='bx bx-book'></i> Violation Record 
            </a>
            <a href="../../AVR/escalation_assignment/admin_escalation_assignment.php" class="sidebar-dropdown-link">
                <i class='bx bx-transfer'></i> Escalation & Assignment  
            </a>
        </div>

        <!-- Vehicle Routing & Diversion with Dropdown -->
        <div class="dropdown-toggle sidebar-link" data-bs-toggle="collapse" data-bs-target="#vrdDropdown" aria-expanded="false">
            <i class='bx bx-map-alt'></i>
            <span class="text">Vehicle Routing & Diversion</span>
        </div>
        <div class="sidebar-dropdown collapse" id="vrdDropdown">
            <a href="../../VRD/route_configuration_panel/route_configuration_panel.php" class="sidebar-dropdown-link">
                <i class='bx bx-map-alt'></i> Route Configuration Panel
            </a>
            <a href="../../VRD/diversion_planning/diversion_planning.php" class="sidebar-dropdown-link">
                <i class='bx bx-git-branch'></i> Diversion Planning
            </a>
            <a href="../../VRD/ai_rule_management/rule_management.php" class="sidebar-dropdown-link">
                <i class='bx bx-cog'></i> AI Rule Management
            </a>
            <a href="../../VRD/osm/osm_integration.php" class="sidebar-dropdown-link">
                <i class='bx bx-globe'></i> OSM (Leaflet) Integration
            </a>
            <a href="../../VRD/routing_analytics/routing_analytics.php" class="sidebar-dropdown-link">
                <i class='bx bx-analyse'></i> Routing Analytics 
            </a>
        </div>

        <!-- Traffic Signal Control with Dropdown -->
        <div class="dropdown-toggle sidebar-link" data-bs-toggle="collapse" data-bs-target="#tscDropdown" aria-expanded="false">
            <i class='bx bx-time-five'></i>
            <span class="text">Traffic Signal Control</span>
        </div>
        <div class="sidebar-dropdown collapse" id="tscDropdown">
            <a href="../../TSC/signal_timing_management/signal_timing_management.php" class="sidebar-dropdown-link">
                <i class='bx bx-timer'></i> Signal Timing Management
            </a>
            <a href="../../TSC/real_time_signal_override/real_time_signal_override.php" class="sidebar-dropdown-link">
                <i class='bx bx-play-circle'></i> Real-Time Signal Override
            </a>
            <a href="../../TSC/automation_settings/admin_traffic_log.php" class="sidebar-dropdown-link">
                <i class='bx bx-cog'></i> Automation Settings
            </a>
            <a href="../../TSC/performance_logs/performance_logs.php" class="sidebar-dropdown-link">
                <i class='bx bx-line-chart'></i> Performance Logs 
            </a>
        </div>

        <!-- Public Transport with Dropdown -->
        <div class="dropdown-toggle sidebar-link" data-bs-toggle="collapse" data-bs-target="#ptsDropdown" aria-expanded="false">
            <i class='bx bx-bus'></i>
            <span class="text">Public Transport</span>
        </div>
        <div class="sidebar-dropdown collapse" id="ptsDropdown">
            <a href="../../PT/fleet_management/fleet_management.php" class="sidebar-dropdown-link">
                <i class='bx bx-car'></i> Fleet Management
            </a>
            <a href="../../PT/route_and_schedule/route_and_schedule.php" class="sidebar-dropdown-link">
                <i class='bx bx-time-five'></i> Route & Schedule Management
            </a>
            <a href="../../PT/real_time_tracking/real_time_tracking.php" class="sidebar-dropdown-link">
                <i class='bx bx-map-pin'></i> Real-Time Tracking
            </a>
            <a href="../../PT/passenger_capacity_compliance/passenger_capacity_compliance.php" class="sidebar-dropdown-link">
                <i class='bx bx-group'></i> Passenger Capacity Compliance 
            </a>
        </div>

        <!-- Permit & Ticketing System with Dropdown -->
        <div class="dropdown-toggle sidebar-link" data-bs-toggle="collapse" data-bs-target="#patsDropdown" aria-expanded="false">
            <i class='bx bx-receipt'></i>
            <span class="text">Permit & Ticketing System</span>
        </div>
        <div class="sidebar-dropdown collapse" id="patsDropdown">
            <a href="../../PTS/permit_application_processing/permit_application_processing.php" class="sidebar-dropdown-link">
                <i class='bx bx-file'></i> Permit Application Processing
            </a>
            <a href="../../PTS/ticket_issuance_control/ticket_issuance_control.php" class="sidebar-dropdown-link">
                <i class='bx bx-list-check'></i> Ticket Issuance Control          
            </a>
            <a href="../../PTS/payment_settlement_management/payment_settlement_management.php" class="sidebar-dropdown-link">
                <i class='bx bx-credit-card'></i> Payment & Settlement Management
            </a>
            <a href="../../PTS/offender_management/offender_management.php" class="sidebar-dropdown-link">
                <i class='bx bx-user-x'></i> Database of Offenders
            </a>
            <a href="../../PTS/compliance_revenue_reports/compliance_revenue_reports.php" class="sidebar-dropdown-link">
                <i class='bx bx-bar-chart-square'></i> Compliance & Revenue Reports
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
                    <h1>Traffic Volume Dashboard</h1>
                    <p>Monitor and analyze traffic volume across Quezon City</p>
                </div>
                
                <div class="header-actions">
                    <div class="user-info">
                        <span class="fw-medium"><?= htmlspecialchars($user_profile['full_name']) ?></span>
                        <span class="text-muted d-block small"><?= htmlspecialchars($user_profile['role']) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Filters Card -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0"><i class='bx bx-filter-alt'></i> Filter Options</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="" id="filterForm">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="location_id" class="form-label">Location</label>
                                    <select class="form-select" id="location_id" name="location_id">
                                        <option value="all" <?= $filters['location_id'] == 'all' ? 'selected' : '' ?>>All Locations</option>
                                        <?php foreach ($location_options as $location): ?>
                                            <option value="<?= $location['location_id'] ?>" <?= $filters['location_id'] == $location['location_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($location['location_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="date_range" class="form-label">Date Range</label>
                                    <select class="form-select" id="date_range" name="date_range">
                                        <option value="today" <?= $filters['date_range'] == 'today' ? 'selected' : '' ?>>Today</option>
                                        <option value="yesterday" <?= $filters['date_range'] == 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                                        <option value="week" <?= $filters['date_range'] == 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                                        <option value="month" <?= $filters['date_range'] == 'month' ? 'selected' : '' ?>>Last 30 Days</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="time_interval" class="form-label">Time Interval</label>
                                    <select class="form-select" id="time_interval" name="time_interval">
                                        <option value="hourly" <?= $filters['time_interval'] == 'hourly' ? 'selected' : '' ?>>Hourly</option>
                                        <option value="daily" <?= $filters['time_interval'] == 'daily' ? 'selected' : '' ?>>Daily</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <div class="mb-3 w-100">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class='bx bx-filter'></i> Apply Filters
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid mb-4">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class='bx bx-car'></i>
                    </div>
                    <div class="stat-content">
                        <h3>
                            <?php
                            $total_vehicles = 0;
                            if (!empty($traffic_data)) {
                                foreach ($traffic_data as $record) {
                                    $total_vehicles += $record['avg_vehicles'] * $record['record_count'];
                                }
                                echo number_format($total_vehicles);
                            } else {
                                echo "0";
                            }
                            ?>
                        </h3>
                        <p>Total Vehicles Tracked</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(25, 135, 84, 0.1); color: #198754;">
                        <i class='bx bx-trending-up'></i>
                    </div>
                    <div class="stat-content">
                        <h3>
                            <?php
                            if (!empty($traffic_data)) {
                                $max_vehicle = max(array_column($traffic_data, 'max_vehicles'));
                                echo number_format($max_vehicle);
                            } else {
                                echo "0";
                            }
                            ?>
                        </h3>
                        <p>Peak Volume</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(13, 110, 253, 0.1); color: #0d6efd;">
                        <i class='bx bx-time'></i>
                    </div>
                    <div class="stat-content">
                        <h3>
                            <?php
                            if (!empty($traffic_data)) {
                                $avg_vehicle = array_sum(array_column($traffic_data, 'avg_vehicles')) / count($traffic_data);
                                echo number_format($avg_vehicle, 1);
                            } else {
                                echo "0";
                            }
                            ?>
                        </h3>
                        <p>Average Volume</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(108, 117, 125, 0.1); color: #6c757d;">
                        <i class='bx bx-stats'></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= count($traffic_data) ?></h3>
                        <p>Data Points</p>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="chart-card">
                        <div class="card-header">
                            <h5 class="card-title"><i class='bx bx-bar-chart-alt'></i> Traffic Volume Trend</h5>
                            <button class="btn btn-sm btn-outline-primary" onclick="exportChartData()">
                                <i class='bx bx-download'></i> Export
                            </button>
                        </div>
                        <div class="card-body">
                            <canvas id="trafficVolumeChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="chart-card">
                        <div class="card-header">
                            <h5 class="card-title"><i class='bx bx-pie-chart-alt'></i> Volume by Location</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="locationVolumeChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Data Table -->
            <div class="card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class='bx bx-table'></i> Traffic Volume Data</h5>
                    <button class="btn btn-sm btn-success" onclick="exportToCSV()">
                        <i class='bx bx-download'></i> Export CSV
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="trafficVolumeTable">
                            <thead>
                                <tr>
                                    <th>Location</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Avg Vehicles</th>
                                    <th>Max Vehicles</th>
                                    <th>Min Vehicles</th>
                                    <th>Records</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($traffic_data)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">No traffic data available for the selected filters.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($traffic_data as $record): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($record['location_name'] ?? 'Unknown') ?></td>
                                            <td><?= date('M j, Y', strtotime($record['date'])) ?></td>
                                            <td>
                                                <?php if ($filters['time_interval'] == 'hourly'): ?>
                                                    <?= sprintf('%02d:00', $record['hour']) ?>
                                                <?php else: ?>
                                                    Daily Summary
                                                <?php endif; ?>
                                            </td>
                                            <td><?= number_format($record['avg_vehicles'], 1) ?></td>
                                            <td><?= number_format($record['max_vehicles']) ?></td>
                                            <td><?= number_format($record['min_vehicles']) ?></td>
                                            <td><?= $record['record_count'] ?></td>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize charts when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
        });

        // Initialize charts
        function initializeCharts() {
            // Traffic Volume Trend Chart
            const trafficVolumeCtx = document.getElementById('trafficVolumeChart').getContext('2d');
            
            // Prepare data for the chart
            const chartData = prepareChartData();
            
            // Create the chart
            new Chart(trafficVolumeCtx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Average Vehicles',
                        data: chartData.values,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Traffic Volume Trend'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Vehicles'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: '<?= $filters['time_interval'] == 'hourly' ? 'Time' : 'Date' ?>'
                            }
                        }
                    }
                }
            });
            
            // Location Volume Pie Chart (if multiple locations selected)
            <?php if ($filters['location_id'] == 'all' && !empty($traffic_data)): ?>
                const locationVolumeCtx = document.getElementById('locationVolumeChart').getContext('2d');
                const locationData = prepareLocationData();
                
                new Chart(locationVolumeCtx, {
                    type: 'pie',
                    data: {
                        labels: locationData.labels,
                        datasets: [{
                            data: locationData.values,
                            backgroundColor: [
                                '#0d6efd', '#6f42c1', '#d63384', '#fd7e14', 
                                '#ffc107', '#198754', '#20c997', '#0dcaf0'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            title: {
                                display: true,
                                text: 'Volume Distribution by Location'
                            }
                        }
                    }
                });
            <?php endif; ?>
        }
        
        // Prepare data for the trend chart
        function prepareChartData() {
            // This would normally come from PHP via AJAX or direct data pass
            // For simplicity, we're using the data already on the page
            const data = <?= json_encode($traffic_data) ?>;
            const timeInterval = '<?= $filters['time_interval'] ?>';
            
            const labels = [];
            const values = [];
            
            data.forEach(record => {
                if (timeInterval === 'hourly') {
                    labels.push(`${record.date} ${record.hour}:00`);
                } else {
                    labels.push(record.date);
                }
                values.push(record.avg_vehicles);
            });
            
            return { labels, values };
        }
        
        // Prepare data for the location chart
        function prepareLocationData() {
            const data = <?= json_encode($traffic_data) ?>;
            const locationMap = {};
            
            data.forEach(record => {
                const location = record.location_name || 'Unknown';
                if (!locationMap[location]) {
                    locationMap[location] = 0;
                }
                locationMap[location] += record.avg_vehicles;
            });
            
            const labels = Object.keys(locationMap);
            const values = Object.values(locationMap);
            
            return { labels, values };
        }
        
        // Export chart data
        function exportChartData() {
            Swal.fire({
                title: 'Export Chart Data',
                text: 'Select export format',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'CSV',
                cancelButtonText: 'Cancel',
                showDenyButton: true,
                denyButtonText: 'PNG Image'
            }).then((result) => {
                if (result.isConfirmed) {
                    exportToCSV();
                } else if (result.isDenied) {
                    // Export as PNG
                    const chartCanvas = document.getElementById('trafficVolumeChart');
                    const link = document.createElement('a');
                    link.download = 'traffic-volume-chart.png';
                    link.href = chartCanvas.toDataURL('image/png');
                    link.click();
                    
                    Swal.fire('Exported!', 'Chart exported as PNG image.', 'success');
                }
            });
        }
        
        // Export to CSV
        function exportToCSV() {
            // Simple CSV export implementation
            const table = document.getElementById('trafficVolumeTable');
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                let row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    row.push('"' + cols[j].innerText + '"');
                }
                
                csv.push(row.join(','));
            }
            
            // Download CSV file
            const csvContent = "data:text/csv;charset=utf-8," + csv.join('\n');
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'traffic-volume-data.csv');
            document.body.appendChild(link);
            link.click();
            
            Swal.fire('Exported!', 'Traffic data exported to CSV.', 'success');
        }
        
        // Apply filters with confirmation
        document.getElementById('filterForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Applying Filters',
                text: 'Updating traffic data...',
                icon: 'info',
                showConfirmButton: false,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    // Submit the form after a brief delay to show the loading state
                    setTimeout(() => {
                        e.target.submit();
                    }, 500);
                }
            });
        });
    </script>
</body>
</html>