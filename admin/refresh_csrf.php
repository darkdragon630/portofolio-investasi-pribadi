<?php
// refresh_csrf.php
session_start();
header('Content-Type: application/json');

// =========================
// KONFIGURASI
// =========================
define('RATE_LIMIT_WINDOW', 60); // detik
define('RATE_LIMIT_MAX', 10);     // maksimal request per window
define('LOG_FILE', __DIR__ . '/../logs/security.log');

/* =========================
   FUNGSIONAL CSRF
========================= */
function generate_csrf_token() {
    return bin2hex(random_bytes(32)); // 32-byte = 64 karakter hex
}

/* =========================
   LOGGING SECURITY
========================= */
function log_security_event($event, $details = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $log_entry = date('Y-m-d H:i:s') . " | $ip | $event | $details" . PHP_EOL;
    error_log($log_entry, 3, LOG_FILE);
}

/* =========================
   RESPON JSON
========================= */
function respond($status_code, $data) {
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

/* =========================
   PENGECEKAN METODE
========================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_security_event("CSRF_REFRESH_METHOD_NOT_ALLOWED", "Method: {$_SERVER['REQUEST_METHOD']}");
    respond(405, ['error' => 'Method not allowed. Gunakan POST.']);
}

/* =========================
   RATE LIMITING PER IP
========================= */
$ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
$current_time = time();
$rate_key = "csrf_refresh_$ip";

if (!isset($_SESSION[$rate_key])) {
    $_SESSION[$rate_key] = ['count' => 1, 'time' => $current_time];
} else {
    $rate_data = $_SESSION[$rate_key];

    // Reset counter jika lebih dari window
    if ($current_time - $rate_data['time'] > RATE_LIMIT_WINDOW) {
        $_SESSION[$rate_key] = ['count' => 1, 'time' => $current_time];
    } else {
        $_SESSION[$rate_key]['count']++;
        if ($_SESSION[$rate_key]['count'] > RATE_LIMIT_MAX) {
            log_security_event("CSRF_REFRESH_RATE_LIMIT", "IP: $ip, Count: " . $_SESSION[$rate_key]['count']);
            respond(429, ['error' => 'Rate limit exceeded. Coba lagi nanti.']);
        }
    }
}

/* =========================
   GENERATE TOKEN BARU
========================= */
try {
    $_SESSION['csrf_token'] = generate_csrf_token();
    log_security_event("CSRF_TOKEN_REFRESHED", "IP: $ip");

    respond(200, [
        'success' => true,
        'token' => $_SESSION['csrf_token']
    ]);

} catch (Exception $e) {
    log_security_event("CSRF_REFRESH_ERROR", $e->getMessage());
    respond(500, ['error' => 'Internal server error']);
}
?>