<?php
/**
 * TAMBAHAN HELPER FUNCTIONS untuk config/koneksi.php
 * Tambahkan kode ini di bagian akhir file config/koneksi.php (sebelum ?>)
 */

// ==============================
// Flash Message Functions (TAMBAHAN)
// ==============================

/**
 * Set flash message (alternatif untuk redirect_with_message)
 */
if (!function_exists('set_flash_message')) {
    function set_flash_message($type, $message) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash_type'] = $type;
        $_SESSION['flash_message'] = $message;
    }
}

// ==============================
// Additional Format Functions
// ==============================

/**
 * Format angka biasa (tanpa desimal)
 */
if (!function_exists('format_number')) {
    function format_number($number) {
        return number_format($number, 0, ',', '.');
    }
}

/**
 * Format persentase
 */
if (!function_exists('format_percent')) {
    function format_percent($percent, $decimals = 2) {
        return number_format($percent, $decimals, ',', '.') . '%';
    }
}

/**
 * Format tanggal Indonesia
 */
if (!function_exists('format_date_indonesia')) {
    function format_date_indonesia($date, $show_time = false) {
        if (empty($date)) {
            return '-';
        }
        
        $timestamp = is_numeric($date) ? $date : strtotime($date);
        
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        
        $hari = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu'
        ];
        
        $day = date('d', $timestamp);
        $month = $bulan[(int)date('n', $timestamp)];
        $year = date('Y', $timestamp);
        
        $result = "$day $month $year";
        
        if ($show_time) {
            $time = date('H:i', $timestamp);
            $result .= " pukul $time";
        }
        
        return $result;
    }
}

// ==============================
// Validation Functions
// ==============================

/**
 * Validasi email
 */
