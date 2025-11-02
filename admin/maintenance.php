// ========================================
// MAINTENANCE FUNCTIONS
// ========================================

/**
 * Check maintenance status
 */
function check_maintenance_status() {
    $status_file = __DIR__ . '/../maintenance/maintenance_status.json';
    $default_status = [
        'active' => false,
        'activated_at' => null,
        'scheduled' => false,
        'schedule_start' => null,
        'schedule_end' => null
    ];
    
    if (file_exists($status_file)) {
        $status = json_decode(file_get_contents($status_file), true);
        return array_merge($default_status, $status);
    }
    
    return $default_status;
}

/**
 * Handle maintenance file upload
 */
function handle_maintenance_upload($file) {
    try {
        // Validate file
        if ($file['type'] != 'text/html' && pathinfo($file['name'], PATHINFO_EXTENSION) != 'html') {
            throw new Exception("File harus berupa HTML (index.html)");
        }
        
        if ($file['size'] > 5 * 1024 * 1024) { // 5MB max
            throw new Exception("File terlalu besar. Maksimal 5MB");
        }
        
        // Create maintenance directory if not exists
        $maintenance_dir = __DIR__ . '/../maintenance';
        if (!file_exists($maintenance_dir)) {
            mkdir($maintenance_dir, 0755, true);
        }
        
        // Backup existing index.html if exists
        if (file_exists($maintenance_dir . '/index.html')) {
            $backup_dir = $maintenance_dir . '/backups';
            if (!file_exists($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }
            $backup_name = 'index_backup_' . date('Y-m-d_H-i-s') . '.html';
            rename($maintenance_dir . '/index.html', $backup_dir . '/' . $backup_name);
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $maintenance_dir . '/index.html')) {
            // Update maintenance status
            $status = [
                'active' => true,
                'activated_at' => time(),
                'scheduled' => false,
                'schedule_start' => null,
                'schedule_end' => null
            ];
            
            file_put_contents($maintenance_dir . '/maintenance_status.json', json_encode($status));
            
            return ['success' => true, 'message' => 'Maintenance mode diaktifkan'];
        } else {
            throw new Exception("Gagal mengupload file");
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Disable maintenance mode
 */
function disable_maintenance_mode() {
    try {
        $maintenance_dir = __DIR__ . '/../maintenance';
        
        // Archive current index.html
        if (file_exists($maintenance_dir . '/index.html')) {
            $archive_dir = $maintenance_dir . '/archive';
            if (!file_exists($archive_dir)) {
                mkdir($archive_dir, 0755, true);
            }
            $archive_name = 'index_archive_' . date('Y-m-d_H-i-s') . '.html';
            rename($maintenance_dir . '/index.html', $archive_dir . '/' . $archive_name);
        }
        
        // Update maintenance status
        $status = [
            'active' => false,
            'activated_at' => null,
            'scheduled' => false,
            'schedule_start' => null,
            'schedule_end' => null
        ];
        
        file_put_contents($maintenance_dir . '/maintenance_status.json', json_encode($status));
        
        return ['success' => true, 'message' => 'Maintenance mode dimatikan'];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Schedule maintenance
 */
function schedule_maintenance($start_time, $end_time) {
    try {
        $maintenance_dir = __DIR__ . '/../maintenance';
        if (!file_exists($maintenance_dir)) {
            mkdir($maintenance_dir, 0755, true);
        }
        
        $status = [
            'active' => false,
            'activated_at' => null,
            'scheduled' => true,
            'schedule_start' => $start_time,
            'schedule_end' => $end_time
        ];
        
        file_put_contents($maintenance_dir . '/maintenance_status.json', json_encode($status));
        
        return ['success' => true, 'message' => 'Maintenance terjadwal'];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Cancel scheduled maintenance
 */
function cancel_scheduled_maintenance() {
    try {
        $maintenance_dir = __DIR__ . '/../maintenance';
        
        $status = [
            'active' => false,
            'activated_at' => null,
            'scheduled' => false,
            'schedule_start' => null,
            'schedule_end' => null
        ];
        
        file_put_contents($maintenance_dir . '/maintenance_status.json', json_encode($status));
        
        return ['success' => true, 'message' => 'Jadwal maintenance dibatalkan'];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Check if maintenance should be active (for cron job)
 */
function check_maintenance_schedule() {
    $status = check_maintenance_status();
    
    if ($status['scheduled']) {
        $now = time();
        $start = strtotime($status['schedule_start']);
        $end = strtotime($status['schedule_end']);
        
        if ($now >= $start && $now <= $end) {
            // Within scheduled time, activate maintenance
            if (!$status['active']) {
                // Activate maintenance mode
                $maintenance_dir = __DIR__ . '/../maintenance';
                $status['active'] = true;
                $status['activated_at'] = $now;
                file_put_contents($maintenance_dir . '/maintenance_status.json', json_encode($status));
            }
            return true;
        } elseif ($now > $end) {
            // Past scheduled time, deactivate
            disable_maintenance_mode();
            return false;
        }
    }
    
    return $status['active'];
}
