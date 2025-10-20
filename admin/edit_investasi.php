<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * Edit Investasi - FIXED VERSION
 */

session_start();
require_once "../config/koneksi.php";

// Enable error logging (ganti dengan 0 di production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// TEMPORARY: Show upload settings
echo "<!-- DEBUG INFO:\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "\n";
echo "file_uploads: " . (ini_get('file_uploads') ? 'ON' : 'OFF') . "\n";
echo "-->\n";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

// Get investment ID
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
$success = "";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    
    // CRITICAL DEBUG: Show what's received
    echo "<!-- POST DATA:\n";
    print_r($_POST);
    echo "\nFILES DATA:\n";
    print_r($_FILES);
    echo "\n-->\n";
    
    try {
        // Debug log
        error_log("=== EDIT INVESTASI START ===");
        error_log("Investment ID: " . $id);
        error_log("Old bukti_file: " . ($investasi['bukti_file'] ?? 'NULL'));
        error_log("POST keys: " . implode(', ', array_keys($_POST)));
        error_log("FILES keys: " . implode(', ', array_keys($_FILES)));
        
        if (isset($_FILES['bukti'])) {
            error_log("bukti file array: " . print_r($_FILES['bukti'], true));
        }
        
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
        
        if ($jumlah < 0) {
            throw new Exception("Jumlah investasi tidak valid.");
        }
        
        if (empty($tanggal)) {
            throw new Exception("Tanggal investasi wajib diisi.");
        }
        
        if ($kategori_id <= 0) {
            throw new Exception("Kategori wajib dipilih.");
        }
        
        // Initialize file variables
        $old_file_id = $investasi['bukti_file'];
        $new_file_id = $old_file_id; // Default: keep old file
        $file_changed = false;
        
        // Debug file upload
        error_log("FILE upload info:");
        error_log("- isset: " . (isset($_FILES['bukti']) ? 'YES' : 'NO'));
        error_log("- error code: " . ($_FILES['bukti']['error'] ?? 'NOT SET'));
        error_log("- file name: " . ($_FILES['bukti']['name'] ?? 'NOT SET'));
        error_log("- file size: " . ($_FILES['bukti']['size'] ?? 'NOT SET'));
        
        // Handle file upload if new file provided
        if (isset($_FILES['bukti']) && $_FILES['bukti']['error'] === UPLOAD_ERR_OK) {
            error_log("Processing file upload...");
            
            try {
                // Upload new file to JSON
                $uploaded_file_id = handle_file_upload($_FILES['bukti'], JSON_FILE_INVESTASI);
                
                if ($uploaded_file_id) {
                    error_log("File uploaded successfully with ID: " . $uploaded_file_id);
                    $new_file_id = $uploaded_file_id;
                    $file_changed = true;
                    
                    // Delete old file from JSON if exists
                    if ($old_file_id && $old_file_id !== $new_file_id) {
                        error_log("Deleting old file ID: " . $old_file_id);
                        $delete_result = delete_file($old_file_id, JSON_FILE_INVESTASI);
                        error_log("Delete result: " . ($delete_result ? 'SUCCESS' : 'FAILED'));
                    }
                } else {
                    error_log("ERROR: handle_file_upload returned NULL");
                }
            } catch (Exception $e) {
                error_log("File upload exception: " . $e->getMessage());
                throw new Exception("Gagal upload bukti: " . $e->getMessage());
            }
        } elseif (isset($_FILES['bukti']) && $_FILES['bukti']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Handle upload errors
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi upload_max_filesize)',
                UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi MAX_FILE_SIZE)',
                UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
                UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan',
                UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
                UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP'
            ];
            
            $error_msg = $upload_errors[$_FILES['bukti']['error']] ?? 'Error tidak diketahui';
            error_log("Upload error: " . $error_msg);
            throw new Exception($error_msg);
        } else {
            error_log("No file uploaded (UPLOAD_ERR_NO_FILE or not set)");
        }
        
        // Track changes
        $changes = [];
        if ($investasi['judul_investasi'] !== $judul) {
            $changes[] = 'judul';
            error_log("Changed: judul");
        }
        if ($investasi['kategori_id'] != $kategori_id) {
            $changes[] = 'kategori';
            error_log("Changed: kategori");
        }
        if ($investasi['jumlah'] != $jumlah) {
            $changes[] = 'jumlah';
            error_log("Changed: jumlah");
        }
        if ($investasi['tanggal_investasi'] !== $tanggal) {
            $changes[] = 'tanggal';
            error_log("Changed: tanggal");
        }
        if ($investasi['deskripsi'] !== $deskripsi) {
            $changes[] = 'deskripsi';
            error_log("Changed: deskripsi");
        }
        if ($file_changed) {
            $changes[] = 'bukti';
            error_log("Changed: bukti file");
        }
        
        error_log("Total changes: " . count($changes));
        error_log("Changes list: " . implode(', ', $changes));
        error_log("File ID to save: " . ($new_file_id ?? 'NULL'));
        
        // Check if there are any changes
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
        
        error_log("Executing UPDATE query...");
        error_log("Parameters: " . json_encode([
            'judul' => $judul,
            'deskripsi' => substr($deskripsi, 0, 50),
            'jumlah' => $jumlah,
            'tanggal' => $tanggal,
            'kategori_id' => $kategori_id,
            'bukti_file' => $new_file_id,
            'id' => $id
        ]));
        
        $stmt_update = $koneksi->prepare($sql_update);
        $result = $stmt_update->execute([
            $judul,
            $deskripsi,
            $jumlah,
            $tanggal,
            $kategori_id,
            $new_file_id,
            $id
        ]);
        
        if ($result) {
            error_log("UPDATE successful!");
            
            // Create detailed success message
            $change_details = implode(', ', $changes);
            $message = "✅ Data investasi berhasil diperbarui!";
            
            $message .= " | Perubahan: " . $change_details;
            
            if ($file_changed) {
                // Get file info
                $file_info = get_file_from_json($new_file_id, JSON_FILE_INVESTASI);
                $file_name = $file_info['original_name'] ?? 'file baru';
                $message .= " | 📎 Bukti baru: " . $file_name;
            }
            
            error_log("Success message: " . $message);
            error_log("=== EDIT INVESTASI END ===");
            
            //log_security_event("INVESTMENT_UPDATED", "ID: $id, User: " . $_SESSION['username'] . ", Changes: $change_details");
            redirect_with_message("../dashboard.php", "success", $message);
            exit;
        } else {
            $errorInfo = $stmt_update->errorInfo();
            error_log("UPDATE failed!");
            error_log("SQL Error: " . json_encode($errorInfo));
            throw new Exception("Gagal memperbarui data investasi. Error: " . $errorInfo[2]);
        }
        
    } catch (Exception $e) {
        $error = "❌ " . $e->getMessage();
        error_log("Edit investment error: " . $e->getMessage());
        error_log("=== EDIT INVESTASI END (ERROR) ===");
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Investasi - SAZEN v3.0</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/form_edit.css">
</head>
<body>
    <div class="form-wrapper">
        <!-- Header -->
        <div class="form-header">
            <a href="../dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                <span>Kembali</span>
            </a>
        </div>

        <!-- Form Container -->
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
                    <?php if ($investasi['bukti_file']): ?>
                        <div class="info-item">
                            <span class="info-label">Bukti:</span>
                            <span class="info-value">
                               <a href="../view_file.php?type=investasi&id=<?= $investasi['id'] ?>"
                                   target="_blank" class="file-link">
                                    <i class="fas fa-file-alt"></i>
                                    File ID: <?= htmlspecialchars($investasi['bukti_file']) ?>
                                </a>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($error): ?>
                <div class="message error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="message success-message">
                    <i class="fas fa-check-circle"></i>
                    <span><?= $success ?></span>
                </div>
            <?php endif; ?>
            
            <!-- DEBUG PANEL (hapus di production) -->
            <?php if (isset($_POST['update'])): ?>
            <div style="background: #1e293b; color: #e2e8f0; padding: 20px; border-radius: 8px; margin-bottom: 20px; font-family: 'Courier New', monospace; font-size: 12px;">
                <h3 style="color: #f59e0b; margin-top: 0;">🔍 DEBUG INFO - Last Submit</h3>
                <div style="display: grid; gap: 10px;">
                    <div><strong style="color: #22d3ee;">FILES Array:</strong></div>
                    <pre style="background: #0f172a; padding: 10px; border-radius: 4px; overflow-x: auto;"><?php print_r($_FILES); ?></pre>
                    
                    <?php if (isset($_FILES['bukti'])): ?>
                    <div><strong style="color: #22d3ee;">Upload Error Code:</strong> 
                        <?php 
                        $err = $_FILES['bukti']['error'];
                        $err_msgs = [
                            0 => '✅ UPLOAD_ERR_OK (No error)',
                            1 => '❌ UPLOAD_ERR_INI_SIZE',
                            2 => '❌ UPLOAD_ERR_FORM_SIZE', 
                            3 => '❌ UPLOAD_ERR_PARTIAL',
                            4 => '⚠️ UPLOAD_ERR_NO_FILE (No file selected)',
                            6 => '❌ UPLOAD_ERR_NO_TMP_DIR',
                            7 => '❌ UPLOAD_ERR_CANT_WRITE',
                            8 => '❌ UPLOAD_ERR_EXTENSION'
                        ];
                        echo $err . ' = ' . ($err_msgs[$err] ?? 'Unknown');
                        ?>
                    </div>
                    <?php endif; ?>
                    
                    <div><strong style="color: #22d3ee;">JSON File Status:</strong></div>
                    <pre style="background: #0f172a; padding: 10px; border-radius: 4px;">
Path: <?= JSON_FILE_INVESTASI ?>

Exists: <?= file_exists(JSON_FILE_INVESTASI) ? '✅ YES' : '❌ NO' ?>

Readable: <?= is_readable(JSON_FILE_INVESTASI) ? '✅ YES' : '❌ NO' ?>

Writable: <?= is_writable(JSON_FILE_INVESTASI) ? '✅ YES' : '❌ NO' ?>

Size: <?= file_exists(JSON_FILE_INVESTASI) ? filesize(JSON_FILE_INVESTASI) : 'N/A' ?> bytes
</pre>
                </div>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" enctype="multipart/form-data" class="investment-form" id="editForm" onsubmit="return false;">
                <!-- Hidden input untuk trigger update -->
                <input type="hidden" name="update" value="1" id="updateInput">
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
                        placeholder="Contoh: Saham BBCA"
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
                                placeholder="0,00"
                                required
                            >
                        </div>
                        <small class="form-hint">
                            <i class="fas fa-info-circle"></i>
                            Format: 1.000.000,50 atau 1000000.50
                        </small>
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
                        placeholder="Tambahkan catatan atau deskripsi investasi..."
                    ><?= htmlspecialchars($investasi['deskripsi']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="bukti">
                        <i class="fas fa-file-upload"></i>
                        Ganti Bukti Transaksi (Opsional)
                    </label>
                    
                    <?php if ($investasi['bukti_file']): 
                        $current_file = get_file_from_json($investasi['bukti_file'], JSON_FILE_INVESTASI);
                    ?>
                        <div class="current-file">
                            <div class="file-info">
                                <i class="fas fa-file-alt"></i>
                                <span>File saat ini: <strong><?= htmlspecialchars($current_file['original_name'] ?? 'Unknown') ?></strong></span>
                                <span class="file-size">(<?= number_format(($current_file['size'] ?? 0) / 1024, 2) ?> KB)</span>
                            </div>
                            <a href="../view_file.php?type=investasi&id=<?= $investasi['id'] ?>"
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
                    <button type="button" onclick="submitFormDirectly()" class="btn btn-primary" id="submitBtn">
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
        console.log('%c 🔧 DEBUG MODE ACTIVE ', 'background: #f59e0b; color: white; font-size: 14px; padding: 8px; font-weight: bold;');
        console.log('Investment ID:', <?= $id ?>);
        console.log('Current bukti_file:', '<?= $investasi['bukti_file'] ?? 'NULL' ?>');

        // IMPORTANT: Disable any SweetAlert or custom confirm dialogs
        if (window.Swal) {
            console.warn('SweetAlert detected - will be bypassed for form submit');
        }

        // Format currency input
        const jumlahInput = document.getElementById('jumlah');
        
        jumlahInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d,]/g, '');
            e.target.value = value;
        });

        // File upload preview with validation
        const fileInput = document.getElementById('bukti');
        const filePreview = document.getElementById('filePreview');
        const fileLabel = document.querySelector('.file-label .file-text');

        fileInput.addEventListener('change', function() {
            console.log('%c FILE CHANGE EVENT ', 'background: #3b82f6; color: white; padding: 6px;');
            
            filePreview.innerHTML = '';
            
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const fileName = file.name;
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                const fileType = file.type;

                console.log('File details:', {
                    name: fileName,
                    size: fileSize + ' MB',
                    type: fileType
                });

                // Validate file size
                if (fileSize > 5) {
                    alert('❌ File terlalu besar! Maksimal 5MB');
                    this.value = '';
                    fileLabel.textContent = 'Pilih file baru (JPG, PNG, PDF - Max 5MB)';
                    console.error('File too large:', fileSize, 'MB');
                    return;
                }

                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                if (!allowedTypes.includes(fileType)) {
                    alert('❌ Tipe file tidak didukung! Gunakan JPG, PNG, atau PDF');
                    this.value = '';
                    fileLabel.textContent = 'Pilih file baru (JPG, PNG, PDF - Max 5MB)';
                    console.error('Invalid file type:', fileType);
                    return;
                }

                console.log('✓ File validation passed');

                // Update label
                fileLabel.textContent = fileName;

                // Create preview
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
                        console.log('✓ Image preview created');
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
                    console.log('✓ PDF preview created');
                }
            } else {
                console.log('No file selected');
            }
        });

        function removeFile() {
            console.log('Removing file...');
            fileInput.value = '';
            filePreview.innerHTML = '';
            fileLabel.textContent = 'Pilih file baru (JPG, PNG, PDF - Max 5MB)';
        }

        // DIRECT FORM SUBMIT - Bypass all event listeners
        function submitFormDirectly() {
            console.log('%c 🚀 DIRECT SUBMIT TRIGGERED ', 'background: #ef4444; color: white; padding: 8px; font-weight: bold;');
            
            const form = document.getElementById('editForm');
            const submitButton = document.getElementById('submitBtn');
            const judul = document.getElementById('judul_investasi').value.trim();
            const jumlah = jumlahInput.value.trim();
            const tanggal = document.getElementById('tanggal_investasi').value;
            const kategori = document.getElementById('kategori_id').value;
            const fileUploaded = fileInput.files.length > 0;
            
            console.log('Form validation check:');
            console.table({
                'Judul': judul || '❌ EMPTY',
                'Kategori': kategori || '❌ EMPTY',
                'Jumlah': jumlah || '❌ EMPTY',
                'Tanggal': tanggal || '❌ EMPTY',
                'File Uploaded': fileUploaded ? '✅ YES: ' + fileInput.files[0].name : '⚠️ NO (Optional)'
            });
            
            // Basic validation
            if (!judul) {
                alert('❌ Judul investasi wajib diisi!');
                document.getElementById('judul_investasi').focus();
                return false;
            }
            
            if (!kategori || kategori === '0' || kategori === '') {
                alert('❌ Kategori wajib dipilih!');
                document.getElementById('kategori_id').focus();
                return false;
            }
            
            if (!jumlah || jumlah === '0' || jumlah === '0,00') {
                alert('❌ Jumlah investasi wajib diisi!');
                jumlahInput.focus();
                return false;
            }
            
            if (!tanggal) {
                alert('❌ Tanggal investasi wajib diisi!');
                document.getElementById('tanggal_investasi').focus();
                return false;
            }
            
            console.log('✅ All validation passed!');
            
            // Show loading
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span> Menyimpan...</span>';
            
            console.log('🔄 Submitting form to server...');
            console.log('Form action:', form.action || 'Same page (POST)');
            console.log('Form method:', form.method);
            console.log('Form enctype:', form.enctype);
            
            // Submit form directly using native submit
            form.submit();
            
            console.log('✅ Form.submit() called!');
        }

        // DIRECT FORM SUBMIT - Bypass all event listeners
        function submitFormDirectly() {
            console.log('%c 🚀 DIRECT SUBMIT TRIGGERED ', 'background: #ef4444; color: white; padding: 8px; font-weight: bold;');
            
            const form = document.getElementById('editForm');
            const submitButton = document.getElementById('submitBtn');
            const judul = document.getElementById('judul_investasi').value.trim();
            const jumlah = jumlahInput.value.trim();
            const tanggal = document.getElementById('tanggal_investasi').value;
            const kategori = document.getElementById('kategori_id').value;
            const fileUploaded = fileInput.files.length > 0;
            
            console.log('Form validation check:');
            console.table({
                'Judul': judul || '❌ EMPTY',
                'Kategori': kategori || '❌ EMPTY',
                'Jumlah': jumlah || '❌ EMPTY',
                'Tanggal': tanggal || '❌ EMPTY',
                'File Uploaded': fileUploaded ? '✅ YES: ' + fileInput.files[0].name : '⚠️ NO (Optional)'
            });
            
            // Basic validation
            if (!judul) {
                alert('❌ Judul investasi wajib diisi!');
                document.getElementById('judul_investasi').focus();
                return false;
            }
            
            if (!kategori || kategori === '0' || kategori === '') {
                alert('❌ Kategori wajib dipilih!');
                document.getElementById('kategori_id').focus();
                return false;
            }
            
            if (!jumlah || jumlah === '0' || jumlah === '0,00') {
                alert('❌ Jumlah investasi wajib diisi!');
                jumlahInput.focus();
                return false;
            }
            
            if (!tanggal) {
                alert('❌ Tanggal investasi wajib diisi!');
                document.getElementById('tanggal_investasi').focus();
                return false;
            }
            
            console.log('✅ All validation passed!');
            
            // Show loading
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span> Menyimpan...</span>';
            
            console.log('🔄 Submitting form to server...');
            console.log('Form action:', form.action || 'Same page (POST)');
            console.log('Form method:', form.method);
            console.log('Form enctype:', form.enctype);
            
            // Submit form directly using native submit
            form.submit();
            
            console.log('✅ Form.submit() called!');
        }

        // Keep old event listener but it won't fire (onsubmit="return false")
        const form = document.getElementById('editForm');
        const submitButton = document.getElementById('submitBtn');
        
        form.addEventListener('submit', function(e) {
            console.log('%c 📤 FORM SUBMIT EVENT ', 'background: #10b981; color: white; padding: 8px; font-weight: bold;');
            
            const judul = document.getElementById('judul_investasi').value.trim();
            const jumlah = jumlahInput.value.trim();
            const tanggal = document.getElementById('tanggal_investasi').value;
            const kategori = document.getElementById('kategori_id').value;
            const fileUploaded = fileInput.files.length > 0;
            
            console.log('Form data:', {
                judul: judul,
                kategori: kategori,
                jumlah: jumlah,
                tanggal: tanggal,
                fileUploaded: fileUploaded,
                fileName: fileUploaded ? fileInput.files[0].name : 'N/A'
            });
            
            // Basic validation only - no confirm
            if (!judul || !kategori || !jumlah || !tanggal) {
                e.preventDefault();
                alert('❌ Semua field wajib diisi!');
                return false;
            }
            
            // Show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span> Menyimpan...</span>';
            
            console.log('✅ Validation passed - submitting form to server...');
            // Form will submit naturally - don't preventDefault
        });

        // Form validation with detailed checks
        document.getElementById('editForm').addEventListener('submit', function(e) {
            console.log('%c FORM SUBMIT ', 'background: #10b981; color: white; padding: 6px; font-weight: bold;');
            
            const judul = document.getElementById('judul_investasi').value.trim();
            const jumlah = jumlahInput.value.trim();
            const tanggal = document.getElementById('tanggal_investasi').value;
            const kategori = document.getElementById('kategori_id').value;
            const fileUploaded = fileInput.files.length > 0;
            
            console.table({
                'Judul': judul,
                'Kategori': kategori,
                'Jumlah': jumlah,
                'Tanggal': tanggal,
                'File Uploaded': fileUploaded ? 'YES' : 'NO',
                'File Name': fileUploaded ? fileInput.files[0].name : 'N/A',
                'File Size': fileUploaded ? (fileInput.files[0].size / 1024 / 1024).toFixed(2) + ' MB' : 'N/A'
            });
            
            // Validate all fields
            if (!judul) {
                e.preventDefault();
                alert('❌ Judul investasi harus diisi!');
                document.getElementById('judul_investasi').focus();
                return false;
            }

            if (!kategori || kategori === '0') {
                e.preventDefault();
                alert('❌ Kategori harus dipilih!');
                document.getElementById('kategori_id').focus();
                return false;
            }
            
            if (!jumlah || jumlah === '0' || jumlah === '0,00') {
                e.preventDefault();
                alert('❌ Jumlah investasi harus diisi dengan benar!');
                jumlahInput.focus();
                return false;
            }

            if (!tanggal) {
                e.preventDefault();
                alert('❌ Tanggal investasi harus diisi!');
                document.getElementById('tanggal_investasi').focus();
                return false;
            }

            // REMOVE confirm dialog - langsung submit
            console.log('✓ Form validation passed, submitting form...');

            // Disable submit button to prevent double submit
            const submitBtn = this.querySelector('button[name="update"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Menyimpan...</span>';
            
            // Let form submit naturally - DO NOT preventDefault
            console.log('Form submitting now...');
        });

        // Auto-hide messages
        setTimeout(() => {
            document.querySelectorAll('.message').forEach(msg => {
                msg.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => msg.remove(), 300);
            });
        }, 7000);

        // Drag and drop
        const fileUploadWrapper = document.querySelector('.file-upload-wrapper');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileUploadWrapper.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            fileUploadWrapper.addEventListener(eventName, () => {
                fileUploadWrapper.classList.add('drag-over');
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            fileUploadWrapper.addEventListener(eventName, () => {
                fileUploadWrapper.classList.remove('drag-over');
            }, false);
        });
        
        fileUploadWrapper.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            
            const event = new Event('change', { bubbles: true });
            fileInput.dispatchEvent(event);
        }, false);

        console.log('%c SAZEN Edit Investment v3.0 Ready ', 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 14px; padding: 8px; border-radius: 4px;');
    </script>
</body>
</html>