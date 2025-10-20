<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * Delete Investment
 * Menghapus investasi beserta file bukti dari JSON
 */

session_start();
require_once "../config/koneksi.php";

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

// Validasi ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect_with_message("../dashboard.php", "error", "ID investasi tidak valid.");
    exit;
}

$id = (int)$_GET['id'];

try {
    // Ambil data investasi untuk mendapatkan nama file bukti
    $sql = "SELECT * FROM investasi WHERE id = ?";
    $stmt = $koneksi->prepare($sql);
    $stmt->execute([$id]);
    $investasi = $stmt->fetch();

    if (!$investasi) {
        redirect_with_message("../dashboard.php", "error", "Data investasi tidak ditemukan.");
        exit;
    }

    // Mulai transaksi
    $koneksi->beginTransaction();

    try {
        // Hapus data keuntungan terkait
        $sql_delete_keuntungan = "DELETE FROM keuntungan_investasi WHERE investasi_id = ?";
        $stmt_keuntungan = $koneksi->prepare($sql_delete_keuntungan);
        $stmt_keuntungan->execute([$id]);

        // Hapus data kerugian terkait
        $sql_delete_kerugian = "DELETE FROM kerugian_investasi WHERE investasi_id = ?";
        $stmt_kerugian = $koneksi->prepare($sql_delete_kerugian);
        $stmt_kerugian->execute([$id]);

        // Hapus data investasi
        $sql_delete = "DELETE FROM investasi WHERE id = ?";
        $stmt_delete = $koneksi->prepare($sql_delete);
        $stmt_delete->execute([$id]);

        // Komit transaksi
        $koneksi->commit();

        // Hapus file bukti dari JSON jika ada
        if (!empty($investasi['bukti_file'])) {
            try {
                delete_file($investasi['bukti_file'], JSON_FILE_INVESTASI);
                //log_security_event("FILE_DELETED", "Investasi ID: $id, File: " . $investasi['bukti_file']);
            } catch (Exception $e) {
                // Log saja, tidak mengganggu proses delete
                //log_security_event("FILE_DELETE_FAILED", "Investasi ID: $id, Error: " . $e->getMessage());
            }
        }

        // Log aksi
        //log_security_event("INVESTASI_DELETED", "ID: $id, User: " . $_SESSION['username']);

        // Redirect sukses
        redirect_with_message("../dashboard.php", "success", "✅ Investasi berhasil dihapus beserta data terkait.");

    } catch (Exception $e) {
        // Rollback jika ada kesalahan
        $koneksi->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Delete Investasi Error: " . $e->getMessage());
    log_security_event("DELETE_ERROR", "ID: $id, Error: " . $e->getMessage());
    redirect_with_message("../dashboard.php", "error", "❌ Gagal menghapus investasi: " . $e->getMessage());
}
?>