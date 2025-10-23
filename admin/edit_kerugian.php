<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * Edit Kerugian - Database Storage
 * FIXED: Currency parsing issue
 */

session_start();
require_once "../config/koneksi.php";

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

// Get kerugian ID from URL
$kerugian_id = $_GET['id'] ?? '';

if (empty($kerugian_id)) {
    redirect_with_message("../dashboard.php", "error", "❌ ID Kerugian tidak valid");
    exit;
}

// Get existing kerugian data
$sql_kerugian = "
    SELECT ki.*, i.judul_investasi, i.jumlah as modal_investasi, k.nama_kategori
    FROM kerugian_investasi ki
    JOIN investasi i ON ki.investasi_id = i.id
    JOIN kategori k ON ki.kategori_id = k.id
    WHERE ki.id = ?
";
$stmt_kerugian = $koneksi->prepare($sql_kerugian);
$stmt_kerugian->execute([$kerugian_id]);
$kerugian = $stmt_kerugian->fetch();

if (!$kerugian) {
    redirect_with_message("../dashboard.php", "error", "❌ Data kerugian tidak ditemukan");
    exit;
}

// Get investments for dropdown
$sql_investasi = "
    SELECT i.id, i.judul_investasi, i.jumlah, k.nama_kategori, i.kategori_id 
    FROM investasi i 
    JOIN kategori k ON i.kategori_id = k.id 
    ORDER BY i.judul_investasi
";
$stmt_investasi = $koneksi->query($sql_investasi);
$investasi_list = $stmt_investasi->fetchAll();

// Initialize variables
$error = '';
$success = '';

// Get flash message
$flash = get_flash_message();
if ($flash) {
    $flash['type'] == 'success' ? $success = $flash['message'] : $error = $flash['message'];
}

/**
 * Parse currency input correctly
 * Handles formats: 1500000, 1.500.000, 1,500,000
 */
