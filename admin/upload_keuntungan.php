<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * Upload Keuntungan - Database Storage
 * FINAL FIXED: Menggunakan fungsi dari koneksi.php
 */

session_start();
require_once "../config/koneksi.php";

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
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

// Get flash message (menggunakan fungsi dari koneksi.php)
$flash = get_flash_message();
if ($flash) {
    $flash['type'] == 'success' ? $success = $flash['message'] : $error = $flash['message'];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Collect form data (menggunakan sanitize_input dari koneksi.php)
        $investasi_id = $_POST['investasi_id'] ?? '';
        $kategori_id = $_POST['kategori_id'] ?? '';
        $judul_keuntungan = sanitize_input($_POST['judul_keuntungan'] ?? '');
        $deskripsi = sanitize_input($_POST['deskripsi'] ?? '');
        
        // USE FIXED PARSER
        $jumlah_keuntungan = parse_currency_fixed($_POST['jumlah_keuntungan'] ?? '0');
        
        // Debug log (optional - remove in production)
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
        
        // Handle file upload (menggunakan fungsi dari koneksi.php)
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
        if ($stmt->execute([
            $investasi_id, $kategori_id, $judul_keuntungan, $deskripsi,
            $jumlah_keuntungan, $persentase_keuntungan, $tanggal_keuntungan,
            $sumber_keuntungan, $status, $bukti_file_data
        ])) {
            $msg = "✅ Keuntungan berhasil ditambahkan!";
            if ($bukti_file_data) $msg .= " 📎 Bukti tersimpan";
            
            redirect_with_message("../dashboard.php", "success", $msg);
        } else {
            throw new Exception('Gagal menyimpan data keuntungan.');
        }
        
    } catch (Exception $e) {
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
    <title>Tambah Keuntungan - SAZEN v3.0</title>
    
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
                        <small class="form-hint">Format bebas: 4, 1500000, atau 1.500.000</small>
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
