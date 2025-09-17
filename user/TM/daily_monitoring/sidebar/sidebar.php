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
                    <a href="../../TM/manual_logs/admin_traffic_log.php" class="sidebar-dropdown-link">
                        <i class='bx bx-clipboard'></i> Manual Traffic Logs
                    </a>
                    <a href="../../TM/traffic_volume/tv.php" class="sidebar-dropdown-link ">
                        <i class='bx bx-signal-4'></i> Traffic Volume Status
                    </a>
                    <a href="../../TM/daily_monitoring/daily_monitoring.php" class="sidebar-dropdown-link">
                        <i class='bx bx-report'></i> Daily Monitoring Reports
                    </a>
                     <a href="../../TM/cctv_integration/admin_cctv.php" class="sidebar-dropdown-link">
                        <i class='bx bx-report'></i> CCTV Integration
                    </a>
                </div>

                <!-- Real-time Road Update with Dropdown -->
                <div class="dropdown-toggle sidebar-link" data-bs-toggle="collapse" data-bs-target="#rruDropdown" aria-expanded="false">
                    <i class='bx bx-road'></i>
                    <span class="text">Real-time Road Update</span>
                </div>
                <div class="sidebar-dropdown collapse" id="rruDropdown">
                    <a href="../../RTR/post_dashboard/admin_road_updates.php" class="sidebar-dropdown-link">
                        <i class='bx bx-edit'></i> Post Dashboard
                    </a>

                <a href="../../RTR/status_management/status_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-stats'></i> Status Management
                    </a>
                <a href="../../RTR/real_time_road/admin_road_updates.php" class="sidebar-dropdown-link">
                        <i class='bx bx-bell'></i> Real Time Road
                    </a>
                </div>

                <!-- Accident & Violation Report with Dropdown -->
                <div class="dropdown-toggle sidebar-link <?= $active_tab == 'accident_violation' ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#avrDropdown" aria-expanded="<?= $active_tab == 'accident_violation' ? 'true' : 'false' ?>">
                    <i class='bx bx-error-circle'></i>
                    <span class="text">Accident & Violation Report</span>
                </div>
               <div class="sidebar-dropdown collapse <?= $active_tab == 'routing_diversion' ? 'show' : '' ?>" id="vrdDropdown">
                    <a href="../../AVR/report_management/report_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-directions'></i> Report Management
                    </a>
                    <a href="../../AVR/violation_categorization/violation_categorization.php" class="sidebar-dropdown-link">
                        <i class='bx bx-notification'></i> Violation Categorization
                    </a>
                    <a href="../../AVR/evidence_handling/evidence_admin.php" class="sidebar-dropdown-link">
                        <i class='bx bx-edit-alt'></i> Evidence Handling
                    </a>
                    <a href="../../AVR/violation_records/admin_violation_records.php" class="sidebar-dropdown-link">
                        <i class='bx bx-check-circle'></i> Violation Record 
                    </a>
                     <a href="../../AVR/escalation_assignment/admin_escalation_assignment.php" class="sidebar-dropdown-link">
                        <i class='bx bx-check-circle'></i> Escalation & Assignment  
                    </a>
                </div>

                  <!-- Vehicle Routing & Diversion with Dropdown -->
                <div class="dropdown-toggle sidebar-link <?= $active_tab == 'routing_diversion' ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#vrdDropdown" aria-expanded="<?= $active_tab == 'routing_diversion' ? 'true' : 'false' ?>">
                    <i class='bx bx-map-alt'></i>
                    <span class="text">Vehicle Routing & Diversion</span>
                </div>
                <div class="sidebar-dropdown collapse <?= $active_tab == 'routing_diversion' ? 'show' : '' ?>" id="vrdDropdown">
                    <a href="../../VRD/route_configuration_panel/route_configuration_panel.php" class="sidebar-dropdown-link">
                        <i class='bx bx-directions'></i> Route Configuration Panel
                    </a>
                    <a href="../../VRD/diversion_planning/diversion_planning.php" class="sidebar-dropdown-link">
                        <i class='bx bx-notification'></i> Diversion Planning
                    </a>
                    <a href="../../VRD/ai_rule_management/rule_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-edit-alt'></i> AI Rule Management
                    </a>
                    <a href="../../VRD/osm/osm_integration.php" class="sidebar-dropdown-link">
                        <i class='bx bx-check-circle'></i> OSM (Leaflet) Integration
                    </a>
                     <a href="../../VRD/routing_analytics/routing_analytics.php" class="sidebar-dropdown-link">
                        <i class='bx bx-check-circle'></i> Routing Analytics 
                    </a>
                </div>

                <!-- Traffic Signal Control with Dropdown -->
                <div class="dropdown-toggle sidebar-link <?= $active_tab == 'signal_control' ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#tscDropdown" aria-expanded="<?= $active_tab == 'signal_control' ? 'true' : 'false' ?>">
                    <i class='bx bx-traffic-light'></i>
                    <span class="text">Traffic Signal Control</span>
                </div>
                <div class="sidebar-dropdown collapse <?= $active_tab == 'signal_control' ? 'show' : '' ?>" id="tscDropdown">
                    <a href="../../TSC/signal_timing_management/signal_timing_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-slider-alt'></i> Signal Timing Management
                    </a>
                    <a href="../../TSC/real_time_signal_override/real_time_signal_override.php" class="sidebar-dropdown-link">
                        <i class='bx bx-time-five'></i> Real-Time Signal Override
                    </a>
                    <a href="../../TSC/automation_settings/admin_traffic_log.php" class="sidebar-dropdown-link">
                        <i class='bx bx-calendar'></i> Automation Settings
                    </a>
                       <a href="../../TSC/performance_logs/performance_logs.php" class="sidebar-dropdown-link">
                        <i class='bx bx-calendar'></i> Performance Logs 
                    </a>
                </div>

                <!-- Public Transport with Dropdown -->
                <div class="dropdown-toggle sidebar-link <?= $active_tab == 'public_transport' ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#ptsDropdown" aria-expanded="<?= $active_tab == 'public_transport' ? 'true' : 'false' ?>">
                    <i class='bx bx-bus'></i>
                    <span class="text">Public Transport</span>
                </div>
                <div class="sidebar-dropdown collapse <?= $active_tab == 'public_transport' ? 'show' : '' ?>" id="ptsDropdown">
                    <a href="../../PT/fleet_management/fleet_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-table'></i> Fleet Management
                    </a>
                    <a href="../../PT/route_and_schedule/route_and_schedule.php" class="sidebar-dropdown-link">
                        <i class='bx bx-time'></i> Route & Schedule Management
                    </a>
                    <a href="../../PT/real_time_tracking/real_time_tracking.php" class="sidebar-dropdown-link">
                        <i class='bx bx-info-circle'></i> Real-Time Tracking
                    </a>
                     <a href="../../PT/passenger_capacity_compliance/passenger_capacity_compliance.php" class="sidebar-dropdown-link">
                        <i class='bx bx-info-circle'></i> Passenger Capacity Compliance 
                    </a>
                </div>

                <!-- Permit & Ticketing System with Dropdown -->
                <div class="dropdown-toggle sidebar-link <?= $active_tab == 'permit_ticketing' ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#patsDropdown" aria-expanded="<?= $active_tab == 'permit_ticketing' ? 'true' : 'false' ?>">
                    <i class='bx bx-receipt'></i>
                    <span class="text">Permit & Ticketing System</span>
                </div>
                <div class="sidebar-dropdown collapse <?= $active_tab == 'permit_ticketing' ? 'show' : '' ?>" id="patsDropdown">
                    <a href="../../PTS/permit_application_processing/permit_application_processing.php" class="sidebar-dropdown-link">
                        <i class='bx bx-file'></i> Permit Application Processing
                    </a>
                    <a href="../../PTS/ticket_issuance_control/ticket_issuance_control.php" class="sidebar-dropdown-link">
                        <i class='bx bx-list-check'></i> Ticket Issuance Control          
                    </a>
                    <a href="../../PTS/payment_settlement_management/payment_settlement_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-show'></i> Payment & Settlement Management
                    </a>
                      <a href="../../PTS/offender_management/offender_management.php" class="sidebar-dropdown-link">
                        <i class='bx bx-show'></i> Database of Offenders
                    </a>
                      <a href="../../PTS/compliance_revenue_reports/compliance_revenue_reports.php" class="sidebar-dropdown-link">
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