function parse_currency_fixed($value) {
    if (empty($value)) return 0;
    
    // Remove whitespace
    $value = trim($value);
    
    // Remove Rp and currency symbols
    $value = preg_replace('/[Rp\s]/', '', $value);
    
    // Count dots and commas to determine format
    $dotCount = substr_count($value, '.');
    $commaCount = substr_count($value, ',');
    
    // If contains both dots and commas, determine which is decimal separator
    if ($dotCount > 0 && $commaCount > 0) {
        $lastDot = strrpos($value, '.');
        $lastComma = strrpos($value, ',');
        
        // The last one is decimal separator
        if ($lastDot > $lastComma) {
            // Format: 1,500,000.50 (English)
            $value = str_replace(',', '', $value); // Remove thousand separator
        } else {
            // Format: 1.500.000,50 (Indonesian)
            $value = str_replace('.', '', $value); // Remove thousand separator
            $value = str_replace(',', '.', $value); // Change decimal separator
        }
    }
    // If only dots (could be thousand separator or decimal)
    else if ($dotCount > 0) {
        if ($dotCount > 1) {
            // Multiple dots = thousand separator (1.500.000)
            $value = str_replace('.', '', $value);
        } else {
            // Single dot - check if it's decimal or thousand separator
            $parts = explode('.', $value);
            if (strlen($parts[1]) <= 2) {
                // Likely decimal: 1500.50
                // Keep as is
            } else {
                // Likely thousand separator: 1.500 or 1.500000
                $value = str_replace('.', '', $value);
            }
        }
    }
    // If only commas (could be thousand separator or decimal)
    else if ($commaCount > 0) {
        if ($commaCount > 1) {
            // Multiple commas = thousand separator (1,500,000)
            $value = str_replace(',', '', $value);
        } else {
            // Single comma - check if it's decimal or thousand separator
            $parts = explode(',', $value);
            if (strlen($parts[1]) <= 2) {
                // Likely decimal: 1500,50
                $value = str_replace(',', '.', $value);
            } else {
                // Likely thousand separator: 1,500 or 1,500000
                $value = str_replace(',', '', $value);
            }
        }
    }
    // No separator - plain number (4, 1500000)
    // Keep as is
    
    // Convert to float
    return floatval($value);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Collect form data
        $investasi_id = $_POST['investasi_id'] ?? '';
        $kategori_id = $_POST['kategori_id'] ?? '';
        $judul_kerugian = sanitize_input($_POST['judul_kerugian'] ?? '');
        $deskripsi = sanitize_input($_POST['deskripsi'] ?? '');
        
        // USE FIXED PARSER
        $jumlah_kerugian = parse_currency_fixed($_POST['jumlah_kerugian'] ?? '0');
        
        // Debug log (optional - remove in production)
        error_log("Edit Kerugian - Original input: " . ($_POST['jumlah_kerugian'] ?? '0'));
        error_log("Edit Kerugian - Parsed value: " . $jumlah_kerugian);
        
        // Parse percentage
        $persentase_input = $_POST['persentase_kerugian'] ?? '';
        $persentase_kerugian = null;
        if (!empty($persentase_input) && is_numeric($persentase_input)) {
            $persentase_kerugian = floatval($persentase_input) / 100;
        }
        
        $tanggal_kerugian = $_POST['tanggal_kerugian'] ?? '';
        $sumber_kerugian = $_POST['sumber_kerugian'] ?? 'lainnya';
        $status = $_POST['status'] ?? 'realized';
        
        // Validation
        if (empty($investasi_id) || empty($kategori_id) || empty($judul_kerugian) || 
            $jumlah_kerugian < 0 || empty($tanggal_kerugian)) {
            throw new Exception('Semua field wajib diisi. Jumlah kerugian harus ≥ 0.');
        }
        
        // Auto-calculate percentage if not provided
        if (is_null($persentase_kerugian)) {
            $sql_invest = "SELECT jumlah FROM investasi WHERE id = ?";
            $stmt_invest = $koneksi->prepare($sql_invest);
            $stmt_invest->execute([$investasi_id]);
            $invest_data = $stmt_invest->fetch();
            
            if ($invest_data && $invest_data['jumlah'] > 0) {
                $persentase_kerugian = $jumlah_kerugian / $invest_data['jumlah'];
            }
        }
        
        // Handle file upload
        $bukti_file_data = $kerugian['bukti_file']; // Keep existing file
        $file_updated = false;
        
        if (isset($_FILES['bukti_file']) && $_FILES['bukti_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $bukti_file_data = file_get_contents($_FILES['bukti_file']['tmp_name']);
                $file_updated = true;
            } catch (Exception $e) {
                throw new Exception("Gagal upload bukti: " . $e->getMessage());
            }
        }
        
        // Check if user wants to delete existing file
        if (isset($_POST['delete_file']) && $_POST['delete_file'] == '1') {
            $bukti_file_data = null;
            $file_updated = true;
        }
        
        // Update database
        $sql = "UPDATE kerugian_investasi 
                SET investasi_id = ?, 
                    kategori_id = ?, 
                    judul_kerugian = ?, 
                    deskripsi = ?, 
                    jumlah_kerugian = ?, 
                    persentase_kerugian = ?, 
                    tanggal_kerugian = ?, 
                    sumber_kerugian = ?, 
                    status = ?, 
                    bukti_file = ?
                WHERE id = ?";
        
        $stmt = $koneksi->prepare($sql);
        if ($stmt->execute([
            $investasi_id, $kategori_id, $judul_kerugian, $deskripsi,
            $jumlah_kerugian, $persentase_kerugian, $tanggal_kerugian,
            $sumber_kerugian, $status, $bukti_file_data, $kerugian_id
        ])) {
            $msg = "✅ Kerugian berhasil diperbarui!";
            if ($file_updated) {
                $msg .= $bukti_file_data ? " 📎 Bukti diperbarui" : " 🗑️ Bukti dihapus";
            }
            
            redirect_with_message("../dashboard.php", "success", $msg);
        } else {
            throw new Exception('Gagal memperbarui data kerugian.');
        }
        
    } catch (Exception $e) {
        error_log("Edit Kerugian Error: " . $e->getMessage());
        $error = '❌ ' . $e->getMessage();
    }
}

// Format data untuk display
$persentase_display = $kerugian['persentase_kerugian'] ? 
    number_format($kerugian['persentase_kerugian'] * 100, 2) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Kerugian - SAZEN v3.0</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons & Styles -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/form_kerugian.css">
