<?php
/**
 * SAZEN - Maintenance System (Database Based) - FIXED VERSION
 */

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start session dengan error handling
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication Check - hanya user yang login bisa akses
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

// Include database connection from config folder (naik 1 level dari admin)
$koneksi_file = __DIR__ . "/../config/koneksi.php";
if (!file_exists($koneksi_file)) {
    die("❌ Database configuration file not found at: " . $koneksi_file);
}
require_once $koneksi_file;

// Check if connection variable exists
if (!isset($koneksi) || $koneksi === null) {
    die("❌ Database connection failed. Please check your koneksi.php file.");
}

$koneksi_loaded = true;

// Include maintenance functions from config folder (naik 1 level dari admin)
$functions_file = __DIR__ . "/../config/maintenance_functions.php";
if (!file_exists($functions_file)) {
    die("❌ Maintenance functions file not found at: " . $functions_file);
}
require_once $functions_file;

$functions_loaded = true;

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['enable_maintenance'])) {
            // Enable maintenance mode with uploaded file
            if (isset($_FILES['maintenance_file'])) {
                $file = $_FILES['maintenance_file'];
                
                // Check for upload errors
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $upload_errors = [
                        UPLOAD_ERR_INI_SIZE => 'File melebihi batas upload_max_filesize',
                        UPLOAD_ERR_FORM_SIZE => 'File melebihi batas MAX_FILE_SIZE',
                        UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
                        UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
                        UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan',
                        UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
                        UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP'
                    ];
                    
                    $error_msg = $upload_errors[$file['error']] ?? 'Unknown upload error';
                    throw new Exception($error_msg);
                }
                
                // Basic validation
                if ($file['size'] > 5 * 1024 * 1024) {
                    throw new Exception("File terlalu besar. Maksimal 5MB");
                }
                
                if ($file['size'] == 0) {
                    throw new Exception("File kosong atau tidak valid");
                }
                
                $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($fileExtension !== 'html') {
                    throw new Exception("File harus berupa HTML (.html)");
                }
                
                // Read HTML content
                $html_content = @file_get_contents($file['tmp_name']);
                
                if ($html_content === false) {
                    throw new Exception("Gagal membaca isi file HTML");
                }
                
                if (empty(trim($html_content))) {
                    throw new Exception("File HTML kosong");
                }
                
                // Enable maintenance in database
                $result = enable_maintenance_db($html_content);
                
                if ($result['success']) {
                    $success = "✅ Maintenance mode berhasil diaktifkan! HTML disimpan di database.";
                } else {
                    throw new Exception($result['error'] ?? "Unknown error occurred");
                }
            } else {
                throw new Exception("Silakan pilih file HTML untuk diupload");
            }
        } 
        elseif (isset($_POST['disable_maintenance'])) {
            // Disable maintenance mode
            $result = disable_maintenance_db();
            
            if ($result['success']) {
                $success = "✅ Maintenance mode berhasil dimatikan!";
            } else {
                throw new Exception($result['error'] ?? "Unknown error occurred");
            }
        }
        elseif (isset($_POST['use_default'])) {
            // Use default maintenance page
            $default_html = get_default_maintenance_html();
            $result = enable_maintenance_db($default_html);
            
            if ($result['success']) {
                $success = "✅ Maintenance mode diaktifkan dengan template default!";
            } else {
                throw new Exception($result['error'] ?? "Unknown error occurred");
            }
        }
        
    } catch (Exception $e) {
        error_log("Maintenance system error: " . $e->getMessage());
        $error = '❌ ' . $e->getMessage();
    }
}

