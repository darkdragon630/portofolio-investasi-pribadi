<?php
/**
 * Detail Cash Balance
 */

session_start();
require_once "../config/koneksi.php";
require_once "../config/functions.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: ../dashboard.php");
    exit;
}

$id = (int)$_GET['id'];

// Get cash balance detail
function get_cash_transaction_by_id($koneksi, $id)
try {
    $sql = "SELECT * FROM cash_balance WHERE id = ? AND user_id = ?";
    $stmt = $koneksi->prepare($sql);
    $stmt->execute([$id, $_SESSION['user_id']]);
    $cash = $stmt->fetch();
    
    if (!$cash) {
        redirect_with_message("../dashboard.php", 'error', 'Data cash balance tidak ditemukan');
    }
} catch (PDOException $e) {
    error_log("Get cash detail error: " . $e->getMessage());
    redirect_with_message("../dashboard.php", 'error', 'Terjadi kesalahan saat mengambil data');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Cash Balance - SAZEN</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/form.css">
</head>
<body>

<div class="form-container">
    <div class="form-header">
        <a href="../dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="form-title">
            <h1><i class="fas fa-wallet"></i> Detail Cash Balance</h1>
            <p>Informasi lengkap transaksi cash</p>
        </div>
    </div>

    <!-- Cash Summary -->
    <div class="cash-summary">
        <div class="summary-card <?= $cash['jenis_transaksi'] == 'deposit' ? 'deposit' : 'withdraw' ?>">
            <div class="summary-icon">
                <i class="fas fa-<?= $cash['jenis_transaksi'] == 'deposit' ? 'arrow-down' : 'arrow-up' ?>"></i>
            </div>
            <div class="summary-info">
                <div class="summary-label"><?= $cash['jenis_transaksi'] == 'deposit' ? 'Deposit' : 'Penarikan' ?></div>
                <div class="summary-value <?= $cash['jenis_transaksi'] == 'deposit' ? 'positive' : 'negative' ?>">
                    <?= $cash['jenis_transaksi'] == 'deposit' ? '+' : '-' ?><?= format_currency($cash['jumlah']) ?>
                </div>
                <div class="summary-balance">Saldo: <?= format_currency($cash['saldo_setelah']) ?></div>
            </div>
        </div>
    </div>

    <!-- Detail Information -->
    <div class="investment-form">
        
        <!-- Transaction Info -->
        <div class="form-section">
            <h3><i class="fas fa-info-circle"></i> Informasi Transaksi</h3>
            
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Tanggal Transaksi:</label>
                    <span><?= date('d F Y', strtotime($cash['tanggal_transaksi'])) ?></span>
                </div>
                
                <div class="detail-item">
                    <label>Jenis Transaksi:</label>
                    <span class="badge badge-<?= $cash['jenis_transaksi'] == 'deposit' ? 'success' : 'warning' ?>">
                        <?= strtoupper($cash['jenis_transaksi']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Transaction Details -->
        <div class="form-section">
            <h3><i class="fas fa-calculator"></i> Detail Keuangan</h3>
            
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Saldo Sebelum:</label>
                    <span><?= format_currency($cash['saldo_sebelum']) ?></span>
                </div>
                
                <div class="detail-item highlight">
                    <label>Jumlah <?= $cash['jenis_transaksi'] == 'deposit' ? 'Deposit' : 'Penarikan' ?>:</label>
                    <span class="large <?= $cash['jenis_transaksi'] == 'deposit' ? 'positive' : 'negative' ?>">
                        <?= $cash['jenis_transaksi'] == 'deposit' ? '+' : '-' ?><?= format_currency($cash['jumlah']) ?>
                    </span>
                </div>
                
                <div class="detail-item highlight">
                    <label>Saldo Setelah:</label>
                    <span class="large"><?= format_currency($cash['saldo_setelah']) ?></span>
                </div>
            </div>
        </div>

        <!-- Source Info -->
        <?php if (!empty($cash['sumber'])): ?>
        <div class="form-section">
            <h3><i class="fas fa-building"></i> Sumber/Tujuan</h3>
            <div class="info-box">
                <?= htmlspecialchars($cash['sumber']) ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Notes -->
        <?php if (!empty($cash['keterangan'])): ?>
        <div class="form-section">
            <h3><i class="fas fa-sticky-note"></i> Keterangan</h3>
            <div class="note-box">
                <?= nl2br(htmlspecialchars($cash['keterangan'])) ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Proof File -->
        <?php if (!empty($cash['bukti_file'])): ?>
        <div class="form-section">
            <h3><i class="fas fa-file-invoice"></i> Bukti Transaksi</h3>
            <div class="file-preview-container">
                <div class="file-preview">
                    <iframe src="../view_cash_file.php?id=<?= $cash['id'] ?>" 
                            style="width: 100%; height: 500px; border: 1px solid #e2e8f0; border-radius: 8px;">
                    </iframe>
                </div>
                <div class="file-actions">
                    <a href="../view_cash_file.php?id=<?= $cash['id'] ?>" 
                       target="_blank" 
                       class="btn btn-primary">
                        <i class="fas fa-external-link-alt"></i> Buka di Tab Baru
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="form-actions">
            <button type="button" 
                    onclick="window.location.href='../dashboard.php'" 
                    class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </button>
            <button type="button" 
                    onclick="window.print()" 
                    class="btn btn-info">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
</div>

<style>
.cash-summary {
    margin-bottom: 2rem;
}

.summary-card {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
}

.summary-card.deposit {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
}

.summary-card.withdraw {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
}

.summary-icon {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
}

.summary-info {
    flex: 1;
}

.summary-label {
    font-size: 1rem;
    opacity: 0.9;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.summary-value {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.summary-balance {
    font-size: 1.125rem;
    opacity: 0.9;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.detail-item label {
    font-size: 0.875rem;
    color: #64748b;
    font-weight: 500;
}

.detail-item span {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1e293b;
}

.detail-item.highlight {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 8px;
    border-left: 4px solid #667eea;
}

.detail-item span.large {
    font-size: 1.5rem;
}

.info-box {
    background: #f0f9ff;
    padding: 1rem;
    border-radius: 8px;
    border-left: 4px solid #3b82f6;
    color: #1e40af;
    line-height: 1.6;
    font-weight: 500;
}

.note-box {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 8px;
    border-left: 4px solid #3b82f6;
    color: #475569;
    line-height: 1.6;
}

.file-preview-container {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 8px;
}

.file-actions {
    margin-top: 1rem;
    text-align: center;
}

.badge {
    display: inline-block;
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-success {
    background: #d1fae5;
    color: #065f46;
}

.badge-warning {
    background: #fef3c7;
    color: #92400e;
}

.positive {
    color: #10b981;
}

.negative {
    color: #f59e0b;
}

@media print {
    .form-actions,
    .back-btn,
    .file-actions {
        display: none;
    }
    
    .summary-card {
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
}

@media (max-width: 768px) {
    .summary-card {
        flex-direction: column;
        text-align: center;
    }
    
    .summary-icon {
        width: 60px;
        height: 60px;
        font-size: 2rem;
    }
    
    .summary-value {
        font-size: 2rem;
    }
    
    .detail-grid {
        grid-template-columns: 1fr;
    }
}
</style>

</body>
</html>
