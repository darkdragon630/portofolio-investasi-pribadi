<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * View File from Database
 */

require_once 'config/koneksi.php';

// Validate parameters
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    http_response_code(400);
    die('Missing required parameters');
}

$type = $_GET['type'];
$record_id = (int)$_GET['id'];

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
        http_response_code(400);
        die('Invalid file type');
}

try {
    // Get bukti_file from database
    $sql = "SELECT bukti_file FROM {$table} WHERE id = ?";
    $stmt = $koneksi->prepare($sql);
    $stmt->execute([$record_id]);
    $result = $stmt->fetch();
    
    if (!$result || empty($result['bukti_file'])) {
        http_response_code(404);
        die('File tidak ditemukan');
    }
    
    // Parse and display file
    display_file_from_db($result['bukti_file']);
    
} catch (Exception $e) {
    error_log("View file error: " . $e->getMessage());
    http_response_code(500);
    die('Terjadi kesalahan saat mengambil file');
}
?>