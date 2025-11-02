<?php
/**
 * SAZEN - Maintenance System (Simplified)
 */

session_start();

// Authentication Check - hanya admin yang bisa akses
if (!isset($_SESSION['user_id'])) {
    header("Location: admin/auth.php");
    exit;
}

$error = '';
$success = '';

// Create maintenance directory if not exists
$maintenance_dir = __DIR__ . '/maintenance';
if (!file_exists($maintenance_dir)) {
    mkdir($maintenance_dir, 0755, true);
}

// Simple maintenance status check
function get_maintenance_status() {
    $status_file = __DIR__ . '/maintenance/maintenance_status.json';
    if (file_exists($status_file)) {
        $data = json_decode(file_get_contents($status_file), true);
        return is_array($data) ? $data : [];
    }
    return ['active' => false];
}

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
                
                if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'html') {
                    throw new Exception("File harus berupa HTML (.html)");
                }
                
                // Move uploaded file
                $target_file = $maintenance_dir . '/index.html';
                if (move_uploaded_file($file['tmp_name'], $target_file)) {
                    // Update maintenance status
                    $status = [
                        'active' => true,
                        'activated_at' => time(),
                        'activated_date' => date('Y-m-d H:i:s')
                    ];
                    
                    file_put_contents($maintenance_dir . '/maintenance_status.json', json_encode($status));
                    $success = "✅ Maintenance mode diaktifkan!";
                } else {
                    throw new Exception("Gagal mengupload file");
                }
            } else {
                throw new Exception("Silakan pilih file index.html untuk diupload");
            }
        } 
        elseif (isset($_POST['disable_maintenance'])) {
            // Disable maintenance mode
            $status_file = $maintenance_dir . '/maintenance_status.json';
            $index_file = $maintenance_dir . '/index.html';
            
            // Archive current file
            if (file_exists($index_file)) {
                $archive_dir = $maintenance_dir . '/archive';
                if (!file_exists($archive_dir)) {
                    mkdir($archive_dir, 0755, true);
                }
                $archive_name = 'index_' . date('Y-m-d_His') . '.html';
                rename($index_file, $archive_dir . '/' . $archive_name);
            }
            
            // Update status
            $status = ['active' => false];
            file_put_contents($status_file, json_encode($status));
            
            $success = "✅ Maintenance mode dimatikan!";
        }
        
    } catch (Exception $e) {
        $error = '❌ ' . $e->getMessage();
    }
}

$status = get_maintenance_status();
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
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .file-upload { 
            border: 2px dashed #dee2e6; border-radius: 8px; padding: 30px; 
            text-align: center; 
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-tools"></i> Maintenance System</h1>
            <p>Kelola mode maintenance untuk website SAZEN</p>
        </div>

        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i><?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <div class="status-card <?= $status['active'] ? 'status-active' : '' ?>">
                <h3>Status Saat Ini</h3>
                <p><strong>Maintenance Mode:</strong> 
                    <?= $status['active'] ? 
                        '<span style="color: #dc3545;">AKTIF</span>' : 
                        '<span style="color: #28a745;">NON-AKTIF</span>' ?>
                </p>
                <?php if ($status['active'] && isset($status['activated_date'])): ?>
                    <p><strong>Diaktifkan:</strong> <?= $status['activated_date'] ?></p>
                <?php endif; ?>
            </div>

            <?php if (!$status['active']): ?>
                <div class="form-group">
                    <h3><i class="fas fa-power-off"></i> Aktifkan Maintenance</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="file-upload">
                            <i class="fas fa-file-upload" style="font-size: 3em; color: #6c757d; margin-bottom: 15px;"></i>
                            <p>Upload file index.html untuk halaman maintenance</p>
                            <input type="file" name="maintenance_file" accept=".html" class="form-control" required>
                            <small style="display: block; margin-top: 10px; color: #6c757d;">
                                Format: HTML file (index.html) - Maksimal 5MB
                            </small>
                        </div>
                        <button type="submit" name="enable_maintenance" class="btn btn-danger">
                            <i class="fas fa-play-circle"></i> Aktifkan Maintenance
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="form-group">
                    <h3><i class="fas fa-times-circle"></i> Non-aktifkan Maintenance</h3>
                    <form method="POST">
                        <p>Website akan kembali normal.</p>
                        <button type="submit" name="disable_maintenance" class="btn btn-success">
                            <i class="fas fa-stop-circle"></i> Matikan Maintenance
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
