<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * Edit Investasi - Database Storage Version
 */

session_start();
require_once "../config/koneksi.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect_with_message("../dashboard.php", "error", "ID investasi tidak valid");
    exit;
}

$id = (int)$_GET['id'];

// Get investment data
try {
    $sql = "SELECT i.*, k.nama_kategori 
            FROM investasi i 
            JOIN kategori k ON i.kategori_id = k.id 
            WHERE i.id = ?";
    $stmt = $koneksi->prepare($sql);
    $stmt->execute([$id]);
    $investasi = $stmt->fetch();
    
    if (!$investasi) {
        redirect_with_message("../dashboard.php", "error", "Data investasi tidak ditemukan");
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching investment: " . $e->getMessage());
    redirect_with_message("../dashboard.php", "error", "Terjadi kesalahan saat mengambil data");
    exit;
}

// Get all categories
$sql_kategori = "SELECT * FROM kategori ORDER BY nama_kategori";
$stmt_kategori = $koneksi->query($sql_kategori);
$kategori_list = $stmt_kategori->fetchAll();

$error = "";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    try {
        error_log("=== EDIT INVESTASI START ===");
        
        $judul = sanitize_input($_POST['judul_investasi']);
        $deskripsi = sanitize_input($_POST['deskripsi']);
        $jumlah_input = $_POST['jumlah'] ?? '';
        $tanggal = $_POST['tanggal_investasi'] ?? '';
        $kategori_id = (int)($_POST['kategori_id'] ?? 0);
        
        $jumlah = parse_currency($jumlah_input);
        
        // Validation
        if (empty($judul)) {
            throw new Exception("Judul investasi wajib diisi.");
        }
        
        if ($jumlah < 0) {
            throw new Exception("Jumlah investasi tidak valid.");
        }
        
        if (empty($tanggal)) {
            throw new Exception("Tanggal investasi wajib diisi.");
        }
        
        if ($kategori_id <= 0) {
            throw new Exception("Kategori wajib dipilih.");
        }
        
        // Keep old file data by default
        $new_bukti_file = $investasi['bukti_file'];
        $file_changed = false;
        
        // Handle file upload if new file provided
        if (isset($_FILES['bukti']) && $_FILES['bukti']['error'] === UPLOAD_ERR_OK) {
            error_log("Processing new file upload...");
            
            try {
                $uploaded_file_data = handle_file_upload_to_db($_FILES['bukti']);
                
                if ($uploaded_file_data) {
                    $new_bukti_file = $uploaded_file_data;
                    $file_changed = true;
                    error_log("File uploaded successfully");
                }
            } catch (Exception $e) {
                error_log("File upload exception: " . $e->getMessage());
                throw new Exception("Gagal upload bukti: " . $e->getMessage());
            }
        } elseif (isset($_FILES['bukti']) && $_FILES['bukti']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi upload_max_filesize)',
                UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi MAX_FILE_SIZE)',
                UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
                UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan',
                UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
                UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP'
            ];
            
            $error_msg = $upload_errors[$_FILES['bukti']['error']] ?? 'Error tidak diketahui';
            throw new Exception($error_msg);
        }
        
        // Track changes
        $changes = [];
        if ($investasi['judul_investasi'] !== $judul) $changes[] = 'judul';
        if ($investasi['kategori_id'] != $kategori_id) $changes[] = 'kategori';
        if ($investasi['jumlah'] != $jumlah) $changes[] = 'jumlah';
        if ($investasi['tanggal_investasi'] !== $tanggal) $changes[] = 'tanggal';
        if ($investasi['deskripsi'] !== $deskripsi) $changes[] = 'deskripsi';
        if ($file_changed) $changes[] = 'bukti';
        
        if (count($changes) === 0) {
            throw new Exception("Tidak ada perubahan data yang perlu disimpan.");
        }
        
        // Update database
        $sql_update = "UPDATE investasi SET 
                      judul_investasi = ?,
                      deskripsi = ?,
                      jumlah = ?,
                      tanggal_investasi = ?,
                      kategori_id = ?,
                      bukti_file = ?,
                      updated_at = NOW()
                      WHERE id = ?";
        
        $stmt_update = $koneksi->prepare($sql_update);
        $result = $stmt_update->execute([
            $judul,
            $deskripsi,
            $jumlah,
            $tanggal,
            $kategori_id,
            $new_bukti_file,
            $id
        ]);
        
        if ($result) {
            $change_details = implode(', ', $changes);
            $message = "✅ Data investasi berhasil diperbarui! Perubahan: " . $change_details;
            
            if ($file_changed) {
                $file_info = parse_bukti_file($new_bukti_file);
                $message .= " | 📎 Bukti baru: " . ($file_info['original_name'] ?? 'file baru');
            }
            
            error_log("=== EDIT INVESTASI SUCCESS ===");
            redirect_with_message("../dashboard.php", "success", $message);
            exit;
        } else {
            throw new Exception("Gagal memperbarui data investasi.");
        }
        
    } catch (Exception $e) {
        $error = "❌ " . $e->getMessage();
        error_log("Edit investment error: " . $e->getMessage());
    }
}

