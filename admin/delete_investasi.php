<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * Delete Investasi - Cascade Delete with Confirmation
 * ---------------------------------------------------
 * Menghapus investasi beserta semua data terkait:
 * - Keuntungan investasi
 * - Kerugian investasi
 * - File bukti (JSON/BLOB)
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
    redirect_with_message("../dashboard.php", "error", "❌ ID investasi tidak valid.");
    exit;
}

$investasi_id = (int) $_GET['id'];
$confirm = $_GET['confirm'] ?? '';

try {
    // ===================================
    // 3. GET INVESTMENT DATA & STATISTICS
    // ===================================
    $sql = "SELECT i.*, k.nama_kategori,
                   (SELECT COUNT(*) FROM keuntungan_investasi WHERE investasi_id = i.id) as total_keuntungan,
                   (SELECT COALESCE(SUM(jumlah_keuntungan), 0) FROM keuntungan_investasi WHERE investasi_id = i.id) as sum_keuntungan,
                   (SELECT COUNT(*) FROM kerugian_investasi WHERE investasi_id = i.id) as total_kerugian,
                   (SELECT COALESCE(SUM(jumlah_kerugian), 0) FROM kerugian_investasi WHERE investasi_id = i.id) as sum_kerugian
            FROM investasi i
            JOIN kategori k ON i.kategori_id = k.id
            WHERE i.id = ?";
    
    $stmt = $koneksi->prepare($sql);
    $stmt->execute([$investasi_id]);
    $investasi = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$investasi) {
        redirect_with_message("../dashboard.php", "error", "❌ Data investasi tidak ditemukan.");
        exit;
    }

    // ===================================
    // 4. CONFIRMATION CHECK
    // ===================================
    if ($confirm !== 'yes') {
        // Show confirmation page
        showConfirmationPage($investasi);
        exit;
    }

    // ===================================
    // 5. DELETE PROCESS WITH TRANSACTION
    // ===================================
    $koneksi->beginTransaction();

    try {
        // Count records before delete (for logging)
        $deleted_keuntungan = 0;
        $deleted_kerugian = 0;
        
        // Delete related keuntungan
        if ($investasi['total_keuntungan'] > 0) {
            $sql_delete_keuntungan = "DELETE FROM keuntungan_investasi WHERE investasi_id = ?";
            $stmt_keuntungan = $koneksi->prepare($sql_delete_keuntungan);
            $stmt_keuntungan->execute([$investasi_id]);
            $deleted_keuntungan = $stmt_keuntungan->rowCount();
        }

        // Delete related kerugian
        if ($investasi['total_kerugian'] > 0) {
            $sql_delete_kerugian = "DELETE FROM kerugian_investasi WHERE investasi_id = ?";
            $stmt_kerugian = $koneksi->prepare($sql_delete_kerugian);
            $stmt_kerugian->execute([$investasi_id]);
            $deleted_kerugian = $stmt_kerugian->rowCount();
        }

        // Delete main investment record
        $sql_delete = "DELETE FROM investasi WHERE id = ?";
        $stmt_delete = $koneksi->prepare($sql_delete);
        $stmt_delete->execute([$investasi_id]);

        // Commit transaction
        $koneksi->commit();

        // ===================================
        // 6. DELETE FILE FROM JSON (if exists)
        // ===================================
        if (!empty($investasi['bukti_file'])) {
            try {
                delete_file($investasi['bukti_file'], JSON_FILE_INVESTASI);
                // Uncomment if log function exists
                // log_security_event("FILE_DELETED", "Investasi ID: $investasi_id, File: " . $investasi['bukti_file']);
            } catch (Exception $e) {
                // Log error but don't stop the process
                error_log("File deletion warning: " . $e->getMessage());
                // Uncomment if log function exists
                // log_security_event("FILE_DELETE_FAILED", "Investasi ID: $investasi_id, Error: " . $e->getMessage());
            }
        }

        // ===================================
        // 7. LOG & SUCCESS MESSAGE
        // ===================================
        $logMessage = sprintf(
            "ID: %d, Judul: %s, Jumlah: Rp %s, Keuntungan: %d, Kerugian: %d, User: %s",
            $investasi['id'],
            $investasi['judul_investasi'],
            number_format($investasi['jumlah'], 0, ',', '.'),
            $deleted_keuntungan,
            $deleted_kerugian,
            $_SESSION['username'] ?? 'Unknown'
        );
        
        // Uncomment if log function exists
        // log_security_event("INVESTASI_DELETED", $logMessage);

        // Build success message
        $successMsg = "✅ Investasi '{$investasi['judul_investasi']}' berhasil dihapus.";
        if ($deleted_keuntungan > 0 || $deleted_kerugian > 0) {
            $successMsg .= " (Keuntungan: $deleted_keuntungan, Kerugian: $deleted_kerugian)";
        }

        redirect_with_message("../dashboard.php", "success", $successMsg);

    } catch (Exception $e) {
        // Rollback transaction on error
        $koneksi->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Delete Investasi Error: " . $e->getMessage());
    
    // Uncomment if log function exists
    // log_security_event("DELETE_INVESTASI_ERROR", "ID: $investasi_id, Error: " . $e->getMessage());
    
    redirect_with_message(
        "../dashboard.php", 
        "error", 
        "❌ Gagal menghapus investasi: " . $e->getMessage()
    );
}

