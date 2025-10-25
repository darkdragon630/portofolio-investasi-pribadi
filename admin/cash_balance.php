<?php
/**
 * Form Cash Balance Management
 */

session_start();
require_once "../config/koneksi.php";
require_once "../config/functions.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal = $_POST['tanggal'];
    $judul = sanitize_input($_POST['judul']);
    $tipe = $_POST['tipe'];
    $jumlah = (float)str_replace(',', '.', str_replace('.', '', $_POST['jumlah']));
    $kategori = $_POST['kategori'];
    $keterangan = sanitize_input($_POST['keterangan'] ?? '');
    
    // Validate
    if (empty($tanggal)) {
        $errors[] = "Tanggal harus diisi";
    }
    if (empty($judul)) {
        $errors[] = "Judul transaksi harus diisi";
    }
    if (!in_array($tipe, ['masuk', 'keluar'])) {
        $errors[] = "Tipe transaksi tidak valid";
    }
    if ($jumlah <= 0) {
        $errors[] = "Jumlah harus lebih dari 0";
    }
    if (empty($kategori)) {
        $errors[] = "Kategori harus dipilih";
    }
    
    // Handle file upload untuk LONGBLOB
    $bukti_file = null;
    if (isset($_FILES['bukti_file']) && $_FILES['bukti_file']['error'] === UPLOAD_ERR_OK) {
        try {
            // Untuk LONGBLOB, simpan sebagai base64 dengan metadata JSON
            $bukti_file = handle_file_upload_to_db($_FILES['bukti_file']);
        } catch (Exception $e) {
            $errors[] = "Upload error: " . $e->getMessage();
        }
    }
    
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO cash_balance 
                    (tanggal, judul, tipe, jumlah, kategori, keterangan, bukti_file) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $koneksi->prepare($sql);
            $result = $stmt->execute([
                $tanggal,
                $judul,
                $tipe,
                $jumlah,
                $kategori,
                $keterangan,
                $bukti_file
            ]);
            
            if ($result) {
                redirect_with_message("../dashboard.php", 'success', 'Transaksi kas berhasil dicatat!');
            } else {
                $errors[] = "Gagal menyimpan transaksi kas";
            }
        } catch (PDOException $e) {
            error_log("Error save cash: " . $e->getMessage());
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Get current balance
$balance = get_cash_balance($koneksi);
$recent_transactions = get_recent_cash_transactions($koneksi, 10);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kas - SAZEN</title>
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
            <h1><i class="fas fa-wallet"></i> Kelola Kas</h1>
            <p>Catat transaksi kas masuk dan keluar</p>
        </div>
    </div>

    <!-- Current Balance Display -->
    <div class="balance-summary">
        <div class="balance-card">
            <div class="balance-label">Saldo Kas Saat Ini</div>
            <div class="balance-value <?= $balance['saldo_akhir'] >= 0 ? 'positive' : 'negative' ?>">
                <?= format_currency($balance ? $balance['saldo_akhir'] : 0) ?>
            </div>
            <div class="balance-details">
                <span class="in">Masuk: <?= format_currency($balance ? $balance['total_masuk'] : 0) ?></span>
                <span class="out">Keluar: <?= format_currency($balance ? $balance['total_keluar'] : 0) ?></span>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <div>
                <strong>Terjadi Kesalahan:</strong>
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div>Transaksi berhasil disimpan!</div>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="investment-form" id="cashForm">
        
        <div class="form-section">
            <h3><i class="fas fa-info-circle"></i> Informasi Transaksi</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="tanggal">Tanggal <span class="required">*</span></label>
                    <input type="date" name="tanggal" id="tanggal" 
                           value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label for="tipe">Tipe Transaksi <span class="required">*</span></label>
                    <select name="tipe" id="tipe" required>
                        <option value="masuk">Kas Masuk</option>
                        <option value="keluar">Kas Keluar</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="judul">Judul Transaksi <span class="required">*</span></label>
                <input type="text" name="judul" id="judul" 
                       placeholder="Misal: Dividen Saham, Pembelian Aset, dll" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="jumlah">Jumlah <span class="required">*</span></label>
                    <input type="text" name="jumlah" id="jumlah" 
                           placeholder="0" required class="currency-input">
                </div>

                <div class="form-group">
                    <label for="kategori">Kategori <span class="required">*</span></label>
                    <select name="kategori" id="kategori" required>
                        <option value="">-- Pilih Kategori --</option>
                        <option value="modal_awal">Modal Awal</option>
                        <option value="top_up">Top Up</option>
                        <option value="hasil_jual">Hasil Jual Investasi</option>
                        <option value="tarik_dana">Penarikan Dana</option>
                        <option value="investasi_baru">Investasi Baru</option>
                        <option value="dividen">Dividen</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="keterangan">Keterangan</label>
                <textarea name="keterangan" id="keterangan" rows="3" 
                          placeholder="Catatan tambahan tentang transaksi ini..."></textarea>
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
                <i class="fas fa-save"></i> Simpan Transaksi
            </button>
        </div>
    </form>

    <!-- Recent Transactions -->
    <?php if (count($recent_transactions) > 0): ?>
    <div class="recent-section">
        <h3><i class="fas fa-history"></i> Transaksi Terakhir</h3>
        <div class="transactions-list">
            <?php foreach ($recent_transactions as $tx): ?>
                <div class="transaction-item">
                    <div class="transaction-icon <?= $tx['tipe'] ?>">
                        <i class="fas fa-arrow-<?= $tx['tipe'] == 'masuk' ? 'down' : 'up' ?>"></i>
                    </div>
                    <div class="transaction-info">
                        <h4><?= htmlspecialchars($tx['judul']) ?></h4>
                        <p><?= ucfirst(str_replace('_', ' ', $tx['kategori'])) ?> • <?= date('d M Y', strtotime($tx['tanggal'])) ?></p>
                    </div>
                    <div class="transaction-amount <?= $tx['tipe'] == 'masuk' ? 'positive' : 'negative' ?>">
                        <?= $tx['tipe'] == 'masuk' ? '+' : '-' ?><?= format_currency($tx['jumlah']) ?>
                    </div>
                    <div class="transaction-actions">
                        <a href="edit_cash.php?id=<?= $tx['id'] ?>" class="btn-icon" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="delete_cash.php?id=<?= $tx['id'] ?>" class="btn-icon danger" 
                           title="Hapus" onclick="return confirm('Yakin hapus transaksi ini?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Format currency input
document.getElementById('jumlah').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    e.target.value = new Intl.NumberFormat('id-ID').format(value);
});

