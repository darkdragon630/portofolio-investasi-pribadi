<?php
/**
 * SAZEN - Maintenance System
 * Untuk mengatur mode maintenance dengan upload index.html
 */

session_start();
require_once "config/koneksi.php";
require_once "config/functions.php";

// Authentication Check - hanya admin yang bisa akses
if (!isset($_SESSION['user_id'])) {
    header("Location: admin/auth.php");
    exit;
}

$error = '';
$success = '';
$maintenance_status = check_maintenance_status();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['enable_maintenance'])) {
            // Enable maintenance mode
            if (isset($_FILES['maintenance_file']) && $_FILES['maintenance_file']['error'] === UPLOAD_ERR_OK) {
                $upload_result = handle_maintenance_upload($_FILES['maintenance_file']);
                if ($upload_result['success']) {
                    $success = "✅ Maintenance mode diaktifkan! File index.html berhasil diupload.";
                } else {
                    throw new Exception($upload_result['error']);
                }
            } else {
                throw new Exception("Silakan pilih file index.html untuk diupload");
            }
        } 
        elseif (isset($_POST['disable_maintenance'])) {
            // Disable maintenance mode
            $disable_result = disable_maintenance_mode();
            if ($disable_result['success']) {
                $success = "✅ Maintenance mode dimatikan! Website kembali normal.";
            } else {
                throw new Exception($disable_result['error']);
            }
        }
        elseif (isset($_POST['schedule_maintenance'])) {
            // Schedule maintenance
            $start_time = $_POST['start_time'] ?? '';
            $end_time = $_POST['end_time'] ?? '';
            
            if (empty($start_time) || empty($end_time)) {
                throw new Exception("Waktu mulai dan selesai harus diisi");
            }
            
            $schedule_result = schedule_maintenance($start_time, $end_time);
            if ($schedule_result['success']) {
                $success = "✅ Maintenance terjadwal pada " . date('d M Y H:i', strtotime($start_time)) . " - " . date('d M Y H:i', strtotime($end_time));
            } else {
                throw new Exception($schedule_result['error']);
            }
        }
        elseif (isset($_POST['cancel_schedule'])) {
            // Cancel scheduled maintenance
            $cancel_result = cancel_scheduled_maintenance();
            if ($cancel_result['success']) {
                $success = "✅ Jadwal maintenance dibatalkan";
            } else {
                throw new Exception($cancel_result['error']);
            }
        }
        
        // Refresh status
        $maintenance_status = check_maintenance_status();
        
    } catch (Exception $e) {
        $error = '❌ ' . $e->getMessage();
    }
}

