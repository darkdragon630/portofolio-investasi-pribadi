<?php
/**
 * SAZEN Investment Portfolio Manager v3.1
 * CRON JOB - Daily Update Script
 * 
 * FUNGSI:
 * 1. Recalculate semua investasi aktif
 * 2. Update daily snapshot untuk semua investasi
 * 3. Update monthly performance stats
 * 4. Cleanup old data
 * 5. Generate alerts
 * 
 * SETUP CRON:
 * Tambahkan ke crontab untuk run setiap hari jam 00:05:
 * 5 0 * * * /usr/bin/php /path/to/your/project/config/cron_daily_update.php >> /var/log/sazen_cron.log 2>&1
 * 
 * Atau run setiap jam:
 * 0 * * * * /usr/bin/php /path/to/your/project/config/cron_daily_update.php >> /var/log/sazen_cron.log 2>&1
 * 
 * @version 3.1.0
 * @author SAAZ
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from command line.\n");
}

// Load required files
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auto_calculate_investment.php';

// Start execution
$start_time = microtime(true);
echo "\n";
echo "========================================\n";
echo "SAZEN Daily Update - " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

try {
    // ========================================
    // 1. BATCH RECALCULATE ALL INVESTMENTS
    // ========================================
    echo "[1/5] Recalculating all investments...\n";
    $recalc_result = batch_recalculate_all_investments($koneksi);
    
    if ($recalc_result['success']) {
        echo "   ✅ Success: {$recalc_result['updated_count']} investments updated\n";
        echo "   📊 Global Stats:\n";
        echo "      - Total Keuntungan: " . format_currency($recalc_result['global_stats']['total_keuntungan']) . "\n";
        echo "      - Total Kerugian: " . format_currency($recalc_result['global_stats']['total_kerugian']) . "\n";
        echo "      - Net Profit: " . format_currency($recalc_result['global_stats']['net_profit']) . "\n";
        echo "   ⏱️  Execution time: " . number_format($recalc_result['execution_time'], 2) . " seconds\n\n";
    } else {
        throw new Exception("Recalculation failed");
    }
    
    // ========================================
    // 2. UPDATE DAILY SNAPSHOTS
    // ========================================
    echo "[2/5] Updating daily snapshots...\n";
    $snapshot_count = 0;
    
    $sql = "SELECT id FROM investasi WHERE status = 'aktif'";
    $stmt = $koneksi->query($sql);
    $investments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($investments as $inv) {
        $inv_id = $inv['id'];
        
        // Get current value from view
        $sql_val = "SELECT nilai_sekarang FROM v_investasi_summary WHERE id = ?";
        $stmt_val = $koneksi->prepare($sql_val);
        $stmt_val->execute([$inv_id]);
        $value = $stmt_val->fetchColumn();
        
        if ($value !== false) {
            $result = update_daily_snapshot($koneksi, $inv_id, (float)$value);
            if ($result) $snapshot_count++;
        }
    }
    
    echo "   ✅ Success: {$snapshot_count} snapshots updated\n\n";
    
    // ========================================
    // 3. UPDATE MONTHLY PERFORMANCE STATS
    // ========================================
    echo "[3/5] Updating monthly performance stats...\n";
    $monthly_result = update_monthly_performance($koneksi);
    
    if ($monthly_result) {
        echo "   ✅ Success: Monthly stats updated for current month\n\n";
    } else {
        echo "   ⚠️  Warning: Monthly stats update failed\n\n";
    }
    
    // ========================================
    // 4. CLEANUP OLD DATA
    // ========================================
    echo "[4/5] Cleaning up old data...\n";
    
    // Cleanup snapshots older than 90 days
    $deleted = cleanup_old_snapshots($koneksi, 90);
    echo "   ✅ Deleted $deleted old snapshot records (keeping last 90 days)\n";
    
    // Cleanup old change logs (keep last 180 days)
    $sql_cleanup_logs = "DELETE FROM investasi_change_log 
                        WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)";
    $stmt_cleanup = $koneksi->query($sql_cleanup_logs);
    $deleted_logs = $stmt_cleanup->rowCount();
    echo "   ✅ Deleted $deleted_logs old change log records (keeping last 180 days)\n\n";
    
    // ========================================
    // 5. GENERATE ALERTS
    // ========================================
    echo "[5/5] Generating investment alerts...\n";
    $alerts = get_investment_alerts($koneksi);
    
    if (count($alerts) > 0) {
        echo "   ⚠️  Found " . count($alerts) . " alerts:\n";
        foreach ($alerts as $alert) {
            $icon = $alert['type'] === 'danger' ? '🔴' : ($alert['type'] === 'success' ? '🟢' : '🟡');
            echo "      $icon {$alert['judul']}: {$alert['message']}\n";
        }
        echo "\n";
        
        // TODO: Send email notifications for critical alerts
        // send_alert_email($alerts);
        
    } else {
        echo "   ✅ No alerts\n\n";
    }
    
    // ========================================
    // EXECUTION SUMMARY
    // ========================================
    $total_time = microtime(true) - $start_time;
    
    echo "========================================\n";
    echo "EXECUTION SUMMARY\n";
    echo "========================================\n";
    echo "Start time: " . date('Y-m-d H:i:s', (int)$start_time) . "\n";
    echo "End time: " . date('Y-m-d H:i:s') . "\n";
    echo "Total execution time: " . number_format($total_time, 2) . " seconds\n";
    echo "Memory used: " . number_format(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB\n";
    echo "Status: ✅ SUCCESS\n";
    echo "========================================\n\n";
    
    // Log to database (optional)
    log_cron_execution($koneksi, 'daily_update', 'success', $total_time, 
                      "Updated {$recalc_result['updated_count']} investments, " .
                      "{$snapshot_count} snapshots, " .
                      count($alerts) . " alerts");
    
    exit(0);
    
} catch (Exception $e) {
    $error_msg = $e->getMessage();
    $total_time = microtime(true) - $start_time;
    
    echo "\n";
    echo "========================================\n";
    echo "❌ ERROR OCCURRED\n";
    echo "========================================\n";
    echo "Error: $error_msg\n";
    echo "Time before error: " . number_format($total_time, 2) . " seconds\n";
    echo "========================================\n\n";
    
    error_log("SAZEN Cron Error: $error_msg");
    
    // Log to database
    log_cron_execution($koneksi, 'daily_update', 'error', $total_time, $error_msg);
    
    // TODO: Send error notification email
    // send_error_email($error_msg);
    
    exit(1);
}

/**
 * Log cron execution to database
 * 
 * @param PDO $koneksi Database connection
 * @param string $job_name Job name
 * @param string $status Status: success|error
 * @param float $execution_time Execution time in seconds
 * @param string $details Details
 * @return bool
 */
