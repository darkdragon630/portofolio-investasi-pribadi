<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * Database Configuration (Fixed Non-SSL Connection)
 * Updated: JSON-based file storage
 */

// ==============================
// Database credentials
// ==============================
define('DB_HOST', 'db.fr-pari1.bengt.wasmernet.com');
define('DB_NAME', 'dbRvXBrhE5kk945ac7gqVTNN');
define('DB_USER', 'f141e6017c7d8000353a742134d7');
define('DB_PASS', '068ff141-e601-7e29-8000-3aba0db6a393');
define('DB_PORT', 10272);
define('DB_CHARSET', 'utf8mb4');

// ==============================
// Environment settings
// ==============================
date_default_timezone_set('Asia/Jakarta');

// Error reporting (development)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Session hardening
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
}

// ==============================
// JSON Storage Configuration
// ==============================
define('JSON_STORAGE_DIR', __DIR__ . '/../storage/json/');
define('JSON_FILE_INVESTASI', JSON_STORAGE_DIR . 'bukti_investasi.json');
define('JSON_FILE_KEUNTUNGAN', JSON_STORAGE_DIR . 'bukti_keuntungan.json');
define('JSON_FILE_KERUGIAN', JSON_STORAGE_DIR . 'bukti_kerugian.json');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB untuk base64
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

// Create storage directories if missing
$storage_dirs = [
    JSON_STORAGE_DIR,
    __DIR__ . '/../logs/'
];

foreach ($storage_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Initialize JSON files if not exist
$json_files = [
    JSON_FILE_INVESTASI,
    JSON_FILE_KEUNTUNGAN,
    JSON_FILE_KERUGIAN
];

foreach ($json_files as $file) {
    if (!file_exists($file)) {
        file_put_contents($file, json_encode([], JSON_PRETTY_PRINT));
    }
}

// ==============================
// PDO Connection
// ==============================
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => true,
        PDO::ATTR_TIMEOUT            => 5,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ];

    try {
        // Koneksi utama (tanpa SSL)
        $koneksi = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e1) {
        // Fallback: paksa non-SSL mode
        $dsn_no_ssl = $dsn . ';sslmode=disabled';
        $koneksi = new PDO($dsn_no_ssl, DB_USER, DB_PASS, $options);
    }

} catch (PDOException $e) {
    // Log error koneksi
    error_log(
        sprintf(
            "[%s] PDO connection failed: %s (DSN: %s)\n",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $dsn ?? 'unknown'
        ),
        3,
        __DIR__ . '/../logs/php_errors.log'
    );

    // Pesan ramah untuk user
    die("
    <div style='font-family: Arial; padding: 50px; text-align: center;'>
        <h2 style='color: #e74c3c;'>⚠️ Database Connection Failed</h2>
        <p>Tidak dapat terhubung ke database. Silakan cek konfigurasi.</p>
        <p style='color: #7f8c8d; font-size: 14px;'>
            Error: " . htmlspecialchars($e->getMessage()) . "
        </p>
        <p style='color: #95a5a6; font-size: 12px;'>
            Host: " . DB_HOST . ':' . DB_PORT . "
        </p>
    </div>
    ");
}

// ==============================
// Helper Functions
// ==============================

// Sanitize input
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Parse currency input correctly - SUPER FIXED VERSION v2
 * Handles formats: 1500000, 1.500.000, 1,500,000, 0.82, 0,82, 4
 * 
 * FIXED ISSUES:
 * - ✅ Deteksi desimal kecil seperti 0,82 atau 0.82
 * - ✅ Handle edge cases dengan array bounds checking
 * - ✅ Validasi parts sebelum mengakses index
 * - ✅ FIX: 0.82 tidak salah dianggap sebagai Rp 82
 */
function parse_currency_fixed($value) {
    if (empty($value)) return 0;
    
    // Remove whitespace
    $value = trim($value);
    
    // Remove Rp and currency symbols
    $value = preg_replace('/[Rp\s]/', '', $value);
    
    // Remove any remaining non-numeric except dots and commas
    $value = preg_replace('/[^\d\.\,]/', '', $value);
    
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
            
            // IMPORTANT: Validate parts array
            if (count($parts) === 2 && isset($parts[1])) {
                $decimalPart = $parts[1];
                $integerPart = $parts[0];
                
                // CRITICAL FIX: Check if integer part is 0
                if ($integerPart == '0') {
                    // Format: 0.82 = desimal, BUKAN ribuan!
                    // Keep as is
                }
                // Check decimal length
                else if (strlen($decimalPart) <= 2) {
                    // Likely decimal: 1500.50
                    // Keep as is
                } else if (strlen($decimalPart) === 3) {
                    // Ambiguous: could be 1.500 (thousand) or 1.500 (???)
                    // Default: treat as thousand separator if integer part <= 2 digits
                    if (strlen($integerPart) <= 2) {
                        $value = str_replace('.', '', $value);
                    }
                } else {
                    // Long decimal part = thousand separator: 1.500000
                    $value = str_replace('.', '', $value);
                }
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
            
            // IMPORTANT: Validate parts array
            if (count($parts) === 2 && isset($parts[1])) {
                $decimalPart = $parts[1];
                $integerPart = $parts[0];
                
                // CRITICAL FIX: Check if integer part is 0
                if ($integerPart == '0') {
                    // Format: 0,82 = desimal, BUKAN ribuan!
                    $value = str_replace(',', '.', $value);
                }
                // Check decimal length
                else if (strlen($decimalPart) <= 2) {
                    // Likely decimal: 1500,50
                    $value = str_replace(',', '.', $value);
                } else if (strlen($decimalPart) === 3) {
                    // Ambiguous: could be 1,500 (thousand) or ???
                    // Default: treat as thousand separator if integer part <= 2 digits
                    if (strlen($integerPart) <= 2) {
                        $value = str_replace(',', '', $value);
                    } else {
                        // Treat as decimal with 3 digits (rare but valid)
                        $value = str_replace(',', '.', $value);
                    }
                } else {
                    // Long decimal part = thousand separator: 1,500000
                    $value = str_replace(',', '', $value);
                }
            } else {
                // Invalid format, just remove comma
                $value = str_replace(',', '', $value);
            }
        }
    }
    // No separator - plain number (4, 1500000)
    // Keep as is
    
    // Final conversion to float
    $result = floatval($value);
    
    // Debug log untuk troubleshooting
    error_log(sprintf(
        "parse_currency_fixed: INPUT='%s' → OUTPUT=%.2f",
        $_POST['jumlah_keuntungan'] ?? $value,
        $result
    ));
    
    return $result;
}


