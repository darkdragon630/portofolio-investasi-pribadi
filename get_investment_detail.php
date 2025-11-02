<?php
/**
 * SAZEN Investment Portfolio Manager v3.1
 * AJAX Endpoint - Get Investment Detail (FIXED for ID 5)
 * 
 * FIXES APPLIED:
 * 1. Enhanced output buffering control
 * 2. Added strict error handling for JSON encoding
 * 3. Detect and log corrupt data
 * 4. Prevent premature script termination
 */

require_once 'config/koneksi.php';

// CRITICAL: Prevent ANY output before JSON
ob_start();

// Disable display_errors to prevent HTML error output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set proper headers IMMEDIATELY
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Increase limits for large binary data
ini_set('memory_limit', '256M');
ini_set('max_execution_time', '120');

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
    
    // CRITICAL FIX: Check if bukti_file is corrupt
    $bukti_file_raw = $investment['bukti_file'];
    $bukti_file_size = is_string($bukti_file_raw) ? strlen($bukti_file_raw) : 0;
    error_log("  bukti_file size: " . $bukti_file_size . " bytes");
    
    // Detect corrupt binary data
    if ($bukti_file_size > 0 && $bukti_file_size < 50) {
        error_log("  ⚠ WARNING: Suspiciously small bukti_file");
    }
    
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
    
    // CRITICAL FIX: Test JSON encoding BEFORE output
    $response = [
        'success' => true,
        'investment' => $response_data
    ];
    
    $json_test = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if ($json_test === false) {
        $json_error = json_last_error_msg();
        error_log("✗ JSON ENCODING FAILED: " . $json_error);
        throw new Exception("JSON encoding failed: " . $json_error);
    }
    
    $json_length = strlen($json_test);
    error_log("✓ JSON encoded successfully: " . $json_length . " bytes");
    
    // Clear output buffer and send response
    ob_end_clean();
    
    // Send JSON response with proper length header
    header('Content-Length: ' . $json_length);
    echo $json_test;
    
    error_log("=== GET_INVESTMENT_DETAIL END (SUCCESS) ===");
    exit(0); // Explicit success exit
    
} catch (Exception $e) {
    // Log error
    error_log("✗ ERROR: " . $e->getMessage());
    error_log("  Stack: " . $e->getTraceAsString());
    error_log("=== GET_INVESTMENT_DETAIL END (ERROR) ===");
    
    // Clear any accidental output
    ob_end_clean();
    
    // Error response
    $code = $e->getCode() ?: 500;
    http_response_code(in_array($code, [400, 404, 405]) ? $code : 500);
    
    $error_response = [
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $code
    ];
    
    $error_json = json_encode($error_response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    header('Content-Length: ' . strlen($error_json));
    echo $error_json;
    exit(1);
}

/**
 * Helper function to safely parse bukti file data
 */
function get_safe_bukti_data($bukti_file, $type, $id) {
    if (empty($bukti_file)) {
        error_log("  → No bukti_file for $type ID $id");
        return null;
    }
    
    error_log("  → Parsing bukti for $type ID $id");
    
    try {
        // Convert resource to string if needed
        if (is_resource($bukti_file)) {
            error_log("    → Converting resource to string");
            $bukti_file = stream_get_contents($bukti_file);
        }
        
        // Check for new format (metadata|||base64)
        if (strpos($bukti_file, '|||') !== false) {
            error_log("    → Detected new format");
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
            ];
            
            error_log("    ✓ Parsed: " . $file_info['original_name']);
            return $result;
        }
        
        // Try old format (pipe-separated)
        error_log("    → Trying old format");
        $parts = explode('|', $bukti_file);
        
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
            ];
            
            error_log("    ✓ Parsed (old format): " . $parts[1]);
            return $result;
        }
        
        error_log("    ✗ Unknown format, size: " . strlen($bukti_file));
        return null;
        
    } catch (Exception $e) {
        error_log("    ✗ Exception: " . $e->getMessage());
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