if (!function_exists('validate_email')) {
    function validate_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

/**
 * Validasi angka
 */
if (!function_exists('validate_number')) {
    function validate_number($value, $min = null, $max = null) {
        if (!is_numeric($value)) {
            return false;
        }
        
        $num = (float)$value;
        
        if ($min !== null && $num < $min) {
            return false;
        }
        
        if ($max !== null && $num > $max) {
            return false;
        }
        
        return true;
    }
}

// ==============================
// Security Functions
// ==============================

/**
 * Generate random token
 */
if (!function_exists('generate_token')) {
    function generate_token($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
}

/**
 * Check if user is logged in
 */
if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

/**
 * Require login - redirect if not logged in
 */
if (!function_exists('require_login')) {
    function require_login($redirect_to = 'admin/auth.php') {
        if (!is_logged_in()) {
            header("Location: " . $redirect_to);
            exit;
        }
    }
}

/**
 * Get user data dari session
 */
if (!function_exists('get_user_data')) {
    function get_user_data($key = null) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if ($key === null) {
            return [
                'user_id' => $_SESSION['user_id'] ?? null,
                'username' => $_SESSION['username'] ?? null,
                'email' => $_SESSION['email'] ?? null
            ];
        }
        
        return $_SESSION[$key] ?? null;
    }
}

/**
 * Log security event (opsional untuk audit)
 */
if (!function_exists('log_security_event')) {
    function log_security_event($event_type, $description, $ip_address = null) {
        if ($ip_address === null) {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        }
        
        $log_dir = __DIR__ . '/../logs';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $log_file = $log_dir . '/security_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $user_id = $_SESSION['user_id'] ?? 'Guest';
        
        $log_entry = "[{$timestamp}] [{$event_type}] User: {$user_id} | IP: {$ip_address} | {$description}\n";
        
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
}

// ==============================
// Calculation Functions
// ==============================

/**
 * Calculate ROI (Return on Investment)
 */
if (!function_exists('calculate_roi')) {
    function calculate_roi($initial_investment, $current_value) {
        if ($initial_investment <= 0) {
            return 0;
        }
        
        return (($current_value - $initial_investment) / $initial_investment) * 100;
    }
}

/**
 * Get color class based on value
 */
if (!function_exists('get_value_color_class')) {
    function get_value_color_class($value) {
        if ($value > 0) {
            return 'positive';
        } elseif ($value < 0) {
            return 'negative';
        }
        return 'neutral';
    }
}

// ==============================
// String Functions
// ==============================

/**
 * Truncate text
 */
if (!function_exists('truncate_text')) {
    function truncate_text($text, $length = 100, $suffix = '...') {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length) . $suffix;
    }
}

/**
 * Slug generator
 */
if (!function_exists('create_slug')) {
    function create_slug($text) {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        $text = trim($text, '-');
        return $text;
    }
}

// ==============================
// Pagination Functions
// ==============================

/**
 * Create pagination
 */
if (!function_exists('create_pagination')) {
    function create_pagination($total_records, $current_page = 1, $records_per_page = 10) {
        $total_pages = ceil($total_records / $records_per_page);
        $current_page = max(1, min($current_page, $total_pages));
        $offset = ($current_page - 1) * $records_per_page;
        
        return [
            'total_records' => $total_records,
            'total_pages' => $total_pages,
            'current_page' => $current_page,
            'records_per_page' => $records_per_page,
            'offset' => $offset,
            'has_previous' => $current_page > 1,
            'has_next' => $current_page < $total_pages,
            'first_page' => 1,
            'last_page' => $total_pages,
            'previous_page' => max(1, $current_page - 1),
            'next_page' => min($total_pages, $current_page + 1)
        ];
    }
}

// ==============================
// Debug Functions
// ==============================

/**
 * Debug print (hanya di development mode)
 */
if (!function_exists('debug_print')) {
    function debug_print($data, $die = false) {
        echo '<pre style="background: #1e293b; color: #f8fafc; padding: 15px; border: 1px solid #334155; border-radius: 8px; margin: 10px; font-family: monospace; overflow-x: auto;">';
        echo '<strong style="color: #fbbf24;">DEBUG OUTPUT:</strong><br><br>';
        print_r($data);
        echo '</pre>';
        
        if ($die) {
            die();
        }
    }
}

/**
 * Dump and die
 */
if (!function_exists('dd')) {
    function dd($data) {
        debug_print($data, true);
    }
}

// ==============================
// Array Helper Functions
// ==============================

/**
 * Get value from array with default
 */
if (!function_exists('array_get')) {
    function array_get($array, $key, $default = null) {
        return isset($array[$key]) ? $array[$key] : $default;
    }
}

/**
 * Check if array is associative
 */
if (!function_exists('is_assoc_array')) {
    function is_assoc_array($array) {
        if (!is_array($array) || empty($array)) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }
}

// ==============================
// Date/Time Helper Functions
// ==============================

/**
 * Get relative time (misal: 2 jam yang lalu)
 */
if (!function_exists('time_ago')) {
    function time_ago($datetime) {
        $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'baru saja';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' menit yang lalu';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' jam yang lalu';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' hari yang lalu';
        } else {
            return date('d M Y', $timestamp);
        }
    }
}

// ==============================
// HTTP Helper Functions
// ==============================

/**
 * Get current URL
 */
if (!function_exists('current_url')) {
    function current_url() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
}

/**
 * Get base URL
 */
if (!function_exists('base_url')) {
    function base_url($path = '') {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $base = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

/**
 * Redirect to URL
 */
if (!function_exists('redirect')) {
    function redirect($url, $status_code = 302) {
        header("Location: $url", true, $status_code);
        exit;
    }
}

// ==============================
// JSON Response Helper
// ==============================

/**
 * Send JSON response
 */
if (!function_exists('json_response')) {
    function json_response($data, $status_code = 200) {
        http_response_code($status_code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/**
 * Success JSON response
 */
if (!function_exists('json_success')) {
    function json_success($message, $data = null) {
        json_response([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }
}

/**
 * Error JSON response
 */
if (!function_exists('json_error')) {
    function json_error($message, $status_code = 400) {
        json_response([
            'success' => false,
            'message' => $message
        ], $status_code);
    }
}

// ==============================
// File Size Helper
// ==============================

/**
 * Format file size ke human readable
 */
if (!function_exists('format_file_size')) {
    function format_file_size($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// ==============================
// END OF ADDITIONAL HELPERS
// ==============================
