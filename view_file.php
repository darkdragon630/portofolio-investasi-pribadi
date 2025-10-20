<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * View File from JSON Storage
 */

require_once 'config/koneksi.php';

// Validate parameters
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    http_response_code(400);
    die('Missing required parameters');
}

$type = $_GET['type'];
$file_id = $_GET['id'];

// Determine which JSON file to use
switch ($type) {
    case 'investasi':
        $json_file = JSON_FILE_INVESTASI;
        break;
    case 'keuntungan':
        $json_file = JSON_FILE_KEUNTUNGAN;
        break;
    case 'kerugian':
        $json_file = JSON_FILE_KERUGIAN;
        break;
    default:
        http_response_code(400);
        die('Invalid file type');
}

// Get file from JSON
$file_data = get_file_from_json($file_id, $json_file);

if (!$file_data) {
    http_response_code(404);
    die('File tidak ditemukan');
}

// Check if download is requested
$is_download = isset($_GET['download']) && $_GET['download'] === '1';

// Set appropriate headers
header('Content-Type: ' . $file_data['mime_type']);
header('Content-Length: ' . $file_data['size']);

if ($is_download) {
    // Force download
    header('Content-Disposition: attachment; filename="' . $file_data['original_name'] . '"');
} else {
    // Display inline (preview in browser)
    header('Content-Disposition: inline; filename="' . $file_data['original_name'] . '"');
}

// Prevent caching for sensitive files (optional)
header('Cache-Control: private, max-age=3600');
header('Pragma: private');

// Output decoded base64 data
echo base64_decode($file_data['base64_data']);
exit;
?>