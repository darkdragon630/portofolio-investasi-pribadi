<?php
/**
 * Simple maintenance checker - run via cron
 */

$maintenance_dir = __DIR__ . '/maintenance';
$status_file = $maintenance_dir . '/maintenance_status.json';

// Check if maintenance should be active
if (file_exists($status_file)) {
    $status = json_decode(file_get_contents($status_file), true);
    
    if ($status['active'] ?? false) {
        // Maintenance is active
        error_log("Maintenance mode is ACTIVE - " . date('Y-m-d H:i:s'));
    } else {
        // Maintenance is inactive
        error_log("Maintenance mode is INACTIVE - " . date('Y-m-d H:i:s'));
    }
} else {
    error_log("No maintenance status file found");
}