function log_cron_execution($koneksi, $job_name, $status, $execution_time, $details = '') {
    try {
        // Create table if not exists
        $sql_create = "CREATE TABLE IF NOT EXISTS cron_execution_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_name VARCHAR(100) NOT NULL,
            status ENUM('success', 'error', 'warning') NOT NULL,
            execution_time DECIMAL(10,2) NOT NULL COMMENT 'Seconds',
            details TEXT,
            memory_used VARCHAR(50),
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_job_status (job_name, status),
            INDEX idx_executed (executed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $koneksi->exec($sql_create);
        
        // Insert log
        $sql = "INSERT INTO cron_execution_log 
               (job_name, status, execution_time, details, memory_used) 
               VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $koneksi->prepare($sql);
        return $stmt->execute([
            $job_name,
            $status,
            $execution_time,
            $details,
            number_format(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB'
        ]);
        
    } catch (Exception $e) {
        error_log("Log Cron Execution Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send alert email (optional - implement as needed)
 * 
 * @param array $alerts Alert list
 * @return bool
 */
function send_alert_email($alerts) {
    // TODO: Implement email sending
    // Example using PHPMailer or mail() function
    
    $to = 'burhanjepara41@gmail.com'; // Change to your email
    $subject = 'LUMINARK HOLDINGS Alerts - ' . date('Y-m-d');
    
    $message = "Investment Alerts:\n\n";
    foreach ($alerts as $alert) {
        $message .= "- [{$alert['type']}] {$alert['judul']}: {$alert['message']}\n";
        $message .= "  Action: {$alert['action']}\n\n";
    }
    
    // mail($to, $subject, $message);
    
    return true;
}

// ========================================
// EOF - SAZEN Cron Job v3.1
// ========================================