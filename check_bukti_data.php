<?php
/**
 * Diagnostic Tool - Check Bukti File Data Format
 * Usage: check_bukti_data.php?id=123&type=investasi
 */

require_once 'config/koneksi.php';

header('Content-Type: text/html; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$type = isset($_GET['type']) ? $_GET['type'] : 'investasi';

if (!$id) {
    die('<h2>Usage: check_bukti_data.php?id=123&type=investasi</h2>');
}

// Determine table and column names
$table_map = [
    'investasi' => ['table' => 'investasi', 'title' => 'judul_investasi'],
    'keuntungan' => ['table' => 'keuntungan_investasi', 'title' => 'judul_keuntungan'],
    'kerugian' => ['table' => 'kerugian_investasi', 'title' => 'judul_kerugian']
];

if (!isset($table_map[$type])) {
    die('<h2>Invalid type. Use: investasi, keuntungan, or kerugian</h2>');
}

$config = $table_map[$type];

// Query data
$sql = "SELECT id, {$config['title']} as title, bukti_file FROM {$config['table']} WHERE id = ?";
$stmt = $koneksi->prepare($sql);
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die('<h2>Data not found!</h2>');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Bukti Data Diagnostic - <?= $type ?> #<?= $id ?></title>
    <style>
        body { font-family: 'Courier New', monospace; padding: 20px; background: #1e293b; color: #e2e8f0; }
        h1 { color: #60a5fa; border-bottom: 2px solid #3b82f6; padding-bottom: 10px; }
        h2 { color: #34d399; margin-top: 30px; }
        .info-box { background: #334155; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #3b82f6; }
        .error-box { background: #7f1d1d; border-left: 4px solid #ef4444; }
        .success-box { background: #064e3b; border-left: 4px solid #10b981; }
        .warning-box { background: #713f12; border-left: 4px solid #f59e0b; }
        pre { background: #0f172a; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 12px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: bold; margin: 5px; }
        .badge-success { background: #10b981; color: white; }
        .badge-error { background: #ef4444; color: white; }
        .badge-info { background: #3b82f6; color: white; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #475569; }
        th { background: #1e40af; color: white; }
        .btn { display: inline-block; padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; margin: 10px 5px; }
        .btn:hover { background: #2563eb; }
    </style>
</head>
<body>
    <h1>🔍 Bukti File Diagnostic Tool</h1>
    
    <div class="info-box">
        <table>
            <tr><th>Type</th><td><?= ucfirst($type) ?></td></tr>
            <tr><th>ID</th><td><?= $data['id'] ?></td></tr>
            <tr><th>Title</th><td><?= htmlspecialchars($data['title']) ?></td></tr>
        </table>
    </div>

    <h2>📊 Raw Data Analysis</h2>
    <?php
    $bukti = $data['bukti_file'];
    
    if (empty($bukti)) {
        echo '<div class="info-box warning-box"><strong>⚠️ WARNING:</strong> bukti_file is NULL or empty</div>';
    } else {
        echo '<div class="info-box success-box">';
        echo '<strong>✓ Data exists</strong><br>';
        echo '<span class="badge badge-info">Type: ' . gettype($bukti) . '</span>';
        
        // Handle resource type (PDO might return BLOB as resource)
        if (is_resource($bukti)) {
            echo '<span class="badge badge-info">Resource detected - converting...</span>';
            $bukti = stream_get_contents($bukti);
        }
        
        if (is_string($bukti)) {
            echo '<span class="badge badge-success">Length: ' . number_format(strlen($bukti)) . ' bytes</span>';
            echo '<span class="badge badge-info">Size: ' . format_file_size(strlen($bukti)) . '</span>';
        }
        echo '</div>';
    }
    ?>

    <?php if (!empty($bukti)): ?>
    
    <h2>🔬 Format Detection</h2>
    <?php
    $has_delimiter = strpos($bukti, '|||') !== false;
    $has_pipes = substr_count(substr($bukti, 0, 500), '|') > 0;
    $first_char = ord(substr($bukti, 0, 1));
    $starts_with_json = $first_char === 123; // '{' character
    ?>
    
    <div class="info-box">
        <table>
            <tr>
                <th>Check</th>
                <th>Result</th>
                <th>Interpretation</th>
            </tr>
            <tr>
                <td>Has '|||' delimiter</td>
                <td><?= $has_delimiter ? '<span class="badge badge-success">YES</span>' : '<span class="badge badge-error">NO</span>' ?></td>
                <td><?= $has_delimiter ? 'New format (metadata|||base64)' : 'Not new format' ?></td>
            </tr>
            <tr>
                <td>Has '|' pipes</td>
                <td><?= $has_pipes ? '<span class="badge badge-success">YES</span>' : '<span class="badge badge-error">NO</span>' ?></td>
                <td><?= $has_pipes ? 'Possibly old format' : 'Not old format' ?></td>
            </tr>
            <tr>
                <td>Starts with '{'</td>
                <td><?= $starts_with_json ? '<span class="badge badge-success">YES</span>' : '<span class="badge badge-error">NO</span>' ?></td>
                <td><?= $starts_with_json ? 'Starts with JSON metadata' : 'Not JSON' ?></td>
            </tr>
            <tr>
                <td>First byte</td>
                <td><span class="badge badge-info"><?= $first_char ?></span></td>
                <td>ASCII: <?= $first_char ?> (<?= htmlspecialchars(chr($first_char)) ?>)</td>
            </tr>
        </table>
    </div>

    <h2>📝 First 500 Characters (Hex)</h2>
    <pre><?= chunk_split(bin2hex(substr($bukti, 0, 500)), 2, ' ') ?></pre>

    <h2>📝 First 500 Characters (UTF-8)</h2>
    <pre><?= htmlspecialchars(substr($bukti, 0, 500)) ?></pre>

    <?php if ($has_delimiter): ?>
    <h2>🔧 New Format Parsing Test</h2>
    <?php
    try {
        $parts = explode('|||', $bukti, 2);
        if (count($parts) === 2) {
            $metadata_json = $parts[0];
            $base64_data = $parts[1];
            
            echo '<div class="info-box success-box">';
            echo '<strong>✓ Successfully split data</strong><br>';
            echo '<span class="badge badge-info">Metadata length: ' . strlen($metadata_json) . ' bytes</span>';
            echo '<span class="badge badge-info">Base64 length: ' . number_format(strlen($base64_data)) . ' bytes</span>';
            echo '</div>';
            
            echo '<h3>Metadata JSON:</h3>';
            echo '<pre>' . htmlspecialchars($metadata_json) . '</pre>';
            
            $metadata = json_decode($metadata_json, true);
            if ($metadata) {
                echo '<h3>Parsed Metadata:</h3>';
                echo '<div class="info-box success-box">';
                echo '<table>';
                foreach ($metadata as $key => $value) {
                    echo "<tr><th>$key</th><td>" . htmlspecialchars($value) . "</td></tr>";
                }
                echo '</table>';
                echo '</div>';
            } else {
                echo '<div class="info-box error-box"><strong>✗ Failed to parse JSON:</strong> ' . json_last_error_msg() . '</div>';
            }
            
            echo '<h3>Base64 Preview (first 200 chars):</h3>';
            echo '<pre>' . htmlspecialchars(substr($base64_data, 0, 200)) . '...</pre>';
            
            // Try to decode base64
            $decoded = base64_decode(substr($base64_data, 0, 1000), true);
            if ($decoded !== false) {
                echo '<div class="info-box success-box"><strong>✓ Base64 is valid</strong></div>';
            } else {
                echo '<div class="info-box error-box"><strong>✗ Base64 is invalid or corrupted</strong></div>';
            }
        } else {
            echo '<div class="info-box error-box"><strong>✗ Failed to split on |||</strong></div>';
        }
    } catch (Exception $e) {
        echo '<div class="info-box error-box"><strong>✗ Exception:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    ?>
    <?php endif; ?>

    <?php if ($has_pipes && !$has_delimiter): ?>
    <h2>🔧 Old Format Parsing Test</h2>
    <?php
    $parts = explode('|', $bukti);
    echo '<div class="info-box">';
    echo '<strong>Parts found: ' . count($parts) . '</strong><br>';
    if (count($parts) >= 5) {
        echo '<span class="badge badge-success">✓ Valid old format (needs 5+ parts)</span>';
        echo '<table>';
        echo '<tr><th>Index</th><th>Value</th><th>Length</th></tr>';
        for ($i = 0; $i < min(10, count($parts)); $i++) {
            echo '<tr><td>' . $i . '</td><td>' . htmlspecialchars(substr($parts[$i], 0, 100)) . '</td><td>' . strlen($parts[$i]) . '</td></tr>';
        }
        echo '</table>';
    } else {
        echo '<span class="badge badge-error">✗ Invalid old format (needs 5+ parts, got ' . count($parts) . ')</span>';
    }
    echo '</div>';
    ?>
    <?php endif; ?>

    <h2>🧪 parse_bukti_file() Function Test</h2>
    <?php
    try {
        $result = parse_bukti_file($bukti);
        if ($result) {
            echo '<div class="info-box success-box"><strong>✓ Function returned data</strong></div>';
            echo '<table>';
            foreach ($result as $key => $value) {
                if ($key === 'base64_data') {
                    echo '<tr><th>' . $key . '</th><td>[' . number_format(strlen($value)) . ' bytes - hidden]</td></tr>';
                } else {
                    echo '<tr><th>' . $key . '</th><td>' . htmlspecialchars($value) . '</td></tr>';
                }
            }
            echo '</table>';
        } else {
            echo '<div class="info-box error-box"><strong>✗ Function returned NULL</strong></div>';
        }
    } catch (Exception $e) {
        echo '<div class="info-box error-box"><strong>✗ Exception:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    ?>

    <?php endif; ?>

    <h2>🌐 View File URL Test</h2>
    <div class="info-box">
        <a class="btn" href="view_file.php?type=<?= $type ?>&id=<?= $id ?>" target="_blank">
            📄 Open in New Tab
        </a>
        <a class="btn" href="view_file.php?type=<?= $type ?>&id=<?= $id ?>&download=1" target="_blank">
            💾 Download File
        </a>
    </div>

    <h2>🔄 Test API Endpoint</h2>
    <div class="info-box">
        <a class="btn" href="get_investment_detail.php?id=<?= $id ?>" target="_blank">
            🔗 Test get_investment_detail.php?id=<?= $id ?>
        </a>
    </div>

</body>
</html>

<?php
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