// Get maintenance status dengan error handling
try {
    $status = get_maintenance_status_db();
    if (!is_array($status)) {
        throw new Exception("Invalid maintenance status data");
    }
} catch (Exception $e) {
    error_log("Failed to get maintenance status: " . $e->getMessage());
    $status = get_default_maintenance_status();
    if (empty($error)) {
        $error = '⚠️ Gagal memuat status maintenance: ' . $e->getMessage();
    }
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance System - SAZEN</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh; padding: 20px; 
        }
        .container { 
            max-width: 800px; margin: 0 auto; background: white; 
            border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden; 
        }
        .header { 
            background: linear-gradient(135deg, #2c3e50, #34495e); color: white; 
            padding: 30px; text-align: center; 
        }
        .header h1 { font-size: 2em; margin-bottom: 10px; }
        .header p { opacity: 0.9; }
        .content { padding: 30px; }
        .alert { 
            padding: 15px; border-radius: 8px; margin-bottom: 20px; 
            display: flex; align-items: flex-start; gap: 10px; 
        }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert i { margin-top: 2px; }
        .status-card { 
            background: #f8f9fa; border-radius: 10px; padding: 20px; 
            margin-bottom: 30px; border-left: 4px solid #007bff; 
        }
        .status-active { border-left-color: #dc3545; background: #fff5f5; }
        .status-card h3 { margin-bottom: 15px; color: #2c3e50; }
        .status-card p { margin: 8px 0; }
        .form-group { margin-bottom: 25px; }
        .form-group h3 { margin-bottom: 15px; color: #2c3e50; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        .form-control { 
            width: 100%; padding: 12px; border: 2px solid #e9ecef; 
            border-radius: 8px; font-size: 1em; transition: border 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: #007bff;
        }
        .btn { 
            padding: 12px 24px; border: none; border-radius: 8px; 
            font-size: 1em; font-weight: 600; cursor: pointer; 
            display: inline-flex; align-items: center; gap: 8px; 
            margin: 5px; transition: all 0.3s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .file-upload { 
            border: 2px dashed #dee2e6; border-radius: 8px; padding: 30px; 
            text-align: center; margin: 20px 0;
        }
        .action-buttons { display: flex; gap: 10px; flex-wrap: wrap; margin: 15px 0; }
        .preview-area { 
            margin-top: 20px; padding: 15px; background: #f8f9fa; 
            border-radius: 8px; border: 1px solid #dee2e6;
        }
        .preview-area h4 { margin-bottom: 10px; }
        .divider { 
            text-align: center; margin: 20px 0; color: #6c757d; 
            position: relative;
        }
        .divider::before,
        .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 45%;
            height: 1px;
            background: #dee2e6;
        }
        .divider::before { left: 0; }
        .divider::after { right: 0; }
        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }
        .debug-info h4 {
            margin-bottom: 10px;
            color: #856404;
        }
        .debug-info ul {
            list-style: none;
            padding-left: 0;
        }
        .debug-info li {
            padding: 5px 0;
        }
        .debug-info .check {
            color: #28a745;
        }
        .debug-info .cross {
            color: #dc3545;
        }
        @media (max-width: 768px) {
            .container { margin: 10px; }
            .content { padding: 20px; }
            .header h1 { font-size: 1.5em; }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-tools"></i> Maintenance System</h1>
            <p>Database-Based Maintenance Mode Manager</p>
        </div>

        <div class="content">
            <!-- Debug Info -->
            <div class="debug-info">
                <h4><i class="fas fa-info-circle"></i> System Status</h4>
                <ul>
                    <li>
                        <span class="<?php echo $koneksi_loaded ? 'check' : 'cross'; ?>">
                            <?php echo $koneksi_loaded ? '✓' : '✗'; ?>
                        </span>
                        Database Connection: <?php echo $koneksi_loaded ? 'Connected' : 'Failed'; ?>
                    </li>
                    <li>
                        <span class="<?php echo $functions_loaded ? 'check' : 'cross'; ?>">
                            <?php echo $functions_loaded ? '✓' : '✗'; ?>
                        </span>
                        Maintenance Functions: <?php echo $functions_loaded ? 'Loaded' : 'Failed'; ?>
                    </li>
                    <li>
                        <span class="<?php echo isset($koneksi) ? 'check' : 'cross'; ?>">
                            <?php echo isset($koneksi) ? '✓' : '✗'; ?>
                        </span>
                        PDO Object: <?php echo isset($koneksi) ? 'Available' : 'Not Available'; ?>
                    </li>
                </ul>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <div class="status-card <?= (isset($status['is_active']) && $status['is_active']) ? 'status-active' : '' ?>">
                <h3><i class="fas fa-info-circle"></i> Status Maintenance</h3>
                <p><strong>Mode:</strong> 
                    <?= (isset($status['is_active']) && $status['is_active']) ? 
                        '<span style="color: #dc3545; font-weight: bold;">🔴 AKTIF</span>' : 
                        '<span style="color: #28a745; font-weight: bold;">🟢 NON-AKTIF</span>' ?>
                </p>
                <?php if (isset($status['is_active']) && $status['is_active']): ?>
                    <?php if (!empty($status['activated_at'])): ?>
                        <p><strong>Diaktifkan:</strong> <?= htmlspecialchars($status['activated_at']) ?></p>
                    <?php endif; ?>
                    <?php if (isset($status['maintenance_html'])): ?>
                        <p><strong>HTML Size:</strong> <?= number_format(strlen($status['maintenance_html'])) ?> bytes</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if (!isset($status['is_active']) || !$status['is_active']): ?>
                <div class="form-group">
                    <h3><i class="fas fa-power-off"></i> Aktifkan Maintenance Mode</h3>
                    
                    <div class="action-buttons">
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="use_default" class="btn btn-warning">
                                <i class="fas fa-magic"></i> Gunakan Template Default
                            </button>
                        </form>
                    </div>

                    <div class="divider">ATAU</div>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="file-upload">
                            <i class="fas fa-file-upload" style="font-size: 3em; color: #6c757d; margin-bottom: 15px;"></i>
                            <p style="margin-bottom: 15px;">Upload file HTML custom untuk halaman maintenance</p>
                            <input type="file" name="maintenance_file" accept=".html" class="form-control" required>
                            <small style="display: block; margin-top: 10px; color: #6c757d;">
                                <i class="fas fa-info-circle"></i> Format: HTML file (.html) - Maksimal 5MB
                            </small>
                        </div>
                        <button type="submit" name="enable_maintenance" class="btn btn-danger">
                            <i class="fas fa-play-circle"></i> Aktifkan Maintenance Custom
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="form-group">
                    <h3><i class="fas fa-times-circle"></i> Non-aktifkan Maintenance</h3>
                    <form method="POST">
                        <p style="margin-bottom: 15px;">Website akan kembali normal dan HTML akan disimpan di database untuk riwayat.</p>
                        <button type="submit" name="disable_maintenance" class="btn btn-success">
                            <i class="fas fa-stop-circle"></i> Matikan Maintenance Mode
                        </button>
                    </form>
                    
                    <?php if (!empty($status['maintenance_html'])): ?>
                    <div class="preview-area">
                        <h4><i class="fas fa-eye"></i> Preview HTML</h4>
                        <textarea class="form-control" rows="6" readonly style="font-family: monospace; font-size: 0.8em;"><?= htmlspecialchars(substr($status['maintenance_html'], 0, 500)) ?>...</textarea>
                        <small style="color: #6c757d; display: block; margin-top: 10px;">
                            <i class="fas fa-info-circle"></i> Preview 500 karakter pertama dari total <?= number_format(strlen($status['maintenance_html'])) ?> karakter
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center; color: #6c757d;">
                <small><i class="fas fa-shield-alt"></i> SAZEN Maintenance System v3.1 - Database Edition</small>
            </div>
        </div>
    </div>
</body>
</html>
