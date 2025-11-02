<?php
/**
 * Cron job untuk check maintenance schedule
 * Jalankan setiap menit: * * * * * php /path/to/check_maintenance.php
 */

require_once "config/functions.php";

// Check maintenance schedule
check_maintenance_schedule();

error_log("Maintenance schedule checked: " . date('Y-m-d H:i:s'));
