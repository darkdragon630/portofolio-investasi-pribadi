<?php
/**
 * SAZEN Investment Portfolio Manager v3.1.3
 * ULTIMATE Dashboard - FIXED LOGIC
 * 
 * FIXED v3.1.3:
 * ✅ Keuntungan = Akumulatif (dijumlahkan semua)
 * ✅ Kerugian = Hanya nilai TERBARU per investasi (tidak dijumlahkan)
 * ✅ Query optimized untuk performa
 */

session_start();
require_once "config/koneksi.php";
require_once "config/functions.php";
require_once "config/auto_calculate_investment.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: admin/auth.php");
    exit;
}

// Get user info
$username = $_SESSION['username'];
$email = $_SESSION['email'];

// Logout handler
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: admin/auth.php");
    exit;
}

// Handle manual recalculation
if (isset($_POST['recalculate_all'])) {
    try {
        $batch_result = batch_recalculate_all_investments($koneksi);
        if ($batch_result['success']) {
            set_flash_message('success', "✅ Recalculate berhasil! {$batch_result['updated_count']} investasi diupdate dalam " . 
                             number_format($batch_result['execution_time'], 2) . " detik");
        } else {
            set_flash_message('error', "❌ Gagal recalculate");
        }
    } catch (Exception $e) {
        set_flash_message('error', "❌ Error: " . $e->getMessage());
    }
    header("Location: dashboard.php");
    exit;
}

// Flash message handling
$flash_message = null;
$flash_type = null;
if (isset($_SESSION['_flash'])) {
    foreach ($_SESSION['_flash'] as $type => $message) {
        $flash_message = $message;
        $flash_type = $type;
        break;
    }
    unset($_SESSION['_flash']);
}

/* ========================================
   STATISTIK GLOBAL & KAS
======================================== */
$cash_balance = get_cash_balance($koneksi);
$saldo_kas = $cash_balance ? (float)$cash_balance['saldo_akhir'] : 0;
$total_kas_masuk = $cash_balance ? (float)$cash_balance['total_masuk'] : 0;
$total_kas_keluar = $cash_balance ? (float)$cash_balance['total_keluar'] : 0;

$sales_stats = get_sales_statistics($koneksi);
$total_sales = $sales_stats ? (float)$sales_stats['total_penjualan'] : 0;
$sales_profit_loss = $sales_stats ? (float)$sales_stats['total_profit_loss'] : 0;
$avg_roi_sales = $sales_stats ? (float)$sales_stats['avg_roi'] : 0;

/* ========================================
   QUERY INVESTASI (FIXED LOGIC v3.1.3)
======================================== */
$sql_investasi_all = "
    SELECT 
        i.id,
        i.judul_investasi,
        i.deskripsi,
        i.jumlah as modal_investasi,
        i.tanggal_investasi,
        i.bukti_file,
        i.status,
        k.nama_kategori,
        COALESCE(ku_agg.total_keuntungan, 0) as total_keuntungan,
        COALESCE(kr_latest.kerugian_terbaru, 0) as kerugian_terbaru,
        (i.jumlah + COALESCE(ku_agg.total_keuntungan, 0) - COALESCE(kr_latest.kerugian_terbaru, 0)) as nilai_sekarang,
        CASE 
            WHEN i.jumlah > 0 THEN 
                ((COALESCE(ku_agg.total_keuntungan, 0) - COALESCE(kr_latest.kerugian_terbaru, 0)) / i.jumlah * 100)
            ELSE 0 
        END as roi_persen
    FROM investasi i
    JOIN kategori k ON i.kategori_id = k.id
    LEFT JOIN (
        SELECT investasi_id, SUM(jumlah_keuntungan) AS total_keuntungan
        FROM keuntungan_investasi GROUP BY investasi_id
    ) ku_agg ON i.id = ku_agg.investasi_id
    LEFT JOIN (
        SELECT investasi_id, jumlah_kerugian as kerugian_terbaru
        FROM (
            SELECT 
                investasi_id, 
                jumlah_kerugian,
                ROW_NUMBER() OVER (PARTITION BY investasi_id ORDER BY tanggal_kerugian DESC, created_at DESC) as rn
            FROM kerugian_investasi
        ) ranked
        WHERE rn = 1
    ) kr_latest ON i.id = kr_latest.investasi_id
    ORDER BY i.tanggal_investasi DESC
