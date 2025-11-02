<?php
/**
 * Maintenance Functions - Database Based (FIXED VERSION v2)
 */

// Pastikan koneksi database sudah ada - FIXED PATH untuk config folder
if (!isset($koneksi)) {
    // File ini ada di /config/, jadi koneksi.php ada di folder yang sama
    $koneksi_path = __DIR__ . "/koneksi.php";
    if (file_exists($koneksi_path)) {
        require_once $koneksi_path;
    } else {
        error_log("Koneksi file not found at: " . $koneksi_path);
        throw new Exception("Database configuration file not found");
    }
}

/**
 * Get maintenance status from database
 */
function get_maintenance_status_db() {
    global $koneksi;
    
    try {
        // Validate connection
        if (!$koneksi) {
            error_log("Database connection is null");
            return get_default_maintenance_status();
        }
        
        // Check if table exists
        $table_check = $koneksi->query("SHOW TABLES LIKE 'maintenance_mode'");
        if (!$table_check || $table_check->rowCount() == 0) {
            error_log("Maintenance table does not exist, creating...");
            ensure_maintenance_table_exists();
        }
        
        $sql = "SELECT * FROM maintenance_mode ORDER BY id DESC LIMIT 1";
        $stmt = $koneksi->prepare($sql);
        
        if (!$stmt) {
            error_log("Failed to prepare statement");
            return get_default_maintenance_status();
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Convert tinyint to boolean
            $result['is_active'] = (bool)$result['is_active'];
            return $result;
        }
        
        return get_default_maintenance_status();
        
    } catch (PDOException $e) {
        error_log("Maintenance DB Error: " . $e->getMessage());
        return get_default_maintenance_status();
    } catch (Exception $e) {
        error_log("Maintenance Error: " . $e->getMessage());
        return get_default_maintenance_status();
    }
}

/**
 * Default maintenance status when no record exists
 */
function get_default_maintenance_status() {
    return [
        'id' => 0,
        'is_active' => false,
        'maintenance_html' => '',
        'activated_at' => null,
        'deactivated_at' => null,
        'scheduled_start' => null,
        'scheduled_end' => null,
        'created_at' => null,
        'updated_at' => null
    ];
}

/**
 * Enable maintenance mode with HTML content
 */
