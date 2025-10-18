<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * Database Configuration
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'sazen_v3');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Error reporting (development mode)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Upload configuration
define('UPLOAD_DIR_INVESTASI', __DIR__ . '/uploads/bukti_investasi/');
define('UPLOAD_DIR_KEUNTUNGAN', __DIR__ . '/uploads/bukti_keuntungan/');
define('UPLOAD_DIR_KERUGIAN', __DIR__ . '/uploads/bukti_kerugian/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

// Create upload directories if not exist
$upload_dirs = [
    UPLOAD_DIR_INVESTASI,
    UPLOAD_DIR_KEUNTUNGAN,
    UPLOAD_DIR_KERUGIAN,
    __DIR__ . '/logs/'
];

foreach ($upload_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// PDO Connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];
    
    $koneksi = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch (PDOException $e) {
    // Log error
    error_log("Database Connection Error: " . $e->getMessage());
    
    // Display user-friendly error
    die("
    <div style='font-family: Arial; padding: 50px; text-align: center;'>
        <h2 style='color: #e74c3c;'>⚠️ Database Connection Failed</h2>
        <p>Tidak dapat terhubung ke database. Silakan cek konfigurasi.</p>
        <p style='color: #7f8c8d; font-size: 14px;'>Error: " . $e->getMessage() . "</p>
    </div>
    ");
}

/**
 * Helper Functions
 */

// Sanitize input
function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Parse currency input (support Rp 1.000,50 or 1000.50)
function parse_currency($value) {
    if (empty($value)) return 0;
    
    $value = preg_replace('/[^\d\.\,]/', '', $value);
    
    $lastComma = strrpos($value, ',');
    $lastDot = strrpos($value, '.');
    
    if ($lastComma === false && $lastDot === false) {
        return (float)$value;
    } elseif ($lastComma !== false && $lastDot !== false) {
        if ($lastComma > $lastDot) {
            return (float)str_replace(['.', ','], ['', '.'], $value);
        } else {
            return (float)str_replace(',', '', $value);
        }
    } elseif ($lastComma !== false) {
        return (float)str_replace(',', '.', $value);
    } else {
        return (float)str_replace('.', '', $value);
    }
}

// Format currency for display
function format_currency($value) {
    return 'Rp ' . number_format($value, 2, ',', '.');
}

// Handle file upload
function handle_file_upload($file, $upload_dir) {
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
    
    $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $filepath = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Gagal menyimpan file');
    }
    
    return $filename;
}

// Delete file
function delete_file($filename, $upload_dir) {
    if ($filename && file_exists($upload_dir . $filename)) {
        unlink($upload_dir . $filename);
    }
}

// Log security event
function log_security_event($event, $details = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $log_entry = date('Y-m-d H:i:s') . " | $ip | $event | $details" . PHP_EOL;
    error_log($log_entry, 3, __DIR__ . '/logs/security.log');
}

// Generate CSRF token
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Redirect with message
function redirect_with_message($url, $type, $message) {
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_message'] = $message;
    header("Location: $url");
    exit;
}

// Get flash message
function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_type'] ?? 'info';
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_type'], $_SESSION['flash_message']);
        return ['type' => $type, 'message' => $message];
    }
    return null;
}
?>