</head>
<body>
    <div class="form-wrapper">
        <div class="form-container">
            
            <!-- ===== HEADER ===== -->
            <div class="form-header">
                <div class="header-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <h1>Edit Kerugian</h1>
                <p>Perbarui data kerugian investasi Anda</p>
            </div>

            <!-- ===== MESSAGES ===== -->
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

            <!-- ===== FORM ===== -->
            <form method="POST" enctype="multipart/form-data" class="data-form">
                
                <!-- Pilih Investasi -->
                <div class="form-group">
                    <label for="investasi_id">
                        <i class="fas fa-briefcase"></i>
                        Pilih Investasi *
                    </label>
                    <select name="investasi_id" id="investasi_id" class="form-control" required>
                        <option value="">-- Pilih Investasi --</option>
                        <?php foreach ($investasi_list as $inv): ?>
                            <option value="<?= $inv['id'] ?>"
                                    data-kategori="<?= $inv['kategori_id'] ?>"
                                    data-nama-kategori="<?= htmlspecialchars($inv['nama_kategori']) ?>"
                                    data-jumlah="<?= $inv['jumlah'] ?>"
                                    <?= $inv['id'] == $kerugian['investasi_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($inv['judul_investasi']) ?> 
                                (<?= htmlspecialchars($inv['nama_kategori']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Investment Info Box -->
                <div class="investment-info show" id="investmentInfo">
                    <div class="info-item">
                        <strong>Kategori:</strong> 
                        <span id="selectedCategory"><?= htmlspecialchars($kerugian['nama_kategori']) ?></span>
                    </div>
                    <div class="info-item" id="investmentAmountContainer" style="display: block;">
                        <strong>Modal Investasi:</strong> 
                        Rp <span id="selectedAmount"><?= number_format($kerugian['modal_investasi'], 0, ',', '.') ?></span>
                    </div>
                    <input type="hidden" name="kategori_id" id="kategori_id" value="<?= $kerugian['kategori_id'] ?>">
                </div>

                <!-- Judul Kerugian -->
                <div class="form-group">
                    <label for="judul_kerugian">
                        <i class="fas fa-tag"></i>
                        Judul Kerugian *
                    </label>
                    <input type="text" 
                           name="judul_kerugian" 
                           id="judul_kerugian" 
                           class="form-control" 
                           placeholder="Contoh: Penurunan Nilai Saham"
                           value="<?= htmlspecialchars($kerugian['judul_kerugian']) ?>" 
                           required>
                </div>

                <!-- Jumlah & Persentase (2 Kolom) -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="jumlah_kerugian">
                            <i class="fas fa-money-bill-wave"></i>
                            Jumlah Kerugian (Rp) *
                        </label>
                        <input type="text" 
                               name="jumlah_kerugian" 
                               id="jumlah_kerugian" 
                               class="form-control" 
                               placeholder="Contoh: 1500000 atau 1.500.000"
                               value="<?= number_format($kerugian['jumlah_kerugian'], 0, ',', '.') ?>" 
                               required>
                        <small class="form-hint">Format bebas: 4, 1500000, atau 1.500.000</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="persentase_kerugian">
                            <i class="fas fa-percentage"></i>
                            Persentase (%)
                        </label>
                        <input type="number" 
                               name="persentase_kerugian" 
                               id="persentase_kerugian"
                               class="form-control" 
                               step="0.01" 
                               min="0" 
                               placeholder="Opsional"
                               value="<?= $persentase_display ?>">
                        <small class="form-hint">Kosongkan untuk auto-calculate</small>
                    </div>
                </div>

                <!-- Tanggal Kerugian -->
                <div class="form-group">
                    <label for="tanggal_kerugian">
                        <i class="fas fa-calendar-alt"></i>
                        Tanggal Kerugian *
                    </label>
                    <input type="date" 
                           name="tanggal_kerugian" 
                           id="tanggal_kerugian" 
                           class="form-control"
                           value="<?= $kerugian['tanggal_kerugian'] ?>" 
                           required>
                </div>

                <!-- Sumber Kerugian (Radio Cards) -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-source"></i>
                        Sumber Kerugian *
                    </label>
                    <div class="radio-grid">
                        <label class="radio-card">
                            <input type="radio" 
                                   name="sumber_kerugian" 
                                   value="capital_loss" 
                                   <?= $kerugian['sumber_kerugian'] == 'capital_loss' ? 'checked' : '' ?> 
                                   required>
                            <div class="radio-content">
                                <i class="fas fa-chart-line"></i>
                                <span>Capital Loss</span>
                            </div>
                        </label>
                        
                        <label class="radio-card">
                            <input type="radio" 
                                   name="sumber_kerugian" 
                                   value="biaya_admin" 
                                   <?= $kerugian['sumber_kerugian'] == 'biaya_admin' ? 'checked' : '' ?> 
                                   required>
                            <div class="radio-content">
                                <i class="fas fa-file-invoice-dollar"></i>
                                <span>Biaya Admin</span>
                            </div>
                        </label>
                        
                        <label class="radio-card">
                            <input type="radio" 
                                   name="sumber_kerugian" 
                                   value="biaya_transaksi" 
                                   <?= $kerugian['sumber_kerugian'] == 'biaya_transaksi' ? 'checked' : '' ?> 
                                   required>
                            <div class="radio-content">
                                <i class="fas fa-exchange-alt"></i>
                                <span>Biaya Transaksi</span>
                            </div>
                        </label>
                        
                        <label class="radio-card">
                            <input type="radio" 
                                   name="sumber_kerugian" 
                                   value="penurunan_nilai" 
                                   <?= $kerugian['sumber_kerugian'] == 'penurunan_nilai' ? 'checked' : '' ?> 
                                   required>
                            <div class="radio-content">
                                <i class="fas fa-arrow-down"></i>
                                <span>Penurunan Nilai</span>
                            </div>
                        </label>
                        
                        <label class="radio-card">
                            <input type="radio" 
                                   name="sumber_kerugian" 
                                   value="lainnya" 
                                   <?= $kerugian['sumber_kerugian'] == 'lainnya' ? 'checked' : '' ?> 
                                   required>
                            <div class="radio-content">
                                <i class="fas fa-ellipsis-h"></i>
                                <span>Lainnya</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Status (Radio Cards) -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-flag"></i>
                        Status *
                    </label>
                    <div class="radio-grid status-grid">
                        <label class="radio-card">
                            <input type="radio" 
                                   name="status" 
                                   value="realized" 
                                   <?= $kerugian['status'] == 'realized' ? 'checked' : '' ?> 
                                   required>
                            <div class="radio-content">
                                <i class="fas fa-check-circle"></i>
                                <span>Sudah Direalisasi</span>
                            </div>
                        </label>
                        
                        <label class="radio-card">
                            <input type="radio" 
                                   name="status" 
                                   value="unrealized" 
                                   <?= $kerugian['status'] == 'unrealized' ? 'checked' : '' ?> 
                                   required>
                            <div class="radio-content">
                                <i class="fas fa-clock"></i>
                                <span>Belum Direalisasi</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Upload Bukti -->
                <div class="form-group">
                    <label for="bukti_file">
                        <i class="fas fa-file-upload"></i>
                        Upload Bukti (Opsional)
                    </label>
                    
                    <?php if ($kerugian['bukti_file']): ?>
                        <!-- Existing File Preview -->
                        <div class="existing-file">
                            <div class="file-card">
                                <i class="fas fa-file-alt"></i>
                                <div class="file-details">
                                    <strong>File bukti sudah ada</strong>
                                    <span><?= number_format(strlen($kerugian['bukti_file']) / 1024, 2) ?> KB</span>
                                </div>
                                <label class="delete-checkbox">
                                    <input type="checkbox" name="delete_file" value="1" id="deleteFile">
                                    <span>Hapus file</span>
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="file-upload">
                        <input type="file" 
                               name="bukti_file" 
                               id="bukti_file" 
                               accept=".jpg,.jpeg,.png,.pdf" 
                               class="file-input">
                        <label for="bukti_file" class="file-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span class="file-text">
                                <?= $kerugian['bukti_file'] ? 'Ganti file atau drag & drop' : 'Pilih file atau drag & drop' ?>
                            </span>
                            <span class="file-info">JPG, PNG, PDF (Max 5MB)</span>
                        </label>
                        <div class="file-preview" id="filePreview"></div>
                    </div>
                </div>

                <!-- Deskripsi -->
                <div class="form-group">
                    <label for="deskripsi">
                        <i class="fas fa-align-left"></i>
                        Deskripsi (Opsional)
                    </label>
                    <textarea name="deskripsi" 
                              id="deskripsi" 
                              class="form-control" 
                              rows="3"
                              placeholder="Catatan tambahan tentang kerugian ini..."><?= htmlspecialchars($kerugian['deskripsi']) ?></textarea>
                </div>

                <!-- Action Buttons -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <span>Simpan Perubahan</span>
                    </button>
                    <a href="../dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        <span>Kembali</span>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== JAVASCRIPT ===== -->
    <script src="form_kerugian.js"></script>
    
    <script>
        // Additional script for delete file checkbox
        document.getElementById('deleteFile')?.addEventListener('change', function() {
            const fileInput = document.getElementById('bukti_file');
            const fileLabel = document.querySelector('.file-label');
            
            if (this.checked) {
                fileInput.disabled = true;
                fileLabel.style.opacity = '0.5';
                fileLabel.style.pointerEvents = 'none';
            } else {
                fileInput.disabled = false;
                fileLabel.style.opacity = '1';
                fileLabel.style.pointerEvents = 'auto';
            }
        });
    </script>
</body>
</html>
