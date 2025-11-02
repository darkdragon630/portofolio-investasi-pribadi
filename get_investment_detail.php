<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * AJAX Endpoint - Get Investment Detail (FIXED VERSION)
 * Fixed: JSON parsing, bukti file handling, error handling
 */

require_once 'config/koneksi.php';

header('Content-Type: application/json; charset=utf-8');

// Disable HTML error output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Increase memory limit for large files
ini_set('memory_limit', '256M');

// Start output buffering to catch any accidental output
ob_start();

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method not allowed', 405);
    }

    // Validate ID parameter
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid investment ID', 400);
    }

    $id = (int)$_GET['id'];

    error_log("=== GET_INVESTMENT_DETAIL START (ID: $id) ===");
    error_log("Request URI: " . $_SERVER['REQUEST_URI']);
    error_log("PHP Version: " . PHP_VERSION);

    // Check database connection
    if (!$koneksi) {
        throw new Exception("Database connection failed");
    }

    // Get investment detail
    $sql_investment = "
        SELECT 
            i.id,
            i.judul_investasi,
            i.deskripsi,
            i.jumlah as modal_investasi,
            i.tanggal_investasi,
            i.bukti_file,
            i.status,
            k.nama_kategori,
            k.id as kategori_id
        FROM investasi i
        JOIN kategori k ON i.kategori_id = k.id
        WHERE i.id = ?
    ";
    
    $stmt_investment = $koneksi->prepare($sql_investment);
    if (!$stmt_investment) {
        throw new Exception("Prepare failed: " . $koneksi->errorInfo()[2]);
    }
    
    $stmt_investment->execute([$id]);
    $investment = $stmt_investment->fetch(PDO::FETCH_ASSOC);
    
    if (!$investment) {
        throw new Exception("Investment not found", 404);
    }

    error_log("✓ Investment found: " . $investment['judul_investasi']);
    error_log("  Status: " . ($investment['status'] ?? 'NULL'));
    error_log("  bukti_file type: " . gettype($investment['bukti_file']));
    error_log("  bukti_file is_null: " . (is_null($investment['bukti_file']) ? 'YES' : 'NO'));
    error_log("  bukti_file length: " . (is_string($investment['bukti_file']) ? strlen($investment['bukti_file']) : 'N/A'));
    
    // Parse investment bukti file using helper function
    $bukti_data = get_safe_bukti_data($investment['bukti_file'], 'investasi', $investment['id']);
    
    // Get all keuntungan for this investment
    $sql_keuntungan = "
        SELECT 
            id,
            judul_keuntungan,
            jumlah_keuntungan,
            persentase_keuntungan,
            tanggal_keuntungan,
            sumber_keuntungan,
            bukti_file,
            status
        FROM keuntungan_investasi
        WHERE investasi_id = ?
        ORDER BY tanggal_keuntungan DESC
    ";
    
    $stmt_keuntungan = $koneksi->prepare($sql_keuntungan);
    $stmt_keuntungan->execute([$id]);
    $keuntungan_list = $stmt_keuntungan->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("✓ Keuntungan count: " . count($keuntungan_list));
    
    // Get only LATEST kerugian for this investment
    $sql_kerugian = "
        SELECT 
            id,
            judul_kerugian,
            jumlah_kerugian,
            persentase_kerugian,
            tanggal_kerugian,
            sumber_kerugian,
            bukti_file,
            status
        FROM kerugian_investasi
        WHERE investasi_id = ?
        ORDER BY tanggal_kerugian DESC
        LIMIT 1
    ";
    
    $stmt_kerugian = $koneksi->prepare($sql_kerugian);
    $stmt_kerugian->execute([$id]);
    $kerugian_terbaru = $stmt_kerugian->fetch(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $total_keuntungan = 0;
    foreach ($keuntungan_list as $k) {
        $total_keuntungan += $k['jumlah_keuntungan'];
    }
    
    $total_kerugian = 0;
    if ($kerugian_terbaru) {
        $total_kerugian = $kerugian_terbaru['jumlah_kerugian'];
    }
    
    $nilai_sekarang = $investment['modal_investasi'] + $total_keuntungan - $total_kerugian;
    $roi_persen = $investment['modal_investasi'] > 0 
        ? (($nilai_sekarang - $investment['modal_investasi']) / $investment['modal_investasi'] * 100) 
        : 0;
    
    // Format data for response
    $response_data = [
        'id' => (int)$investment['id'],
        'judul_investasi' => $investment['judul_investasi'],
        'deskripsi' => $investment['deskripsi'] ?? '',
        'modal_investasi' => (float)$investment['modal_investasi'],
        'modal_investasi_formatted' => 'Rp ' . number_format($investment['modal_investasi'], 2, ',', '.'),
        'total_keuntungan' => (float)$total_keuntungan,
        'total_keuntungan_formatted' => 'Rp ' . number_format($total_keuntungan, 2, ',', '.'),
        'total_kerugian' => (float)$total_kerugian,
        'total_kerugian_formatted' => 'Rp ' . number_format($total_kerugian, 2, ',', '.'),
        'nilai_sekarang' => (float)$nilai_sekarang,
        'nilai_sekarang_formatted' => 'Rp ' . number_format($nilai_sekarang, 2, ',', '.'),
        'roi_persen' => round($roi_persen, 2),
        'roi_persen_formatted' => number_format($roi_persen, 2) . '%',
        'tanggal_investasi' => $investment['tanggal_investasi'],
        'tanggal_investasi_formatted' => date('d F Y', strtotime($investment['tanggal_investasi'])),
        'nama_kategori' => $investment['nama_kategori'],
        'kategori_id' => (int)$investment['kategori_id'],
        'status' => $investment['status'] ?? 'aktif',
        'has_bukti' => !empty($investment['bukti_file']),
        'bukti_data' => $bukti_data,
        'keuntungan' => [],
        'kerugian_terbaru' => null,
        'statistics' => [
            'total_transactions' => count($keuntungan_list) + ($kerugian_terbaru ? 1 : 0),
            'profit_count' => count($keuntungan_list),
            'loss_count' => $kerugian_terbaru ? 1 : 0,
            'net_profit' => $total_keuntungan - $total_kerugian,
            'net_profit_formatted' => 'Rp ' . number_format($total_keuntungan - $total_kerugian, 2, ',', '.')
        ]
    ];
    
    // Format keuntungan with bukti data
    foreach ($keuntungan_list as $k) {
        $keuntungan_bukti = get_safe_bukti_data($k['bukti_file'], 'keuntungan', $k['id']);
        
        $response_data['keuntungan'][] = [
            'id' => (int)$k['id'],
            'judul_keuntungan' => $k['judul_keuntungan'],
            'jumlah_keuntungan' => (float)$k['jumlah_keuntungan'],
            'jumlah_keuntungan_formatted' => 'Rp ' . number_format($k['jumlah_keuntungan'], 2, ',', '.'),
            'persentase_keuntungan' => $k['persentase_keuntungan'] ? (float)$k['persentase_keuntungan'] : null,
            'persentase_keuntungan_formatted' => $k['persentase_keuntungan'] ? number_format($k['persentase_keuntungan'], 2) . '%' : '-',
            'tanggal_keuntungan' => $k['tanggal_keuntungan'],
            'tanggal_keuntungan_formatted' => date('d M Y', strtotime($k['tanggal_keuntungan'])),
            'sumber_keuntungan' => $k['sumber_keuntungan'],
            'sumber_keuntungan_formatted' => ucwords(str_replace('_', ' ', $k['sumber_keuntungan'])),
            'status' => $k['status'],
            'status_formatted' => ucfirst($k['status']),
            'has_bukti' => !empty($k['bukti_file']),
            'bukti_data' => $keuntungan_bukti
        ];
    }
    
    // Format kerugian terbaru
    if ($kerugian_terbaru) {
        $kerugian_bukti = get_safe_bukti_data($kerugian_terbaru['bukti_file'], 'kerugian', $kerugian_terbaru['id']);
        
        $response_data['kerugian_terbaru'] = [
            'id' => (int)$kerugian_terbaru['id'],
            'judul_kerugian' => $kerugian_terbaru['judul_kerugian'],
            'jumlah_kerugian' => (float)$kerugian_terbaru['jumlah_kerugian'],
            'jumlah_kerugian_formatted' => 'Rp ' . number_format($kerugian_terbaru['jumlah_kerugian'], 2, ',', '.'),
            'persentase_kerugian' => $kerugian_terbaru['persentase_kerugian'] ? (float)$kerugian_terbaru['persentase_kerugian'] : null,
            'persentase_kerugian_formatted' => $kerugian_terbaru['persentase_kerugian'] ? number_format($kerugian_terbaru['persentase_kerugian'], 2) . '%' : '-',
            'tanggal_kerugian' => $kerugian_terbaru['tanggal_kerugian'],
            'tanggal_kerugian_formatted' => date('d M Y', strtotime($kerugian_terbaru['tanggal_kerugian'])),
            'sumber_kerugian' => $kerugian_terbaru['sumber_kerugian'],
            'sumber_kerugian_formatted' => ucwords(str_replace('_', ' ', $kerugian_terbaru['sumber_kerugian'])),
            'status' => $kerugian_terbaru['status'],
            'status_formatted' => ucfirst($kerugian_terbaru['status']),
            'has_bukti' => !empty($kerugian_terbaru['bukti_file']),
            'bukti_data' => $kerugian_bukti
        ];
    }
    
    error_log("✓ Response prepared successfully");
    error_log("=== GET_INVESTMENT_DETAIL END (SUCCESS) ===");
    
    // Clear any accidental output
    ob_end_clean();
    
    // Add debug info in response (only in development)
    $debug_mode = false; // Set to true to enable debug output
    
    $response = [
        'success' => true,
        'investment' => $response_data
    ];
    
    if ($debug_mode) {
        $response['_debug'] = [
            'php_version' => PHP_VERSION,
            'time' => date('Y-m-d H:i:s'),
            'memory_usage' => memory_get_usage(true)
        ];
    }
    
    // Success response with proper encoding
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Log error
    error_log("✗ ERROR: " . $e->getMessage());
    error_log("=== GET_INVESTMENT_DETAIL END (ERROR) ===");
    
    // Clear any accidental output
    ob_end_clean();
    
    // Error response
    $code = $e->getCode() ?: 500;
    http_response_code(in_array($code, [400, 404, 405]) ? $code : 500);
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $code
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Helper function to safely parse bukti file data
 * Handles both old format (filename|...) and new format (metadata|||base64)
 * 
 * ⚠️ IMPORTANT: Returns ONLY metadata, NOT base64_data (to keep JSON response small)
 * 
 * @param string|null $bukti_file Raw bukti file data from database
 * @param string $type Type: 'investasi', 'keuntungan', 'kerugian'
 * @param int $id Record ID
 * @return array|null Bukti data array or null
 */
function get_safe_bukti_data($bukti_file, $type, $id) {
    if (empty($bukti_file)) {
        error_log("  → No bukti_file for $type ID $id");
        return null;
    }
    
    error_log("  → Parsing bukti for $type ID $id");
    error_log("    Data type: " . gettype($bukti_file));
    error_log("    Is string: " . (is_string($bukti_file) ? 'YES' : 'NO'));
    error_log("    Length: " . (is_string($bukti_file) ? strlen($bukti_file) : 'N/A'));
    
    try {
        // LONGBLOB might be returned as resource or string depending on PDO fetch mode
        // Convert to string if it's a resource
        if (is_resource($bukti_file)) {
            error_log("    → Converting resource to string");
            $bukti_file = stream_get_contents($bukti_file);
        }
        
        // Check if it's binary data (starts with JSON metadata)
        $first_100_chars = substr($bukti_file, 0, 100);
        error_log("    First 100 chars: " . bin2hex($first_100_chars));
        
        // Try new format first (metadata|||base64)
        if (strpos($bukti_file, '|||') !== false) {
            error_log("    → Detected new format (metadata|||base64)");
            $file_info = parse_bukti_file($bukti_file);
            
            if (!$file_info) {
                error_log("    ✗ parse_bukti_file returned null");
                return null;
            }
            
            $result = [
                'original_name' => $file_info['original_name'],
                'extension' => $file_info['extension'],
                'size' => (int)$file_info['size'],
                'size_formatted' => format_file_size($file_info['size']),
                'mime_type' => $file_info['mime_type'],
                'uploaded_at' => $file_info['uploaded_at'],
                'preview_url' => "view_file.php?type=$type&id=$id",
                'download_url' => "view_file.php?type=$type&id=$id&download=1",
                'is_image' => in_array(strtolower($file_info['extension']), ['jpg', 'jpeg', 'png', 'gif', 'webp']),
                'is_pdf' => strtolower($file_info['extension']) === 'pdf'
                // ⚠️ CRITICAL: DO NOT include 'base64_data' here to keep JSON response small
            ];
            
            error_log("    ✓ Parsed: " . $file_info['original_name'] . " (is_image: " . ($result['is_image'] ? 'YES' : 'NO') . ")");
            return $result;
        }
        
        // Try old format (filename|original_name|size|mime_type|timestamp)
        error_log("    → Trying old format (pipe-separated)");
        $parts = explode('|', $bukti_file);
        error_log("    → Parts count: " . count($parts));
        
        if (count($parts) >= 5) {
            $extension = strtolower(pathinfo($parts[1], PATHINFO_EXTENSION));
            
            $result = [
                'original_name' => $parts[1],
                'extension' => $extension,
                'size' => (int)$parts[2],
                'size_formatted' => format_file_size((int)$parts[2]),
                'mime_type' => $parts[3],
                'uploaded_at' => $parts[4],
                'preview_url' => "view_file.php?type=$type&id=$id",
                'download_url' => "view_file.php?type=$type&id=$id&download=1",
                'is_image' => in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']),
                'is_pdf' => $extension === 'pdf'
                // ⚠️ NO base64_data in old format either
            ];
            
            error_log("    ✓ Parsed (old format): " . $parts[1]);
            return $result;
        }
        
        error_log("    ✗ Unknown format");
        return null;
        
    } catch (Exception $e) {
        error_log("    ✗ Exception parsing bukti: " . $e->getMessage());
        error_log("    ✗ Stack: " . $e->getTraceAsString());
        return null;
    }
}

/**
 * Helper function to format file size
 */
function format_file_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}
