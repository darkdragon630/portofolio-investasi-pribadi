<?php
/**
 * Maintenance Functions - Database Based
 */

// Pastikan koneksi database sudah ada
if (!isset($koneksi)) {
    require_once "koneksi.php";
}

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
        if ($koneksi->inTransaction()) {
            $koneksi->rollBack();
        }
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
    // Skip maintenance check for these pages
    $allowed_pages = ['maintenance.php', 'auth.php', 'login.php', 'admin/auth.php'];
    $current_page = basename($_SERVER['PHP_SELF']);
    
    if (in_array($current_page, $allowed_pages)) {
        return;
    }
    
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
 * Default maintenance HTML template
 */
function get_default_maintenance_html() {
    return '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - SAZEN Investment</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            width: 90%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
        }
        .icon {
            font-size: 4rem;
            color: #667eea;
            margin-bottom: 20px;
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 2.2rem;
        }
        .message {
            color: #555;
            margin-bottom: 25px;
            font-size: 1.1rem;
        }
        .status {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
        .contact {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #17a2b8;
        }
        .footer {
            margin-top: 30px;
            color: #6c757d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔧</div>
        <h1>Sedang Dalam Pemeliharaan</h1>
        
        <div class="message">
            <p>SAZEN Investment Portfolio Manager sedang dalam proses pemeliharaan untuk peningkatan sistem.</p>
            <p>Kami akan segera kembali online dalam waktu singkat.</p>
        </div>

        <div class="status">
            <strong>Status:</strong> Maintenance Mode Aktif<br>
            <strong>Perkiraan Selesai:</strong> 1-2 Jam
        </div>

        <div class="contact">
            <strong>Butuh Bantuan?</strong><br>
            Email: support@sazen.com<br>
            Telepon: +62 123 4567 890
        </div>

        <div class="footer">
            <strong>SAZEN v3.1</strong><br>
            Investment Portfolio Management System
        </div>
    </div>

    <script>
        // Auto reload every 5 minutes to check if maintenance is over
        setTimeout(function() {
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>';
}
?>
