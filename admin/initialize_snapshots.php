<?php
/**
 * Initialize Snapshots for Existing Investments
 * Run this ONCE after migration
 */

session_start();
require_once "../config/koneksi.php";
require_once "../config/functions.php";
require_once "../config/auto_calculate_investment.php";

// Auth check
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized. Please login first.");
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Initialize Snapshots - SAZEN v3.1</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .status-box {
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border: 2px solid;
        }
        .loading {
            border-color: #667eea;
            background: #f0f4ff;
            color: #667eea;
        }
        .success {
            border-color: #10b981;
            background: #f0fff4;
            color: #059669;
        }
        .error {
            border-color: #ef4444;
            background: #fff0f0;
            color: #dc2626;
        }
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(102, 126, 234, 0.3);
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 20px;
        }
        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .detail-item {
            padding: 5px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .detail-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Initialize Investment Snapshots</h1>
        <p class="subtitle">Initializing daily snapshots for existing investments...</p>

        <?php
        // Start processing
        echo '<div class="status-box loading">';
        echo '<div class="spinner"></div> Processing...';
        echo '</div>';
        
        flush();
        
        try {
            $result = initialize_snapshots_for_existing_investments($koneksi);
            
            if ($result['success']) {
                echo '<div class="status-box success">';
                echo '<h2>✅ Success!</h2>';
                echo "<p><strong>Initialized:</strong> {$result['initialized']} of {$result['total']} investments</p>";
                
                if ($result['initialized'] > 0) {
                    echo '<div class="details">';
                    echo '<strong>Details:</strong><br>';
                    foreach ($result as $key => $value) {
                        if ($key !== 'success' && !is_array($value)) {
                            echo "<div class='detail-item'>$key: $value</div>";
                        }
                    }
                    echo '</div>';
                }
                
                echo '</div>';
                
                echo '<a href="../dashboard.php" class="btn">Go to Dashboard →</a>';
                
            } else {
                throw new Exception($result['error'] ?? 'Unknown error');
            }
            
        } catch (Exception $e) {
            echo '<div class="status-box error">';
            echo '<h2>❌ Error Occurred</h2>';
            echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p><strong>What to do:</strong></p>';
            echo '<ul style="margin-left: 20px; margin-top: 10px;">';
            echo '<li>Check if file auto_calculate_investment.php exists</li>';
            echo '<li>Check database connection</li>';
            echo '<li>Check PHP error log</li>';
            echo '</ul>';
            echo '</div>';
            
            echo '<a href="../dashboard.php" class="btn">Back to Dashboard</a>';
        }
        ?>

        <script>
        // Auto-hide loading after result shown
        setTimeout(() => {
            const loading = document.querySelector('.loading');
            if (loading) loading.style.display = 'none';
        }, 500);
        </script>
    </div>
</body>
</html>