// Get flash message
$flash = get_flash_message();
if ($flash) {
    $flash['type'] == 'success' ? $success = $flash['message'] : $error = $flash['message'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance System - SAZEN</title>
    
    <!-- Styles -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .maintenance-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .maintenance-header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .maintenance-header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .maintenance-header p {
            opacity: 0.9;
            font-size: 1.1em;
        }

        .maintenance-content {
            padding: 30px;
        }

        .status-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #007bff;
        }

        .status-active {
            border-left-color: #dc3545;
            background: #fff5f5;
        }

        .status-scheduled {
            border-left-color: #ffc107;
            background: #fffbf0;
        }

        .status-item {
            display: flex;
            justify-content: between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .status-label {
            font-weight: 600;
            color: #555;
            min-width: 150px;
        }

        .status-value {
            color: #333;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .badge-active {
            background: #dc3545;
            color: white;
        }

        .badge-inactive {
            background: #28a745;
            color: white;
        }

        .badge-scheduled {
            background: #ffc107;
            color: #212529;
        }

        .action-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.3em;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #007bff;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .file-upload {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            transition: border-color 0.3s;
        }

        .file-upload:hover {
            border-color: #007bff;
        }

        .file-upload i {
            font-size: 3em;
            color: #6c757d;
            margin-bottom: 15px;
        }

        .datetime-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        @media (max-width: 768px) {
            .datetime-inputs {
                grid-template-columns: 1fr;
            }
            
            .maintenance-header h1 {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <!-- Header -->
        <div class="maintenance-header">
            <h1><i class="fas fa-tools"></i> Maintenance System</h1>
            <p>Kelola mode maintenance untuk website SAZEN</p>
        </div>

        <!-- Content -->
        <div class="maintenance-content">
            <!-- Messages -->
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <!-- Status Card -->
            <div class="status-card <?= $maintenance_status['active'] ? 'status-active' : ($maintenance_status['scheduled'] ? 'status-scheduled' : '') ?>">
                <h3>Status Saat Ini</h3>
                <div class="status-item">
                    <span class="status-label">Maintenance Mode:</span>
                    <span class="status-value">
                        <?php if ($maintenance_status['active']): ?>
                            <span class="badge badge-active">AKTIF</span>
                        <?php elseif ($maintenance_status['scheduled']): ?>
                            <span class="badge badge-scheduled">TERJADWAL</span>
                        <?php else: ?>
                            <span class="badge badge-inactive">NON-AKTIF</span>
                        <?php endif; ?>
                    </span>
                </div>
                
                <?php if ($maintenance_status['active']): ?>
                    <div class="status-item">
                        <span class="status-label">Diaktifkan Pada:</span>
                        <span class="status-value"><?= date('d M Y H:i:s', $maintenance_status['activated_at']) ?></span>
                    </div>
                <?php elseif ($maintenance_status['scheduled']): ?>
                    <div class="status-item">
                        <span class="status-label">Jadwal Mulai:</span>
                        <span class="status-value"><?= date('d M Y H:i', strtotime($maintenance_status['schedule_start'])) ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Jadwal Selesai:</span>
                        <span class="status-value"><?= date('d M Y H:i', strtotime($maintenance_status['schedule_end'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Enable Maintenance -->
            <div class="action-section">
                <h3 class="section-title"><i class="fas fa-power-off"></i> Aktifkan Maintenance</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Upload File index.html untuk Maintenance:</label>
                        <div class="file-upload">
                            <i class="fas fa-file-upload"></i>
                            <p>Pilih file index.html yang akan ditampilkan selama maintenance</p>
                            <input type="file" name="maintenance_file" accept=".html" class="form-control" required>
                            <small style="display: block; margin-top: 10px; color: #6c757d;">
                                Format: HTML file (index.html)
                            </small>
                        </div>
                    </div>
                    <button type="submit" name="enable_maintenance" class="btn btn-danger">
                        <i class="fas fa-play-circle"></i> Aktifkan Maintenance Mode
                    </button>
                </form>
            </div>

            <!-- Schedule Maintenance -->
            <div class="action-section">
                <h3 class="section-title"><i class="fas fa-calendar-alt"></i> Jadwalkan Maintenance</h3>
                <form method="POST">
                    <div class="form-group">
                        <div class="datetime-inputs">
                            <div>
                                <label>Waktu Mulai:</label>
                                <input type="datetime-local" name="start_time" class="form-control" required>
                            </div>
                            <div>
                                <label>Waktu Selesai:</label>
                                <input type="datetime-local" name="end_time" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" name="schedule_maintenance" class="btn btn-warning">
                            <i class="fas fa-clock"></i> Jadwalkan Maintenance
                        </button>
                        <?php if ($maintenance_status['scheduled']): ?>
                            <button type="submit" name="cancel_schedule" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Batalkan Jadwal
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Disable Maintenance -->
            <?php if ($maintenance_status['active'] || $maintenance_status['scheduled']): ?>
            <div class="action-section">
                <h3 class="section-title"><i class="fas fa-times-circle"></i> Non-aktifkan Maintenance</h3>
                <form method="POST">
                    <p style="margin-bottom: 15px; color: #6c757d;">
                        Website akan kembali normal dan file index.html akan diarsipkan.
                    </p>
                    <button type="submit" name="disable_maintenance" class="btn btn-success">
                        <i class="fas fa-stop-circle"></i> Matikan Maintenance Mode
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
