<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * Edit Cash Balance Transaction
 */

session_start();
require_once "../config/koneksi.php";
require_once "../config/functions.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    set_flash_message('error', 'ID transaksi kas tidak valid!');
    header("Location: ../dashboard.php");
    exit;
}

$cash_id = (int)$_GET['id'];

// Get cash transaction data
$sql = "SELECT * FROM cash_balance WHERE id = ?";
$stmt = $koneksi->prepare($sql);
$stmt->execute([$cash_id]);
$cash = $stmt->fetch();

if (!$cash) {
    set_flash_message('error', 'Transaksi kas tidak ditemukan!');
    header("Location: ../dashboard.php");
    exit;
}

// Get categories
$categories = $koneksi->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = trim($_POST['judul']);
    $tipe = $_POST['tipe'];
    $kategori = $_POST['kategori'];
    $jumlah = (float)str_replace(['.', ','], ['', '.'], $_POST['jumlah']);
    $tanggal = $_POST['tanggal'];
    $keterangan = trim($_POST['keterangan']);
    $kategori_id = !empty($_POST['kategori_id']) ? (int)$_POST['kategori_id'] : null;
    
    // Validation
    $errors = [];
    if (empty($judul)) $errors[] = "Judul tidak boleh kosong";
    if ($jumlah <= 0) $errors[] = "Jumlah harus lebih dari 0";
    if (empty($tanggal)) $errors[] = "Tanggal tidak boleh kosong";
    
    // Handle file upload
    $bukti_file = $cash['bukti_file']; // Keep old file by default
    if (isset($_FILES['bukti_file']) && $_FILES['bukti_file']['error'] === UPLOAD_ERR_OK) {
        $upload_result = upload_file($_FILES['bukti_file'], 'cash');
        if ($upload_result['success']) {
            // Delete old file if exists
            if (!empty($cash['bukti_file'])) {
                delete_file($cash['bukti_file']);
            }
            $bukti_file = $upload_result['filename'];
        } else {
            $errors[] = $upload_result['message'];
        }
    }
    
    if (empty($errors)) {
        try {
            $sql = "UPDATE cash_balance SET 
                    judul = ?, 
                    tipe = ?, 
                    kategori = ?, 
                    jumlah = ?, 
                    tanggal = ?, 
                    keterangan = ?, 
                    bukti_file = ?,
                    kategori_id = ?
                    WHERE id = ?";
            
            $stmt = $koneksi->prepare($sql);
            $stmt->execute([
                $judul, 
                $tipe, 
                $kategori, 
                $jumlah, 
                $tanggal, 
                $keterangan, 
                $bukti_file,
                $kategori_id,
                $cash_id
            ]);
            
            set_flash_message('success', 'Transaksi kas berhasil diupdate!');
            header("Location: cash_balance.php");
            exit;
            
        } catch (PDOException $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

$page_title = "Edit Transaksi Kas";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - SAZEN</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <a href="cash_balance.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <h1><i class="fas fa-edit"></i> <?= $page_title ?></h1>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" enctype="multipart/form-data" class="admin-form">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="judul">
                            <i class="fas fa-heading"></i> Judul Transaksi *
                        </label>
                        <input type="text" 
                               id="judul" 
                               name="judul" 
                               value="<?= htmlspecialchars($cash['judul']) ?>" 
                               placeholder="Contoh: Gaji Bulan Januari" 
                               required>
                    </div>

                    <div class="form-group">
                        <label for="tipe">
                            <i class="fas fa-exchange-alt"></i> Tipe Transaksi *
                        </label>
                        <select id="tipe" name="tipe" required>
                            <option value="masuk" <?= $cash['tipe'] == 'masuk' ? 'selected' : '' ?>>Kas Masuk</option>
                            <option value="keluar" <?= $cash['tipe'] == 'keluar' ? 'selected' : '' ?>>Kas Keluar</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="kategori">
                            <i class="fas fa-tag"></i> Kategori Kas *
                        </label>
                        <select id="kategori" name="kategori" required>
                            <option value="operasional" <?= $cash['kategori'] == 'operasional' ? 'selected' : '' ?>>Operasional</option>
                            <option value="investasi" <?= $cash['kategori'] == 'investasi' ? 'selected' : '' ?>>Investasi</option>
                            <option value="pendapatan" <?= $cash['kategori'] == 'pendapatan' ? 'selected' : '' ?>>Pendapatan</option>
                            <option value="lainnya" <?= $cash['kategori'] == 'lainnya' ? 'selected' : '' ?>>Lainnya</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="kategori_id">
                            <i class="fas fa-folder"></i> Kategori Investasi (Opsional)
                        </label>
                        <select id="kategori_id" name="kategori_id">
                            <option value="">-- Tidak Ada --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $cash['kategori_id'] == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['nama_kategori']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="jumlah">
                            <i class="fas fa-money-bill-wave"></i> Jumlah (Rp) *
                        </label>
                        <input type="text" 
                               id="jumlah" 
                               name="jumlah" 
                               value="<?= number_format($cash['jumlah'], 0, ',', '.') ?>" 
                               placeholder="0" 
                               required>
                        <small>Gunakan titik atau koma sebagai pemisah ribuan</small>
                    </div>

                    <div class="form-group">
                        <label for="tanggal">
                            <i class="fas fa-calendar"></i> Tanggal *
                        </label>
                        <input type="date" 
                               id="tanggal" 
                               name="tanggal" 
                               value="<?= $cash['tanggal'] ?>" 
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="keterangan">
                        <i class="fas fa-comment"></i> Keterangan
                    </label>
                    <textarea id="keterangan" 
                              name="keterangan" 
                              rows="4" 
                              placeholder="Tambahkan catatan atau keterangan (opsional)"><?= htmlspecialchars($cash['keterangan']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="bukti_file">
                        <i class="fas fa-paperclip"></i> Bukti File (Opsional)
                    </label>
                    <?php if (!empty($cash['bukti_file'])): ?>
                        <div class="current-file">
                            <i class="fas fa-file"></i>
                            <span>File saat ini: <?= htmlspecialchars($cash['bukti_file']) ?></span>
                            <a href="../view_file.php?type=cash&id=<?= $cash_id ?>" target="_blank" class="btn-view-file">
                                <i class="fas fa-eye"></i> Lihat
                            </a>
                        </div>
                    <?php endif; ?>
                    <input type="file" 
                           id="bukti_file" 
                           name="bukti_file" 
                           accept=".jpg,.jpeg,.png,.pdf">
                    <small>Upload file baru untuk mengganti file lama (Max 5MB: JPG, PNG, PDF)</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Transaksi
                    </button>
                    <a href="cash_balance.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Format currency input
        document.getElementById('jumlah').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d]/g, '');
            e.target.value = new Intl.NumberFormat('id-ID').format(value);
        });

        // Form validation
        document.querySelector('.admin-form').addEventListener('submit', function(e) {
            const jumlah = document.getElementById('jumlah').value.replace(/\./g, '');
            if (parseInt(jumlah) <= 0) {
                e.preventDefault();
                alert('Jumlah harus lebih dari 0');
            }
        });
    </script>
</body>
</html>
