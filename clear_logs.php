<?php
/**
 * SAZEN v3.0 - Clear Log Files
 */

$log_files = [
    'auth_errors' => __DIR__ . '/logs/auth_errors.log',
    'security' => __DIR__ . '/logs/security.log',
    'php_errors' => __DIR__ . '/logs/php_errors.log'
];

$log = $_GET['log'] ?? null;
$redirect = $_GET['redirect'] ?? 'view_logs.php';

if ($log && isset($log_files[$log])) {
    $file = $log_files[$log];
    
    if (file_exists($file)) {
        // Backup before clearing (optional)
        $backup_dir = __DIR__ . '/logs/backup/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $backup_file = $backup_dir . $log . '_' . date('Y-m-d_His') . '.log';
        copy($file, $backup_file);
        
        // Clear the log
        file_put_contents($file, '');
        
        $message = "✓ Log '$log' cleared successfully! Backup saved.";
    } else {
        $message = "⚠ Log file does not exist.";
    }
} else {
    $message = "❌ Invalid log type.";
}

header("Location: $redirect&message=" . urlencode($message));
exit;
?>