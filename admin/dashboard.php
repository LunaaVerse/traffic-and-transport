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
    $pdo_tm = getDBConnection('tm');
    $pdo_ttm = getDBConnection('ttm');
    $pdo_rtr = getDBConnection('rtr');
    $pdo_avr = getDBConnection('avr');
    $pdo_vrd = getDBConnection('vrd');
    $pdo_tsc = getDBConnection('tsc');
    $pdo_pts = getDBConnection('pts');
    $pdo_pats = getDBConnection('pats');
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

// Function to get dashboard statistics
function getDashboardStats($pdo_tm, $pdo_rtr, $pdo_avr, $pdo_vrd, $pdo_tsc, $pdo_pts, $pdo_pats) {
    $stats = [];
    
    // Traffic Monitoring Stats
    try {
        $query = "SELECT COUNT(*) as total_logs FROM traffic_logs WHERE DATE(created_at) = CURDATE()";
        $stmt = $pdo_tm->prepare($query);
        $stmt->execute();
        $stats['traffic_logs_today'] = $stmt->fetchColumn();
        
        $query = "SELECT COUNT(*) as active_cameras FROM cctv_cameras WHERE status = 'online'";
        $stmt = $pdo_tm->prepare($query);
        $stmt->execute();
        $stats['active_cameras'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $stats['traffic_logs_today'] = 0;
        $stats['active_cameras'] = 0;
    }
    
    // Real-time Road Update Stats
    try {
        $query = "SELECT COUNT(*) as active_updates FROM road_updates WHERE is_active = 1";
        $stmt = $pdo_rtr->prepare($query);
        $stmt->execute();
        $stats['active_road_updates'] = $stmt->fetchColumn();
        
        $query = "SELECT COUNT(*) as critical_updates FROM road_updates WHERE status_id IN (SELECT status_id FROM road_status_types WHERE severity_level >= 4) AND is_active = 1";
        $stmt = $pdo_rtr->prepare($query);
        $stmt->execute();
        $stats['critical_updates'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $stats['active_road_updates'] = 0;
        $stats['critical_updates'] = 0;
    }
    
    // Accident & Violation Stats
    try {
        $query = "SELECT COUNT(*) as pending_reports FROM reports WHERE status = 'pending'";
        $stmt = $pdo_avr->prepare($query);
        $stmt->execute();
        $stats['pending_reports'] = $stmt->fetchColumn();
        
        $query = "SELECT COUNT(*) as today_reports FROM reports WHERE DATE(report_date) = CURDATE()";
        $stmt = $pdo_avr->prepare($query);
        $stmt->execute();
        $stats['today_reports'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $stats['pending_reports'] = 0;
        $stats['today_reports'] = 0;
    }
    
    // Vehicle Routing Stats
    try {
        $query = "SELECT COUNT(*) as active_routes FROM routing_routes WHERE status = 'active'";
        $stmt = $pdo_vrd->prepare($query);
        $stmt->execute();
        $stats['active_routes'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $stats['active_routes'] = 0;
    }
    
    // Traffic Signal Control Stats
    try {
        $query = "SELECT COUNT(*) as active_intersections FROM intersections WHERE status = 'online'";
        $stmt = $pdo_tsc->prepare($query);
        $stmt->execute();
        $stats['active_intersections'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $stats['active_intersections'] = 0;
    }
    
    // Public Transport Stats
    try {
        $query = "SELECT COUNT(*) as active_transport_routes FROM transport_routes WHERE is_active = 1";
        $stmt = $pdo_pts->prepare($query);
        $stmt->execute();
        $stats['active_transport_routes'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $stats['active_transport_routes'] = 0;
    }
    
    // Permit & Ticketing Stats
    try {
        $query = "SELECT COUNT(*) as pending_applications FROM permit_applications WHERE status = 'pending'";
        $stmt = $pdo_pats->prepare($query);
        $stmt->execute();
        $stats['pending_applications'] = $stmt->fetchColumn();
        
        $query = "SELECT COUNT(*) as unpaid_tickets FROM tickets WHERE status = 'unpaid'";
        $stmt = $pdo_pats->prepare($query);
        $stmt->execute();
        $stats['unpaid_tickets'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $stats['pending_applications'] = 0;
        $stats['unpaid_tickets'] = 0;
    }
    
    return $stats;
}

// Function to get recent activities
function getRecentActivities($pdo_tm, $pdo_rtr, $pdo_avr, $pdo_vrd, $pdo_tsc, $pdo_pts, $pdo_pats) {
    $activities = [];
    
    // Get recent traffic logs
    try {
        $query = "SELECT tl.*, cl.location_name FROM traffic_logs tl 
                 LEFT JOIN camera_locations cl ON tl.location_id = cl.location_id 
                 ORDER BY tl.created_at DESC LIMIT 5";
        $stmt = $pdo_tm->prepare($query);
        $stmt->execute();
        $traffic_logs = $stmt->fetchAll();
        
        foreach ($traffic_logs as $log) {
            $activities[] = [
                'type' => 'traffic',
                'time' => $log['created_at'],
                'title' => 'Traffic Log at ' . $log['location_name'],
                'description' => 'Vehicle count: ' . $log['vehicle_count'] . ', Status: ' . $log['congestion_level'],
                'icon' => 'bx-traffic-cone'
            ];
        }
    } catch (PDOException $e) {
        // Error handling
    }
    
    // Get recent road updates
    try {
        $query = "SELECT ru.*, rs.road_name, rst.name as status_name FROM road_updates ru 
                 LEFT JOIN road_segments rs ON ru.segment_id = rs.segment_id 
                 LEFT JOIN road_status_types rst ON ru.status_id = rst.status_id 
                 ORDER BY ru.created_at DESC LIMIT 5";
        $stmt = $pdo_rtr->prepare($query);
        $stmt->execute();
        $road_updates = $stmt->fetchAll();
        
        foreach ($road_updates as $update) {
            $activities[] = [
                'type' => 'road_update',
                'time' => $update['created_at'],
                'title' => 'Road Update: ' . $update['road_name'],
                'description' => 'Status: ' . $update['status_name'] . ' - ' . $update['title'],
                'icon' => 'bx-road'
            ];
        }
    } catch (PDOException $e) {
        // Error handling
    }
    
    // Get recent reports
    try {
        $query = "SELECT r.* FROM reports r ORDER BY r.created_at DESC LIMIT 5";
        $stmt = $pdo_avr->prepare($query);
        $stmt->execute();
        $reports = $stmt->fetchAll();
        
        foreach ($reports as $report) {
            // Check if priority_level exists in the report array
            $priority = isset($report['priority_level']) ? $report['priority_level'] : 'Unknown';
            $location = isset($report['location']) ? $report['location'] : 'Unknown location';
            $reportType = isset($report['report_type']) ? $report['report_type'] : 'Unknown';
            
            $activities[] = [
                'type' => 'report',
                'time' => $report['created_at'],
                'title' => ucfirst($reportType) . ' Report',
                'description' => 'Priority: ' . $priority . ' at ' . $location,
                'icon' => 'bx-error-circle'
            ];
        }
    } catch (PDOException $e) {
        // Error handling
    }
    
    // Sort activities by time (newest first)
    usort($activities, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });
    
    return array_slice($activities, 0, 10); // Return only the 10 most recent
}

// Fetch data for display
$user_profile = getUserProfile($pdo_ttm, $_SESSION['user_id']);
$dashboard_stats = getDashboardStats($pdo_tm, $pdo_rtr, $pdo_avr, $pdo_vrd, $pdo_tsc, $pdo_pts, $pdo_pats);
$recent_activities = getRecentActivities($pdo_tm, $pdo_rtr, $pdo_avr, $pdo_vrd, $pdo_tsc, $pdo_pts, $pdo_pats);

// Set active tab and submodule
$current_page = basename($_SERVER['PHP_SELF']);
$active_tab = 'dashboard';

// Determine active tab based on current page
if (strpos($current_page, 'traffic_monitoring') !== false) {
    $active_tab = 'traffic_monitoring';
} elseif (strpos($current_page, 'road_update') !== false) {
    $active_tab = 'road_update';
} elseif (strpos($current_page, 'accident_violation') !== false) {
    $active_tab = 'accident_violation';
} elseif (strpos($current_page, 'routing_diversion') !== false) {
    $active_tab = 'routing_diversion';
} elseif (strpos($current_page, 'signal_control') !== false) {
    $active_tab = 'signal_control';
} elseif (strpos($current_page, 'public_transport') !== false) {
    $active_tab = 'public_transport';
} elseif (strpos($current_page, 'permit_ticketing') !== false) {
    $active_tab = 'permit_ticketing';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quezon City Traffic Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
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
                <a href="../dashboard/dashboard.php" class="sidebar-link active">
                    <i class='bx bx-home'></i>
                    <span class="text">Dashboard</span>
                </a>

                <!-- Traffic Monitoring with Dropdown -->
                <div class="sidebar-section mt-4">Traffic Modules</div>
                <div class="dropdown-toggle sidebar-link " data-bs-toggle="collapse" data-bs-target="#tmDropdown" aria-expanded="true">
                    <i class='bx bx-traffic-cone'></i>
                    <span class="text">Traffic Monitoring</span>
                </div>
                <div class="sidebar-dropdown collapse show" id="tmDropdown">
                    <a href="../dashboard.php" class="sidebar-dropdown-link">
                        <i class='bx bx-clipboard'></i> Dashboard
                    </a>
                    <a href="TM/manual_logs/admin_traffic_log.php" class="sidebar-dropdown-link">
                        <i class='bx bx-clipboard'></i> Manual Traffic Logs
                    </a>
                    <a href="TM/traffic_volume/tv.php" class="sidebar-dropdown-link ">
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
                      </a>
                <a href="RTR/road_condition_map/road_condition_map.php" class="sidebar-dropdown-link">
                        <i class='bx bx-bell'></i> Road Condition Map
                    </a>
                </div>

                <!-- Accident & Violation Report with Dropdown -->
                <div class="dropdown-toggle sidebar-link <?= $active_tab == 'accident_violation' ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#avrDropdown" aria-expanded="<?= $active_tab == 'accident_violation' ? 'true' : 'false' ?>">
                    <i class='bx bx-error-circle'></i>
                    <span class="text">Accident & Violation Report</span>
                </div>
               <div class="sidebar-dropdown collapse <?= $active_tab == 'routing_diversion' ? 'show' : '' ?>" id="vrdDropdown">
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
                <div class="dropdown-toggle sidebar-link <?= $active_tab == 'routing_diversion' ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#vrdDropdown" aria-expanded="<?= $active_tab == 'routing_diversion' ? 'true' : 'false' ?>">
                    <i class='bx bx-map-alt'></i>
                    <span class="text">Vehicle Routing & Diversion</span>
                </div>
                <div class="sidebar-dropdown collapse <?= $active_tab == 'routing_diversion' ? 'show' : '' ?>" id="vrdDropdown">
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
                     <a href="VRD/routing_analytics/routing_analytics.php" class="sidebar-dropdown-link">
                        <i class='bx bx-check-circle'></i> Routing Analytics 
                    </a>
                </div>

                <!-- Traffic Signal Control with Dropdown -->
                <div class="dropdown-toggle sidebar-link <?= $active_tab == 'signal_control' ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#tscDropdown" aria-expanded="<?= $active_tab == 'signal_control' ? 'true' : 'false' ?>">
                    <i class='bx bx-traffic-light'></i>
                    <span class="text">Traffic Signal Control</span>
                </div>
                <div class="sidebar-dropdown collapse <?= $active_tab == 'signal_control' ? 'show' : '' ?>" id="tscDropdown">
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
                <div class="dropdown-toggle sidebar-link <?= $active_tab == 'public_transport' ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#ptsDropdown" aria-expanded="<?= $active_tab == 'public_transport' ? 'true' : 'false' ?>">
                    <i class='bx bx-bus'></i>
                    <span class="text">Public Transport</span>
                </div>
                <div class="sidebar-dropdown collapse <?= $active_tab == 'public_transport' ? 'show' : '' ?>" id="ptsDropdown">
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
                <div class="dropdown-toggle sidebar-link <?= $active_tab == 'permit_ticketing' ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#patsDropdown" aria-expanded="<?= $active_tab == 'permit_ticketing' ? 'true' : 'false' ?>">
                    <i class='bx bx-receipt'></i>
                    <span class="text">Permit & Ticketing System</span>
                </div>
                <div class="sidebar-dropdown collapse <?= $active_tab == 'permit_ticketing' ? 'show' : '' ?>" id="patsDropdown">
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
                    <h1>Quezon City Traffic Management System</h1>
                    <p>Monitor and manage traffic data across Quezon City</p>
                </div>
                
                <div class="header-actions">
                    <div class="user-info">
                        <span class="fw-medium"><?= htmlspecialchars($user_profile['full_name']) ?></span>
                        <span class="text-muted d-block small"><?= htmlspecialchars($user_profile['role']) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Dashboard Content -->
            <?php if ($active_tab == 'dashboard'): ?>
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <!-- Traffic Monitoring Stats -->
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class='bx bx-traffic-cone'></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $dashboard_stats['traffic_logs_today'] ?></h3>
                            <p>Traffic Logs Today</p>
                            <div class="stat-trend trend-up">
                                <i class='bx bx-up-arrow-alt'></i>
                                <span>12% from yesterday</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class='bx bx-cctv'></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $dashboard_stats['active_cameras'] ?></h3>
                            <p>Active CCTV Cameras</p>
                            <div class="stat-trend trend-up">
                                <i class='bx bx-up-arrow-alt'></i>
                                <span>All systems operational</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Road Update Stats -->
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class='bx bx-road'></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $dashboard_stats['active_road_updates'] ?></h3>
                            <p>Active Road Updates</p>
                            <div class="stat-trend trend-down">
                                <i class='bx bx-down-arrow-alt'></i>
                                <span>3% from last week</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                            <i class='bx bx-error'></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $dashboard_stats['critical_updates'] ?></h3>
                            <p>Critical Road Issues</p>
                            <div class="stat-trend trend-up">
                                <i class='bx bx-up-arrow-alt'></i>
                                <span>2 new today</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Accident & Violation Stats -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                            <i class='bx bx-error-circle'></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $dashboard_stats['pending_reports'] ?></h3>
                            <p>Pending Reports</p>
                            <div class="stat-trend trend-up">
                                <i class='bx bx-up-arrow-alt'></i>
                                <span>5 new today</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class='bx bx-calendar-event'></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $dashboard_stats['today_reports'] ?></h3>
                            <p>Reports Today</p>
                            <div class="stat-trend trend-up">
                                <i class='bx bx-up-arrow-alt'></i>
                                <span>8% from yesterday</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Vehicle Routing Stats -->
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class='bx bx-map-alt'></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $dashboard_stats['active_routes'] ?></h3>
                            <p>Active Routing Plans</p>
                            <div class="stat-trend trend-up">
                                <i class='bx bx-up-arrow-alt'></i>
                                <span>2 new this week</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Traffic Signal Stats -->
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class='bx bx-traffic-light'></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $dashboard_stats['active_intersections'] ?></h3>
                            <p>Active Intersections</p>
                            <div class="stat-trend trend-up">
                                <i class='bx bx-up-arrow-alt'></i>
                                <span>All systems online</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Public Transport Stats -->
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class='bx bx-bus'></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $dashboard_stats['active_transport_routes'] ?></h3>
                            <p>Active Transport Routes</p>
                            <div class="stat-trend trend-up">
                                <i class='bx bx-up-arrow-alt'></i>
                                <span>No changes</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Permit & Ticketing Stats -->
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                            <i class='bx bx-file'></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $dashboard_stats['pending_applications'] ?></h3>
                            <p>Pending Permit Applications</p>
                            <div class="stat-trend trend-up">
                                <i class='bx bx-up-arrow-alt'></i>
                                <span>3 new today</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                            <i class='bx bx-receipt'></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $dashboard_stats['unpaid_tickets'] ?></h3>
                            <p>Unpaid Traffic Tickets</p>
                            <div class="stat-trend trend-up">
                                <i class='bx bx-up-arrow-alt'></i>
                                <span>12% from last month</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="charts-container">
                    <div class="chart-card">
                        <div class="card-header">
                            <h5 class="card-title"><i class='bx bx-bar-chart-alt'></i> Traffic Volume Trends</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-placeholder">
                                <i class='bx bx-bar-chart'></i>
                                <p>Traffic volume chart would be displayed here</p>
                                <small>Real-time data from monitoring stations</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="card-header">
                            <h5 class="card-title"><i class='bx bx-pie-chart-alt'></i> Incident Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-placeholder">
                                <i class='bx bx-pie-chart'></i>
                                <p>Incident type distribution chart</p>
                                <small>Accidents, violations, and other incidents</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activities -->
                <div class="activity-card">
                    <div class="card-header">
                        <h5 class="card-title"><i class='bx bx-time'></i> Recent Activities</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="activity-list">
                            <?php if (empty($recent_activities)): ?>
                                <li class="activity-item">
                                    <div class="activity-content text-center py-4">
                                        <p class="text-muted">No recent activities</p>
                                    </div>
                                </li>
                            <?php else: ?>
                                <?php foreach ($recent_activities as $activity): ?>
                                    <li class="activity-item">
                                        <div class="activity-icon">
                                            <i class='bx <?= $activity['icon'] ?>'></i>
                                        </div>
                                        <div class="activity-content">
                                            <h6><?= $activity['title'] ?></h6>
                                            <p><?= $activity['description'] ?></p>
                                            <div class="activity-time">
                                                <?= date('M j, Y g:i A', strtotime($activity['time'])) ?>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            <?php else: ?>
                <!-- Content for other pages will be loaded here -->
                <div class="alert alert-info">
                    <i class='bx bx-info-circle'></i> Please navigate using the sidebar menu to access different modules.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto refresh dashboard data every 60 seconds
        <?php if ($active_tab == 'dashboard'): ?>
        setInterval(function() {
            // In a real application, you would fetch updated data via AJAX
            console.log('Refreshing dashboard data...');
            
            // Simple visual indicator that data is refreshing
            document.querySelectorAll('.stat-trend').forEach(trend => {
                trend.innerHTML = '<i class="bx bx-refresh"></i> <span>Updating...</span>';
            });
            
            // Simulate data refresh
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        }, 60000);
        <?php endif; ?>
        
        // Initialize sidebar dropdowns
        document.addEventListener('DOMContentLoaded', function() {
            // Set active dropdowns based on current page
            const activeTab = '<?= $active_tab ?>';
            if (activeTab && activeTab !== 'dashboard') {
                const dropdown = document.getElementById(activeTab + 'Dropdown');
                if (dropdown) {
                    dropdown.classList.add('show');
                }
            }
        });
    </script>
</body>
</html>