";

$stmt_investasi_all = $koneksi->query($sql_investasi_all);
$investasi_all = $stmt_investasi_all->fetchAll();

// Pisahkan berdasarkan status
$investasi_aktif = array_filter($investasi_all, fn($inv) => ($inv['status'] ?? 'aktif') === 'aktif');
$investasi_terjual = array_filter($investasi_all, fn($inv) => ($inv['status'] ?? 'aktif') === 'terjual');

// Hitung statistik investasi aktif
$total_modal_aktif = array_reduce($investasi_aktif, fn($carry, $inv) => $carry + $inv['modal_investasi'], 0);
$total_nilai_investasi_aktif = array_reduce($investasi_aktif, fn($carry, $inv) => $carry + $inv['nilai_sekarang'], 0);
$total_keuntungan_aktif = array_reduce($investasi_aktif, fn($carry, $inv) => $carry + $inv['total_keuntungan'], 0);

// ✅ FIXED: Kerugian = Sum dari kerugian terbaru tiap investasi aktif
$total_kerugian_aktif = array_reduce($investasi_aktif, fn($carry, $inv) => $carry + ($inv['kerugian_terbaru'] ?? 0), 0);

// Total Aset
$total_aset = $saldo_kas + $total_nilai_investasi_aktif;
$persentase_kas = $total_aset > 0 ? ($saldo_kas / $total_aset * 100) : 0;
$persentase_investasi = $total_aset > 0 ? ($total_nilai_investasi_aktif / $total_aset * 100) : 0;

/* ========================================
   QUERY KEUNTUNGAN & KERUGIAN GLOBAL
======================================== */
// Keuntungan: dijumlahkan semua
$sql_total_keuntungan = "SELECT COALESCE(SUM(jumlah_keuntungan), 0) as total FROM keuntungan_investasi";
$stmt_total_keuntungan = $koneksi->query($sql_total_keuntungan);
$total_keuntungan_global = (float)$stmt_total_keuntungan->fetch()['total'];

// ✅ FIXED: Kerugian global = sum dari kerugian terbaru per investasi
$sql_total_kerugian_global = "
    SELECT COALESCE(SUM(kerugian_terbaru), 0) as total
    FROM (
        SELECT investasi_id, jumlah_kerugian as kerugian_terbaru
        FROM (
            SELECT 
                investasi_id, 
                jumlah_kerugian,
                ROW_NUMBER() OVER (PARTITION BY investasi_id ORDER BY tanggal_kerugian DESC, created_at DESC) as rn
            FROM kerugian_investasi
        ) ranked
        WHERE rn = 1
    ) latest_losses
";
$stmt_total_kerugian = $koneksi->query($sql_total_kerugian_global);
$total_kerugian_global = (float)$stmt_total_kerugian->fetch()['total'];

$net_profit = $total_keuntungan_global - $total_kerugian_global;
$roi_global = $total_modal_aktif > 0 ? (($total_keuntungan_aktif - $total_kerugian_aktif) / $total_modal_aktif * 100) : 0;

/* ========================================
   BREAKDOWN KATEGORI
======================================== */
$breakdown_kategori = [];
foreach ($investasi_aktif as $inv) {
    $kategori = $inv['nama_kategori'];
    if (!isset($breakdown_kategori[$kategori])) {
        $breakdown_kategori[$kategori] = [
            'nilai' => 0,
            'count' => 0,
            'modal' => 0,
            'keuntungan' => 0,
            'kerugian' => 0
        ];
    }
    $breakdown_kategori[$kategori]['nilai'] += $inv['nilai_sekarang'];
    $breakdown_kategori[$kategori]['modal'] += $inv['modal_investasi'];
    $breakdown_kategori[$kategori]['keuntungan'] += $inv['total_keuntungan'];
    $breakdown_kategori[$kategori]['kerugian'] += ($inv['kerugian_terbaru'] ?? 0); // ✅ Terbaru
    $breakdown_kategori[$kategori]['count']++;
}
uasort($breakdown_kategori, fn($a, $b) => $b['nilai'] - $a['nilai']);