// Change form color based on type
document.getElementById('tipe').addEventListener('change', function() {
    const form = document.getElementById('cashForm');
    if (this.value === 'masuk') {
        form.classList.remove('type-keluar');
        form.classList.add('type-masuk');
    } else {
        form.classList.remove('type-masuk');
        form.classList.add('type-keluar');
    }
});

// Form validation
document.getElementById('cashForm').addEventListener('submit', function(e) {
    const jumlah = document.getElementById('jumlah').value;
    
    if (!jumlah || parseFloat(jumlah.replace(/\./g, '')) <= 0) {
        e.preventDefault();
        alert('Jumlah harus diisi dan lebih dari 0');
        return false;
    }
    
    return true;
});
</script>

<style>
.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #6ee7b7;
    padding: 1rem 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: start;
    gap: 0.75rem;
}

.balance-summary {
    margin: 2rem 0;
}

.balance-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    text-align: center;
}

.balance-label {
    font-size: 1rem;
    opacity: 0.9;
    margin-bottom: 0.5rem;
}

.balance-value {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 1rem 0;
}

.balance-details {
    display: flex;
    justify-content: center;
    gap: 2rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
}

.balance-details span {
    font-size: 0.875rem;
}

.balance-details .in::before {
    content: '↓ ';
    color: #10b981;
}

.balance-details .out::before {
    content: '↑ ';
    color: #ef4444;
}

.recent-section {
    margin-top: 3rem;
    padding-top: 2rem;
    border-top: 2px solid #e2e8f0;
}

.recent-section h3 {
    margin-bottom: 1.5rem;
    color: #1e293b;
}

.transactions-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.transaction-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    transition: all 0.2s;
}

.transaction-item:hover {
    background: white;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.transaction-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.transaction-icon.masuk {
    background: #d1fae5;
    color: #10b981;
}

.transaction-icon.keluar {
    background: #fee2e2;
    color: #ef4444;
}

.transaction-info {
    flex: 1;
}

.transaction-info h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.transaction-info p {
    font-size: 0.875rem;
    color: #64748b;
}

.transaction-amount {
    font-size: 1.25rem;
    font-weight: 700;
    min-width: 150px;
    text-align: right;
}

.transaction-actions {
    display: flex;
    gap: 0.5rem;
}

.investment-form.type-masuk {
    border-left: 4px solid #10b981;
}

.investment-form.type-keluar {
    border-left: 4px solid #ef4444;
}

@media (max-width: 768px) {
    .transaction-item {
        flex-wrap: wrap;
    }
    
    .transaction-amount {
        width: 100%;
        text-align: left;
        margin-top: 0.5rem;
    }
}
</style>

</body>
</html>
