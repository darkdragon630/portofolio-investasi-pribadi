<?php
/**
 * SAZEN Investment Portfolio Manager v3.1
 * Upload Keuntungan - WITH AUTO CALCULATE (FIXED)
 * FIXED: Removed nested transaction issue
 */

session_start();
require_once "../config/koneksi.php";
require_once "../config/functions.php";
require_once "../config/auto_calculate_investment.php";

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
        // ✅ HANYA 1 TRANSACTION DI SINI
        $koneksi->beginTransaction();
        
        // Collect form data
        $investasi_id = $_POST['investasi_id'] ?? '';
        $kategori_id = $_POST['kategori_id'] ?? '';
        $judul_keuntungan = sanitize_input($_POST['judul_keuntungan'] ?? '');
        $deskripsi = sanitize_input($_POST['deskripsi'] ?? '');
        
        // USE FIXED PARSER
        $jumlah_keuntungan = parse_currency_fixed($_POST['jumlah_keuntungan'] ?? '0');
        
        // Debug log
        error_log("Original input: " . ($_POST['jumlah_keuntungan'] ?? '0'));
        error_log("Parsed value: " . $jumlah_keuntungan);
        
        // Parse percentage
        $persentase_input = $_POST['persentase_keuntungan'] ?? '';
        $persentase_keuntungan = null;
        if (!empty($persentase_input) && is_numeric($persentase_input)) {
            $persentase_keuntungan = floatval($persentase_input) / 100;
        }
        
        $tanggal_keuntungan = $_POST['tanggal_keuntungan'] ?? '';
        $sumber_keuntungan = $_POST['sumber_keuntungan'] ?? 'lainnya';
        $status = $_POST['status'] ?? 'realized';
        
        // Validation
        if (empty($investasi_id) || empty($kategori_id) || empty($judul_keuntungan) || 
            $jumlah_keuntungan < 0 || empty($tanggal_keuntungan)) {
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
        
        // Insert to database
        $sql = "INSERT INTO keuntungan_investasi 
                (investasi_id, kategori_id, judul_keuntungan, deskripsi, jumlah_keuntungan, 
                 persentase_keuntungan, tanggal_keuntungan, sumber_keuntungan, status, bukti_file) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $koneksi->prepare($sql);
        if (!$stmt->execute([
            $investasi_id, $kategori_id, $judul_keuntungan, $deskripsi,
            $jumlah_keuntungan, $persentase_keuntungan, $tanggal_keuntungan,
            $sumber_keuntungan, $status, $bukti_file_data
        ])) {
            throw new Exception('Gagal menyimpan data keuntungan.');
        }
        
        $keuntungan_id = $koneksi->lastInsertId();
        
        // ✅ MANUAL RECALCULATION (TANPA NESTED TRANSACTION)
        // Get profit details
        $sql_detail = "SELECT investasi_id, jumlah_keuntungan FROM keuntungan_investasi WHERE id = ?";
        $stmt_detail = $koneksi->prepare($sql_detail);
        $stmt_detail->execute([$keuntungan_id]);
        $profit_detail = $stmt_detail->fetch(PDO::FETCH_ASSOC);
        
        if (!$profit_detail) {
            throw new Exception("Data keuntungan tidak ditemukan");
        }
        
        $inv_id = $profit_detail['investasi_id'];
        $jumlah = (float)$profit_detail['jumlah_keuntungan'];
        
        // Get investment modal
        $sql_invest = "SELECT jumlah as modal_investasi FROM investasi WHERE id = ?";
        $stmt_invest = $koneksi->prepare($sql_invest);
        $stmt_invest->execute([$inv_id]);
        $invest = $stmt_invest->fetch(PDO::FETCH_ASSOC);
        $modal = (float)$invest['modal_investasi'];
        
        // Calculate totals
        $sql_keuntungan = "SELECT IFNULL(SUM(jumlah_keuntungan), 0) as total
                           FROM keuntungan_investasi WHERE investasi_id = ?";
        $stmt_k = $koneksi->prepare($sql_keuntungan);
        $stmt_k->execute([$inv_id]);
        $total_keuntungan = (float)$stmt_k->fetchColumn();
        
        $sql_kerugian = "SELECT IFNULL(SUM(jumlah_kerugian), 0) as total
                         FROM kerugian_investasi WHERE investasi_id = ?";
        $stmt_kr = $koneksi->prepare($sql_kerugian);
        $stmt_kr->execute([$inv_id]);
        $total_kerugian = (float)$stmt_kr->fetchColumn();
        
        $nilai_sekarang = $modal + $total_keuntungan - $total_kerugian;
        $roi_persen = $modal > 0 ? ((($nilai_sekarang - $modal) / $modal) * 100) : 0;
        
        // Update investasi table
        $sql_update = "UPDATE investasi SET updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt_upd = $koneksi->prepare($sql_update);
        $stmt_upd->execute([$inv_id]);
        
        // Log the change (inline, no function call)
        $sql_prev = "SELECT nilai_sesudah FROM investasi_change_log
                     WHERE investasi_id = ?
                     ORDER BY created_at DESC LIMIT 1";
        $stmt_prev = $koneksi->prepare($sql_prev);
        $stmt_prev->execute([$inv_id]);
        $prev = $stmt_prev->fetch(PDO::FETCH_ASSOC);
        $nilai_sebelum = $prev ? (float)$prev['nilai_sesudah'] : 0;
        
        $sql_log = "INSERT INTO investasi_change_log 
                   (investasi_id, tipe_perubahan, referensi_id, nilai_sebelum,
                    nilai_sesudah, selisih, keterangan)
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_log = $koneksi->prepare($sql_log);
        $stmt_log->execute([
            $inv_id, 'keuntungan', $keuntungan_id,
            $nilai_sebelum, $nilai_sekarang, $jumlah,
            "Profit added: " . format_currency($jumlah)
        ]);
        
        // Update daily snapshot (inline)
        $sql_inv = "SELECT tanggal_investasi, jumlah FROM investasi WHERE id = ?";
        $stmt_inv = $koneksi->prepare($sql_inv);
        $stmt_inv->execute([$inv_id]);
        $inv_data = $stmt_inv->fetch(PDO::FETCH_ASSOC);
        
        if ($inv_data) {
            $tanggal_investasi = $inv_data['tanggal_investasi'];
            $today = date('Y-m-d');
            
            $start = new DateTime($tanggal_investasi);
            $end = new DateTime($today);
            $hari_ke = $start->diff($end)->days + 1;
            
            $sql_yesterday = "SELECT nilai_akhir FROM investasi_snapshot_harian
                             WHERE investasi_id = ? 
                             AND tanggal_snapshot < ?
                             ORDER BY tanggal_snapshot DESC LIMIT 1";
            $stmt_y = $koneksi->prepare($sql_yesterday);
            $stmt_y->execute([$inv_id, $today]);
            $yesterday = $stmt_y->fetch(PDO::FETCH_ASSOC);
            $nilai_awal = $yesterday ? (float)$yesterday['nilai_akhir'] : $modal;
            
            $perubahan_nilai = $nilai_sekarang - $nilai_awal;
            $persentase_perubahan = $nilai_awal > 0 ? (($perubahan_nilai / $nilai_awal) * 100) : 0;
            $roi_kumulatif = $modal > 0 ? ((($nilai_sekarang - $modal) / $modal) * 100) : 0;
            
            $status_perubahan = 'stabil';
            if ($perubahan_nilai > 0.01) {
                $status_perubahan = 'naik';
            } elseif ($perubahan_nilai < -0.01) {
                $status_perubahan = 'turun';
            }
            
            $sql_snapshot = "INSERT INTO investasi_snapshot_harian 
                          (investasi_id, tanggal_snapshot, hari_ke, nilai_awal, nilai_akhir,
                           perubahan_nilai, persentase_perubahan, total_keuntungan_harian,
                           total_kerugian_harian, status_perubahan, roi_kumulatif)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE
                          nilai_akhir = VALUES(nilai_akhir),
                          perubahan_nilai = VALUES(perubahan_nilai),
                          persentase_perubahan = VALUES(persentase_perubahan),
                          total_keuntungan_harian = VALUES(total_keuntungan_harian),
                          total_kerugian_harian = VALUES(total_kerugian_harian),
                          status_perubahan = VALUES(status_perubahan),
                          roi_kumulatif = VALUES(roi_kumulatif)";
            
            $stmt_snap = $koneksi->prepare($sql_snapshot);
            $stmt_snap->execute([
                $inv_id, $today, $hari_ke, $nilai_awal, $nilai_sekarang,
                $perubahan_nilai, $persentase_perubahan, $jumlah,
                0, $status_perubahan, $roi_kumulatif
            ]);
        }
        
        // ✅ COMMIT SEMUA PERUBAHAN
        $koneksi->commit();
        
        // Success message
        $msg = "✅ Keuntungan berhasil ditambahkan!";
        if ($bukti_file_data) $msg .= " 📎 Bukti tersimpan";
        $msg .= "\n📊 Nilai investasi diupdate otomatis: " . 
                format_currency($nilai_sekarang) . 
                " (ROI: " . number_format($roi_persen, 2) . "%)";
        
        redirect_with_message("../dashboard.php", "success", $msg);
        
    } catch (Exception $e) {
        if ($koneksi->inTransaction()) {
            $koneksi->rollBack();
        }
        error_log("Upload Keuntungan Error: " . $e->getMessage());
        $error = '❌ ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Keuntungan - SAZEN v3.1</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons & Styles -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/form_keuntungan.css">
</head>
<body>
    <div class="form-wrapper">
        <div class="form-container">
            
            <!-- ===== HEADER ===== -->
            <div class="form-header">
                <div class="header-icon">
                    <i class="fas fa-arrow-trend-up"></i>
                </div>
                <h1>Tambah Keuntungan</h1>
                <p>Catat keuntungan dari investasi Anda</p>
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
                               placeholder="Contoh: 1500000 atau 1.500.000" 
                               required>
                        <small class="form-hint">Format bebas: 1500000, atau 1.500.000</small>
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
                               placeholder="Opsional">
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
                           required>
                </div>

                <!-- Sumber Keuntungan (Radio Cards) -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-source"></i>
                        Sumber Keuntungan *
                    </label>
                    <div class="radio-grid">
                        <label class="radio-card">
                            <input type="radio" name="sumber_keuntungan" value="dividen" required>
                            <div class="radio-content">
                                <i class="fas fa-coins"></i>
                                <span>Dividen</span>
                            </div>
                        </label>
                        
                        <label class="radio-card">
                            <input type="radio" name="sumber_keuntungan" value="capital_gain" required>
                            <div class="radio-content">
                                <i class="fas fa-chart-line"></i>
                                <span>Capital Gain</span>
                            </div>
                        </label>
                        
                        <label class="radio-card">
                            <input type="radio" name="sumber_keuntungan" value="bunga" required>
                            <div class="radio-content">
                                <i class="fas fa-percent"></i>
                                <span>Bunga</span>
                            </div>
                        </label>
                        
                        <label class="radio-card">
                            <input type="radio" name="sumber_keuntungan" value="bonus" required>
                            <div class="radio-content">
                                <i class="fas fa-gift"></i>
                                <span>Bonus</span>
                            </div>
                        </label>

                        <label class="radio-card">
                            <input type="radio" name="sumber_keuntungan" value="imbal_hasil" required>
                            <div class="radio-content">
                                <i class="fas fa-coins"></i>
                                <span>Imbal Hasil</span>
                            </div>
                        </label>
                        
                        <label class="radio-card">
                            <input type="radio" name="sumber_keuntungan" value="lainnya" checked required>
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
                              placeholder="Catatan tambahan tentang keuntungan ini..."></textarea>
                </div>

                <!-- Action Buttons -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <span>Simpan Keuntungan</span>
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
</body>
</html>
