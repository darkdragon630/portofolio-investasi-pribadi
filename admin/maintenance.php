<?php
/**
 * SAZEN - Maintenance System (Database Based)
 */

// Start session dengan error handling
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication Check - hanya user yang login bisa akses
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

// Include config files dengan error handling
try {
    require_once "config/koneksi.php";
    require_once "config/maintenance_functions.php";
} catch (Exception $e) {
    error_log("Failed to include config files: " . $e->getMessage());
    die("System configuration error. Please contact administrator.");
}

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['enable_maintenance'])) {
            // Enable maintenance mode
            if (isset($_FILES['maintenance_file']) && $_FILES['maintenance_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['maintenance_file'];
                
                // Basic validation
                if ($file['size'] > 5 * 1024 * 1024) {
                    throw new Exception("File terlalu besar. Maksimal 5MB");
                }
                
                $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($fileExtension !== 'html') {
                    throw new Exception("File harus berupa HTML (.html)");
                }
                
                // Read HTML content
                $html_content = file_get_contents($file['tmp_name']);
                
                if (empty($html_content)) {
                    throw new Exception("File HTML kosong atau tidak dapat dibaca");
                }
                
                // Enable maintenance in database
                $result = enable_maintenance_db($html_content);
                
                if ($result['success']) {
                    $success = "✅ Maintenance mode diaktifkan! HTML disimpan di database.";
                } else {
                    throw new Exception($result['error'] ?? "Unknown error occurred");
                }
            } else {
                $uploadError = $_FILES['maintenance_file']['error'] ?? 'Unknown';
                throw new Exception("Silakan pilih file HTML untuk diupload (Error: $uploadError)");
            }
        } 
        elseif (isset($_POST['disable_maintenance'])) {
            // Disable maintenance mode
            $result = disable_maintenance_db();
            
            if ($result['success']) {
                $success = "✅ Maintenance mode dimatikan!";
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
    $status = ['is_active' => false, 'activated_at' => null, 'maintenance_html' => ''];
    $error = '❌ Gagal memuat status maintenance: ' . $e->getMessage();
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
        .content { padding: 30px; }
        .alert { 
            padding: 15px; border-radius: 8px; margin-bottom: 20px; 
            display: flex; align-items: center; gap: 10px; 
        }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-card { 
            background: #f8f9fa; border-radius: 10px; padding: 20px; 
            margin-bottom: 30px; border-left: 4px solid #007bff; 
        }
        .status-active { border-left-color: #dc3545; background: #fff5f5; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        .form-control { 
            width: 100%; padding: 12px; border: 2px solid #e9ecef; 
            border-radius: 8px; font-size: 1em; 
        }
        .btn { 
            padding: 12px 24px; border: none; border-radius: 8px; 
            font-size: 1em; font-weight: 600; cursor: pointer; 
            display: inline-flex; align-items: center; gap: 8px; 
            margin: 5px;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .file-upload { 
            border: 2px dashed #dee2e6; border-radius: 8px; padding: 30px; 
            text-align: center; 
        }
        .action-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
        .preview-area { 
            margin-top: 20px; padding: 15px; background: #f8f9fa; 
            border-radius: 8px; border: 1px solid #dee2e6;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-tools"></i> Maintenance System</h1>
            <p>Database-Based Maintenance Mode</p>
        </div>

        <div class="content">
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i><?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <div class="status-card <?= isset($status['is_active']) && $status['is_active'] ? 'status-active' : '' ?>">
                <h3>Status Maintenance</h3>
                <p><strong>Mode:</strong> 
                    <?= (isset($status['is_active']) && $status['is_active']) ? 
                        '<span style="color: #dc3545;">🟢 AKTIF</span>' : 
                        '<span style="color: #28a745;">🔴 NON-AKTIF</span>' ?>
                </p>
                <?php if (isset($status['is_active']) && $status['is_active'] && !empty($status['activated_at'])): ?>
                    <p><strong>Diaktifkan:</strong> <?= htmlspecialchars($status['activated_at']) ?></p>
                    <p><strong>HTML Size:</strong> <?= strlen($status['maintenance_html'] ?? '') ?> bytes</p>
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

                    <p style="margin: 15px 0; text-align: center; color: #666;">- ATAU -</p>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="file-upload">
                            <i class="fas fa-file-upload" style="font-size: 3em; color: #6c757d; margin-bottom: 15px;"></i>
                            <p>Upload file HTML custom untuk halaman maintenance</p>
                            <input type="file" name="maintenance_file" accept=".html" class="form-control" required>
                            <small style="display: block; margin-top: 10px; color: #6c757d;">
                                Format: HTML file (.html) - Maksimal 5MB
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
                        <p>Website akan kembali normal dan HTML akan disimpan di database untuk riwayat.</p>
                        <button type="submit" name="disable_maintenance" class="btn btn-success">
                            <i class="fas fa-stop-circle"></i> Matikan Maintenance Mode
                        </button>
                    </form>
                    
                    <?php if (!empty($status['maintenance_html'])): ?>
                    <div class="preview-area">
                        <h4><i class="fas fa-eye"></i> Preview HTML</h4>
                        <textarea class="form-control" rows="6" readonly style="font-family: monospace; font-size: 0.8em;">
<?= htmlspecialchars(substr($status['maintenance_html'], 0, 500)) . '...' ?>
                        </textarea>
                        <small>Preview 500 karakter pertama</small>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
