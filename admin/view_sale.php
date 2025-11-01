<?php
/**
 * Detail Transaksi Jual Investasi
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

// Get transaction detail
$sale = get_sale_transaction($koneksi, $id);

if (!$sale) {
    redirect_with_message("../dashboard.php", 'error', 'Transaksi tidak ditemukan');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Penjualan - SAZEN</title>
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
            <h1><i class="fas fa-file-invoice"></i> Detail Transaksi Penjualan</h1>
            <p>Informasi lengkap penjualan investasi</p>
        </div>
    </div>

    <!-- Sale Summary -->
    <div class="sale-summary">
        <div class="summary-card <?= $sale['profit_loss'] >= 0 ? 'profit' : 'loss' ?>">
            <div class="summary-icon">
                <i class="fas fa-<?= $sale['profit_loss'] >= 0 ? 'arrow-trend-up' : 'arrow-trend-down' ?>"></i>
            </div>
            <div class="summary-info">
                <div class="summary-label">Profit/Loss</div>
                <div class="summary-value <?= $sale['profit_loss'] >= 0 ? 'positive' : 'negative' ?>">
                    <?= $sale['profit_loss'] >= 0 ? '+' : '' ?><?= format_currency($sale['profit_loss']) ?>
                </div>
                <div class="summary-roi">ROI: <?= number_format($sale['roi_persen'], 2) ?>%</div>
            </div>
        </div>
    </div>

    <!-- Detail Information -->
    <div class="investment-form">
        
        <!-- Investment Info -->
        <div class="form-section">
            <h3><i class="fas fa-info-circle"></i> Informasi Investasi</h3>
            
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Nama Investasi:</label>
                    <span><?= htmlspecialchars($sale['judul_investasi']) ?></span>
                </div>
                
                <div class="detail-item">
                    <label>Kategori:</label>
                    <span class="badge badge-info"><?= htmlspecialchars($sale['nama_kategori']) ?></span>
                </div>
            </div>
        </div>

        <!-- Transaction Details -->
        <div class="form-section">
            <h3><i class="fas fa-receipt"></i> Detail Transaksi</h3>
            
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Tanggal Penjualan:</label>
                    <span><?= date('d F Y', strtotime($sale['tanggal_jual'])) ?></span>
                </div>
                
                <div class="detail-item">
                    <label>Harga Beli (Modal):</label>
                    <span><?= format_currency($sale['harga_beli']) ?></span>
                </div>
                
                <div class="detail-item">
                    <label>Total Keuntungan (Sebelum Jual):</label>
                    <span class="positive">+<?= format_currency($sale['total_keuntungan']) ?></span>
                </div>
                
                <div class="detail-item">
                    <label>Total Kerugian (Sebelum Jual):</label>
                    <span class="negative">-<?= format_currency($sale['total_kerugian']) ?></span>
                </div>
                
                <div class="detail-item highlight">
                    <label>Harga Jual:</label>
                    <span class="large"><?= format_currency($sale['harga_jual']) ?></span>
                </div>
                
                <div class="detail-item highlight">
                    <label>Profit/Loss Bersih:</label>
                    <span class="large <?= $sale['profit_loss'] >= 0 ? 'positive' : 'negative' ?>">
                        <?= $sale['profit_loss'] >= 0 ? '+' : '' ?><?= format_currency($sale['profit_loss']) ?>
                    </span>
                </div>
                
                <div class="detail-item highlight">
                    <label>ROI:</label>
                    <span class="large <?= $sale['roi_persen'] >= 0 ? 'positive' : 'negative' ?>">
                        <?= $sale['roi_persen'] >= 0 ? '+' : '' ?><?= number_format($sale['roi_persen'], 2) ?>%
                    </span>
                </div>
                
                <div class="detail-item">
                    <label>Status:</label>
                    <span class="badge badge-<?= $sale['status_transaksi'] == 'profit' ? 'success' : ($sale['status_transaksi'] == 'loss' ? 'danger' : 'warning') ?>">
                        <?= strtoupper($sale['status_transaksi']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <?php if (!empty($sale['keterangan'])): ?>
        <div class="form-section">
            <h3><i class="fas fa-sticky-note"></i> Keterangan</h3>
            <div class="note-box">
                <?= nl2br(htmlspecialchars($sale['keterangan'])) ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Proof File -->
        <?php if (!empty($sale['bukti_file'])): ?>
        <div class="form-section">
            <h3><i class="fas fa-file-invoice"></i> Bukti Transaksi</h3>
            <div class="file-preview-container">
                <div class="file-preview">
                    <iframe src="view_sale_file.php?id=<?= $sale['id'] ?>" 
                            style="width: 100%; height: 500px; border: 1px solid #e2e8f0; border-radius: 8px;">
                    </iframe>
                </div>
                <div class="file-actions">
                    <a href="view_sale_file.php?id=<?= $sale['id'] ?>" 
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
.sale-summary {
    margin-bottom: 2rem;
}

.summary-card {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
}

.summary-card.loss {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
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
}

.summary-value {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.summary-roi {
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
