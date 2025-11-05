<?php
/**
 * SAZEN Investment Portfolio Manager v3.1
 * Edit Keuntungan - Database Storage
 * FIXED: Use handle_file_upload_to_db() for base64 storage
 */

session_start();
require_once "../config/koneksi.php";
require_once "../config/functions.php";
require_once "../config/auto_calculate_investment.php"; // ✅ NEW: Auto-calc functions

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

// Get keuntungan ID from URL
$keuntungan_id = $_GET['id'] ?? '';

if (empty($keuntungan_id)) {
    redirect_with_message("../dashboard.php", "error", "❌ ID Keuntungan tidak valid");
    exit;
}

// Get existing keuntungan data
$sql_keuntungan = "
    SELECT ki.*, i.judul_investasi, i.jumlah as modal_investasi, k.nama_kategori
    FROM keuntungan_investasi ki
    JOIN investasi i ON ki.investasi_id = i.id
    JOIN kategori k ON ki.kategori_id = k.id
    WHERE ki.id = ?
";
$stmt_keuntungan = $koneksi->prepare($sql_keuntungan);
$stmt_keuntungan->execute([$keuntungan_id]);
$keuntungan = $stmt_keuntungan->fetch();

if (!$keuntungan) {
    redirect_with_message("../dashboard.php", "error", "❌ Data keuntungan tidak ditemukan");
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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Collect form data
        $investasi_id = $_POST['investasi_id'] ?? '';
        $kategori_id = $_POST['kategori_id'] ?? '';
        $judul_keuntungan = sanitize_input($_POST['judul_keuntungan'] ?? '');
        $deskripsi = sanitize_input($_POST['deskripsi'] ?? '');
        
        // ✅ USE FIXED PARSER dengan fallback
        $jumlah_keuntungan = parse_currency_fixed($_POST['jumlah_keuntungan'] ?? '0');
        
        // ✅ Pastikan 0 adalah nilai valid
        if ($jumlah_keuntungan === false || $jumlah_keuntungan === null) {
            $jumlah_keuntungan = 0;
        }
        
        // Debug log (optional - remove in production)
        error_log("Edit Keuntungan - Original input: " . ($_POST['jumlah_keuntungan'] ?? '0'));
        error_log("Edit Keuntungan - Parsed value: " . $jumlah_keuntungan);
        
        // Parse percentage
        $persentase_input = $_POST['persentase_keuntungan'] ?? '';
        $persentase_keuntungan = null;
        if (!empty($persentase_input) && is_numeric($persentase_input)) {
            $persentase_keuntungan = floatval($persentase_input) / 100;
        }
        
        $tanggal_keuntungan = $_POST['tanggal_keuntungan'] ?? '';
        $sumber_keuntungan = $_POST['sumber_keuntungan'] ?? 'lainnya';
        $status = $_POST['status'] ?? 'realized';
        
        // ✅ FIXED VALIDATION - Accept 0, reject negative and non-numeric
        if (empty($investasi_id) || empty($kategori_id) || empty($judul_keuntungan) || 
            !is_numeric($jumlah_keuntungan) || $jumlah_keuntungan < 0 || empty($tanggal_keuntungan)) {
            throw new Exception('Semua field wajib diisi. Jumlah keuntungan harus ≥ 0.');
        }
        
        // Auto-calculate percentage if not provided
        if (is_null($persentase_keuntungan)) {
            $sql_invest = "SELECT jumlah FROM investasi WHERE id = ?";
            $stmt_invest = $koneksi->prepare($sql_invest);
            $stmt_invest->execute([$investasi_id]);
            $invest_data = $stmt_invest->fetch();
            
            if ($invest_data && $invest_data['jumlah'] > 0) {
                $persentase_keuntungan = $jumlah_keuntungan / $invest_data['jumlah'];
            } else {
                // ✅ Handle case jika modal investasi = 0
                $persentase_keuntungan = 0;
            }
        }
        
        // ✅ Handle file upload using handle_file_upload_to_db()
        $bukti_file_data = $keuntungan['bukti_file']; // Keep existing file
        $file_updated = false;
        
        if (isset($_FILES['bukti_file']) && $_FILES['bukti_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $bukti_file_data = handle_file_upload_to_db($_FILES['bukti_file']);
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
        $sql = "UPDATE keuntungan_investasi 
                SET investasi_id = ?, 
                    kategori_id = ?, 
                    judul_keuntungan = ?, 
                    deskripsi = ?, 
                    jumlah_keuntungan = ?, 
                    persentase_keuntungan = ?, 
                    tanggal_keuntungan = ?, 
                    sumber_keuntungan = ?, 
                    status = ?, 
                    bukti_file = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
        
        $stmt = $koneksi->prepare($sql);
        $result = $stmt->execute([
            $investasi_id, $kategori_id, $judul_keuntungan, $deskripsi,
            $jumlah_keuntungan, $persentase_keuntungan, $tanggal_keuntungan,
            $sumber_keuntungan, $status, $bukti_file_data, $keuntungan_id
        ]);
        
        if ($result) {
            // ✅ AUTO RECALCULATE INVESTMENT
            $calc_result = trigger_after_profit_added($koneksi, $keuntungan_id);
            
            if (!$calc_result['success']) {
                throw new Exception("Gagal recalculate: " . $calc_result['error']);
            }
            
            // Success message with new calculated values
            $msg = "✅ Keuntungan berhasil diperbarui!";
            if ($file_updated) {
                $msg .= $bukti_file_data ? " 📎 Bukti diperbarui" : " 🗑️ Bukti dihapus";
            }
            
            // ✅ Tambah info jika nilai keuntungan = 0
            if ($jumlah_keuntungan == 0) {
                $msg .= "\n💡 Keuntungan tercatat dengan nilai Rp 0";
            }
            
            $msg .= "\n📊 Nilai investasi diupdate otomatis: " . 
                    format_currency($calc_result['new_value']) . 
                    " (ROI: " . number_format($calc_result['roi'], 2) . "%)";
            
            redirect_with_message("../dashboard.php", "success", $msg);
        } else {
            throw new Exception('Gagal memperbarui data keuntungan.');
        }
        
    } catch (Exception $e) {
        error_log("Edit Keuntungan Error: " . $e->getMessage());
        $error = '❌ ' . $e->getMessage();
    }
}

// Format data untuk display
$persentase_display = $keuntungan['persentase_keuntungan'] ? 
    number_format($keuntungan['persentase_keuntungan'] * 100, 2) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Keuntungan - SAZEN v3.1</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons & Styles -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/form_edit_keuntungan.css">
</head>
<body>
    <div class="form-wrapper">
        <div class="form-container">
            
            <!-- ===== HEADER ===== -->
            <div class="form-header">
                <div class="header-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <h1>Edit Keuntungan</h1>
                <p>Perbarui data keuntungan investasi Anda</p>
                <span class="version-badge">v3.1 - Auto Calculate</span>
            </div>

            <!-- ===== MESSAGES ===== -->
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= nl2br(htmlspecialchars($error)) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= nl2br(htmlspecialchars($success)) ?></span>
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
                                    <?= $inv['id'] == $keuntungan['investasi_id'] ? 'selected' : '' ?>>
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
                        <span id="selectedCategory"><?= htmlspecialchars($keuntungan['nama_kategori']) ?></span>
                    </div>
                    <div class="info-item" id="investmentAmountContainer" style="display: block;">
                        <strong>Modal Investasi:</strong> 
                        Rp <span id="selectedAmount"><?= number_format($keuntungan['modal_investasi'], 0, ',', '.') ?></span>
                    </div>
                    <input type="hidden" name="kategori_id" id="kategori_id" value="<?= $keuntungan['kategori_id'] ?>">
                </div>

                <!-- Judul Keuntungan -->
                <div class="form-group">
                    <label for="judul_keuntungan">
                        <i class="fas fa-tag"></i>
                        Judul Keuntungan *
                    </label>
                    <input type="text" 
                           name="judul_keuntungan" 
                           id="judul_keuntungan" 
                           class="form-control" 
                           placeholder="Contoh: Dividen Q1 2025"
                           value="<?= htmlspecialchars($keuntungan['judul_keuntungan']) ?>" 
                           required>
                </div>

                <!-- Jumlah & Persentase (2 Kolom) -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="jumlah_keuntungan">
                            <i class="fas fa-money-bill-wave"></i>
                            Jumlah Keuntungan (Rp) *
                        </label>
                        <input type="text" 
                               name="jumlah_keuntungan" 
                               id="jumlah_keuntungan" 
                               class="form-control" 
                               placeholder="Contoh: 0, 1500000, atau 1.500.000"
                               value="<?= $keuntungan['jumlah_keuntungan'] == 0 ? '0' : number_format($keuntungan['jumlah_keuntungan'], 0, ',', '.') ?>" 
                               required
                               min="0">
                        <small class="form-hint">Format bebas: 0, 1500000, atau 1.500.000 (nilai 0 diperbolehkan)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="persentase_keuntungan">
                            <i class="fas fa-percentage"></i>
                            Persentase (%)
                        </label>
                        <input type="number" 
                               name="persentase_keuntungan" 
                               id="persentase_keuntungan"
                               class="form-control" 
                               step="0.01" 
                               min="0" 
                               placeholder="Opsional"
                               value="<?= $persentase_display ?>">
                        <small class="form-hint">Kosongkan untuk auto-calculate</small>
                    </div>
                </div>

                <!-- Tanggal Keuntungan -->
                <div class="form-group">
                    <label for="tanggal_keuntungan">
                        <i class="fas fa-calendar-alt"></i>
                        Tanggal Keuntungan *
                    </label>
                    <input type="date" 
                           name="tanggal_keuntungan" 
                           id="tanggal_keuntungan" 
                           class="form-control"
                           value="<?= $keuntungan['tanggal_keuntungan'] ?>" 
                           required>
                </div>

                <!-- Sumber Keuntungan (Radio Cards) -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-source"></i>
                        Sumber Keuntungan *
                    </label>
                    <div class="radio-grid">
                        <label class="radio-card <?= $keuntungan['sumber_keuntungan'] == 'dividen' ? 'selected' : '' ?>">
                            <input type="radio" 
                                   name="sumber_keuntungan" 
                                   value="dividen" 
                                   <?= $keuntungan['sumber_keuntungan'] == 'dividen' ? 'checked' : '' ?> 
                                   required>
                            <div class="radio-content">
                                <i class="fas fa-coins"></i>
                                <span>Dividen</span>
                            </div>
                        </label>
                        
                        <label class="radio-card <?= $keuntungan['sumber_keuntungan'] == 'capital_gain' ? 'selected' : '' ?>">
                            <input type="radio" 
                                   name="sumber_keuntungan" 
                                   value="capital_gain" 
                                   <?= $keuntungan['sumber_keuntungan'] == 'capital_gain' ? 'checked' : '' ?> 
                                   required>
                            <div class="radio-content">
                                <i class="fas fa-chart-line"></i>
                                <span>Capital Gain</span>
                            </div>
                        </label>
                        
                        <label class="radio-card <?= $keuntungan['sumber_keuntungan'] == 'bunga' ? 'selected' : '' ?>">
                            <input type="radio" 
                                   name="sumber_keuntungan" 
                                   value="bunga" 
                                   <?= $keuntungan['sumber_keuntungan'] == 'bunga' ? 'checked' : '' ?> 
                                   required>
                            <div class="radio-content">
                                <i class="fas fa-percent"></i>
                                <span>Bunga</span>
                            </div>
                        </label>
                        
                        <label class="radio-card <?= $keuntungan['sumber_keuntungan'] == 'bonus' ? 'selected' : '' ?>">
                            <input type="radio" 
                                   name="sumber_keuntungan" 
                                   value="bonus" 
                                   <?= $keuntungan['sumber_keuntungan'] == 'bonus' ? 'checked' : '' ?> 
                                   required>
                            <div class="radio-content">
                                <i class="fas fa-gift"></i>
                                <span>Bonus</span>
                            </div>
                        </label>

                        <label class="radio-card <?= $keuntungan['sumber_keuntungan'] == 'imbal_hasil' ? 'selected' : '' ?>">
                            <input type="radio" 
                                   name="sumber_keuntungan" 
                                   value="imbal_hasil"
                                   <?= $keuntungan['sumber_keuntungan'] == 'imbal_hasil' ? 'checked' : '' ?> 
                                   required>
                            <div class="radio-content">
                                <i class="fas fa-coins"></i>
                                <span>Imbal Hasil</span>
                            </div>
                        </label>
                        
                        <label class="radio-card <?= $keuntungan['sumber_keuntungan'] == 'lainnya' ? 'selected' : '' ?>">
                            <input type="radio" 
                                   name="sumber_keuntungan" 
                                   value="lainnya" 
                                   <?= $keuntungan['sumber_keuntungan'] == 'lainnya' ? 'checked' : '' ?> 
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
                        <label class="radio-card <?= $keuntungan['status'] == 'realized' ? 'selected' : '' ?>">
                            <input type="radio" 
                                   name="status" 
                                   value="realized" 
                                   <?= $keuntungan['status'] == 'realized' ? 'checked' : '' ?> 
                                   required>
                            <div class="radio-content">
                                <i class="fas fa-check-circle"></i>
                                <span>Sudah Direalisasi</span>
                            </div>
                        </label>
                        
                        <label class="radio-card <?= $keuntungan['status'] == 'unrealized' ? 'selected' : '' ?>">
                            <input type="radio" 
                                   name="status" 
                                   value="unrealized" 
                                   <?= $keuntungan['status'] == 'unrealized' ? 'checked' : '' ?> 
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
                    
                    <?php if ($keuntungan['bukti_file']): ?>
                        <!-- Existing File Preview -->
                        <div class="existing-file">
                            <div class="file-card">
                                <i class="fas fa-file-alt"></i>
                                <div class="file-details">
                                    <strong>File bukti sudah ada</strong>
                                    <span><?= number_format(strlen($keuntungan['bukti_file']) / 1024, 2) ?> KB</span>
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
                                <?= $keuntungan['bukti_file'] ? 'Ganti file atau drag & drop' : 'Pilih file atau drag & drop' ?>
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
                              placeholder="Catatan tambahan tentang keuntungan ini..."><?= htmlspecialchars($keuntungan['deskripsi']) ?></textarea>
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
    <script src="form_keuntungan.js"></script>
    
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
        
        // ✅ Override default date behavior for edit form
        // Do NOT set default date on edit page
        const tanggalInput = document.getElementById('tanggal_keuntungan');
        if (tanggalInput && !tanggalInput.value) {
            // Only set today if field is somehow empty (shouldn't happen in edit mode)
            tanggalInput.value = new Date().toISOString().split('T')[0];
        }
    </script>
</body>
</html>