/* ========================================
   BREAKDOWN SUMBER
======================================== */
$sumber_keuntungan_stats = $koneksi->query("
    SELECT sumber_keuntungan, COUNT(*) as jumlah, SUM(jumlah_keuntungan) as total
    FROM keuntungan_investasi GROUP BY sumber_keuntungan ORDER BY total DESC
")->fetchAll();

$sumber_kerugian_stats = $koneksi->query("
    SELECT sumber_kerugian, COUNT(*) as jumlah, SUM(jumlah_kerugian) as total
    FROM kerugian_investasi GROUP BY sumber_kerugian ORDER BY total DESC
")->fetchAll();

$total_transaksi_keuntungan = array_sum(array_column($sumber_keuntungan_stats, 'jumlah'));
$total_transaksi_kerugian = array_sum(array_column($sumber_kerugian_stats, 'jumlah'));

/* ========================================
   RECENT DATA
======================================== */
$investasi_list = array_slice($investasi_all, 0, 10);
$cash_transactions = get_recent_cash_transactions($koneksi, 10);
$sales_list = get_sale_transactions($koneksi, 10);
$cash_by_category = get_cash_by_category($koneksi);

$keuntungan_list = $koneksi->query("
    SELECT ki.*, i.judul_investasi, k.nama_kategori
    FROM keuntungan_investasi ki
    JOIN investasi i ON ki.investasi_id = i.id
    JOIN kategori k ON ki.kategori_id = k.id
    ORDER BY ki.tanggal_keuntungan DESC LIMIT 10
")->fetchAll();

$kerugian_list = $koneksi->query("
    SELECT kr.*, i.judul_investasi, k.nama_kategori
    FROM kerugian_investasi kr
    JOIN investasi i ON kr.investasi_id = i.id
    JOIN kategori k ON kr.kategori_id = k.id
    ORDER BY kr.tanggal_kerugian DESC LIMIT 10
")->fetchAll();

$last_update = $koneksi->query("SELECT MAX(updated_at) as last_update FROM investasi WHERE status = 'aktif'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Dashboard Admin - SAZEN v3.1.3</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="icon" type="image/png" sizes="64x64" href="/Luminark_Holdings.png">
    
    <style>
        .recalculate-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        .recalculate-info h3 {
            margin: 0 0 8px 0;
            font-size: 18px;
            font-weight: 600;
        }
        .recalculate-info p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }
        .recalculate-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .btn-recalculate {
            background: white;
            color: #667eea;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .btn-recalculate:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .last-update {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
        }
        .version-badge {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 999;
        }
        @media (max-width: 768px) {
            .recalculate-section {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }
            .version-badge {
                bottom: 10px;
                right: 10px;
                font-size: 10px;
                padding: 6px 12px;
            }
        }
    </style>
</head>
<body>

    <div class="loading-screen" id="loadingScreen">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-text">Memuat Dashboard...</div>
        </div>
    </div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-chart-line"></i>
                <span class="logo-text">SAZEN</span>
            </div>
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item active">
                <i class="fas fa-home"></i>
                <span class="nav-text">Dashboard</span>
            </a>
            <a href="index.php" class="nav-item" target="_blank">
                <i class="fas fa-eye"></i>
                <span class="nav-text">Lihat Portfolio</span>
            </a>
            
            <div class="nav-divider"><span>Manajemen Data</span></div>
            
            <a href="admin/upload_investasi.php" class="nav-item">
                <i class="fas fa-plus-circle"></i>
                <span class="nav-text">Tambah Investasi</span>
            </a>
            <a href="admin/upload_keuntungan.php" class="nav-item">
                <i class="fas fa-arrow-trend-up"></i>
                <span class="nav-text">Tambah Keuntungan</span>
            </a>
            <a href="admin/upload_kerugian.php" class="nav-item">
                <i class="fas fa-arrow-trend-down"></i>
                <span class="nav-text">Tambah Kerugian</span>
            </a>
            <a href="admin/transaksi_jual.php" class="nav-item">
                <i class="fas fa-handshake"></i>
                <span class="nav-text">Jual Investasi</span>
            </a>
            <a href="admin/cash_balance.php" class="nav-item">
                <i class="fas fa-wallet"></i>
                <span class="nav-text">Kelola Kas</span>
            </a>
            
            <div class="nav-divider"><span>Lainnya</span></div>
            
            <a href="admin/laporan.php" class="nav-item">
                <i class="fas fa-chart-bar"></i>
                <span class="nav-text">Laporan</span>
            </a>
            <a href="admin/pengaturan.php" class="nav-item">
                <i class="fas fa-cog"></i>
                <span class="nav-text">Pengaturan</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><i class="fas fa-user"></i></div>
                <div class="user-details">
                    <div class="user-name"><?= htmlspecialchars($username) ?></div>
                    <div class="user-email"><?= htmlspecialchars($email) ?></div>
                </div>
            </div>
            <form method="POST" class="logout-form">
                <button type="submit" name="logout" class="logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </form>
        </div>
    </aside>

    <main class="main-content" id="mainContent">
        
        <header class="content-header">
            <div class="header-left">
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="header-title">
                    <h1>Dashboard Admin</h1>
                    <p>Selamat datang, <strong><?= htmlspecialchars($username) ?></strong></p>
                </div>
            </div>
            <div class="header-right">
                <button class="notification-btn" title="Notifikasi">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </button>
                <button class="refresh-btn" onclick="location.reload()" title="Refresh">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </header>

        <?php if ($flash_message): ?>
            <div class="flash-message flash-<?= $flash_type ?>" id="flashMessage">
                <div class="flash-icon">
                    <i class="fas fa-<?= $flash_type == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                </div>
                <div class="flash-content">
                    <div class="flash-text"><?= nl2br(htmlspecialchars($flash_message)) ?></div>
                </div>
                <button class="flash-close" onclick="closeFlash()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <div class="container">

            <!-- RECALCULATE SECTION -->
            <section class="recalculate-section">
                <div class="recalculate-info">
                    <h3><i class="fas fa-calculator"></i> Auto Calculate System v3.1.3</h3>
                    <p>Keuntungan = Akumulatif | Kerugian = Nilai Terbaru</p>
                </div>
                <div class="recalculate-actions">
                    <?php if ($last_update): ?>
                    <div class="last-update">
                        <i class="fas fa-clock"></i>
                        <span>Update: <?= date('d M Y H:i', strtotime($last_update)) ?></span>
                    </div>
                    <?php endif; ?>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="recalculate_all" class="btn-recalculate" onclick="return confirm('Recalculate semua investasi?')">
                            <i class="fas fa-sync-alt"></i>
                            <span>Recalculate Manual</span>
                        </button>
                    </form>
                </div>
            </section>

            <!-- STATS OVERVIEW (8 CARDS) -->
            <section class="stats-overview">
                <div class="stats-grid-enhanced">
                    <div class="stat-card stat-warning">
                        <div class="stat-header">
                            <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                            <div class="stat-trend <?= $saldo_kas >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $saldo_kas >= 0 ? 'up' : 'down' ?>"></i>
                            </div>
                        </div>
                        <div class="stat-body">
                            <div class="stat-label">Saldo Kas</div>
                            <div class="stat-value <?= $saldo_kas >= 0 ? 'positive' : 'negative' ?>"><?= format_currency($saldo_kas) ?></div>
                            <div class="stat-footer"><?= number_format($persentase_kas, 1) ?>% dari total aset</div>
                        </div>
                    </div>

                    <div class="stat-card stat-info">
                        <div class="stat-header">
                            <div class="stat-icon"><i class="fas fa-hand-holding-dollar"></i></div>
                            <div class="stat-trend positive">
                                <i class="fas fa-arrow-up"></i>
                                <span><?= count($investasi_aktif) ?></span>
                            </div>
                        </div>
                        <div class="stat-body">
                            <div class="stat-label">Modal Investasi</div>
                            <div class="stat-value"><?= format_currency($total_modal_aktif) ?></div>
                            <div class="stat-footer">Modal yang ditanamkan</div>
                        </div>
                    </div>

                    <div class="stat-card stat-primary">
                        <div class="stat-header">
                            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                        </div>
                        <div class="stat-body">
                            <div class="stat-label">Nilai Investasi</div>
                            <div class="stat-value"><?= format_currency($total_nilai_investasi_aktif) ?></div>
                            <div class="stat-footer"><?= count($investasi_aktif) ?> Portfolio Aktif</div>
                        </div>
                    </div>

                    <div class="stat-card stat-purple">
                        <div class="stat-header">
                            <div class="stat-icon"><i class="fas fa-coins"></i></div>
                            <?php if ($roi_global != 0): ?>
                            <div class="stat-trend <?= $roi_global >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $roi_global >= 0 ? 'up' : 'down' ?>"></i>
                                <span><?= number_format(abs($roi_global), 1) ?>%</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="stat-body">
                            <div class="stat-label">Total Aset</div>
                            <div class="stat-value highlight"><?= format_currency($total_aset) ?></div>
                            <div class="stat-footer">Kas + Investasi</div>
                        </div>
                    </div>

                    <div class="stat-card stat-success">
                        <div class="stat-header">
                            <div class="stat-icon"><i class="fas fa-arrow-trend-up"></i></div>
                        </div>
                        <div class="stat-body">
                            <div class="stat-label">Total Keuntungan</div>
                            <div class="stat-value positive"><?= format_currency($total_keuntungan_global) ?></div>
                            <div class="stat-footer"><?= $total_transaksi_keuntungan ?> Transaksi (Akumulatif)</div>
                        </div>
                    </div>

                    <div class="stat-card stat-danger">
                        <div class="stat-header">
                            <div class="stat-icon"><i class="fas fa-arrow-trend-down"></i></div>
                        </div>
                        <div class="stat-body">
                            <div class="stat-label">Total Kerugian</div>
                            <div class="stat-value negative"><?= format_currency($total_kerugian_global) ?></div>
                            <div class="stat-footer">Nilai Terbaru per Investasi</div>
                        </div>
                    </div>

                    <div class="stat-card stat-gradient">
                        <div class="stat-header">
                            <div class="stat-icon"><i class="fas fa-trophy"></i></div>
                        </div>
                        <div class="stat-body">
                            <div class="stat-label">Net Profit</div>
                            <div class="stat-value <?= $net_profit >= 0 ? 'positive' : 'negative' ?>"><?= format_currency($net_profit) ?></div>
                            <div class="stat-footer">ROI: <?= number_format($roi_global, 2) ?>%</div>
                        </div>
                    </div>

                    <div class="stat-card stat-warning">
                        <div class="stat-header">
                            <div class="stat-icon"><i class="fas fa-handshake"></i></div>
                            <div class="stat-trend <?= $sales_profit_loss >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $sales_profit_loss >= 0 ? 'up' : 'down' ?>"></i>
                                <span><?= number_format($avg_roi_sales, 1) ?>%</span>
                            </div>
                        </div>
                        <div class="stat-body">
                            <div class="stat-label">Total Penjualan</div>
                            <div class="stat-value"><?= format_currency($total_sales) ?></div>
                            <div class="stat-footer">Profit: <?= format_currency($sales_profit_loss) ?></div>
                        </div>
                    </div>
                </div>
            </section>

             <!-- ✅ ASSET ALLOCATION -->
            <section class="asset-allocation-dashboard">
                <div class="section-header-inline">
                    <h2 class="section-title">
                        <i class="fas fa-chart-pie"></i>
                        Alokasi Aset
                    </h2>
                </div>
                <div class="allocation-container-dashboard">
                    <div class="allocation-visual">
                        <div class="allocation-bar-dashboard">
                            <div class="bar-segment cash-segment" style="width: <?= $persentase_kas ?>%">
                                <?php if ($persentase_kas > 10): ?>
                                <span><?= number_format($persentase_kas, 1) ?>%</span>
                                <?php endif; ?>
                            </div>
                            <div class="bar-segment investment-segment" style="width: <?= $persentase_investasi ?>%">
                                <?php if ($persentase_investasi > 10): ?>
                                <span><?= number_format($persentase_investasi, 1) ?>%</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="allocation-legend-dashboard">
                        <div class="legend-item-dashboard">
                            <div class="legend-color cash-color"></div>
                            <div class="legend-info">
                                <span class="legend-label">Kas</span>
                                <span class="legend-value"><?= format_currency($saldo_kas) ?></span>
                            </div>
                        </div>
                        <div class="legend-item-dashboard">
                            <div class="legend-color investment-color"></div>
                            <div class="legend-info">
                                <span class="legend-label">Investasi</span>
                                <span class="legend-value"><?= format_currency($total_nilai_investasi_aktif) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ✅ QUICK ACTIONS -->
            <section class="quick-actions">
                <h2 class="section-title">
                    <i class="fas fa-bolt"></i>
                    Aksi Cepat
                </h2>
                <div class="actions-grid">
                    <a href="admin/upload_investasi.php" class="action-card">
                        <div class="action-icon primary">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="action-content">
                            <h3>Tambah Investasi</h3>
                            <p>Catat investasi baru</p>
                        </div>
                    </a>

                    <a href="admin/transaksi_jual.php" class="action-card">
                        <div class="action-icon warning">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <div class="action-content">
                            <h3>Jual Investasi</h3>
                            <p>Proses penjualan</p>
                        </div>
                    </a>

                    <a href="admin/cash_balance.php" class="action-card">
                        <div class="action-icon success">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="action-content">
                            <h3>Kelola Kas</h3>
                            <p>Transaksi kas</p>
                        </div>
                    </a>

                    <a href="admin/laporan.php" class="action-card">
                        <div class="action-icon info">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="action-content">
                            <h3>Lihat Laporan</h3>
                            <p>Analisis lengkap</p>
                        </div>
                    </a>
                </div>
            </section>

            <!-- ✅ CASH BALANCE OVERVIEW -->
            <?php if (count($cash_by_category) > 0): ?>
            <section class="card cash-overview">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-coins"></i>
                        Overview Kas per Kategori
                    </h2>
                    <a href="admin/cash_balance.php" class="btn-link">Lihat Semua</a>
                </div>
                <div class="card-body">
                    <div class="cash-grid">
                        <?php foreach ($cash_by_category as $cash_cat): ?>
                            <div class="cash-item">
                                <div class="cash-label">
                                    <?= ucfirst(str_replace('_', ' ', $cash_cat['kategori'])) ?>
                                </div>
                                <div class="cash-value <?= $cash_cat['saldo'] >= 0 ? 'positive' : 'negative' ?>">
                                    <?= format_currency($cash_cat['saldo']) ?>
                                </div>
                                <div class="cash-detail">
                                    <span class="in">↑ <?= format_currency($cash_cat['total_masuk']) ?></span>
                                    <span class="out">↓ <?= format_currency($cash_cat['total_keluar']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- ✅ CATEGORY BREAKDOWN -->
            <?php if (count($breakdown_kategori) > 0): ?>
            <section class="category-breakdown-section">
                <h2 class="section-title">
                    <i class="fas fa-layer-group"></i>
                    Breakdown per Kategori
                </h2>
                <div class="category-breakdown-grid">
                    <?php 
                    $colors = ['#667eea', '#f093fb', '#4facfe', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
                    $colorIndex = 0;
                    foreach ($breakdown_kategori as $kategori => $data): 
                        $persentase = $total_nilai_investasi_aktif > 0 ? ($data['nilai'] / $total_nilai_investasi_aktif * 100) : 0;
                        $roi_kategori = $data['modal'] > 0 ? (($data['keuntungan'] - $data['kerugian']) / $data['modal'] * 100) : 0;
                        $color = $colors[$colorIndex % count($colors)];
                        $colorIndex++;
                    ?>
                    <div class="category-card">
                        <div class="category-card-header">
                            <div class="category-icon" style="background: linear-gradient(135deg, <?= $color ?>, <?= $color ?>99);">
                                <i class="fas fa-tag"></i>
                            </div>
                            <div class="category-info-header">
                                <h4><?= htmlspecialchars($kategori) ?></h4>
                                <span><?= $data['count'] ?> Investasi</span>
                            </div>
                            <div class="category-roi <?= $roi_kategori >= 0 ? 'positive' : 'negative' ?>">
                                <?= number_format($roi_kategori, 1) ?>%
                            </div>
                        </div>
                        <div class="category-card-body">
                            <div class="category-stat">
                                <span class="label">Nilai</span>
                                <span class="value"><?= format_currency($data['nilai']) ?></span>
                            </div>
                            <div class="category-stat">
                                <span class="label">Persentase</span>
                                <span class="value"><?= number_format($persentase, 1) ?>%</span>
                            </div>
                        </div>
                        <div class="category-progress-bar">
                            <div class="progress-fill" style="width: <?= $persentase ?>%; background: <?= $color ?>;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- ✅ ANALYTICS GRID (BREAKDOWN SUMBER) -->
            <div class="dashboard-grid">
                <section class="card source-breakdown">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-layer-group"></i>
                            Breakdown Sumber
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($sumber_keuntungan_stats) > 0): ?>
                            <div class="breakdown-section">
                                <h3 class="breakdown-title">Keuntungan</h3>
                                <div class="breakdown-list">
                                    <?php foreach ($sumber_keuntungan_stats as $sumber): ?>
                                        <div class="breakdown-item">
                                            <div class="breakdown-info">
                                                <i class="fas fa-circle-dot"></i>
                                                <span><?= ucfirst(str_replace('_', ' ', $sumber['sumber_keuntungan'])) ?></span>
                                            </div>
                                            <div class="breakdown-value positive">
                                                +<?= format_currency($sumber['total']) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (count($sumber_kerugian_stats) > 0): ?>
                            <div class="breakdown-section">
                                <h3 class="breakdown-title">Kerugian</h3>
                                <div class="breakdown-list">
                                    <?php foreach ($sumber_kerugian_stats as $sumber): ?>
                                        <div class="breakdown-item">
                                            <div class="breakdown-info">
                                                <i class="fas fa-circle-dot"></i>
                                                <span><?= ucfirst(str_replace('_', ' ', $sumber['sumber_kerugian'])) ?></span>
                                            </div>
                                            <div class="breakdown-value negative">
                                                -<?= format_currency($sumber['total']) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <!-- ✅ RECENT DATA GRID -->
            <div class="recent-data-grid">
                
                <!-- Recent Investments -->
                <section class="card recent-investments">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-briefcase"></i>
                            Investasi Terbaru
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($investasi_list) > 0): ?>
                            <div class="data-table">
                                <?php foreach ($investasi_list as $inv): ?>
                                    <div class="data-row">
                                        <div class="data-main">
                                            <h4><?= htmlspecialchars($inv['judul_investasi']) ?></h4>
                                            <p>
                                                <?= htmlspecialchars($inv['nama_kategori']) ?> • 
                                                <?= date('d M Y', strtotime($inv['tanggal_investasi'])) ?> •
                                                <span class="badge badge-<?= $inv['status'] == 'aktif' ? 'success' : ($inv['status'] == 'terjual' ? 'warning' : 'secondary') ?>">
                                                    <?= ucfirst($inv['status']) ?>
                                                </span>
                                            </p>
                                        </div>
                                        <div class="data-value">
                                            <?= format_currency($inv['modal_investasi']) ?>
                                        </div>
                                        <div class="data-actions">
                                            <a href="admin/edit_investasi.php?id=<?= $inv['id'] ?>" class="btn-icon" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($inv['status'] == 'aktif'): ?>
                                            <a href="admin/transaksi_jual.php?id=<?= $inv['id'] ?>" class="btn-icon warning" title="Jual">
                                                <i class="fas fa-handshake"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-folder-open"></i>
                                <p>Belum ada data investasi</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Recent Cash Transactions -->
                <section class="card recent-cash">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-wallet"></i>
                            Transaksi Kas Terbaru
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($cash_transactions) > 0): ?>
                            <div class="data-table">
                                <?php foreach ($cash_transactions as $cash): ?>
                                    <div class="data-row">
                                        <div class="data-main">
                                            <h4><?= htmlspecialchars($cash['judul']) ?></h4>
                                            <p>
                                                <?= ucfirst(str_replace('_', ' ', $cash['kategori'])) ?> • 
                                                <?= date('d M Y', strtotime($cash['tanggal'])) ?>
                                            </p>
                                        </div>
                                        <div class="data-value <?= $cash['tipe'] == 'masuk' ? 'positive' : 'negative' ?>">
                                            <?= $cash['tipe'] == 'masuk' ? '+' : '-' ?><?= format_currency($cash['jumlah']) ?>
                                        </div>
                                        <div class="data-actions">
                                            <?php if (!empty($cash['bukti_file'])): ?>
                                                <a href="admin/view_cash.php?id=<?= $cash['id'] ?>" class="btn-icon" title="Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="admin/edit_cash.php?id=<?= $cash['id'] ?>" class="btn-icon" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-folder-open"></i>
                                <p>Belum ada transaksi kas</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Recent Sales -->
                <section class="card recent-sales">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-handshake"></i>
                            Penjualan Terbaru
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($sales_list) > 0): ?>
                            <div class="data-table">
                                <?php foreach ($sales_list as $sale): ?>
                                    <div class="data-row">
                                        <div class="data-main">
                                            <h4><?= htmlspecialchars($sale['judul_investasi']) ?></h4>
                                            <p>
                                                <?= htmlspecialchars($sale['nama_kategori']) ?> • 
                                                <?= date('d M Y', strtotime($sale['tanggal_jual'])) ?> •
                                                ROI: <?= number_format($sale['roi_persen'], 2) ?>%
                                            </p>
                                        </div>
                                        <div class="data-value <?= $sale['profit_loss'] >= 0 ? 'positive' : 'negative' ?>">
                                            <?= $sale['profit_loss'] >= 0 ? '+' : '' ?><?= format_currency($sale['profit_loss']) ?>
                                        </div>
                                        <div class="data-actions">
                                            <a href="admin/view_sale.php?id=<?= $sale['id'] ?>" class="btn-icon" title="Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-folder-open"></i>
                                <p>Belum ada data penjualan</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Recent Profits -->
                <section class="card recent-profits">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-arrow-trend-up"></i>
                            Keuntungan Terbaru
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($keuntungan_list) > 0): ?>
                            <div class="data-table">
                                <?php foreach ($keuntungan_list as $profit): ?>
                                    <div class="data-row">
                                        <div class="data-main">
                                            <h4><?= htmlspecialchars($profit['judul_keuntungan']) ?></h4>
                                            <p><?= htmlspecialchars($profit['judul_investasi']) ?> • <?= date('d M Y', strtotime($profit['tanggal_keuntungan'])) ?></p>
                                        </div>
                                        <div class="data-value positive">
                                            +<?= format_currency($profit['jumlah_keuntungan']) ?>
                                        </div>
                                        <div class="data-actions">
                                            <a href="admin/edit_keuntungan.php?id=<?= $profit['id'] ?>" class="btn-icon" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="admin/delete_keuntungan.php?id=<?= $profit['id'] ?>" class="btn-icon danger" title="Hapus" onclick="return confirm('Yakin hapus keuntungan ini?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-folder-open"></i>
                                <p>Belum ada data keuntungan</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Recent Losses -->
                <section class="card recent-losses">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-arrow-trend-down"></i>
                            Kerugian Terbaru
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($kerugian_list) > 0): ?>
                            <div class="data-table">
                                <?php foreach ($kerugian_list as $loss): ?>
                                    <div class="data-row">
                                        <div class="data-main">
                                            <h4><?= htmlspecialchars($loss['judul_kerugian']) ?></h4>
                                            <p><?= htmlspecialchars($loss['judul_investasi']) ?> • <?= date('d M Y', strtotime($loss['tanggal_kerugian'])) ?></p>
                                        </div>
                                        <div class="data-value negative">
                                            -<?= format_currency($loss['jumlah_kerugian']) ?>
                                        </div>
                                        <div class="data-actions">
                                            <a href="admin/edit_kerugian.php?id=<?= $loss['id'] ?>" class="btn-icon" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="admin/delete_kerugian.php?id=<?= $loss['id'] ?>" class="btn-icon danger" title="Hapus" onclick="return confirm('Yakin hapus kerugian ini?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-folder-open"></i>
                                <p>Belum ada data kerugian</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

            </div>

        </div>
    </main>

    <div class="version-badge">
        <i class="fas fa-code"></i> v3.1.3 - Fixed Logic
    </div>

   <script>
        // Loading Screen
        window.addEventListener('load', () => {
            setTimeout(() => {
                document.getElementById('loadingScreen').classList.add('hide');
            }, 800);
        });

        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');

        function toggleSidebar() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }

        sidebarToggle.addEventListener('click', toggleSidebar);
        mobileMenuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
        });

        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }

        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                    sidebar.classList.remove('mobile-open');
                }
            }
        });

        // Flash Message
        function closeFlash() {
            const flash = document.getElementById('flashMessage');
            if (flash) {
                flash.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => flash.remove(), 300);
            }
        }

        setTimeout(closeFlash, 5000);

        // Refresh button animation
        document.querySelectorAll('.refresh-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                this.querySelector('i').style.animation = 'spin 1s linear';
                setTimeout(() => {
                    this.querySelector('i').style.animation = '';
                }, 1000);
            });
        });

        console.log('%c SAZEN Investment Manager v3.1.3 ', 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 16px; padding: 10px; border-radius: 5px;');
        console.log('%c ✅ Fixed Logic: Keuntungan=Akumulatif | Kerugian=Terbaru ', 'color: #10b981; font-size: 12px; font-weight: bold;');
    </script>
</body>
</html>
