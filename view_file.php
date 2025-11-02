<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * File Viewer - Display/Download Files from Database
 * FIXED VERSION: Handles LONGBLOB correctly
 */

require_once 'config/koneksi.php';

// Disable any output before headers
ini_set('display_errors', 0);
error_reporting(0);

// Start output buffering to catch any accidental output
ob_start();

try {
    // Validate parameters
    if (!isset($_GET['type']) || !isset($_GET['id'])) {
        throw new Exception('Missing required parameters');
    }

    $type = $_GET['type'];
    $id = (int)$_GET['id'];
    $download = isset($_GET['download']) && $_GET['download'] == '1';

    error_log("=== VIEW_FILE START ===");
    error_log("Type: $type, ID: $id, Download: " . ($download ? 'YES' : 'NO'));

    // Validate type
    $valid_types = ['investasi', 'keuntungan', 'kerugian'];
    if (!in_array($type, $valid_types)) {
        throw new Exception('Invalid file type');
    }

    // Determine table and ID column
    $table_map = [
        'investasi' => ['table' => 'investasi', 'id_col' => 'id'],
        'keuntungan' => ['table' => 'keuntungan_investasi', 'id_col' => 'id'],
        'kerugian' => ['table' => 'kerugian_investasi', 'id_col' => 'id']
    ];

    $config = $table_map[$type];

    // Query database
    $sql = "SELECT bukti_file FROM {$config['table']} WHERE {$config['id_col']} = ?";
    $stmt = $koneksi->prepare($sql);
    
    if (!$stmt) {
        error_log("Prepare failed: " . implode(' - ', $koneksi->errorInfo()));
        throw new Exception('Database query failed');
    }

    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['bukti_file'])) {
        error_log("File not found for $type ID $id");
        throw new Exception('File not found');
    }

    $bukti_file = $row['bukti_file'];
    
    error_log("Bukti file type: " . gettype($bukti_file));
    error_log("Is resource: " . (is_resource($bukti_file) ? 'YES' : 'NO'));
    
    // Handle resource type (PDO might return LONGBLOB as resource)
    if (is_resource($bukti_file)) {
        error_log("Converting resource to string...");
        $bukti_file = stream_get_contents($bukti_file);
    }
    
    error_log("Bukti file length: " . strlen($bukti_file));
    error_log("First 100 chars (hex): " . bin2hex(substr($bukti_file, 0, 100)));

    // Parse file using helper from koneksi.php
    $file_info = parse_bukti_file($bukti_file);

    if (!$file_info) {
        error_log("Failed to parse bukti_file");
        throw new Exception('Invalid file format');
    }

    error_log("File parsed successfully:");
    error_log("  Original name: " . $file_info['original_name']);
    error_log("  Extension: " . $file_info['extension']);
    error_log("  Size: " . $file_info['size']);
    error_log("  MIME: " . $file_info['mime_type']);

    // Decode base64 data
    $file_content = base64_decode($file_info['base64_data']);
    
    if ($file_content === false) {
        error_log("Base64 decode failed");
        throw new Exception('Failed to decode file data');
    }

    $content_length = strlen($file_content);
    error_log("Decoded content length: " . $content_length);

    // Verify decoded size matches metadata
    if ($content_length != $file_info['size']) {
        error_log("WARNING: Size mismatch! Expected: {$file_info['size']}, Got: $content_length");
    }

    // Clear any accidental output
    ob_end_clean();

    // Set headers
    header('Content-Type: ' . $file_info['mime_type']);
    header('Content-Length: ' . $content_length);
    
    if ($download) {
        header('Content-Disposition: attachment; filename="' . $file_info['original_name'] . '"');
    } else {
        header('Content-Disposition: inline; filename="' . $file_info['original_name'] . '"');
    }
    
    // Cache headers for better performance
    header('Cache-Control: public, max-age=3600');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', strtotime($file_info['uploaded_at'])) . ' GMT');
    
    // Prevent compression for binary files
    if (function_exists('apache_setenv')) {
        apache_setenv('no-gzip', '1');
    }
    ini_set('zlib.output_compression', 'Off');

    error_log("Headers sent, outputting " . $content_length . " bytes");

    // Output file content
    echo $file_content;
    
    error_log("=== VIEW_FILE END (SUCCESS) ===");
    exit;

} catch (Exception $e) {
    // Clear any previous output
    ob_end_clean();
    
    error_log("=== VIEW_FILE ERROR ===");
    error_log("Error: " . $e->getMessage());
    error_log("Stack: " . $e->getTraceAsString());
    
    // Return error image or message
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>File Not Found</title>
        <style>
            body {
                font-family: 'Arial', sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0;
                color: white;
            }
            .error-container {
                text-align: center;
                background: rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(10px);
                padding: 60px 40px;
                border-radius: 20px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
                max-width: 500px;
            }
            .error-icon {
                font-size: 80px;
                margin-bottom: 20px;
                opacity: 0.9;
            }
            h1 {
                margin: 0 0 10px 0;
                font-size: 32px;
                font-weight: 700;
            }
            p {
                margin: 0;
                font-size: 16px;
                opacity: 0.9;
                line-height: 1.6;
            }
            .error-code {
                margin-top: 20px;
                padding: 15px;
                background: rgba(0, 0, 0, 0.2);
                border-radius: 10px;
                font-size: 14px;
                font-family: 'Courier New', monospace;
            }
            .btn-back {
                display: inline-block;
                margin-top: 30px;
                padding: 12px 30px;
                background: white;
                color: #667eea;
                text-decoration: none;
                border-radius: 25px;
                font-weight: 600;
                transition: transform 0.3s;
            }
            .btn-back:hover {
                transform: translateY(-2px);
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">📄❌</div>
            <h1>File Tidak Ditemukan</h1>
            <p>File yang Anda cari tidak dapat ditemukan atau sudah tidak tersedia.</p>
            <div class="error-code">
                Error: <?= htmlspecialchars($e->getMessage()) ?>
            </div>
            <a href="javascript:history.back()" class="btn-back">← Kembali</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
