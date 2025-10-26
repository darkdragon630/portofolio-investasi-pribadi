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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = trim($_POST['judul']);
    $tipe = $_POST['tipe'];
    $kategori = $_POST['kategori'];
    $jumlah = (float)str_replace(['.', ','], ['', '.'], $_POST['jumlah']);
    $tanggal = $_POST['tanggal'];
    $keterangan = trim($_POST['keterangan']);
    
    // Validation
    $errors = [];
    if (empty($judul)) $errors[] = "Judul tidak boleh kosong";
    if ($jumlah <= 0) $errors[] = "Jumlah harus lebih dari 0";
    if (empty($tanggal)) $errors[] = "Tanggal tidak boleh kosong";
    
    // Handle file upload (store to database, not filesystem)
    $bukti_file = $cash['bukti_file']; // Keep old file by default
    $file_changed = false;
    
    if (isset($_FILES['bukti_file']) && $_FILES['bukti_file']['error'] === UPLOAD_ERR_OK) {
        error_log("Processing new file upload...");
        
        try {
            $uploaded_file_data = handle_file_upload_to_db($_FILES['bukti_file']);
            
            if ($uploaded_file_data) {
                $bukti_file = $uploaded_file_data;
                $file_changed = true;
                error_log("File uploaded successfully");
            }
        } catch (Exception $e) {
            error_log("File upload exception: " . $e->getMessage());
            $errors[] = "Gagal upload bukti: " . $e->getMessage();
        }
    } elseif (isset($_FILES['bukti_file']) && $_FILES['bukti_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi MAX_FILE_SIZE)',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP'
        ];
        
        $error_msg = $upload_errors[$_FILES['bukti_file']['error']] ?? 'Error tidak diketahui';
        $errors[] = $error_msg;
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
                    bukti_file = ?
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --primary-light: #dbeafe;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            --radius: 12px;
            --radius-lg: 16px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: var(--gray-800);
            line-height: 1.6;
        }

        .admin-container {
            max-width: 1000px;
            margin: 0 auto;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .admin-header {
            background: white;
            padding: 24px 32px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .back-btn {
            background: var(--gray-100);
            color: var(--gray-700);
            padding: 10px 18px;
            border-radius: var(--radius);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .back-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateX(-4px);
            border-color: var(--primary-dark);
        }

        .admin-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-header h1 i {
            color: var(--primary);
            font-size: 26px;
        }

        .alert {
            padding: 16px 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: slideDown 0.3s ease;
            box-shadow: var(--shadow);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-danger {
            background: #fee;
            color: #c00;
            border-left: 4px solid var(--danger);
        }

        .alert i {
            font-size: 20px;
            margin-top: 2px;
        }

        .alert ul {
            list-style: none;
            margin: 0;
        }

        .alert li {
            margin: 4px 0;
        }

        .form-card {
            background: white;
            padding: 32px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
        }

        .admin-form {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 600;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .form-group label i {
            color: var(--primary);
            width: 18px;
            text-align: center;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 16px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            font-size: 15px;
            font-family: inherit;
            transition: all 0.3s ease;
            background: var(--gray-50);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .form-group input:hover,
        .form-group select:hover,
        .form-group textarea:hover {
            border-color: var(--gray-300);
        }

        .form-group small {
            color: var(--gray-500);
            font-size: 13px;
            margin-top: 4px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .current-file {
            background: var(--gray-50);
            padding: 16px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            border: 2px solid var(--gray-200);
        }

        .current-file i {
            color: var(--primary);
            font-size: 24px;
        }

        .current-file span {
            flex: 1;
            color: var(--gray-700);
            font-weight: 500;
        }

        .btn-view-file {
            background: var(--primary);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-view-file:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .form-actions {
            display: flex;
            gap: 12px;
            padding-top: 12px;
            border-top: 2px solid var(--gray-100);
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: var(--radius);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 2px solid var(--gray-200);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
            border-color: var(--gray-300);
        }

        input[type="file"] {
            padding: 10px 12px !important;
            cursor: pointer;
        }

        input[type="file"]::file-selector-button {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            background: var(--primary);
            color: white;
            cursor: pointer;
            font-weight: 500;
            margin-right: 12px;
            transition: all 0.3s ease;
        }

        input[type="file"]::file-selector-button:hover {
            background: var(--primary-dark);
        }

        @media (max-width: 768px) {
            body {
                padding: 12px;
            }

            .admin-header {
                padding: 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .admin-header h1 {
                font-size: 22px;
            }

            .form-card {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .back-btn {
                width: fit-content;
            }

            .current-file {
                flex-direction: column;
                align-items: flex-start;
            }

            .btn-view-file {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .admin-header h1 {
                font-size: 20px;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                font-size: 14px;
            }
        }

        /* Loading state for submit button */
        .btn-primary:disabled {
            background: var(--gray-300);
            cursor: not-allowed;
            transform: none;
        }

        /* Focus visible for accessibility */
        *:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }
    </style>
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
                            <span>File saat ini tersimpan</span>
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
                return;
            }
            
            // Disable submit button to prevent double submission
            const submitBtn = this.querySelector('.btn-primary');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
        });
    </script>
</body>
</html>
