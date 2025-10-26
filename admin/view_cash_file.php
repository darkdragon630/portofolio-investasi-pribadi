<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * View Cash Balance File from Database
 * File: view_cash_file.php
 */
require_once '../config/koneksi.php';

// Validate parameters
if (!isset($_GET['id'])) {
    http_response_code(400);
    die('Missing required parameter: id');
}

$cash_id = (int)$_GET['id'];

try {
    // Get bukti_file from cash_balance table
    $sql = "SELECT bukti_file FROM cash_balance WHERE id = ?";
    $stmt = $koneksi->prepare($sql);
    $stmt->execute([$cash_id]);
    $result = $stmt->fetch();
    
    if (!$result || empty($result['bukti_file'])) {
        http_response_code(404);
        die('File tidak ditemukan atau belum ada bukti file untuk cash balance ini');
    }
    
    // Parse and display file using function from koneksi.php
    display_file_from_db($result['bukti_file']);
    
} catch (PDOException $e) {
    error_log("View cash file error (PDO): " . $e->getMessage());
    http_response_code(500);
    die('Database error: Terjadi kesalahan saat mengambil file cash balance');
} catch (Exception $e) {
    error_log("View cash file error: " . $e->getMessage());
    http_response_code(500);
    die('Terjadi kesalahan saat mengambil file cash balance');
}
?>
