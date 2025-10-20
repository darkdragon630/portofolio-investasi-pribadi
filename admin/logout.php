<?php
// Mulai session
session_start();

// Hapus semua data session
$_SESSION = [];

// hapus cookie session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Hancurkan session
session_destroy();

// Hindari caching halaman setelah logout
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

// Redirect ke halaman login
header("Location: admin/auth.php");
exit;
?>