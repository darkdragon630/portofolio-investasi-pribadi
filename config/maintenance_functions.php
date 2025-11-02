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

/**
 * Clean old snapshots (keep last N days only)
 * Should be run monthly via cron or manually
 * 
 * @param PDO $koneksi Database connection
 * @param int $keep_days Number of days to keep
 * @return int Number of deleted records
 */
function cleanup_old_snapshots($koneksi, $keep_days = 90) {
    try {
        if (!$koneksi) {
            throw new Exception("Database connection not available");
        }
        
        $cutoff_date = date('Y-m-d', strtotime("-$keep_days days"));
        
        $sql = "DELETE FROM investasi_snapshot_harian 
                WHERE tanggal_snapshot < ?";
        $stmt = $koneksi->prepare($sql);
        $stmt->execute([$cutoff_date]);
        
        $deleted = $stmt->rowCount();
        error_log("Cleanup: Deleted $deleted old snapshot records (older than $cutoff_date)");
        
        return $deleted;
        
    } catch (Exception $e) {
        error_log("Cleanup Snapshots Error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Initialize snapshots for existing investments
 * Run once after database migration or for investments without snapshots
 * 
 * @param PDO $koneksi Database connection
 * @return array Result
 */
function initialize_snapshots_for_existing_investments($koneksi) {
    try {
        if (!$koneksi) {
            throw new Exception("Database connection not available");
        }
        
        $sql = "SELECT id FROM investasi WHERE status = 'aktif'";
        $stmt = $koneksi->query($sql);
        $investments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $initialized = 0;
        foreach ($investments as $inv) {
            // Call auto_recalculate_investment from auto_calculate_investment.php
            if (function_exists('auto_recalculate_investment')) {
                $result = auto_recalculate_investment($koneksi, $inv['id']);
                if ($result['success']) {
                    $initialized++;
                }
            } else {
                error_log("Function auto_recalculate_investment not found");
                break;
            }
        }
        
        return [
            'success' => true,
            'initialized' => $initialized,
            'total' => count($investments)
        ];
        
    } catch (Exception $e) {
        error_log("Initialize Snapshots Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Create database backup (export to SQL)
 * 
 * @param PDO $koneksi Database connection
 * @return array Result with SQL content
 */
function create_database_backup($koneksi) {
    try {
        if (!$koneksi) {
            throw new Exception("Database connection not available");
        }
        
        $dbname = DB_NAME;
        $backup_content = "-- SAZEN Investment Portfolio Backup\n";
        $backup_content .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $backup_content .= "-- Database: $dbname\n\n";
        $backup_content .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $backup_content .= "SET time_zone = \"+00:00\";\n\n";
        
        // Tables to backup
        $tables = [
            'kategori',
            'investasi',
            'keuntungan_investasi',
            'kerugian_investasi',
            'cash_balance',
            'investasi_snapshot_harian',
            'maintenance_mode',
            'system_notifications'
        ];
        
        foreach ($tables as $table) {
            // Check if table exists
            $check = $koneksi->query("SHOW TABLES LIKE '$table'");
            if ($check->rowCount() == 0) {
                continue; // Skip if table doesn't exist
            }
            
            $backup_content .= "\n-- Table: $table\n";
            $backup_content .= "DROP TABLE IF EXISTS `$table`;\n";
            
            // Get CREATE TABLE statement
            $create = $koneksi->query("SHOW CREATE TABLE `$table`")->fetch();
            $backup_content .= $create['Create Table'] . ";\n\n";
            
            // Get table data
            $rows = $koneksi->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($rows) > 0) {
                $backup_content .= "INSERT INTO `$table` VALUES\n";
                $values = [];
                
                foreach ($rows as $row) {
                    $vals = [];
                    foreach ($row as $val) {
                        if ($val === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = "'" . addslashes($val) . "'";
                        }
                    }
                    $values[] = '(' . implode(', ', $vals) . ')';
                }
                
                $backup_content .= implode(",\n", $values) . ";\n\n";
            }
        }
        
        $filename = 'sazen_backup_' . date('Y-m-d_His') . '.sql';
        
        return [
            'success' => true,
            'filename' => $filename,
            'sql_content' => $backup_content
        ];
        
    } catch (Exception $e) {
        error_log("Database Backup Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Run system health check
 * 
 * @param PDO $koneksi Database connection
 * @return array Health check results
 */
function run_system_health_check($koneksi) {
    $checks = [];
    
    // Check 1: Database Connection
    try {
        if ($koneksi && $koneksi->query("SELECT 1")->fetchColumn() == 1) {
            $checks[] = [
                'name' => 'Database Connection',
                'status' => 'OK',
                'message' => 'Database connected successfully'
            ];
        }
    } catch (Exception $e) {
        $checks[] = [
            'name' => 'Database Connection',
            'status' => 'ERROR',
            'message' => 'Failed to connect: ' . $e->getMessage()
        ];
    }
    
    // Check 2: Required Tables
    $required_tables = ['investasi', 'keuntungan_investasi', 'kerugian_investasi', 'kategori', 'cash_balance'];
    $missing_tables = [];
    
    foreach ($required_tables as $table) {
        $result = $koneksi->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() == 0) {
            $missing_tables[] = $table;
        }
    }
    
    if (empty($missing_tables)) {
        $checks[] = [
            'name' => 'Database Tables',
            'status' => 'OK',
            'message' => 'All required tables exist'
        ];
    } else {
        $checks[] = [
            'name' => 'Database Tables',
            'status' => 'ERROR',
            'message' => 'Missing tables: ' . implode(', ', $missing_tables)
        ];
    }
    
    // Check 3: Disk Space (if possible)
    $disk_free = @disk_free_space(__DIR__);
    $disk_total = @disk_total_space(__DIR__);
    
    if ($disk_free && $disk_total) {
        $disk_percent = ($disk_free / $disk_total) * 100;
        
        if ($disk_percent > 20) {
            $checks[] = [
                'name' => 'Disk Space',
                'status' => 'OK',
                'message' => number_format($disk_percent, 1) . '% free (' . format_bytes($disk_free) . ')'
            ];
        } elseif ($disk_percent > 10) {
            $checks[] = [
                'name' => 'Disk Space',
                'status' => 'WARNING',
                'message' => 'Low disk space: ' . number_format($disk_percent, 1) . '% free'
            ];
        } else {
            $checks[] = [
                'name' => 'Disk Space',
                'status' => 'ERROR',
                'message' => 'Critical: Only ' . number_format($disk_percent, 1) . '% free'
            ];
        }
    }
    
    // Check 4: PHP Version
    $php_version = PHP_VERSION;
    if (version_compare($php_version, '7.4.0', '>=')) {
        $checks[] = [
            'name' => 'PHP Version',
            'status' => 'OK',
            'message' => 'PHP ' . $php_version
        ];
    } else {
        $checks[] = [
            'name' => 'PHP Version',
            'status' => 'WARNING',
            'message' => 'PHP ' . $php_version . ' (recommended 7.4+)'
        ];
    }
    
    // Check 5: Data Integrity
    try {
        $orphaned = $koneksi->query("
            SELECT COUNT(*) as count 
            FROM keuntungan_investasi k 
            LEFT JOIN investasi i ON k.investasi_id = i.id 
            WHERE i.id IS NULL
        ")->fetchColumn();
        
        if ($orphaned == 0) {
            $checks[] = [
                'name' => 'Data Integrity',
                'status' => 'OK',
                'message' => 'No orphaned records found'
            ];
        } else {
            $checks[] = [
                'name' => 'Data Integrity',
                'status' => 'WARNING',
                'message' => $orphaned . ' orphaned keuntungan records found'
            ];
        }
    } catch (Exception $e) {
        $checks[] = [
            'name' => 'Data Integrity',
            'status' => 'ERROR',
            'message' => 'Failed to check: ' . $e->getMessage()
        ];
    }
    
    // Check 6: Storage Directory
    $storage_dir = __DIR__ . '/../storage/json/';
    if (is_dir($storage_dir) && is_writable($storage_dir)) {
        $checks[] = [
            'name' => 'Storage Directory',
            'status' => 'OK',
            'message' => 'Writable and accessible'
        ];
    } else {
        $checks[] = [
            'name' => 'Storage Directory',
            'status' => 'ERROR',
            'message' => 'Not writable or missing'
        ];
    }
    
    // Overall status
    $has_error = false;
    $has_warning = false;
    
    foreach ($checks as $check) {
        if ($check['status'] === 'ERROR') $has_error = true;
        if ($check['status'] === 'WARNING') $has_warning = true;
    }
    
    $overall_status = $has_error ? 'CRITICAL' : ($has_warning ? 'WARNING' : 'HEALTHY');
    
    return [
        'checks' => $checks,
        'overall_status' => $overall_status,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

/**
 * Validate and repair data inconsistencies
 * 
 * @param PDO $koneksi Database connection
 * @return array Validation results
 */
function validate_and_repair_data($koneksi) {
    $issues_found = 0;
    $fixed = 0;
    $skipped = 0;
    $details = [];
    
    try {
        // Issue 1: Orphaned keuntungan records
        $orphaned_keuntungan = $koneksi->query("
            SELECT k.id FROM keuntungan_investasi k 
            LEFT JOIN investasi i ON k.investasi_id = i.id 
            WHERE i.id IS NULL
        ")->fetchAll();
        
        $issues_found += count($orphaned_keuntungan);
        if (count($orphaned_keuntungan) > 0) {
            $ids = array_column($orphaned_keuntungan, 'id');
            $koneksi->exec("DELETE FROM keuntungan_investasi WHERE id IN (" . implode(',', $ids) . ")");
            $fixed += count($orphaned_keuntungan);
            $details[] = "Deleted " . count($orphaned_keuntungan) . " orphaned keuntungan records";
        }
        
        // Issue 2: Orphaned kerugian records
        $orphaned_kerugian = $koneksi->query("
            SELECT k.id FROM kerugian_investasi k 
            LEFT JOIN investasi i ON k.investasi_id = i.id 
            WHERE i.id IS NULL
        ")->fetchAll();
        
        $issues_found += count($orphaned_kerugian);
        if (count($orphaned_kerugian) > 0) {
            $ids = array_column($orphaned_kerugian, 'id');
            $koneksi->exec("DELETE FROM kerugian_investasi WHERE id IN (" . implode(',', $ids) . ")");
            $fixed += count($orphaned_kerugian);
            $details[] = "Deleted " . count($orphaned_kerugian) . " orphaned kerugian records";
        }
        
        // Issue 3: Invalid investment status
        $invalid_status = $koneksi->query("
            SELECT COUNT(*) FROM investasi 
            WHERE status NOT IN ('aktif', 'terjual', 'rugi_total', 'ditutup')
        ")->fetchColumn();
        
        $issues_found += $invalid_status;
        if ($invalid_status > 0) {
            $koneksi->exec("UPDATE investasi SET status = 'aktif' WHERE status NOT IN ('aktif', 'terjual', 'rugi_total', 'ditutup')");
            $fixed += $invalid_status;
            $details[] = "Fixed $invalid_status invalid status to 'aktif'";
        }
        
        // Issue 4: Negative investment amounts
        $negative_amounts = $koneksi->query("
            SELECT COUNT(*) FROM investasi WHERE jumlah < 0
        ")->fetchColumn();
        
        $issues_found += $negative_amounts;
        if ($negative_amounts > 0) {
            $details[] = "Found $negative_amounts investments with negative amounts (manual review needed)";
            $skipped += $negative_amounts;
        }
        
        // Issue 5: Investasi without kategori
        $no_category = $koneksi->query("
            SELECT COUNT(*) FROM investasi 
            WHERE kategori_id IS NULL OR kategori_id NOT IN (SELECT id FROM kategori)
        ")->fetchColumn();
        
        $issues_found += $no_category;
        if ($no_category > 0) {
            $details[] = "Found $no_category investments without valid category (manual review needed)";
            $skipped += $no_category;
        }
        
    } catch (Exception $e) {
        error_log("Data Validation Error: " . $e->getMessage());
        $details[] = "Error during validation: " . $e->getMessage();
    }
    
    return [
        'issues_found' => $issues_found,
        'fixed' => $fixed,
        'skipped' => $skipped,
        'details' => $details
    ];
}

/**
 * Get notification settings
 * 
 * @param PDO $koneksi Database connection
 * @return array Settings
 */
function get_notification_settings($koneksi) {
    try {
        // Ensure table exists
        $koneksi->exec("CREATE TABLE IF NOT EXISTS system_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email_enabled TINYINT(1) DEFAULT 0,
            email_address VARCHAR(255) DEFAULT '',
            notify_maintenance TINYINT(1) DEFAULT 1,
            notify_errors TINYINT(1) DEFAULT 1,
            notify_backup TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $stmt = $koneksi->query("SELECT * FROM system_notifications ORDER BY id DESC LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$settings) {
            return [
                'email_enabled' => 0,
                'email_address' => '',
                'notify_maintenance' => 1,
                'notify_errors' => 1,
                'notify_backup' => 1
            ];
        }
        
        return $settings;
        
    } catch (Exception $e) {
        error_log("Get Notification Settings Error: " . $e->getMessage());
        return [
            'email_enabled' => 0,
            'email_address' => '',
            'notify_maintenance' => 1,
            'notify_errors' => 1,
            'notify_backup' => 1
        ];
    }
}

/**
 * Save notification settings
 * 
 * @param PDO $koneksi Database connection
 * @param array $settings Settings to save
 * @return array Result
 */
function save_notification_settings($koneksi, $settings) {
    try {
        // Delete old settings
        $koneksi->exec("DELETE FROM system_notifications");
        
        // Insert new settings
        $sql = "INSERT INTO system_notifications 
                (email_enabled, email_address, notify_maintenance, notify_errors, notify_backup) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $koneksi->prepare($sql);
        $stmt->execute([
            $settings['email_enabled'],
            $settings['email_address'],
            $settings['notify_maintenance'],
            $settings['notify_errors'],
            $settings['notify_backup']
        ]);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        error_log("Save Notification Settings Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Format bytes to human readable
 */
function format_bytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
