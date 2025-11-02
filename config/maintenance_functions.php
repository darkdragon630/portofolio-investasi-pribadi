<?php
/**
 * Maintenance Functions - Database Based
 */

require_once "koneksi.php";

/**
 * Get maintenance status from database
 */
function get_maintenance_status_db() {
    global $koneksi;
    
    try {
        $sql = "SELECT * FROM maintenance_mode ORDER BY id DESC LIMIT 1";
        $stmt = $koneksi->prepare($sql);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result;
        }
        
        // Return default if no record exists
        return [
            'id' => 0,
            'is_active' => false,
            'maintenance_html' => '',
            'activated_at' => null,
            'deactivated_at' => null
        ];
        
    } catch (Exception $e) {
        error_log("Maintenance DB Error: " . $e->getMessage());
        return ['is_active' => false];
    }
}

/**
 * Enable maintenance mode with HTML content
 */
function enable_maintenance_db($html_content) {
    global $koneksi;
    
    try {
        $koneksi->beginTransaction();
        
        // First, deactivate any active maintenance
        $sql_deactivate = "UPDATE maintenance_mode SET is_active = false, deactivated_at = NOW() WHERE is_active = true";
        $stmt_deactivate = $koneksi->prepare($sql_deactivate);
        $stmt_deactivate->execute();
        
        // Insert new maintenance record
        $sql = "INSERT INTO maintenance_mode (is_active, maintenance_html, activated_at) 
                VALUES (true, ?, NOW())";
        $stmt = $koneksi->prepare($sql);
        $result = $stmt->execute([$html_content]);
        
        $koneksi->commit();
        
        return ['success' => true, 'message' => 'Maintenance mode diaktifkan'];
        
    } catch (Exception $e) {
        $koneksi->rollBack();
        error_log("Enable Maintenance DB Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Disable maintenance mode
 */
function disable_maintenance_db() {
    global $koneksi;
    
    try {
        $sql = "UPDATE maintenance_mode 
                SET is_active = false, deactivated_at = NOW() 
                WHERE is_active = true";
        $stmt = $koneksi->prepare($sql);
        $result = $stmt->execute();
        
        return ['success' => true, 'message' => 'Maintenance mode dimatikan'];
        
    } catch (Exception $e) {
        error_log("Disable Maintenance DB Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Serve maintenance page if active
 */
function serve_maintenance_page_if_active() {
    $status = get_maintenance_status_db();
    
    if ($status['is_active'] && !empty($status['maintenance_html'])) {
        // Set HTTP 503 status
        http_response_code(503);
        header('Retry-After: 3600'); // 1 hour
        header('X-Maintenance-Mode: Active');
        
        // Output the HTML
        echo $status['maintenance_html'];
        exit;
    }
}

/**
 * Check if current user can bypass maintenance (admin users)
 */
function can_bypass_maintenance() {
    session_start();
    
    // Allow access to maintenance.php and auth.php
    $allowed_pages = ['maintenance.php', 'auth.php', 'login.php'];
    $current_page = basename($_SERVER['PHP_SELF']);
    
    if (in_array($current_page, $allowed_pages)) {
        return true;
    }
    
    // Allow admin users to bypass maintenance
    if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') == 'admin') {
        return true;
    }
    
    return false;
}
?>
