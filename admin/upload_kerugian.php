<?php
/**
 * SAZEN Investment Portfolio Manager v3.1
 * Upload Kerugian - WITH AUTO CALCULATE
 * UPDATED: Terintegrasi dengan auto_calculate_investment.php
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
        $koneksi->beginTransaction(); // ✅ Start transaction
        
        // Collect form data
        $investasi_id = $_POST['investasi_id'] ?? '';
        $kategori_id = $_POST['kategori_id'] ?? '';
        $judul_kerugian = sanitize_input($_POST['judul_kerugian'] ?? '');
        $deskripsi = sanitize_input($_POST['deskripsi'] ?? '');
        
        // USE FIXED PARSER
        $jumlah_kerugian = parse_currency_fixed($_POST['jumlah_kerugian'] ?? '0');
        
        // Debug log
        error_log("Upload Kerugian - Original input: " . ($_POST['jumlah_kerugian'] ?? '0'));
        error_log("Upload Kerugian - Parsed value: " . $jumlah_kerugian);
        
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
        
        // ✅ NEW: Cek apakah data kerugian sudah ada berdasarkan kriteria tertentu
        $existing_id = null;
        $check_sql = "SELECT id FROM kerugian_investasi 
                     WHERE investasi_id = ? 
                     AND kategori_id = ? 
                     AND tanggal_kerugian = ? 
                     AND sumber_kerugian = ? 
                     LIMIT 1";
        $check_stmt = $koneksi->prepare($check_sql);
        $check_stmt->execute([$investasi_id, $kategori_id, $tanggal_kerugian, $sumber_kerugian]);
        $existing_data = $check_stmt->fetch();
        
        if ($existing_data) {
            $existing_id = $existing_data['id'];
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
        $bukti_file_data = null;
        if (isset($_FILES['bukti_file']) && $_FILES['bukti_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $bukti_file_data = handle_file_upload_to_db($_FILES['bukti_file']);
            } catch (Exception $e) {
                throw new Exception("Gagal upload bukti: " . $e->getMessage());
            }
        }
        
        // ✅ MODIFIED: UPDATE jika data sudah ada, INSERT jika baru
        if ($existing_id) {
            // UPDATE data kerugian yang sudah ada
            $sql = "UPDATE kerugian_investasi 
                    SET judul_kerugian = ?, 
                        deskripsi = ?, 
                        jumlah_kerugian = ?, 
                        persentase_kerugian = ?, 
                        status = ?, 
                        bukti_file = COALESCE(?, bukti_file),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?";
            
            $stmt = $koneksi->prepare($sql);
            $result = $stmt->execute([
                $judul_kerugian, $deskripsi, $jumlah_kerugian, 
                $persentase_kerugian, $status, $bukti_file_data, $existing_id
            ]);
            
            $kerugian_id = $existing_id;
            $action_type = "diupdate";
        } else {
            // INSERT data kerugian baru
            $sql = "INSERT INTO kerugian_investasi 
                    (investasi_id, kategori_id, judul_kerugian, deskripsi, jumlah_kerugian, 
                     persentase_kerugian, tanggal_kerugian, sumber_kerugian, status, bukti_file) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $koneksi->prepare($sql);
            $result = $stmt->execute([
                $investasi_id, $kategori_id, $judul_kerugian, $deskripsi,
                $jumlah_kerugian, $persentase_kerugian, $tanggal_kerugian,
                $sumber_kerugian, $status, $bukti_file_data
            ]);
            
            $kerugian_id = $koneksi->lastInsertId();
            $action_type = "ditambahkan";
        }
        
        if ($result) {
            // ✅ NEW: AUTO RECALCULATE INVESTMENT
            $calc_result = trigger_after_loss_added($koneksi, $kerugian_id);
            
            if (!$calc_result['success']) {
                throw new Exception("Gagal recalculate: " . $calc_result['error']);
            }
            
            $koneksi->commit(); // ✅ Commit transaction
            
            // Success message with new calculated values
            $msg = "✅ Kerugian berhasil $action_type!";
            if ($bukti_file_data) $msg .= " 📎 Bukti tersimpan";
            $msg .= "\n📊 Nilai investasi diupdate otomatis: " . 
                    format_currency($calc_result['new_value']) . 
                    " (ROI: " . number_format($calc_result['roi'], 2) . "%)";
            
            redirect_with_message("../dashboard.php", "success", $msg);
        } else {
            throw new Exception("Gagal menyimpan data kerugian.");
        }
        
    } catch (Exception $e) {
        if ($koneksi->inTransaction()) {
            $koneksi->rollBack(); // ✅ Rollback on error
        }
        error_log("Upload Kerugian Error: " . $e->getMessage());
        $error = '❌ ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Kerugian - SAZEN v3.1</title>
    
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
                    <i class="fas fa-arrow-trend-down"></i>
                </div>
                <h1>Tambah Kerugian</h1>
                <p>Catat kerugian dari investasi Anda</p>
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
                                    data-jumlah="<?= $inv['jumlah'] ?>">
                                <?= htmlspecialchars($inv['judul_investasi']) ?> 
                                (<?= htmlspecialchars($inv['nama_kategori']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Investment Info Box -->
                <div class="investment-info" id="investmentInfo">
                    <div class="info-item">
                        <strong>Kategori:</strong> <span id="selectedCategory"></span>
                    </div>
                    <div class="info-item" id="investmentAmountContainer">
                        <strong>Modal Investasi:</strong> Rp <span id="selectedAmount"></span>
                    </div>
                    <input type="hidden" name="kategori_id" id="kategori_id">
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
                               placeholder="Opsional">
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
                            <input type="radio" name="sumber_kerugian" value="capital_loss" required>
                            <div class="radio-content">
                                <i class="fas fa-chart-line"></i>
                                <span>Capital Loss</span>
                            </div>
                        </label>
                        
                        <label class="radio-card">
                            <input type="radio" name="sumber_kerugian" value="biaya_admin" required>
                            <div class="radio-content">
                                <i class="fas fa-file-invoice-dollar"></i>
                                <span>Biaya Admin</span>
                            </div>
                        </label>
                        
                        <label class="radio-card">
                            <input type="radio" name="sumber_kerugian" value="biaya_transaksi" required>
                            <div class="radio-content">
                                <i class="fas fa-exchange-alt"></i>
                                <span>Biaya Transaksi</span>
                            </div>
                        </label>
                        
                        <label class="radio-card">
                            <input type="radio" name="sumber_kerugian" value="penurunan_nilai" required>
                            <div class="radio-content">
                                <i class="fas fa-arrow-down"></i>
                                <span>Penurunan Nilai</span>
                            </div>
                        </label>
                        
                        <label class="radio-card">
                            <input type="radio" name="sumber_kerugian" value="lainnya" checked required>
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
                            <input type="radio" name="status" value="realized" checked required>
                            <div class="radio-content">
                                <i class="fas fa-check-circle"></i>
                                <span>Sudah Direalisasi</span>
                            </div>
                        </label>
                        
                        <label class="radio-card">
                            <input type="radio" name="status" value="unrealized" required>
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
                    <div class="file-upload">
                        <input type="file" 
                               name="bukti_file" 
                               id="bukti_file" 
                               accept=".jpg,.jpeg,.png,.pdf" 
                               class="file-input">
                        <label for="bukti_file" class="file-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span class="file-text">Pilih file atau drag & drop</span>
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
                              placeholder="Catatan tambahan tentang kerugian ini..."></textarea>
                </div>

                <!-- Action Buttons -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <span>Simpan Kerugian</span>
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
</body>
</html>
