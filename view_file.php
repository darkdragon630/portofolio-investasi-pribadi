<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * View File from Database - Investment Files Only
 * Fixed Version with Built-in File Display
 */

require_once 'config/koneksi.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
error_log("=== VIEW_FILE.PHP START ===");

// Validate parameters
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    error_log("Missing parameters - type: " . ($_GET['type'] ?? 'null') . ", id: " . ($_GET['id'] ?? 'null'));
    http_response_code(400);
    die('Missing required parameters');
}

$type = $_GET['type'];
$record_id = (int)$_GET['id'];
$is_download = isset($_GET['download']) && $_GET['download'] == '1';

error_log("Request params - type: $type, id: $record_id, download: " . ($is_download ? 'YES' : 'NO'));

// Determine which table to query
switch ($type) {
    case 'investasi':
        $table = 'investasi';
        break;
    case 'keuntungan':
        $table = 'keuntungan_investasi';
        break;
    case 'kerugian':
        $table = 'kerugian_investasi';
        break;
    default:
        error_log("Invalid type: $type");
        http_response_code(400);
        die('Invalid file type. Allowed: investasi, keuntungan, kerugian');
}

error_log("Querying table: $table for id: $record_id");

try {
    // Get bukti_file from database
    $sql = "SELECT bukti_file FROM {$table} WHERE id = ?";
    $stmt = $koneksi->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $koneksi->error);
    }
    
    $stmt->execute([$record_id]);
    $result = $stmt->fetch();
    
    if (!$result) {
        error_log("Record not found in $table with id: $record_id");
        http_response_code(404);
        die('Record tidak ditemukan');
    }
    
    if (empty($result['bukti_file'])) {
        error_log("bukti_file is empty for $table id: $record_id");
        http_response_code(404);
        die('File tidak ditemukan atau belum ada bukti file');
    }
    
    $bukti_file = $result['bukti_file'];
    error_log("bukti_file raw data: " . $bukti_file);
    
    // Parse bukti_file format: filename|original_name|size|mime_type|timestamp
    $parts = explode('|', $bukti_file);
    
    if (count($parts) < 5) {
        error_log("Invalid bukti_file format. Parts count: " . count($parts));
        http_response_code(500);
        die('Format file tidak valid');
    }
    
    $filename = $parts[0];
    $original_name = $parts[1];
    $file_size = (int)$parts[2];
    $mime_type = $parts[3];
    $timestamp = $parts[4];
    
    error_log("Parsed - filename: $filename, original: $original_name, mime: $mime_type");
    
    // Construct file path
    $upload_dir = __DIR__ . '/uploads/';
    $file_path = $upload_dir . $filename;
    
    error_log("File path: $file_path");
    error_log("File exists: " . (file_exists($file_path) ? 'YES' : 'NO'));
    
    // Check if file exists
    if (!file_exists($file_path)) {
        error_log("File not found on disk: $file_path");
        http_response_code(404);
        die('File tidak ditemukan di server');
    }
    
    // Check if file is readable
    if (!is_readable($file_path)) {
        error_log("File not readable: $file_path");
        http_response_code(500);
        die('File tidak dapat dibaca');
    }
    
    $actual_file_size = filesize($file_path);
    error_log("File size on disk: $actual_file_size bytes");
    
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers
    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . $actual_file_size);
    header('Cache-Control: public, max-age=31536000');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
    
    if ($is_download) {
        // Force download
        header('Content-Disposition: attachment; filename="' . addslashes($original_name) . '"');
        error_log("Serving as download: $original_name");
    } else {
        // Inline display (for images and PDFs)
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'])) {
            header('Content-Disposition: inline; filename="' . addslashes($original_name) . '"');
            error_log("Serving inline: $original_name");
        } else {
            header('Content-Disposition: attachment; filename="' . addslashes($original_name) . '"');
            error_log("Serving as download (non-viewable): $original_name");
        }
    }
    
    // Output file
    $fp = fopen($file_path, 'rb');
    if ($fp === false) {
        error_log("Failed to open file: $file_path");
        http_response_code(500);
        die('Gagal membuka file');
    }
    
    error_log("Starting file output...");
    fpassthru($fp);
    fclose($fp);
    
    error_log("File served successfully");
    error_log("=== VIEW_FILE.PHP END (SUCCESS) ===");
    exit;
    
} catch (PDOException $e) {
    error_log("View file error (PDO): " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("=== VIEW_FILE.PHP END (ERROR) ===");
    http_response_code(500);
    die('Database error: Terjadi kesalahan saat mengambil file');
} catch (Exception $e) {
    error_log("View file error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("=== VIEW_FILE.PHP END (ERROR) ===");
    http_response_code(500);
    die('Terjadi kesalahan: ' . $e->getMessage());
}
?>
