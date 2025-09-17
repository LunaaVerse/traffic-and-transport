# TODO: Grant Access for All Users - Remove 403 Restrictions

## Overview
Remove role-based access restrictions from admin and restricted pages to allow all logged-in users access, fixing 403 Forbidden errors.

## Tasks
- [ ] Remove role checks from admin/PTS/permit_application_processing/permit_application_proccessing.php
- [ ] Remove role checks from admin/TSC/signal_timing_management/signal_timing_management.php
- [ ] Remove role checks from admin/TSC/real_time_signal_override/real_time.php
- [ ] Remove role checks from admin/TSC/performance_logs/daily_monitoring.php
- [ ] Remove role checks from admin/RTR/real_time_road/admin_road_updates.php
- [ ] Remove role checks from admin/VRD/ai_rule_management/rule_management.php
- [ ] Remove role checks from admin/VRD/diversion_planning/diversion_planning.php
- [ ] Remove role checks from user/PTS/permit_application_processing/permit_application_proccessing.php
- [ ] Verify all changes and test access
