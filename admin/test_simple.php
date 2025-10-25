<?php
/**
 * Simple Test - Cek Data Langsung
 */
require_once "../config/koneksi.php";

echo "<h1>🧪 Simple Test - Direct Query</h1>";
echo "<style>
    body { font-family: Arial; padding: 20px; background: #f8fafc; }
    h2 { color: #667eea; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-top: 30px; }
    .box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .success { color: #10b981; font-weight: bold; }
    .error { color: #ef4444; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { border: 1px solid #e2e8f0; padding: 12px; text-align: left; }
    th { background: #f1f5f9; font-weight: 600; }
    tr:hover { background: #f8fafc; }
    .empty { text-align: center; padding: 40px; color: #94a3b8; }
    .code { background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 8px; overflow-x: auto; }
</style>";

// Test 1: Count Cash Balance
echo "<div class='box'>";
echo "<h2>📊 Test 1: Jumlah Data Cash Balance</h2>";
try {
    $count = $koneksi->query("SELECT COUNT(*) as total FROM cash_balance")->fetch()['total'];
    
    if ($count > 0) {
        echo "<p class='success'>✅ Ada {$count} transaksi kas di database</p>";
    } else {
        echo "<p class='error'>❌ Tidak ada data di tabel cash_balance!</p>";
        echo "<p>➡️ <a href='cash_balance.php'>Tambah Transaksi Kas Sekarang</a></p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 2: Display Cash Balance Data
if ($count > 0) {
    echo "<div class='box'>";
    echo "<h2>💰 Test 2: Data Cash Balance (5 Terakhir)</h2>";
    try {
        $sql = "SELECT id, tanggal, judul, tipe, jumlah, kategori, 
                CASE WHEN bukti_file IS NULL THEN 'No' ELSE 'Yes' END as has_file,
                created_at
                FROM cash_balance 
                ORDER BY created_at DESC, id DESC 
                LIMIT 5";
        
        $stmt = $koneksi->query($sql);
        $data = $stmt->fetchAll();
        
        echo "<table>";
        echo "<tr>
                <th>ID</th>
                <th>Tanggal</th>
                <th>Judul</th>
                <th>Tipe</th>
                <th>Jumlah</th>
                <th>Kategori</th>
                <th>File</th>
                <th>Created At</th>
              </tr>";
        
        foreach ($data as $row) {
            $color = $row['tipe'] == 'masuk' ? 'color: #10b981' : 'color: #ef4444';
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['tanggal']}</td>";
            echo "<td>" . htmlspecialchars($row['judul']) . "</td>";
            echo "<td style='{$color}'>" . strtoupper($row['tipe']) . "</td>";
            echo "<td style='{$color}'>Rp " . number_format($row['jumlah'], 0, ',', '.') . "</td>";
            echo "<td><span style='background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-size: 0.875rem;'>{$row['kategori']}</span></td>";
            echo "<td>{$row['has_file']}</td>";
            echo "<td style='font-size: 0.813rem; color: #64748b;'>{$row['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<div class='code'>";
        echo "Query: {$sql}";
        echo "</div>";
        
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
}

// Test 3: Count Transaksi Jual
echo "<div class='box'>";
echo "<h2>📊 Test 3: Jumlah Data Transaksi Jual</h2>";
try {
    $count_jual = $koneksi->query("SELECT COUNT(*) as total FROM transaksi_jual")->fetch()['total'];
    
    if ($count_jual > 0) {
        echo "<p class='success'>✅ Ada {$count_jual} transaksi jual di database</p>";
    } else {
        echo "<p class='error'>❌ Tidak ada data di tabel transaksi_jual!</p>";
        echo "<p>➡️ <a href='transaksi_jual.php'>Jual Investasi Sekarang</a></p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 4: Display Transaksi Jual Data
if ($count_jual > 0) {
    echo "<div class='box'>";
    echo "<h2>🤝 Test 4: Data Transaksi Jual (5 Terakhir)</h2>";
    try {
        $sql = "SELECT 
                    tj.id,
                    tj.tanggal_jual,
                    i.judul_investasi,
                    k.nama_kategori,
                    tj.harga_jual,
                    tj.profit_loss,
                    tj.roi_persen,
                    CASE WHEN tj.bukti_file IS NULL THEN 'No' ELSE 'Yes' END as has_file,
                    tj.created_at
                FROM transaksi_jual tj
                JOIN investasi i ON tj.investasi_id = i.id
                JOIN kategori k ON i.kategori_id = k.id
                ORDER BY tj.created_at DESC, tj.id DESC
                LIMIT 5";
        
        $stmt = $koneksi->query($sql);
        $data = $stmt->fetchAll();
        
        echo "<table>";
        echo "<tr>
                <th>ID</th>
                <th>Tanggal</th>
                <th>Investasi</th>
                <th>Kategori</th>
                <th>Harga Jual</th>
                <th>Profit/Loss</th>
                <th>ROI %</th>
                <th>File</th>
                <th>Created At</th>
              </tr>";
        
        foreach ($data as $row) {
            $pl_color = $row['profit_loss'] >= 0 ? 'color: #10b981' : 'color: #ef4444';
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['tanggal_jual']}</td>";
            echo "<td>" . htmlspecialchars($row['judul_investasi']) . "</td>";
            echo "<td><span style='background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-size: 0.875rem;'>{$row['nama_kategori']}</span></td>";
            echo "<td>Rp " . number_format($row['harga_jual'], 0, ',', '.') . "</td>";
            echo "<td style='{$pl_color}; font-weight: 600;'>" . ($row['profit_loss'] >= 0 ? '+' : '') . "Rp " . number_format($row['profit_loss'], 0, ',', '.') . "</td>";
            echo "<td style='{$pl_color}'>" . number_format($row['roi_persen'], 2) . "%</td>";
            echo "<td>{$row['has_file']}</td>";
            echo "<td style='font-size: 0.813rem; color: #64748b;'>{$row['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<div class='code'>";
        echo "Query: {$sql}";
        echo "</div>";
        
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
}

// Summary
echo "<div class='box'>";
echo "<h2>📋 Summary</h2>";
echo "<table>";
echo "<tr><th>Item</th><th>Status</th><th>Action</th></tr>";

echo "<tr>";
echo "<td>Cash Balance Data</td>";
echo "<td>" . ($count > 0 ? "<span class='success'>✅ {$count} records</span>" : "<span class='error'>❌ Empty</span>") . "</td>";
echo "<td>" . ($count > 0 ? "✓ OK" : "<a href='cash_balance.php'>Add Now</a>") . "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Transaksi Jual Data</td>";
echo "<td>" . ($count_jual > 0 ? "<span class='success'>✅ {$count_jual} records</span>" : "<span class='error'>❌ Empty</span>") . "</td>";
echo "<td>" . ($count_jual > 0 ? "✓ OK" : "<a href='transaksi_jual.php'>Add Now</a>") . "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>Dashboard Display</td>";
echo "<td>" . (($count > 0 || $count_jual > 0) ? "<span class='success'>✅ Should Work</span>" : "<span class='error'>❌ Will be Empty</span>") . "</td>";
echo "<td><a href='../dashboard.php'>Check Dashboard</a></td>";
echo "</tr>";

echo "</table>";
echo "</div>";

echo "<hr style='margin: 40px 0;'>";
echo "<p style='text-align: center;'>";
echo "<a href='../dashboard.php' style='padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; margin: 0 10px;'>← Dashboard</a>";
echo "<a href='cash_balance.php' style='padding: 10px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 8px; margin: 0 10px;'>Add Cash</a>";
echo "<a href='transaksi_jual.php' style='padding: 10px 20px; background: #f59e0b; color: white; text-decoration: none; border-radius: 8px; margin: 0 10px;'>Add Sale</a>";
echo "</p>";
?>