// Parse existing file data for display
$current_file = $investasi['bukti_file'] ? parse_bukti_file($investasi['bukti_file']) : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Investasi - SAZEN v3.0</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/form_edit.css">
</head>
<body>
    <div class="form-wrapper">
        <div class="form-header">
            <a href="../dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                <span>Kembali</span>
            </a>
        </div>

        <div class="form-container">
            <div class="form-title-section">
                <div class="title-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="title-content">
                    <h1>Edit Investasi</h1>
                    <p>Perbarui informasi investasi Anda</p>
                </div>
            </div>

            <!-- Current Data Info -->
            <div class="info-card">
                <div class="info-header">
                    <i class="fas fa-info-circle"></i>
                    <span>Data Saat Ini</span>
                </div>
                <div class="info-content">
                    <div class="info-item">
                        <span class="info-label">Judul:</span>
                        <span class="info-value"><?= htmlspecialchars($investasi['judul_investasi']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Kategori:</span>
                        <span class="info-value"><?= htmlspecialchars($investasi['nama_kategori']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Jumlah:</span>
                        <span class="info-value"><?= format_currency($investasi['jumlah']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Tanggal:</span>
                        <span class="info-value"><?= date('d F Y', strtotime($investasi['tanggal_investasi'])) ?></span>
                    </div>
                    <?php if ($current_file): ?>
                        <div class="info-item">
                            <span class="info-label">Bukti:</span>
                            <span class="info-value">
                               <a href="../view_file.php?id=<?= $investasi['id'] ?>&type=investasi"
                                   target="_blank" class="file-link">
                                    <i class="fas fa-file-alt"></i>
                                    <?= htmlspecialchars($current_file['original_name']) ?>
                                </a>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="message error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="investment-form">
                <input type="hidden" name="update" value="1">
                
                <div class="form-group">
                    <label for="judul_investasi">
                        <i class="fas fa-heading"></i>
                        Judul Investasi <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="judul_investasi" 
                        name="judul_investasi" 
                        class="form-input"
                        value="<?= htmlspecialchars($investasi['judul_investasi']) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="kategori_id">
                        <i class="fas fa-tags"></i>
                        Kategori <span class="required">*</span>
                    </label>
                    <select id="kategori_id" name="kategori_id" class="form-select" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($kategori_list as $kat): ?>
                            <option value="<?= $kat['id'] ?>" <?= $investasi['kategori_id'] == $kat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kat['nama_kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="jumlah">
                            <i class="fas fa-money-bill-wave"></i>
                            Jumlah Investasi (Rp) <span class="required">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-prefix">Rp</span>
                            <input 
                                type="text" 
                                id="jumlah" 
                                name="jumlah" 
                                class="form-input"
                                value="<?= number_format($investasi['jumlah'], 2, ',', '.') ?>"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="tanggal_investasi">
                            <i class="fas fa-calendar-alt"></i>
                            Tanggal Investasi <span class="required">*</span>
                        </label>
                        <input 
                            type="date" 
                            id="tanggal_investasi" 
                            name="tanggal_investasi" 
                            class="form-input"
                            value="<?= $investasi['tanggal_investasi'] ?>"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="deskripsi">
                        <i class="fas fa-align-left"></i>
                        Deskripsi
                    </label>
                    <textarea 
                        id="deskripsi" 
                        name="deskripsi" 
                        class="form-textarea" 
                        rows="4"
                    ><?= htmlspecialchars($investasi['deskripsi']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="bukti">
                        <i class="fas fa-file-upload"></i>
                        Ganti Bukti Transaksi (Opsional)
                    </label>
                    
                    <?php if ($current_file): ?>
                        <div class="current-file">
                            <div class="file-info">
                                <i class="fas fa-file-alt"></i>
                                <span>File saat ini: <strong><?= htmlspecialchars($current_file['original_name']) ?></strong></span>
                                <span class="file-size">(<?= number_format($current_file['size'] / 1024, 2) ?> KB)</span>
                            </div>
                            <a href="../view_file.php?id=<?= $investasi['id'] ?>&type=investasi"
                               target="_blank" 
                               class="btn-preview">
                                <i class="fas fa-eye"></i>
                                Lihat
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="file-upload-wrapper">
                        <input 
                            type="file" 
                            id="bukti" 
                            name="bukti" 
                            class="file-input"
                            accept=".jpg,.jpeg,.png,.pdf"
                        >
                        <label for="bukti" class="file-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span class="file-text">Pilih file baru (JPG, PNG, PDF - Max 5MB)</span>
                        </label>
                    </div>

                    <div id="filePreview" class="file-preview"></div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <span>Simpan Perubahan</span>
                    </button>
                    <a href="../dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        <span>Batal</span>
                    </a>
                    <button type="button" class="btn btn-warning" onclick="location.reload();">
                        <i class="fas fa-undo"></i>
                        <span>Reset</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // File upload preview
        const fileInput = document.getElementById('bukti');
        const filePreview = document.getElementById('filePreview');
        const fileLabel = document.querySelector('.file-label .file-text');

        fileInput.addEventListener('change', function() {
            filePreview.innerHTML = '';
            
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const fileName = file.name;
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                const fileType = file.type;

                // Validate file size
                if (fileSize > 5) {
                    alert('❌ File terlalu besar! Maksimal 5MB');
                    this.value = '';
                    fileLabel.textContent = 'Pilih file baru (JPG, PNG, PDF - Max 5MB)';
                    return;
                }

                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                if (!allowedTypes.includes(fileType)) {
                    alert('❌ Tipe file tidak didukung! Gunakan JPG, PNG, atau PDF');
                    this.value = '';
                    fileLabel.textContent = 'Pilih file baru (JPG, PNG, PDF - Max 5MB)';
                    return;
                }

                fileLabel.textContent = fileName;

                const previewDiv = document.createElement('div');
                previewDiv.className = 'preview-item';

                if (fileType.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewDiv.innerHTML = `
                            <img src="${e.target.result}" alt="Preview">
                            <div class="preview-info">
                                <span class="preview-name">${fileName}</span>
                                <span class="preview-size">${fileSize} MB</span>
                            </div>
                            <button type="button" class="btn-remove" onclick="removeFile()">
                                <i class="fas fa-times"></i>
                            </button>
                        `;
                        filePreview.appendChild(previewDiv);
                    };
                    reader.readAsDataURL(file);
                } else {
                    previewDiv.innerHTML = `
                        <i class="fas fa-file-pdf"></i>
                        <div class="preview-info">
                            <span class="preview-name">${fileName}</span>
                            <span class="preview-size">${fileSize} MB</span>
                        </div>
                        <button type="button" class="btn-remove" onclick="removeFile()">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    filePreview.appendChild(previewDiv);
                }
            }
        });

        function removeFile() {
            fileInput.value = '';
            filePreview.innerHTML = '';
            fileLabel.textContent = 'Pilih file baru (JPG, PNG, PDF - Max 5MB)';
        }

        // Format currency input
        const jumlahInput = document.getElementById('jumlah');
        jumlahInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d,]/g, '');
            e.target.value = value;
        });

        // Auto-hide messages
        setTimeout(() => {
            document.querySelectorAll('.message').forEach(msg => {
                msg.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => msg.remove(), 300);
            });
        }, 7000);
    </script>
</body>
</html>