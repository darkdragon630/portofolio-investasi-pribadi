<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * Delete Kerugian - Soft/Hard Delete with Confirmation
 * -----------------------------------------------------
 * Menghapus record kerugian beserta BLOB bukti_file
 * Dengan konfirmasi dan validasi keamanan
 */

session_start();
require_once "../config/koneksi.php";

// ===================================
// 1. AUTHENTICATION CHECK
// ===================================
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

// ===================================
// 2. PARAMETER VALIDATION
// ===================================
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect_with_message("../dashboard.php", "error", "❌ ID kerugian tidak valid.");
    exit;
}

$kerugian_id = (int) $_GET['id'];
$confirm = $_GET['confirm'] ?? '';

try {
    // ===================================
    // 3. GET DATA BEFORE DELETE
    // ===================================
    $sql = "SELECT ki.id, ki.judul_kerugian, ki.jumlah_kerugian, 
                   ki.tanggal_kerugian, ki.bukti_file,
                   i.judul_investasi, k.nama_kategori
            FROM kerugian_investasi ki
            JOIN investasi i ON ki.investasi_id = i.id
            JOIN kategori k ON ki.kategori_id = k.id
            WHERE ki.id = ?";
    
    $stmt = $koneksi->prepare($sql);
    $stmt->execute([$kerugian_id]);
    $kerugian = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$kerugian) {
        redirect_with_message("../dashboard.php", "error", "❌ Data kerugian tidak ditemukan.");
        exit;
    }

    // ===================================
    // 4. CONFIRMATION CHECK
    // ===================================
    if ($confirm !== 'yes') {
        // Show confirmation page
        showConfirmationPage($kerugian);
        exit;
    }

    // ===================================
    // 5. DELETE RECORD
    // ===================================
    $koneksi->beginTransaction();
    
    $sqlDelete = "DELETE FROM kerugian_investasi WHERE id = ?";
    $stmtDelete = $koneksi->prepare($sqlDelete);
    $stmtDelete->execute([$kerugian_id]);
    
    $koneksi->commit();

    // ===================================
    // 6. LOG & REDIRECT
    // ===================================
    $logMessage = sprintf(
        "ID: %d, Judul: %s, Jumlah: Rp %s, User: %s",
        $kerugian['id'],
        $kerugian['judul_kerugian'],
        number_format($kerugian['jumlah_kerugian'], 0, ',', '.'),
        $_SESSION['username'] ?? 'Unknown'
    );
    
    // Uncomment if log function exists
    // log_security_event("KERUGIAN_DELETED", $logMessage);
    
    redirect_with_message(
        "../dashboard.php", 
        "success", 
        "✅ Kerugian '{$kerugian['judul_kerugian']}' berhasil dihapus."
    );

} catch (Exception $e) {
    // Rollback if transaction started
    if ($koneksi->inTransaction()) {
        $koneksi->rollBack();
    }
    
    error_log("Delete Kerugian Error: " . $e->getMessage());
    
    // Uncomment if log function exists
    // log_security_event("DELETE_KERUGIAN_ERROR", "ID: {$kerugian_id}, Error: " . $e->getMessage());
    
    redirect_with_message(
        "../dashboard.php", 
        "error", 
        "❌ Gagal menghapus kerugian: " . $e->getMessage()
    );
}

