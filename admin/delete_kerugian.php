<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * Delete Kerugian
 * Menghapus kerugian investasi beserta file bukti
 */

session_start();
require_once "../config.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

// Validate ID parameter
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect_with_message("../dashboard.php", "error", "ID kerugian tidak valid.");
}

$id = (int)$_GET['id'];

try {
    // Get kerugian data first (to get file name)
    $sql = "SELECT * FROM kerugian_investasi WHERE id = ?";
    $stmt = $koneksi->prepare($sql);
    $stmt->execute([$id]);
    $kerugian = $stmt->fetch();
    
    if (!$kerugian) {
        redirect_with_message("../dashboard.php", "error", "Data kerugian tidak ditemukan.");
    }
    
    // Delete kerugian record
    $sql_delete = "DELETE FROM kerugian_investasi WHERE id = ?";
    $stmt_delete = $koneksi->prepare($sql_delete);
    
    if ($stmt_delete->execute([$id])) {
        // Delete file if exists
        if (!empty($kerugian['bukti_file'])) {
            $file_path = UPLOAD_DIR_KERUGIAN . $kerugian['bukti_file'];
            if (file_exists($file_path)) {
                unlink($file_path);
                log_security_event("FILE_DELETED", "Kerugian ID: $id, File: " . $kerugian['bukti_file']);
            }
        }
        
        // Log the action
        log_security_event("KERUGIAN_DELETED", "ID: $id, User: " . $_SESSION['username']);
        
        // Redirect with success message
        redirect_with_message("../dashboard.php", "success", "Kerugian berhasil dihapus.");
    } else {
        redirect_with_message("../dashboard.php", "error", "Gagal menghapus kerugian.");
    }
    
} catch (Exception $e) {
    error_log("Delete Kerugian Error: " . $e->getMessage());
    log_security_event("DELETE_KERUGIAN_ERROR", "ID: $id, Error: " . $e->getMessage());
    redirect_with_message("../dashboard.php", "error", "Gagal menghapus kerugian: " . $e->getMessage());
}
?>