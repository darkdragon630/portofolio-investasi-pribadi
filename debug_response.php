<?php
/**
 * Debug Script - Check Raw Response for ID 5
 * Diagnose why JSON parsing fails
 */

require_once 'config/koneksi.php';

// Set high limits
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 120);
set_time_limit(120);

header('Content-Type: text/html; charset=utf-8');

$id = 5;

echo "<h1>🔍 Debugging Investment ID: $id - Raw Response Check</h1>";
echo "<style>
    body { font-family: monospace; padding: 20px; background: #1e293b; color: #e2e8f0; }
    h2 { color: #60a5fa; border-bottom: 2px solid #3b82f6; padding-bottom: 10px; margin-top: 30px; }
    .info { background: #334155; padding: 15px; border-radius: 8px; margin: 10px 0; }
    .error { background: #7f1d1d; border-left: 4px solid #ef4444; }
    .success { background: #064e3b; border-left: 4px solid #10b981; }
    .warning { background: #713f12; border-left: 4px solid #f59e0b; }
    pre { background: #0f172a; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 11px; max-height: 400px; white-space: pre-wrap; word-wrap: break-word; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    th, td { padding: 8px; text-align: left; border-bottom: 1px solid #475569; }
    th { background: #1e40af; color: white; }
    .btn { display: inline-block; padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; margin: 10px 5px; }
</style>";

try {
    echo "<h2>Step 1: Query Investment ID $id</h2>";
    
    $sql = "
        SELECT 
            i.id,
            i.judul_investasi,
            LENGTH(i.bukti_file) as bukti_size,
            k.nama_kategori
        FROM investasi i
        JOIN kategori k ON i.kategori_id = k.id
        WHERE i.id = ?
    ";
    
    $stmt = $koneksi->prepare($sql);
    $stmt->execute([$id]);
    $investment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$investment) {
        echo "<div class='info error'>Investment not found!</div>";
        exit;
    }
    
    echo "<div class='info success'>✓ Investment found: " . htmlspecialchars($investment['judul_investasi']) . "</div>";
    echo "<div class='info'>Bukti file size: " . number_format($investment['bukti_size']) . " bytes (" . round($investment['bukti_size'] / 1024 / 1024, 2) . " MB)</div>";
    
    // Check keuntungan
    echo "<h2>Step 2: Query Keuntungan</h2>";
    
    $sql_keun = "
        SELECT 
            id,
            judul_keuntungan,
            LENGTH(bukti_file) as bukti_size
        FROM keuntungan_investasi 
        WHERE investasi_id = ?
        ORDER BY tanggal_keuntungan DESC
    ";
    
    $stmt_keun = $koneksi->prepare($sql_keun);
    $stmt_keun->execute([$id]);
    $keuntungan_list = $stmt_keun->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='info'>Keuntungan count: " . count($keuntungan_list) . "</div>";
    
    $total_keuntungan_size = 0;
    if (count($keuntungan_list) > 0) {
        echo "<table>";
        echo "<tr><th>#</th><th>Judul</th><th>Bukti Size</th></tr>";
        foreach ($keuntungan_list as $idx => $k) {
            $size = $k['bukti_size'] ?? 0;
            $total_keuntungan_size += $size;
            $size_mb = round($size / 1024 / 1024, 2);
            $class = $size > 5000000 ? 'error' : ($size > 1000000 ? 'warning' : 'success');
            echo "<tr>";
            echo "<td>" . ($idx + 1) . "</td>";
            echo "<td>" . htmlspecialchars($k['judul_keuntungan']) . "</td>";
            echo "<td class='info $class'>" . number_format($size) . " bytes ($size_mb MB)</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check kerugian
    echo "<h2>Step 3: Query Kerugian</h2>";
    
    $sql_kerugian = "
        SELECT 
            id,
            judul_kerugian,
            LENGTH(bukti_file) as bukti_size
        FROM kerugian_investasi 
        WHERE investasi_id = ?
        ORDER BY tanggal_kerugian DESC
        LIMIT 1
    ";
    
    $stmt_kerugian = $koneksi->prepare($sql_kerugian);
    $stmt_kerugian->execute([$id]);
    $kerugian = $stmt_kerugian->fetch(PDO::FETCH_ASSOC);
    
    $total_kerugian_size = 0;
    if ($kerugian) {
        $size = $kerugian['bukti_size'] ?? 0;
        $total_kerugian_size = $size;
        $size_mb = round($size / 1024 / 1024, 2);
        $class = $size > 5000000 ? 'error' : ($size > 1000000 ? 'warning' : 'success');
        
        echo "<div class='info $class'>";
        echo "Kerugian terbaru: " . htmlspecialchars($kerugian['judul_kerugian']) . "<br>";
        echo "Bukti size: " . number_format($size) . " bytes ($size_mb MB)";
        echo "</div>";
    } else {
        echo "<div class='info'>No kerugian found</div>";
    }
    
    // Total size
    echo "<h2>Step 4: Total Data Size</h2>";
    
    $total_size = $investment['bukti_size'] + $total_keuntungan_size + $total_kerugian_size;
    $total_mb = round($total_size / 1024 / 1024, 2);
    
    $class = $total_size > 10000000 ? 'error' : ($total_size > 5000000 ? 'warning' : 'success');
    
    echo "<div class='info $class'>";
    echo "<strong>Total raw data size: " . number_format($total_size) . " bytes ($total_mb MB)</strong><br>";
    echo "Investment: " . round($investment['bukti_size'] / 1024 / 1024, 2) . " MB<br>";
    echo "Keuntungan: " . round($total_keuntungan_size / 1024 / 1024, 2) . " MB<br>";
    echo "Kerugian: " . round($total_kerugian_size / 1024 / 1024, 2) . " MB";
    echo "</div>";
    
    if ($total_size > 10000000) {
        echo "<div class='info error'>";
        echo "⚠️ <strong>CRITICAL:</strong> Total size > 10MB!<br>";
        echo "Base64 encoding akan membuat response ~" . round($total_mb * 1.33, 2) . " MB<br>";
        echo "Ini terlalu besar untuk JSON response dan akan menyebabkan:<br>";
        echo "• Response timeout<br>";
        echo "• Memory limit exceeded<br>";
        echo "• JSON parse error di browser";
        echo "</div>";
    } elseif ($total_size > 5000000) {
        echo "<div class='info warning'>⚠️ WARNING: Total size > 5MB - may cause issues</div>";
    }
    
    // Test actual API call
    echo "<h2>Step 5: Test API Response</h2>";
    
    echo "<div class='info'>";
    echo "<strong>Direct API test:</strong><br>";
    echo "<a href='get_investment_detail.php?id=$id' target='_blank' class='btn'>Open API Response</a>";
    echo "</div>";
    
    // Simulate API call with output buffering
    echo "<h2>Step 6: Simulate API Response Length</h2>";
    
    ob_start();
    
    // Include the actual API file logic here (simplified)
    $response_start_time = microtime(true);
    $max_execution_time = ini_get('max_execution_time');
    
    echo "<div class='info'>";
    echo "Max execution time: $max_execution_time seconds<br>";
    echo "Current memory: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB<br>";
    echo "Memory limit: " . ini_get('memory_limit');
    echo "</div>";
    
    // Test JSON encoding
    echo "<h2>Step 7: Test JSON Encoding</h2>";
    
    $test_data = [
        'success' => true,
        'investment' => [
            'id' => $id,
            'judul_investasi' => $investment['judul_investasi'],
            'total_files' => count($keuntungan_list) + 1 + ($kerugian ? 1 : 0),
            'estimated_size' => $total_mb . ' MB',
            'warning' => $total_size > 5000000 ? 'Data too large for JSON response' : 'OK'
        ]
    ];
    
    $json_test = json_encode($test_data);
    if ($json_test === false) {
        echo "<div class='info error'>";
        echo "✗ JSON encoding failed: " . json_last_error_msg();
        echo "</div>";
    } else {
        echo "<div class='info success'>";
        echo "✓ JSON encoding test successful<br>";
        echo "Test JSON length: " . strlen($json_test) . " bytes";
        echo "</div>";
    }
    
    echo "<h2>🔧 Recommended Solutions</h2>";
    
    if ($total_size > 5000000) {
        echo "<div class='info error'>";
        echo "<strong>SOLUTION 1: Don't include base64 data in JSON</strong><br>";
        echo "✓ Current code already only sends metadata + URLs<br>";
        echo "✓ Files should be loaded via view_file.php<br>";
        echo "✓ Check if base64_data is accidentally included in API response";
        echo "</div>";
        
        echo "<div class='info warning'>";
        echo "<strong>SOLUTION 2: Lazy load images</strong><br>";
        echo "✓ Don't load all images immediately<br>";
        echo "✓ Use data-src and load on demand<br>";
        echo "✓ Paginate transactions if many";
        echo "</div>";
        
        echo "<div class='info'>";
        echo "<strong>SOLUTION 3: Check actual API response</strong><br>";
        echo "Open the API link above and check:<br>";
        echo "• Is the JSON complete?<br>";
        echo "• Does it end with closing braces?<br>";
        echo "• Is there any PHP error/warning before JSON?<br>";
        echo "• What's the actual response size?";
        echo "</div>";
    }
    
    echo "<h2>📊 Memory Usage</h2>";
    echo "<div class='info'>";
    echo "Current: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB<br>";
    echo "Peak: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB<br>";
    echo "Limit: " . ini_get('memory_limit');
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='info error'>";
    echo "<strong>ERROR:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine();
    echo "</div>";
}