// Format currency for display
function format_currency($value) {
    return 'Rp ' . number_format($value, 2, ',', '.');
}

// Handle file upload to JSON
function handle_file_upload($file, $json_file) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload error: ' . $file['error']);
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('File terlalu besar. Maksimal ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        throw new Exception('Format file tidak didukung. Hanya: ' . implode(', ', ALLOWED_EXTENSIONS));
    }

    // Read file content and convert to base64
    $file_content = file_get_contents($file['tmp_name']);
    $base64_data = base64_encode($file_content);

    // Generate unique ID
    $file_id = time() . '_' . bin2hex(random_bytes(8));

    // Prepare file data
    $file_data = [
        'id' => $file_id,
        'original_name' => $file['name'],
        'extension' => $ext,
        'size' => $file['size'],
        'mime_type' => mime_content_type($file['tmp_name']),
        'base64_data' => $base64_data,
        'uploaded_at' => date('Y-m-d H:i:s')
    ];

    // Read existing JSON data
    $json_content = file_get_contents($json_file);
    $files_array = json_decode($json_content, true) ?: [];

    // Add new file
    $files_array[$file_id] = $file_data;

    // Save to JSON file
    if (!file_put_contents($json_file, json_encode($files_array, JSON_PRETTY_PRINT))) {
        throw new Exception('Gagal menyimpan file ke JSON');
    }

    return $file_id;
}

// Get file from JSON
function get_file_from_json($file_id, $json_file) {
    if (!file_exists($json_file)) {
        return null;
    }

    $json_content = file_get_contents($json_file);
    $files_array = json_decode($json_content, true) ?: [];

    return $files_array[$file_id] ?? null;
}

// Delete file from JSON
function delete_file($file_id, $json_file) {
    if (!$file_id || !file_exists($json_file)) {
        return false;
    }

    $json_content = file_get_contents($json_file);
    $files_array = json_decode($json_content, true) ?: [];

    if (isset($files_array[$file_id])) {
        unset($files_array[$file_id]);
        file_put_contents($json_file, json_encode($files_array, JSON_PRETTY_PRINT));
        return true;
    }

    return false;
}

// Display file from JSON (output base64 data)
function display_file_from_json($file_id, $json_file) {
    $file_data = get_file_from_json($file_id, $json_file);
    
    if (!$file_data) {
        http_response_code(404);
        die('File tidak ditemukan');
    }

    // Set appropriate headers
    header('Content-Type: ' . $file_data['mime_type']);
    header('Content-Length: ' . $file_data['size']);
    header('Content-Disposition: inline; filename="' . $file_data['original_name'] . '"');

    // Output decoded base64 data
    echo base64_decode($file_data['base64_data']);
    exit;
}

