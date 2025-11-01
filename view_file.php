<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * AJAX Endpoint - Get Investment Detail (Database Storage)
 * Fixed Version with Enhanced Debugging
 */

require_once 'config/koneksi.php';

header('Content-Type: application/json');

// Enable error logging
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Validate ID parameter
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid investment ID'
    ]);
    exit;
}

$id = (int)$_GET['id'];

// Debug log
error_log("=== GET_INVESTMENT_DETAIL START (ID: $id) ===");

try {
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
            k.nama_kategori,
            k.id as kategori_id
        FROM investasi i
        JOIN kategori k ON i.kategori_id = k.id
        WHERE i.id = ?
    ";
    
    $stmt_investment = $koneksi->prepare($sql_investment);
    if (!$stmt_investment) {
        throw new Exception("Prepare failed: " . $koneksi->error);
    }
    
    $stmt_investment->execute([$id]);
    $investment = $stmt_investment->fetch();
    
    if (!$investment) {
        error_log("Investment not found for ID: $id");
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Investment not found'
        ]);
        exit;
    }

    error_log("✓ Investment found: " . $investment['judul_investasi']);
    error_log("  bukti_file raw: " . ($investment['bukti_file'] ?? 'NULL'));
    
    // Parse investment bukti file
    $bukti_data = null;
    if ($investment['bukti_file']) {
        error_log("→ Parsing investment bukti_file...");
        $file_info = get_bukti_metadata($investment['bukti_file']);
        if ($file_info) {
            $bukti_data = [
                'original_name' => $file_info['original_name'],
                'extension' => $file_info['extension'],
                'size' => $file_info['size'],
                'size_formatted' => format_file_size($file_info['size']),
                'mime_type' => $file_info['mime_type'],
                'uploaded_at' => $file_info['uploaded_at'],
                'preview_url' => 'view_file.php?type=investasi&id=' . $investment['id'],
                'download_url' => 'view_file.php?type=investasi&id=' . $investment['id'] . '&download=1',
                'is_image' => in_array($file_info['extension'], ['jpg', 'jpeg', 'png', 'gif', 'webp']),
                'is_pdf' => $file_info['extension'] === 'pdf'
            ];
            error_log("  ✓ Investment bukti parsed: " . $file_info['original_name']);
            error_log("  ✓ Is image: " . ($bukti_data['is_image'] ? 'YES' : 'NO'));
            error_log("  ✓ Preview URL: " . $bukti_data['preview_url']);
        } else {
            error_log("  ✗ Failed to parse investment bukti_file");
        }
    } else {
        error_log("  → No bukti_file for investment");
    }
    
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
    if (!$stmt_keuntungan) {
        throw new Exception("Prepare keuntungan failed: " . $koneksi->error);
    }
    
    $stmt_keuntungan->execute([$id]);
    $keuntungan_list = $stmt_keuntungan->fetchAll();
    
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
    if (!$stmt_kerugian) {
        throw new Exception("Prepare kerugian failed: " . $koneksi->error);
    }
    
    $stmt_kerugian->execute([$id]);
    $kerugian_terbaru = $stmt_kerugian->fetch();
    
    if ($kerugian_terbaru) {
        error_log("✓ Kerugian terbaru found: " . $kerugian_terbaru['judul_kerugian']);
        error_log("  bukti_file raw: " . ($kerugian_terbaru['bukti_file'] ?? 'NULL'));
    } else {
        error_log("→ No kerugian found");
    }
    
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
    
    error_log("✓ Calculations done:");
    error_log("  Total Keuntungan: " . $total_keuntungan);
    error_log("  Total Kerugian: " . $total_kerugian);
    error_log("  Nilai Sekarang: " . $nilai_sekarang);
    error_log("  ROI: " . round($roi_persen, 2) . "%");
    
    // Format data for response
    $response_data = [
        'id' => $investment['id'],
        'judul_investasi' => $investment['judul_investasi'],
        'deskripsi' => $investment['deskripsi'],
        'modal_investasi' => $investment['modal_investasi'],
        'modal_investasi_formatted' => 'Rp ' . number_format($investment['modal_investasi'], 2, ',', '.'),
        'total_keuntungan' => $total_keuntungan,
        'total_keuntungan_formatted' => 'Rp ' . number_format($total_keuntungan, 2, ',', '.'),
        'total_kerugian' => $total_kerugian,
        'total_kerugian_formatted' => 'Rp ' . number_format($total_kerugian, 2, ',', '.'),
        'nilai_sekarang' => $nilai_sekarang,
        'nilai_sekarang_formatted' => 'Rp ' . number_format($nilai_sekarang, 2, ',', '.'),
        'roi_persen' => round($roi_persen, 2),
        'roi_persen_formatted' => number_format($roi_persen, 2) . '%',
        'tanggal_investasi' => $investment['tanggal_investasi'],
        'tanggal_investasi_formatted' => date('d F Y', strtotime($investment['tanggal_investasi'])),
        'nama_kategori' => $investment['nama_kategori'],
        'kategori_id' => $investment['kategori_id'],
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
    error_log("→ Processing keuntungan bukti files...");
    foreach ($keuntungan_list as $index => $k) {
        $keuntungan_bukti = null;
        if ($k['bukti_file']) {
            error_log("  [" . ($index+1) . "] Parsing keuntungan bukti (ID: " . $k['id'] . ")");
            $file_info = get_bukti_metadata($k['bukti_file']);
            if ($file_info) {
                $keuntungan_bukti = [
                    'original_name' => $file_info['original_name'],
                    'extension' => $file_info['extension'],
                    'size' => $file_info['size'],
                    'size_formatted' => format_file_size($file_info['size']),
                    'mime_type' => $file_info['mime_type'],
                    'uploaded_at' => $file_info['uploaded_at'],
                    'preview_url' => 'view_file.php?type=keuntungan&id=' . $k['id'],
                    'download_url' => 'view_file.php?type=keuntungan&id=' . $k['id'] . '&download=1',
                    'is_image' => in_array($file_info['extension'], ['jpg', 'jpeg', 'png', 'gif', 'webp']),
                    'is_pdf' => $file_info['extension'] === 'pdf'
                ];
                error_log("      ✓ Parsed: " . $file_info['original_name']);
            } else {
                error_log("      ✗ Failed to parse");
            }
        }
        
        $response_data['keuntungan'][] = [
            'id' => $k['id'],
            'judul_keuntungan' => $k['judul_keuntungan'],
            'jumlah_keuntungan' => $k['jumlah_keuntungan'],
            'jumlah_keuntungan_formatted' => 'Rp ' . number_format($k['jumlah_keuntungan'], 2, ',', '.'),
            'persentase_keuntungan' => $k['persentase_keuntungan'],
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
        error_log("→ Processing kerugian terbaru bukti...");
        $kerugian_bukti = null;
        if ($kerugian_terbaru['bukti_file']) {
            error_log("  Parsing kerugian bukti (ID: " . $kerugian_terbaru['id'] . ")");
            $file_info = get_bukti_metadata($kerugian_terbaru['bukti_file']);
            if ($file_info) {
                $kerugian_bukti = [
                    'original_name' => $file_info['original_name'],
                    'extension' => $file_info['extension'],
                    'size' => $file_info['size'],
                    'size_formatted' => format_file_size($file_info['size']),
                    'mime_type' => $file_info['mime_type'],
                    'uploaded_at' => $file_info['uploaded_at'],
                    'preview_url' => 'view_file.php?type=kerugian&id=' . $kerugian_terbaru['id'],
                    'download_url' => 'view_file.php?type=kerugian&id=' . $kerugian_terbaru['id'] . '&download=1',
                    'is_image' => in_array($file_info['extension'], ['jpg', 'jpeg', 'png', 'gif', 'webp']),
                    'is_pdf' => $file_info['extension'] === 'pdf'
                ];
                error_log("  ✓ Parsed: " . $file_info['original_name']);
            } else {
                error_log("  ✗ Failed to parse");
            }
        }
        
        $response_data['kerugian_terbaru'] = [
            'id' => $kerugian_terbaru['id'],
            'judul_kerugian' => $kerugian_terbaru['judul_kerugian'],
            'jumlah_kerugian' => $kerugian_terbaru['jumlah_kerugian'],
            'jumlah_kerugian_formatted' => 'Rp ' . number_format($kerugian_terbaru['jumlah_kerugian'], 2, ',', '.'),
            'persentase_kerugian' => $kerugian_terbaru['persentase_kerugian'],
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
    
    error_log("✓ Response data prepared successfully");
    error_log("  Investment has_bukti: " . ($response_data['has_bukti'] ? 'YES' : 'NO'));
    error_log("  Keuntungan count: " . count($response_data['keuntungan']));
    error_log("  Kerugian terbaru: " . ($response_data['kerugian_terbaru'] ? 'YES' : 'NO'));
    error_log("=== GET_INVESTMENT_DETAIL END (SUCCESS) ===");
    
    // Success response
    echo json_encode([
        'success' => true,
        'investment' => $response_data
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Log error
    error_log("✗ ERROR: " . $e->getMessage());
    error_log("  Stack trace: " . $e->getTraceAsString());
    error_log("=== GET_INVESTMENT_DETAIL END (ERROR) ===");
    
    // Error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
        'debug' => [
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ]
    ], JSON_PRETTY_PRINT);
}

/**
 * Helper function to parse bukti file data from database
 * Uses parse_bukti_file() from koneksi.php which handles base64 storage
 */
function get_bukti_metadata($bukti_file) {
    error_log("    get_bukti_metadata INPUT length: " . strlen($bukti_file));
    
    // Use the parse_bukti_file function from koneksi.php
    $file_info = parse_bukti_file($bukti_file);
    
    if (!$file_info) {
        error_log("    ✗ parse_bukti_file returned null");
        return null;
    }
    
    // Return metadata (without base64_data for API response)
    $result = [
        'original_name' => $file_info['original_name'],
        'extension' => $file_info['extension'],
        'size' => $file_info['size'],
        'mime_type' => $file_info['mime_type'],
        'uploaded_at' => $file_info['uploaded_at']
    ];
    
    error_log("    ✓ parse_bukti_file SUCCESS");
    return $result;
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
?>
