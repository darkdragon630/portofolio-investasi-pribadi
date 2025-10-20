<?php
/**
 * SAZEN v3.0 - Real-time Log Viewer
 * Lihat log error dan security secara real-time
 */

$log_files = [
    'auth_errors' => __DIR__ . '/logs/auth_errors.log',
    'security' => __DIR__ . '/logs/security.log',
    'php_errors' => __DIR__ . '/logs/php_errors.log'
];

$active_log = $_GET['log'] ?? 'auth_errors';
$lines = $_GET['lines'] ?? 50;
$auto_refresh = isset($_GET['auto_refresh']);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAZEN Log Viewer</title>
    <?php if ($auto_refresh): ?>
    <meta http-equiv="refresh" content="3">
    <?php endif; ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
        }
        .header {
            background: #2d2d2d;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #4ec9b0;
            margin-bottom: 10px;
            font-size: 24px;
        }
        .controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        .btn {
            padding: 8px 16px;
            background: #007acc;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:hover { background: #005a9e; }
        .btn.active { background: #4ec9b0; color: #1e1e1e; }
        .btn.danger { background: #f48771; }
        .btn.danger:hover { background: #d16969; }
        .log-container {
            background: #2d2d2d;
            border-radius: 8px;
            padding: 20px;
            max-height: 70vh;
            overflow-y: auto;
        }
        .log-line {
            padding: 8px 12px;
            margin-bottom: 4px;
            border-radius: 4px;
            font-size: 13px;
            line-height: 1.6;
            border-left: 3px solid transparent;
        }
        .log-line:hover { background: #3d3d3d; }
        .log-error { border-left-color: #f48771; background: rgba(244, 135, 113, 0.1); }
        .log-success { border-left-color: #4ec9b0; background: rgba(78, 201, 176, 0.1); }
        .log-warning { border-left-color: #dcdcaa; background: rgba(220, 220, 170, 0.1); }
        .log-info { border-left-color: #569cd6; background: rgba(86, 156, 214, 0.1); }
        .timestamp { color: #858585; margin-right: 10px; }
        .event { color: #dcdcaa; font-weight: bold; margin-right: 10px; }
        .details { color: #d4d4d4; }
        .empty {
            text-align: center;
            padding: 40px;
            color: #858585;
            font-size: 16px;
        }
        .stats {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .stat-box {
            background: #3d3d3d;
            padding: 12px 20px;
            border-radius: 6px;
            flex: 1;
            min-width: 150px;
        }
        .stat-label { color: #858585; font-size: 12px; margin-bottom: 5px; }
        .stat-value { color: #4ec9b0; font-size: 20px; font-weight: bold; }
        .filter-input {
            padding: 8px 12px;
            background: #3d3d3d;
            border: 1px solid #555;
            color: #d4d4d4;
            border-radius: 4px;
            font-family: inherit;
            font-size: 14px;
        }
        .auto-refresh-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4ec9b0;
            color: #1e1e1e;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
    </style>
</head>
<body>
    <?php if ($auto_refresh): ?>
    <div class="auto-refresh-indicator">● AUTO REFRESH</div>
    <?php endif; ?>

    <div class="header">
        <h1>📊 SAZEN Log Viewer</h1>
        <p style="color: #858585; margin-top: 5px;">Real-time monitoring untuk debugging</p>
        
        <div class="controls">
            <?php foreach ($log_files as $name => $path): ?>
                <a href="?log=<?= $name ?>&lines=<?= $lines ?><?= $auto_refresh ? '&auto_refresh' : '' ?>" 
                   class="btn <?= $active_log === $name ? 'active' : '' ?>">
                    <?= ucwords(str_replace('_', ' ', $name)) ?>
                </a>
            <?php endforeach; ?>
            
            <span style="margin: 0 10px; color: #555;">|</span>
            
            <a href="?log=<?= $active_log ?>&lines=20<?= $auto_refresh ? '&auto_refresh' : '' ?>" 
               class="btn <?= $lines == 20 ? 'active' : '' ?>">20 lines</a>
            <a href="?log=<?= $active_log ?>&lines=50<?= $auto_refresh ? '&auto_refresh' : '' ?>" 
               class="btn <?= $lines == 50 ? 'active' : '' ?>">50 lines</a>
            <a href="?log=<?= $active_log ?>&lines=100<?= $auto_refresh ? '&auto_refresh' : '' ?>" 
               class="btn <?= $lines == 100 ? 'active' : '' ?>">100 lines</a>
            
            <span style="margin: 0 10px; color: #555;">|</span>
            
            <a href="?log=<?= $active_log ?>&lines=<?= $lines ?><?= $auto_refresh ? '' : '&auto_refresh' ?>" 
               class="btn <?= $auto_refresh ? 'active' : '' ?>">
                <?= $auto_refresh ? '⏸ Stop' : '▶ Auto Refresh' ?>
            </a>
            
            <a href="clear_logs.php?log=<?= $active_log ?>&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
               class="btn danger" onclick="return confirm('Clear this log file?')">
                🗑 Clear Log
            </a>
        </div>
    </div>

    <?php
    $log_file = $log_files[$active_log] ?? $log_files['auth_errors'];
    
    if (file_exists($log_file)) {
        $content = file_get_contents($log_file);
        $all_lines = array_filter(explode("\n", $content));
        $total_lines = count($all_lines);
        $log_lines = array_slice($all_lines, -$lines);
        
        // Calculate stats
        $error_count = count(array_filter($log_lines, fn($l) => stripos($l, 'error') !== false || stripos($l, '✗') !== false));
        $success_count = count(array_filter($log_lines, fn($l) => stripos($l, 'success') !== false || stripos($l, '✓') !== false));
        $file_size = filesize($log_file);
        $last_modified = date('Y-m-d H:i:s', filemtime($log_file));
    ?>
    
    <div class="stats">
        <div class="stat-box">
            <div class="stat-label">Total Lines</div>
            <div class="stat-value"><?= number_format($total_lines) ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Errors</div>
            <div class="stat-value" style="color: #f48771;"><?= $error_count ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Success</div>
            <div class="stat-value" style="color: #4ec9b0;"><?= $success_count ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">File Size</div>
            <div class="stat-value"><?= number_format($file_size / 1024, 2) ?> KB</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Last Modified</div>
            <div class="stat-value" style="font-size: 14px;"><?= $last_modified ?></div>
        </div>
    </div>

    <div class="log-container">
        <?php if (empty($log_lines)): ?>
            <div class="empty">
                📭 No log entries found<br>
                <small style="color: #666; margin-top: 10px; display: block;">
                    Log file exists but is empty
                </small>
            </div>
        <?php else: ?>
            <?php foreach (array_reverse($log_lines) as $line): ?>
                <?php
                $line = htmlspecialchars($line);
                $class = 'log-info';
                
                if (stripos($line, 'error') !== false || stripos($line, '✗') !== false || stripos($line, 'failed') !== false) {
                    $class = 'log-error';
                } elseif (stripos($line, 'success') !== false || stripos($line, '✓') !== false) {
                    $class = 'log-success';
                } elseif (stripos($line, 'warning') !== false) {
                    $class = 'log-warning';
                }
                
                // Parse timestamp if exists
                if (preg_match('/^\[?(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\]?/', $line, $matches)) {
                    $timestamp = $matches[1];
                    $rest = trim(str_replace($matches[0], '', $line));
                    
                    // Parse event and details
                    if (preg_match('/\|\s*([^\|]+)\s*\|\s*(.+)/', $rest, $parts)) {
                        $ip = trim($parts[1]);
                        $event_details = trim($parts[2]);
                        
                        if (preg_match('/^([^\|]+)\s*\|\s*(.+)/', $event_details, $event_parts)) {
                            $event = trim($event_parts[1]);
                            $details = trim($event_parts[2]);
                            
                            echo "<div class='log-line $class'>";
                            echo "<span class='timestamp'>$timestamp</span>";
                            echo "<span style='color: #858585;'>[$ip]</span> ";
                            echo "<span class='event'>$event</span>";
                            echo "<span class='details'>$details</span>";
                            echo "</div>";
                        } else {
                            echo "<div class='log-line $class'>$line</div>";
                        }
                    } else {
                        echo "<div class='log-line $class'>";
                        echo "<span class='timestamp'>$timestamp</span>";
                        echo "<span class='details'>$rest</span>";
                        echo "</div>";
                    }
                } else {
                    echo "<div class='log-line $class'>$line</div>";
                }
                ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php } else { ?>
        <div class="log-container">
            <div class="empty">
                ❌ Log file not found<br>
                <small style="color: #666; margin-top: 10px; display: block;">
                    <?= $log_file ?>
                </small>
            </div>
        </div>
    <?php } ?>

    <script>
        // Scroll to bottom on load
        window.addEventListener('load', () => {
            const container = document.querySelector('.log-container');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        });
    </script>
</body>
</html>