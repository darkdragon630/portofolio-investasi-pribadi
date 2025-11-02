<?php
/**
 * SAZEN - Maintenance System (Database Based)
 */

session_start();
require_once "config/koneksi.php";
require_once "config/maintenance_functions.php";

// Authentication Check - hanya admin yang bisa akses
if (!isset($_SESSION['user_id'])) {
    header("Location: admin/auth.php");
    exit;
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
                
                if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'html') {
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
                    throw new Exception($result['error']);
                }
            } else {
                throw new Exception("Silakan pilih file index.html untuk diupload");
            }
        } 
        elseif (isset($_POST['disable_maintenance'])) {
            // Disable maintenance mode
            $result = disable_maintenance_db();
            
            if ($result['success']) {
                $success = "✅ Maintenance mode dimatikan!";
            } else {
                throw new Exception($result['error']);
            }
        }
        elseif (isset($_POST['use_default'])) {
            // Use default maintenance page
            $default_html = get_default_maintenance_html();
            $result = enable_maintenance_db($default_html);
            
            if ($result['success']) {
                $success = "✅ Maintenance mode diaktifkan dengan template default!";
            } else {
                throw new Exception($result['error']);
            }
        }
        
    } catch (Exception $e) {
        $error = '❌ ' . $e->getMessage();
    }
}

$status = get_maintenance_status_db();

// Default maintenance HTML template
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

            <div class="status-card <?= $status['is_active'] ? 'status-active' : '' ?>">
                <h3>Status Maintenance</h3>
                <p><strong>Mode:</strong> 
                    <?= $status['is_active'] ? 
                        '<span style="color: #dc3545;">🟢 AKTIF</span>' : 
                        '<span style="color: #28a745;">🔴 NON-AKTIF</span>' ?>
                </p>
                <?php if ($status['is_active'] && $status['activated_at']): ?>
                    <p><strong>Diaktifkan:</strong> <?= $status['activated_at'] ?></p>
                    <p><strong>HTML Size:</strong> <?= strlen($status['maintenance_html'] ?? '') ?> bytes</p>
                <?php endif; ?>
            </div>

            <?php if (!$status['is_active']): ?>
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
                    
                    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                        <h4><i class="fas fa-eye"></i> Preview HTML</h4>
                        <textarea class="form-control" rows="6" readonly style="font-family: monospace; font-size: 0.8em;">
<?= htmlspecialchars(substr($status['maintenance_html'] ?? '', 0, 500)) . '...' ?>
                        </textarea>
                        <small>Preview 500 karakter pertama</small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
