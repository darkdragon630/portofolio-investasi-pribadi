<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * View File from Database - Base64 Storage Version
 * FIXED: Using display_file_from_db() from koneksi.php
 */

require_once 'config/koneksi.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
error_log("=== VIEW_FILE.PHP START (Base64 Version) ===");

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
        throw new Exception("Failed to prepare statement");
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
    error_log("bukti_file length: " . strlen($bukti_file) . " chars");
    
    // Parse bukti_file using function from koneksi.php
    $file_data = parse_bukti_file($bukti_file);
    
    if (!$file_data) {
        error_log("Failed to parse bukti_file");
        http_response_code(500);
        die('Format file tidak valid');
    }
    
    error_log("Parsed - original: {$file_data['original_name']}, mime: {$file_data['mime_type']}, size: {$file_data['size']}");
    
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Decode base64
    $file_content = base64_decode($file_data['base64_data']);
    
    if ($file_content === false) {
        error_log("Failed to decode base64 data");
        http_response_code(500);
        die('Gagal decode file data');
    }
    
    $actual_size = strlen($file_content);
    error_log("Decoded file size: $actual_size bytes");
    
    // Set headers
    header('Content-Type: ' . $file_data['mime_type']);
    header('Content-Length: ' . $actual_size);
    header('Cache-Control: public, max-age=31536000');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
    
    if ($is_download) {
        // Force download
        header('Content-Disposition: attachment; filename="' . addslashes($file_data['original_name']) . '"');
        error_log("Serving as download: {$file_data['original_name']}");
    } else {
        // Inline display (for images and PDFs)
        $extension = strtolower($file_data['extension']);
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'])) {
            header('Content-Disposition: inline; filename="' . addslashes($file_data['original_name']) . '"');
            error_log("Serving inline: {$file_data['original_name']}");
        } else {
            header('Content-Disposition: attachment; filename="' . addslashes($file_data['original_name']) . '"');
            error_log("Serving as download (non-viewable): {$file_data['original_name']}");
        }
    }
    
    // Output file content
    echo $file_content;
    
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
