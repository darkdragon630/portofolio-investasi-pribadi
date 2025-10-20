<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * AJAX Endpoint - Get Investment Detail (Database Storage)
 */

require_once 'config/koneksi.php';

header('Content-Type: application/json');

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

try {
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
    $stmt_investment->execute([$id]);
    $investment = $stmt_investment->fetch();
    
    if (!$investment) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Investment not found'
        ]);
        exit;
    }
    
    // Get bukti file data from DATABASE
    $bukti_data = null;
    if ($investment['bukti_file']) {
        $file_info = parse_bukti_file($investment['bukti_file']);
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
                'is_image' => in_array($file_info['extension'], ['jpg', 'jpeg', 'png']),
                'is_pdf' => $file_info['extension'] === 'pdf'
            ];
        }
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
    $stmt_keuntungan->execute([$id]);
    $keuntungan_list = $stmt_keuntungan->fetchAll();
    
    // Get all kerugian for this investment
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
    ";
    
    $stmt_kerugian = $koneksi->prepare($sql_kerugian);
    $stmt_kerugian->execute([$id]);
    $kerugian_list = $stmt_kerugian->fetchAll();
    
    // Calculate totals
    $total_keuntungan = 0;
    foreach ($keuntungan_list as $k) {
        $total_keuntungan += $k['jumlah_keuntungan'];
    }
    
    $total_kerugian = 0;
    foreach ($kerugian_list as $k) {
        $total_kerugian += $k['jumlah_kerugian'];
    }
    
    $nilai_sekarang = $investment['modal_investasi'] + $total_keuntungan - $total_kerugian;
    $roi_persen = $investment['modal_investasi'] > 0 
        ? (($total_keuntungan - $total_kerugian) / $investment['modal_investasi'] * 100) 
        : 0;
    
    // Format data for response
    $response_data = [
        'id' => $investment['id'],
        'judul_investasi' => $investment['judul_investasi'],
        'deskripsi' => $investment['deskripsi'],
        'modal_investasi' => $investment['modal_investasi'],
        'modal_investasi_formatted' => format_currency($investment['modal_investasi']),
        'total_keuntungan' => $total_keuntungan,
        'total_keuntungan_formatted' => format_currency($total_keuntungan),
        'total_kerugian' => $total_kerugian,
        'total_kerugian_formatted' => format_currency($total_kerugian),
        'nilai_sekarang' => $nilai_sekarang,
        'nilai_sekarang_formatted' => format_currency($nilai_sekarang),
        'roi_persen' => number_format($roi_persen, 2),
        'tanggal_investasi' => $investment['tanggal_investasi'],
        'tanggal_investasi_formatted' => date('d F Y', strtotime($investment['tanggal_investasi'])),
        'nama_kategori' => $investment['nama_kategori'],
        'has_bukti' => !empty($investment['bukti_file']),
        'bukti_data' => $bukti_data,
        'keuntungan' => [],
        'kerugian' => []
    ];
    
    // Format keuntungan with bukti data from DATABASE
    foreach ($keuntungan_list as $k) {
        $keuntungan_bukti = null;
        if ($k['bukti_file']) {
            $file_info = parse_bukti_file($k['bukti_file']);
            if ($file_info) {
                $keuntungan_bukti = [
                    'original_name' => $file_info['original_name'],
                    'extension' => $file_info['extension'],
                    'size_formatted' => format_file_size($file_info['size']),
                    'preview_url' => 'view_file.php?type=keuntungan&id=' . $k['id'],
                    'download_url' => 'view_file.php?type=keuntungan&id=' . $k['id'] . '&download=1',
                    'is_image' => in_array($file_info['extension'], ['jpg', 'jpeg', 'png']),
                    'is_pdf' => $file_info['extension'] === 'pdf'
                ];
            }
        }
        
        $response_data['keuntungan'][] = [
            'id' => $k['id'],
            'judul_keuntungan' => $k['judul_keuntungan'],
            'jumlah_keuntungan' => $k['jumlah_keuntungan'],
            'jumlah_keuntungan_formatted' => format_currency($k['jumlah_keuntungan']),
            'persentase_keuntungan' => $k['persentase_keuntungan'],
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
    
    // Format kerugian with bukti data from DATABASE
    foreach ($kerugian_list as $k) {
        $kerugian_bukti = null;
        if ($k['bukti_file']) {
            $file_info = parse_bukti_file($k['bukti_file']);
            if ($file_info) {
                $kerugian_bukti = [
                    'original_name' => $file_info['original_name'],
                    'extension' => $file_info['extension'],
                    'size_formatted' => format_file_size($file_info['size']),
                    'preview_url' => 'view_file.php?type=kerugian&id=' . $k['id'],
                    'download_url' => 'view_file.php?type=kerugian&id=' . $k['id'] . '&download=1',
                    'is_image' => in_array($file_info['extension'], ['jpg', 'jpeg', 'png']),
                    'is_pdf' => $file_info['extension'] === 'pdf'
                ];
            }
        }
        
        $response_data['kerugian'][] = [
            'id' => $k['id'],
            'judul_kerugian' => $k['judul_kerugian'],
            'jumlah_kerugian' => $k['jumlah_kerugian'],
            'jumlah_kerugian_formatted' => format_currency($k['jumlah_kerugian']),
            'persentase_kerugian' => $k['persentase_kerugian'],
            'tanggal_kerugian' => $k['tanggal_kerugian'],
            'tanggal_kerugian_formatted' => date('d M Y', strtotime($k['tanggal_kerugian'])),
            'sumber_kerugian' => $k['sumber_kerugian'],
            'sumber_kerugian_formatted' => ucwords(str_replace('_', ' ', $k['sumber_kerugian'])),
            'status' => $k['status'],
            'status_formatted' => ucfirst($k['status']),
            'has_bukti' => !empty($k['bukti_file']),
            'bukti_data' => $kerugian_bukti
        ];
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'investment' => $response_data
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Log error
    error_log("Get Investment Detail Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage() // Remove in production
    ]);
}

// Helper function to format file size
function format_file_size($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}
?>