function enable_maintenance_db($html_content) {
    global $koneksi;
    
    try {
        // Validate database connection
        if (!$koneksi) {
            throw new Exception("Database connection not available");
        }
        
        // Validate HTML content
        if (empty($html_content)) {
            throw new Exception("HTML content cannot be empty");
        }
        
        // Check if table exists, create if not
        ensure_maintenance_table_exists();
        
        $koneksi->beginTransaction();
        
        // First, deactivate any active maintenance
        $sql_deactivate = "UPDATE maintenance_mode SET is_active = 0, deactivated_at = NOW() WHERE is_active = 1";
        $stmt_deactivate = $koneksi->prepare($sql_deactivate);
        $stmt_deactivate->execute();
        
        // Insert new maintenance record
        $sql = "INSERT INTO maintenance_mode (is_active, maintenance_html, activated_at, created_at, updated_at) 
                VALUES (1, :html_content, NOW(), NOW(), NOW())";
        $stmt = $koneksi->prepare($sql);
        $result = $stmt->execute([':html_content' => $html_content]);
        
        if (!$result) {
            throw new Exception("Failed to insert maintenance record");
        }
        
        $koneksi->commit();
        
        return ['success' => true, 'message' => 'Maintenance mode diaktifkan'];
        
    } catch (PDOException $e) {
        if (isset($koneksi) && $koneksi->inTransaction()) {
            $koneksi->rollBack();
        }
        error_log("Enable Maintenance DB Error (PDO): " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        if (isset($koneksi) && $koneksi->inTransaction()) {
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
        // Validate database connection
        if (!$koneksi) {
            throw new Exception("Database connection not available");
        }
        
        $sql = "UPDATE maintenance_mode 
                SET is_active = 0, deactivated_at = NOW(), updated_at = NOW()
                WHERE is_active = 1";
        $stmt = $koneksi->prepare($sql);
        $result = $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Maintenance mode dimatikan'];
        } else {
            return ['success' => true, 'message' => 'Tidak ada maintenance mode yang aktif'];
        }
        
    } catch (PDOException $e) {
        error_log("Disable Maintenance DB Error (PDO): " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        error_log("Disable Maintenance DB Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Ensure maintenance_mode table exists
 */
function ensure_maintenance_table_exists() {
    global $koneksi;
    
    try {
        if (!$koneksi) {
            throw new Exception("Database connection not available");
        }
        
        $table_check = $koneksi->query("SHOW TABLES LIKE 'maintenance_mode'");
        
        if (!$table_check || $table_check->rowCount() == 0) {
            // Create the table with correct syntax
            $sql = "CREATE TABLE IF NOT EXISTS maintenance_mode (
                id INT AUTO_INCREMENT PRIMARY KEY,
                is_active TINYINT(1) DEFAULT 0,
                maintenance_html LONGTEXT NULL,
                activated_at TIMESTAMP NULL DEFAULT NULL,
                deactivated_at TIMESTAMP NULL DEFAULT NULL,
                scheduled_start TIMESTAMP NULL DEFAULT NULL,
                scheduled_end TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_is_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $koneksi->exec($sql);
            error_log("Maintenance table created successfully");
            return true;
        }
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Create table error (PDO): " . $e->getMessage());
        throw new Exception("Failed to create maintenance table: " . $e->getMessage());
    } catch (Exception $e) {
        error_log("Create table error: " . $e->getMessage());
        throw new Exception("Failed to create maintenance table: " . $e->getMessage());
    }
}

/**
 * Serve maintenance page if active
 */
function serve_maintenance_page_if_active() {
    // Skip maintenance check for these pages
    $allowed_pages = ['maintenance.php', 'auth.php', 'login.php', 'admin.php', 'admin_auth.php'];
    $current_page = basename($_SERVER['PHP_SELF']);
    
    // Skip maintenance for API endpoints and assets
    $allowed_paths = ['/api/', '/assets/', '/css/', '/js/', '/img/', '/images/', '/vendor/'];
    $current_path = $_SERVER['REQUEST_URI'] ?? '';
    
    foreach ($allowed_paths as $path) {
        if (strpos($current_path, $path) !== false) {
            return;
        }
    }
    
    if (in_array($current_page, $allowed_pages)) {
        return;
    }
    
    // Skip if it's an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        return;
    }
    
    try {
        $status = get_maintenance_status_db();
        
        if (isset($status['is_active']) && $status['is_active'] && !empty($status['maintenance_html'])) {
            // Set HTTP 503 status
            http_response_code(503);
            header('Retry-After: 3600'); // 1 hour
            header('X-Maintenance-Mode: Active');
            header('Content-Type: text/html; charset=utf-8');
            
            // Output the HTML
            echo $status['maintenance_html'];
            exit;
        }
    } catch (Exception $e) {
        // If there's an error checking maintenance status, continue normally
        error_log("Maintenance check error: " . $e->getMessage());
        return;
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
            padding: 20px;
        }
        .container {
            max-width: 600px;
            width: 100%;
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
        @media (max-width: 480px) {
            .container {
                padding: 20px;
            }
            h1 {
                font-size: 1.8rem;
            }
            .icon {
                font-size: 3rem;
            }
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
        
        // Show current time
        document.addEventListener("DOMContentLoaded", function() {
            var now = new Date();
            var timeString = now.toLocaleString("id-ID");
            var timeElement = document.createElement("div");
            timeElement.style.marginTop = "10px";
            timeElement.style.fontSize = "0.8rem";
            timeElement.style.color = "#6c757d";
            timeElement.innerHTML = "Waktu akses: " + timeString;
            document.querySelector(".footer").appendChild(timeElement);
        });
    </script>
</body>
</html>';
}

/**
 * Get maintenance statistics
 */
function get_maintenance_stats() {
    global $koneksi;
    
    try {
        if (!$koneksi) {
            throw new Exception("Database connection not available");
        }
        
        $sql = "SELECT 
                COUNT(*) as total_activations,
                SUM(is_active) as currently_active,
                MAX(activated_at) as last_activation
                FROM maintenance_mode";
        $stmt = $koneksi->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Maintenance stats error: " . $e->getMessage());
        return ['total_activations' => 0, 'currently_active' => 0, 'last_activation' => null];
    }
}
?>
