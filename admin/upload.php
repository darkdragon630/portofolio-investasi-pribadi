<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * Upload Investasi - Bukti disimpan ke JSON
 */

session_start();
require_once "../config/koneksi.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

$error = "";
$success = "";

// Get categories
try {
    $stmt_kategori = $koneksi->query("SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori");
    $kategori_list = $stmt_kategori->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $kategori_list = [];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    try {
        // Validate input
        $judul = sanitize_input($_POST['judul_investasi']);
        $deskripsi = sanitize_input($_POST['deskripsi']);
        $jumlah_input = $_POST['jumlah'] ?? '';
        $tanggal = $_POST['tanggal_investasi'] ?? '';
        $kategori_id = (int)($_POST['kategori_id'] ?? 0);

        // Parse currency
        $jumlah = parse_currency($jumlah_input);

        // Validation
        if (empty($judul)) {
            throw new Exception("Judul investasi wajib diisi.");
        }

        if ($jumlah <= 0) {
            throw new Exception("Jumlah investasi harus lebih dari 0.");
        }

        if (empty($tanggal)) {
            throw new Exception("Tanggal investasi wajib diisi.");
        }

        if ($kategori_id <= 0) {
            throw new Exception("Kategori wajib dipilih.");
        }

        // Handle file upload to JSON
        $bukti_file_id = null;
        if (isset($_FILES['bukti']) && $_FILES['bukti']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $bukti_file_id = handle_file_upload($_FILES['bukti'], JSON_FILE_INVESTASI);
            } catch (Exception $e) {
                throw new Exception("Gagal upload bukti: " . $e->getMessage());
            }
        }

        // Insert to database
        $sql = "INSERT INTO investasi (judul_investasi, deskripsi, jumlah, tanggal_investasi, kategori_id, bukti_file, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $koneksi->prepare($sql);
        $result = $stmt->execute([
            $judul,
            $deskripsi,
            $jumlah,
            $tanggal,
            $kategori_id,
            $bukti_file_id
        ]);

        if ($result) {
            //log_security_event("INVESTASI_CREATED", "User: {$_SESSION['username']}, Judul: $judul, Jumlah: $jumlah");
            redirect_with_message("../dashboard.php", "success", "✅ Investasi berhasil ditambahkan!");
        } else {
            throw new Exception("Gagal menyimpan data investasi.");
        }

    } catch (Exception $e) {
        $error = "❌ " . $e->getMessage();
        error_log("Upload Investasi Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Investasi - SAZEN v3.0</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/form_upload.css">
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <a href="../dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                <span>Kembali</span>
            </a>
            <h1><i class="fas fa-plus-circle"></i> Tambah Investasi</h1>
            <p>Catat investasi baru dengan bukti transaksi</p>
        </div>

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

        <form method="POST" enctype="multipart/form-data" class="investment-form">
            <div class="form-section">
                <h3><i class="fas fa-info-circle"></i> Informasi Dasar</h3>
                
                <div class="form-group">
                    <label for="judul_investasi">
                        <i class="fas fa-heading"></i>
                        Judul Investasi *
                    </label>
                    <input 
                        type="text" 
                        id="judul_investasi" 
                        name="judul_investasi" 
                        class="form-control"
                        placeholder="Contoh: Saham BBCA - 10 lot"
                        value="<?= isset($_POST['judul_investasi']) ? htmlspecialchars($_POST['judul_investasi']) : '' ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="kategori_id">
                        <i class="fas fa-tag"></i>
                        Kategori *
                    </label>
                    <select id="kategori_id" name="kategori_id" class="form-control" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($kategori_list as $kat): ?>
                            <option value="<?= $kat['id'] ?>" <?= (isset($_POST['kategori_id']) && $_POST['kategori_id'] == $kat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kat['nama_kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="deskripsi">
                        <i class="fas fa-align-left"></i>
                        Deskripsi
                    </label>
                    <textarea 
                        id="deskripsi" 
                        name="deskripsi" 
                        class="form-control"
                        rows="4"
                        placeholder="Deskripsi detail investasi (opsional)"
                    ><?= isset($_POST['deskripsi']) ? htmlspecialchars($_POST['deskripsi']) : '' ?></textarea>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-wallet"></i> Detail Finansial</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="jumlah">
                            <i class="fas fa-money-bill-wave"></i>
                            Jumlah Investasi (Rp) *
                        </label>
                        <input 
                            type="text" 
                            id="jumlah" 
                            name="jumlah" 
                            class="form-control"
                            placeholder="Contoh: 10000000 atau 10.000.000"
                            value="<?= isset($_POST['jumlah']) ? htmlspecialchars($_POST['jumlah']) : '' ?>"
                            required
                        >
                        <small class="form-hint">
                            <i class="fas fa-info-circle"></i>
                            Masukkan angka tanpa simbol atau dengan format: 10.000.000
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="tanggal_investasi">
                            <i class="fas fa-calendar-alt"></i>
                            Tanggal Investasi *
                        </label>
                        <input 
                            type="date" 
                            id="tanggal_investasi" 
                            name="tanggal_investasi" 
                            class="form-control"
                            value="<?= isset($_POST['tanggal_investasi']) ? htmlspecialchars($_POST['tanggal_investasi']) : date('Y-m-d') ?>"
                            required
                        >
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-file-upload"></i> Bukti Transaksi</h3>
                
                <div class="form-group">
                    <label for="bukti">
                        <i class="fas fa-paperclip"></i>
                        Upload Bukti Investasi
                    </label>
                    <div class="file-upload-wrapper">
                        <input 
                            type="file" 
                            id="bukti" 
                            name="bukti" 
                            class="file-input"
                            accept=".jpg,.jpeg,.png,.pdf"
                            onchange="previewFile(this)"
                        >
                        <label for="bukti" class="file-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Pilih File atau Drag & Drop</span>
                            <small>JPG, PNG, PDF (Max 5MB)</small>
                        </label>
                    </div>
                    <div id="filePreview" class="file-preview"></div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" name="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <span>Simpan Investasi</span>
                </button>
                <a href="../dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    <span>Batal</span>
                </a>
            </div>
        </form>
    </div>

    <script>
        // File preview
        function previewFile(input) {
            const preview = document.getElementById('filePreview');
            const file = input.files[0];
            
            if (file) {
                const reader = new FileReader();
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                const fileType = file.type;
                
                // Check file size
                if (fileSize > 5) {
                    alert('File terlalu besar! Maksimal 5MB');
                    input.value = '';
                    preview.innerHTML = '';
                    return;
                }
                
                reader.onload = function(e) {
                    let previewHTML = '';
                    
                    if (fileType.startsWith('image/')) {
                        previewHTML = `
                            <div class="preview-card">
                                <img src="${e.target.result}" alt="Preview">
                                <div class="preview-info">
                                    <strong>${file.name}</strong>
                                    <span>${fileSize} MB</span>
                                </div>
                                <button type="button" onclick="removeFile()" class="remove-btn">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        `;
                    } else if (fileType === 'application/pdf') {
                        previewHTML = `
                            <div class="preview-card">
                                <div class="pdf-icon">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <div class="preview-info">
                                    <strong>${file.name}</strong>
                                    <span>${fileSize} MB</span>
                                </div>
                                <button type="button" onclick="removeFile()" class="remove-btn">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        `;
                    }
                    
                    preview.innerHTML = previewHTML;
                    preview.style.display = 'block';
                };
                
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
                preview.style.display = 'none';
            }
        }
        
        function removeFile() {
            const input = document.getElementById('bukti');
            const preview = document.getElementById('filePreview');
            input.value = '';
            preview.innerHTML = '';
            preview.style.display = 'none';
        }
        
        // Format currency input
        const jumlahInput = document.getElementById('jumlah');
        jumlahInput.addEventListener('blur', function() {
            let value = this.value.replace(/[^\d]/g, '');
            if (value) {
                this.value = parseInt(value).toLocaleString('id-ID');
            }
        });
        
        jumlahInput.addEventListener('focus', function() {
            this.value = this.value.replace(/[^\d]/g, '');
        });
        
        // Form validation
        document.querySelector('.investment-form').addEventListener('submit', function(e) {
            const jumlah = document.getElementById('jumlah').value.replace(/[^\d]/g, '');
            if (!jumlah || parseInt(jumlah) <= 0) {
                e.preventDefault();
                alert('Jumlah investasi harus lebih dari 0');
                return false;
            }
        });
        
        // Drag & drop
        const fileLabel = document.querySelector('.file-label');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileLabel.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            fileLabel.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            fileLabel.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            fileLabel.classList.add('drag-over');
        }
        
        function unhighlight() {
            fileLabel.classList.remove('drag-over');
        }
        
        fileLabel.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            document.getElementById('bukti').files = files;
            previewFile(document.getElementById('bukti'));
        }
    </script>
</body>
</html>