// ===================================
// CONFIRMATION PAGE FUNCTION
// ===================================
function showConfirmationPage($data) {
    $hasFile = !empty($data['bukti_file']);
    $hasKeuntungan = $data['total_keuntungan'] > 0;
    $hasKerugian = $data['total_kerugian'] > 0;
    $netProfit = $data['sum_keuntungan'] - $data['sum_kerugian'];
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Konfirmasi Hapus Investasi - SAZEN v3.0</title>
        
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
                --success-color: #10B981;
                --warning-color: #F59E0B;
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
                max-width: 700px;
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
                position: relative;
                overflow: hidden;
            }

            .confirm-header::before {
                content: '';
                position: absolute;
                top: -50%;
                right: -50%;
                width: 200%;
                height: 200%;
                background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
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
                position: relative;
                z-index: 1;
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
                position: relative;
                z-index: 1;
            }

            .confirm-header p {
                font-size: 1rem;
                opacity: 0.95;
                position: relative;
                z-index: 1;
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
                align-items: center;
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

            .data-value.positive {
                color: var(--success-color);
            }

            .data-value.negative {
                color: var(--error-color);
            }

            .stats-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
                margin-bottom: 1.5rem;
            }

            .stat-card {
                background: linear-gradient(135deg, #F0F9FF 0%, #E0F2FE 100%);
                border: 2px solid #BAE6FD;
                border-radius: 12px;
                padding: 1.25rem;
                text-align: center;
            }

            .stat-card.danger {
                background: linear-gradient(135deg, #FEE2E2 0%, #FECACA 100%);
                border-color: #FCA5A5;
            }

            .stat-icon {
                font-size: 1.75rem;
                margin-bottom: 0.5rem;
                color: #0369A1;
            }

            .stat-card.danger .stat-icon {
                color: var(--error-dark);
            }

            .stat-value {
                font-size: 1.5rem;
                font-weight: 700;
                color: #0C4A6E;
                margin-bottom: 0.25rem;
            }

            .stat-card.danger .stat-value {
                color: #991B1B;
            }

            .stat-label {
                font-size: 0.85rem;
                color: #075985;
                font-weight: 500;
            }

            .stat-card.danger .stat-label {
                color: #7F1D1D;
            }

            .warning-box {
                background: #FEF3C7;
                border-left: 4px solid var(--warning-color);
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
                color: var(--warning-color);
                font-size: 1.25rem;
                margin-top: 0.1rem;
            }

            .warning-box strong {
                display: block;
                margin-bottom: 0.5rem;
            }

            .warning-box ul {
                margin-left: 1.75rem;
                margin-top: 0.5rem;
            }

            .warning-box li {
                margin: 0.25rem 0;
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
                .stats-grid {
                    grid-template-columns: 1fr;
                }
                
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
                <h1>Konfirmasi Hapus Investasi</h1>
                <p>Data terkait juga akan dihapus permanen!</p>
            </div>

            <!-- Body -->
            <div class="confirm-body">
                <!-- Statistics Grid -->
                <?php if ($hasKeuntungan || $hasKerugian): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-arrow-trend-up"></i>
                        </div>
                        <div class="stat-value"><?= $data['total_keuntungan'] ?></div>
                        <div class="stat-label">Data Keuntungan</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-icon">
                            <i class="fas fa-arrow-trend-down"></i>
                        </div>
                        <div class="stat-value"><?= $data['total_kerugian'] ?></div>
                        <div class="stat-label">Data Kerugian</div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Investment Data Card -->
                <div class="data-card">
                    <div class="data-row">
                        <span class="data-label">
                            <i class="fas fa-briefcase"></i>
                            Judul Investasi
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
                            Modal Investasi
                        </span>
                        <span class="data-value">Rp <?= number_format($data['jumlah'], 0, ',', '.') ?></span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">
                            <i class="fas fa-calendar"></i>
                            Tanggal Investasi
                        </span>
                        <span class="data-value"><?= date('d M Y', strtotime($data['tanggal_investasi'])) ?></span>
                    </div>
                    <?php if ($hasKeuntungan || $hasKerugian): ?>
                    <div class="data-row">
                        <span class="data-label">
                            <i class="fas fa-chart-line"></i>
                            Total Keuntungan
                        </span>
                        <span class="data-value positive">
                            + Rp <?= number_format($data['sum_keuntungan'], 0, ',', '.') ?>
                        </span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">
                            <i class="fas fa-chart-line"></i>
                            Total Kerugian
                        </span>
                        <span class="data-value negative">
                            - Rp <?= number_format($data['sum_kerugian'], 0, ',', '.') ?>
                        </span>
                    </div>
                    <div class="data-row">
                        <span class="data-label">
                            <i class="fas fa-calculator"></i>
                            Net Profit/Loss
                        </span>
                        <span class="data-value <?= $netProfit >= 0 ? 'positive' : 'negative' ?>">
                            <?= $netProfit >= 0 ? '+' : '' ?> Rp <?= number_format($netProfit, 0, ',', '.') ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if ($hasFile): ?>
                    <div class="data-row">
                        <span class="data-label">
                            <i class="fas fa-file-alt"></i>
                            Bukti File
                        </span>
                        <span class="data-value">Ada</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Warning -->
                <div class="warning-box">
                    <p>
                        <i class="fas fa-exclamation-circle"></i>
                        <span>
                            <strong>Perhatian! Tindakan ini tidak dapat dibatalkan.</strong>
                            Data yang akan dihapus:
                            <ul>
                                <li><strong>1 Investasi</strong>: <?= htmlspecialchars($data['judul_investasi']) ?></li>
                                <?php if ($hasKeuntungan): ?>
                                <li><strong><?= $data['total_keuntungan'] ?> Data Keuntungan</strong> (Total: Rp <?= number_format($data['sum_keuntungan'], 0, ',', '.') ?>)</li>
                                <?php endif; ?>
                                <?php if ($hasKerugian): ?>
                                <li><strong><?= $data['total_kerugian'] ?> Data Kerugian</strong> (Total: Rp <?= number_format($data['sum_kerugian'], 0, ',', '.') ?>)</li>
                                <?php endif; ?>
                                <?php if ($hasFile): ?>
                                <li><strong>File Bukti</strong> akan dihapus dari sistem</li>
                                <?php endif; ?>
                            </ul>
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
                        <span>Ya, Hapus Semua!</span>
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>