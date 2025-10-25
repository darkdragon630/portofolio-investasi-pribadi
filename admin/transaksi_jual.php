<?php
/**
 * Form Transaksi Jual Investasi
 */

session_start();
require_once "../config/koneksi.php";
require_once "../config/functions.php";

require_login();

$errors = [];
$success = false;

// Get investasi ID dari URL (jika ada)
$investasi_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get list investasi yang masih aktif
$sql_investasi = "SELECT i.*, k.nama_kategori,
                  COALESCE(SUM(ku.jumlah_keuntungan), 0) as total_keuntungan,
                  COALESCE(SUM(kr.jumlah_kerugian), 0) as total_kerugian
                  FROM investasi i
                  JOIN kategori k ON i.kategori_id = k.id
                  LEFT JOIN keuntungan_investasi ku ON i.id = ku.investasi_id
                  LEFT JOIN kerugian_investasi kr ON i.id = kr.investasi_id
                  WHERE i.status = 'aktif'
                  GROUP BY i.id
                  ORDER BY i.tanggal_investasi DESC";
$stmt = $koneksi->query($sql_investasi);
$investasi_list = $stmt->fetchAll();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $investasi_id = (int)$_POST['investasi_id'];
    $tanggal_jual = $_POST['tanggal_jual'];
    $harga_jual = (float)str_replace(',', '.', str_replace('.', '', $_POST['harga_jual']));
    $keterangan = $_POST['keterangan'] ?? '';
    
    // Validate
    if ($investasi_id <= 0) {
        $errors[] = "Pilih investasi yang akan dijual";
    }
    if (empty($tanggal_jual)) {
        $errors[] = "Tanggal jual harus diisi";
    }
    if ($harga_jual <= 0) {
        $errors[] = "Harga jual harus lebih dari 0";
    }
    
    // Handle file upload (menggunakan fungsi dari koneksi.php - JSON based)
    $bukti_file = null;
    if (isset($_FILES['bukti_file']) && $_FILES['bukti_file']['error'] === UPLOAD_ERR_OK) {
        try {
            // Simpan ke database sebagai base64 (JSON metadata + base64 data)
            $bukti_file = handle_file_upload_to_db($_FILES['bukti_file']);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
    
    if (empty($errors)) {
        $data = [
            'investasi_id' => $investasi_id,
            'tanggal_jual' => $tanggal_jual,
            'harga_jual' => $harga_jual,
            'keterangan' => $keterangan,
            'bukti_file' => $bukti_file
        ];
        
        $result = add_sale_transaction($koneksi, $data);
        
        if ($result['success']) {
            set_flash_message('success', 'Transaksi penjualan berhasil dicatat! Profit/Loss: ' . format_currency($result['profit_loss']));
            header("Location: ../dashboard.php");
            exit;
        } else {
            $errors[] = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jual Investasi - SAZEN</title>
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
            <h1><i class="fas fa-handshake"></i> Jual Investasi</h1>
            <p>Catat transaksi penjualan investasi Anda</p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <div>
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="investment-form" id="saleForm">
        
        <div class="form-section">
            <h3><i class="fas fa-info-circle"></i> Informasi Penjualan</h3>
            
            <div class="form-group">
                <label for="investasi_id">Pilih Investasi <span class="required">*</span></label>
                <select name="investasi_id" id="investasi_id" required>
                    <option value="">-- Pilih Investasi --</option>
                    <?php foreach ($investasi_list as $inv): ?>
                        <option value="<?= $inv['id'] ?>" 
                                data-modal="<?= $inv['jumlah'] ?>"
                                data-keuntungan="<?= $inv['total_keuntungan'] ?>"
                                data-kerugian="<?= $inv['total_kerugian'] ?>"
                                <?= $investasi_id == $inv['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($inv['judul_investasi']) ?> - 
                            <?= htmlspecialchars($inv['nama_kategori']) ?> 
                            (Modal: <?= format_currency($inv['jumlah']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="investmentInfo" class="investment-info" style="display: none;">
                <div class="info-grid">
                    <div class="info-item">
                        <label>Modal Investasi:</label>
                        <span id="modalInvestasi">-</span>
                    </div>
                    <div class="info-item">
                        <label>Total Keuntungan:</label>
                        <span id="totalKeuntungan" class="positive">-</span>
                    </div>
                    <div class="info-item">
                        <label>Total Kerugian:</label>
                        <span id="totalKerugian" class="negative">-</span>
                    </div>
                    <div class="info-item">
                        <label>Nilai Buku Sekarang:</label>
                        <span id="nilaiBuku">-</span>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="tanggal_jual">Tanggal Penjualan <span class="required">*</span></label>
                    <input type="date" name="tanggal_jual" id="tanggal_jual" 
                           value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label for="harga_jual">Harga Jual <span class="required">*</span></label>
                    <input type="text" name="harga_jual" id="harga_jual" 
                           placeholder="0" required class="currency-input">
                    <small>Masukkan harga penjualan aktual</small>
                </div>
            </div>

            <div id="profitLossPreview" class="profit-loss-preview" style="display: none;">
                <div class="preview-grid">
                    <div class="preview-item">
                        <label>Estimasi Profit/Loss:</label>
                        <span id="profitLossAmount">-</span>
                    </div>
                    <div class="preview-item">
                        <label>ROI:</label>
                        <span id="roiPercent">-</span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="keterangan">Keterangan</label>
                <textarea name="keterangan" id="keterangan" rows="3" 
                          placeholder="Catatan tambahan tentang penjualan ini..."></textarea>
            </div>

            <div class="form-group">
                <label for="bukti_file">Bukti Transaksi</label>
                <input type="file" name="bukti_file" id="bukti_file" accept="image/*,.pdf">
                <small>Format: JPG, PNG, PDF (Max 5MB)</small>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" onclick="window.location.href='../dashboard.php'" class="btn btn-secondary">
                <i class="fas fa-times"></i> Batal
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan Transaksi Jual
            </button>
        </div>
    </form>
</div>

<script>
// Format currency input
document.getElementById('harga_jual').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    e.target.value = new Intl.NumberFormat('id-ID').format(value);
    calculateProfitLoss();
});

// Handle investasi selection
document.getElementById('investasi_id').addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    if (this.value) {
        const modal = parseFloat(selected.dataset.modal);
        const keuntungan = parseFloat(selected.dataset.keuntungan);
        const kerugian = parseFloat(selected.dataset.kerugian);
        
        document.getElementById('modalInvestasi').textContent = formatCurrency(modal);
        document.getElementById('totalKeuntungan').textContent = '+' + formatCurrency(keuntungan);
        document.getElementById('totalKerugian').textContent = '-' + formatCurrency(kerugian);
        document.getElementById('nilaiBuku').textContent = formatCurrency(modal + keuntungan - kerugian);
        
        document.getElementById('investmentInfo').style.display = 'block';
        calculateProfitLoss();
    } else {
        document.getElementById('investmentInfo').style.display = 'none';
        document.getElementById('profitLossPreview').style.display = 'none';
    }
});

// Calculate profit/loss
function calculateProfitLoss() {
    const investasiSelect = document.getElementById('investasi_id');
    const hargaJualInput = document.getElementById('harga_jual');
    
    if (investasiSelect.value && hargaJualInput.value) {
        const selected = investasiSelect.options[investasiSelect.selectedIndex];
        const modal = parseFloat(selected.dataset.modal);
        const hargaJual = parseFloat(hargaJualInput.value.replace(/\./g, '').replace(',', '.'));
        
        const profitLoss = hargaJual - modal;
        const roi = modal > 0 ? (profitLoss / modal * 100) : 0;
        
        const profitLossEl = document.getElementById('profitLossAmount');
        profitLossEl.textContent = (profitLoss >= 0 ? '+' : '') + formatCurrency(profitLoss);
        profitLossEl.className = profitLoss >= 0 ? 'positive' : 'negative';
        
        const roiEl = document.getElementById('roiPercent');
        roiEl.textContent = (roi >= 0 ? '+' : '') + roi.toFixed(2) + '%';
        roiEl.className = roi >= 0 ? 'positive' : 'negative';
        
        document.getElementById('profitLossPreview').style.display = 'block';
    } else {
        document.getElementById('profitLossPreview').style.display = 'none';
    }
}

// Format currency helper
function formatCurrency(amount) {
    return 'Rp ' + new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

// Trigger on load if ID is preset
if (document.getElementById('investasi_id').value) {
    document.getElementById('investasi_id').dispatchEvent(new Event('change'));
}

// Form validation
document.getElementById('saleForm').addEventListener('submit', function(e) {
    const investasiId = document.getElementById('investasi_id').value;
    const hargaJual = document.getElementById('harga_jual').value;
    
    if (!investasiId) {
        e.preventDefault();
        alert('Pilih investasi yang akan dijual');
        return false;
    }
    
    if (!hargaJual || parseFloat(hargaJual.replace(/\./g, '')) <= 0) {
        e.preventDefault();
        alert('Harga jual harus diisi dan lebih dari 0');
        return false;
    }
    
    return confirm('Yakin ingin menjual investasi ini?');
});
</script>

<style>
.investment-info, .profit-loss-preview {
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    padding: 1rem;
    margin: 1rem 0;
}

.info-grid, .preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.info-item, .preview-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.info-item label, .preview-item label {
    font-size: 0.875rem;
    color: #64748b;
    font-weight: 500;
}

.info-item span, .preview-item span {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1e293b;
}

.info-item span.positive, .preview-item span.positive {
    color: #10b981;
}

.info-item span.negative, .preview-item span.negative {
    color: #ef4444;
}

.profit-loss-preview {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.profit-loss-preview label {
    color: rgba(255, 255, 255, 0.9);
}

.profit-loss-preview span {
    color: white;
    font-size: 1.5rem;
}
</style>

</body>
</html>