// Redirect with message
function redirect_with_message($url, $type, $message) {
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_message'] = $message;
    header("Location: $url");
    exit;
}


// Upload file dan simpan ke database (return JSON metadata)
function handle_file_upload_to_db($file) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload error: ' . $file['error']);
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('File terlalu besar. Maksimal ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        throw new Exception('Format file tidak didukung. Hanya: ' . implode(', ', ALLOWED_EXTENSIONS));
    }

    // Read file and encode to base64
    $file_content = file_get_contents($file['tmp_name']);
    $base64_data = base64_encode($file_content);

    // Create metadata JSON
    $metadata = json_encode([
        'original_name' => $file['name'],
        'extension' => $ext,
        'size' => $file['size'],
        'mime_type' => mime_content_type($file['tmp_name']),
        'uploaded_at' => date('Y-m-d H:i:s')
    ]);

    // Return combined: metadata|base64data
    return $metadata . '|||' . $base64_data;
}

// Parse bukti_file dari database
function parse_bukti_file($bukti_file) {
    if (empty($bukti_file)) {
        return null;
    }

    // Split metadata dan base64
    $parts = explode('|||', $bukti_file, 2);
    
    if (count($parts) !== 2) {
        return null; // Invalid format
    }

    $metadata = json_decode($parts[0], true);
    $base64_data = $parts[1];

    if (!$metadata) {
        return null;
    }

    return [
        'original_name' => $metadata['original_name'] ?? 'file',
        'extension' => $metadata['extension'] ?? 'bin',
        'size' => $metadata['size'] ?? 0,
        'mime_type' => $metadata['mime_type'] ?? 'application/octet-stream',
        'uploaded_at' => $metadata['uploaded_at'] ?? date('Y-m-d H:i:s'),
        'base64_data' => $base64_data
    ];
}

// Display file dari database
function display_file_from_db($bukti_file) {
    $file_data = parse_bukti_file($bukti_file);
    
    if (!$file_data) {
        http_response_code(404);
        die('File tidak ditemukan');
    }

    // Set headers
    header('Content-Type: ' . $file_data['mime_type']);
    header('Content-Length: ' . $file_data['size']);
    header('Content-Disposition: inline; filename="' . $file_data['original_name'] . '"');
    header('Cache-Control: private, max-age=3600');

    // Output decoded base64
    echo base64_decode($file_data['base64_data']);
    exit;
}

/**
 * Get flash message - KOMPATIBEL DENGAN DUA CARA
 * Cara 1: get_flash_message() - return array atau null
 * Cara 2: get_flash_message('success') - return string atau null
 * 
 * @param string|null $type Optional type parameter
 * @return mixed
 */
if (!function_exists('get_flash_message')) {
    function get_flash_message($type = null) {
        // Jika ada parameter type - ambil pesan specific
        if ($type !== null) {
            if (isset($_SESSION['_flash'][$type])) {
                $msg = $_SESSION['_flash'][$type];
                unset($_SESSION['_flash'][$type]);
                return $msg;
            }
            return null;
        }
        
        // Jika tanpa parameter - ambil pesan dari flash_message (cara lama)
        if (isset($_SESSION['flash_message'])) {
            $flash = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $flash;
        }
        
        return null;
    }
}

/**
 * Set flash message
 * @param string $type Type: success, error, warning, info
 * @param string $message Message text
 */
if (!function_exists('set_flash_message')) {
    function set_flash_message($type, $message) {
        $_SESSION['_flash'][$type] = $message;
    }
}

/**
 * Redirect with flash message - UPDATED
 * Kompatibel dengan get_flash_message() tanpa parameter
 * 
 * @param string $url Destination URL
 * @param string $type Message type
 * @param string $message Message text
 */
if (!function_exists('redirect_with_message')) {
    function redirect_with_message($url, $type, $message) {
        // Set menggunakan cara lama (flash_message) untuk kompatibilitas
        $_SESSION['flash_message'] = [
            'type' => $type,
            'message' => $message
        ];
        header("Location: $url");
        exit;
    }
}

/**
 * Display flash message HTML
 * @param string $type Type of message
 * @return string HTML or empty string
 */
if (!function_exists('flash')) {
    function flash($type) {
        $msg = get_flash_message($type);
        if (!$msg) {
            return '';
        }
        
        $icons = [
            'success' => 'check-circle',
            'error'   => 'exclamation-circle',
            'warning' => 'exclamation-triangle',
            'info'    => 'info-circle'
        ];
        
        $icon = $icons[$type] ?? 'info-circle';
        
        return <<<HTML
        <div class="alert alert-{$type}">
            <i class="fas fa-{$icon}"></i>
            <span>{$msg}</span>
        </div>
HTML;
    }
}
?>