// ===================================
// CONFIRMATION PAGE FUNCTION
// ===================================
function showConfirmationPage($data) {
    $hasFile = !empty($data['bukti_file']);
    $fileSize = $hasFile ? number_format(strlen($data['bukti_file']) / 1024, 2) : 0;
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Konfirmasi Hapus Kerugian - SAZEN v3.0</title>
        
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        
        <!-- Icons -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            :root {
                --error-color: #EF4444;
                --error-dark: #DC2626;
                --text-primary: #1F2937;
                --text-secondary: #6B7280;
                --border-color: #E5E7EB;
                --white: #FFFFFF;
                --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
                --radius-lg: 16px;
            }

            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
                background: linear-gradient(135deg, #FEE2E2 0%, #FECACA 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem 1rem;
            }

            .confirm-container {
                max-width: 600px;
                width: 100%;
                background: var(--white);
                border-radius: var(--radius-lg);
                box-shadow: var(--shadow-lg);
                overflow: hidden;
                animation: slideUp 0.4s ease-out;
            }

            @keyframes slideUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .confirm-header {
                background: linear-gradient(135deg, var(--error-color) 0%, var(--error-dark) 100%);
                padding: 2.5rem;
                text-align: center;
                color: var(--white);
            }

            .confirm-icon {
                width: 80px;
                height: 80px;
                background: rgba(255, 255, 255, 0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1.5rem;
                animation: shake 0.5s ease-in-out;
            }

            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-10px); }
                75% { transform: translateX(10px); }
            }

            .confirm-icon i {
                font-size: 2.5rem;
            }

            .confirm-header h1 {
                font-size: 1.75rem;
                font-weight: 700;
                margin-bottom: 0.5rem;
            }

            .confirm-header p {
                font-size: 1rem;
                opacity: 0.95;
            }

            .confirm-body {
                padding: 2rem;
            }

            .data-card {
                background: #F9FAFB;
                border: 2px solid var(--border-color);
                border-radius: 12px;
                padding: 1.5rem;
                margin-bottom: 1.5rem;
            }

            .data-row {
                display: flex;
                justify-content: space-between;
                padding: 0.75rem 0;
                border-bottom: 1px solid var(--border-color);
            }

            .data-row:last-child {
                border-bottom: none;
            }

            .data-label {
                font-weight: 600;
                color: var(--text-secondary);
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .data-value {
                font-weight: 600;
                color: var(--text-primary);
                text-align: right;
            }

            .warning-box {
                background: #FEF3C7;
                border-left: 4px solid #F59E0B;
                padding: 1.25rem;
                border-radius: 8px;
                margin-bottom: 1.5rem;
            }

            .warning-box p {
                display: flex;
                align-items: start;
                gap: 0.75rem;
                color: #78350F;
                font-size: 0.95rem;
                line-height: 1.6;
            }

            .warning-box i {
                color: #F59E0B;
                font-size: 1.25rem;
                margin-top: 0.1rem;
            }

            .confirm-actions {
                display: flex;
                gap: 1rem;
                margin-top: 2rem;
            }

            .btn {
                flex: 1;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.75rem;
                padding: 1rem 2rem;
                border: none;
                border-radius: 10px;
                font-size: 1rem;
                font-weight: 600;
                font-family: inherit;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
            }

            .btn-delete {
                background: linear-gradient(135deg, var(--error-color) 0%, var(--error-dark) 100%);
                color: var(--white);
            }

            .btn-delete:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3);
            }

            .btn-cancel {
                background: var(--white);
                color: var(--text-primary);
                border: 2px solid var(--border-color);
            }

            .btn-cancel:hover {
                background: #F9FAFB;
                border-color: var(--text-secondary);
            }

            @media (max-width: 640px) {
                .confirm-actions {
                    flex-direction: column-reverse;
                }
            }
        </style>
    </head>
    <body>
        <div class="confirm-container">
            <!-- Header -->
            <div class="confirm-header">
                <div class="confirm-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h1>Konfirmasi Hapus</h1>
                <p>Apakah Anda yakin ingin menghapus data ini?</p>
            </div>

            <!-- Body -->
            <div class="confirm-body">
                <!-- Data Card -->
                <div class="data-card">
                    <div class="data-row">
                        <span class="data-label">
                            <i class="fas fa-tag"></i>
                            Judul Kerugian
                        </span>
                        <span class="data-value"><?= htmlspecialchars($data['judul_kerugian']) ?></span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">
                            <i class="fas fa-briefcase"></i>
                            Investasi
                        </span>
                        <span class="data-value"><?= htmlspecialchars($data['judul_investasi']) ?></span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">
                            <i class="fas fa-folder"></i>
                            Kategori
                        </span>
                        <span class="data-value"><?= htmlspecialchars($data['nama_kategori']) ?></span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">
                            <i class="fas fa-money-bill-wave"></i>
                            Jumlah
                        </span>
                        <span class="data-value">Rp <?= number_format($data['jumlah_kerugian'], 0, ',', '.') ?></span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">
                            <i class="fas fa-calendar"></i>
                            Tanggal
                        </span>
                        <span class="data-value"><?= date('d M Y', strtotime($data['tanggal_kerugian'])) ?></span>
                    </div>
                    <?php if ($hasFile): ?>
                    <div class="data-row">
                        <span class="data-label">
                            <i class="fas fa-file-alt"></i>
                            Bukti File
                        </span>
                        <span class="data-value"><?= $fileSize ?> KB</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Warning -->
                <div class="warning-box">
                    <p>
                        <i class="fas fa-exclamation-circle"></i>
                        <span>
                            <strong>Perhatian!</strong> Data yang dihapus tidak dapat dikembalikan. 
                            <?php if ($hasFile): ?>
                            File bukti (<?= $fileSize ?> KB) juga akan dihapus permanen.
                            <?php endif ?>
                        </span>
                    </p>
                </div>

                <!-- Actions -->
                <div class="confirm-actions">
                    <a href="../dashboard.php" class="btn btn-cancel">
                        <i class="fas fa-times"></i>
                        <span>Batal</span>
                    </a>
                    <a href="?id=<?= $data['id'] ?>&confirm=yes" class="btn btn-delete">
                        <i class="fas fa-trash-alt"></i>
                        <span>Ya, Hapus!</span>
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>