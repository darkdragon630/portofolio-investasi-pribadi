<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * ULTIMATE Dashboard - Gabungan Semua Fitur
 */

session_start();
require_once "config/koneksi.php";
require_once "config/functions.php";

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

// Get flash message
echo get_flash_message('success');
echo get_flash_message('error');

function get_all_flash_messages(): array
{
    if (empty($_SESSION['_flash'])) {
        return [];
    }
    $messages = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $messages;
}

/* Tampilkan semua pesan flash sekaligus */
foreach (get_all_flash_messages() as $type => $msg) {
    echo flash($type);   // fungsi flash() sudah otomatis memanggil get_flash_message($type)
}

/* ========================================
   STATISTIK GLOBAL & KAS
======================================== */
// Cash Balance
$cash_balance = get_cash_balance($koneksi);
$saldo_kas = $cash_balance ? (float)$cash_balance['saldo_akhir'] : 0;
$total_kas_masuk = $cash_balance ? (float)$cash_balance['total_masuk'] : 0;
$total_kas_keluar = $cash_balance ? (float)$cash_balance['total_keluar'] : 0;

// Sales Statistics
$sales_stats = get_sales_statistics($koneksi);
$total_sales = $sales_stats ? (float)$sales_stats['total_penjualan'] : 0;
$sales_profit_loss = $sales_stats ? (float)$sales_stats['total_profit_loss'] : 0;
$avg_roi_sales = $sales_stats ? (float)$sales_stats['avg_roi'] : 0;

/* ========================================
   QUERY INVESTASI (DENGAN STATUS)
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
        COALESCE(kr_agg.total_kerugian, 0) as total_kerugian,
        (i.jumlah + COALESCE(ku_agg.total_keuntungan, 0) - COALESCE(kr_agg.total_kerugian, 0)) as nilai_sekarang
    FROM investasi i
    JOIN kategori k ON i.kategori_id = k.id
    LEFT JOIN (
        SELECT investasi_id, SUM(jumlah_keuntungan) AS total_keuntungan
        FROM keuntungan_investasi GROUP BY investasi_id
    ) ku_agg ON i.id = ku_agg.investasi_id
    LEFT JOIN (
        SELECT investasi_id, SUM(jumlah_kerugian) AS total_kerugian
        FROM kerugian_investasi GROUP BY investasi_id
    ) kr_agg ON i.id = kr_agg.investasi_id
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
$total_kerugian_aktif = array_reduce($investasi_aktif, fn($carry, $inv) => $carry + $inv['total_kerugian'], 0);

// Total Aset
$total_aset = $saldo_kas + $total_nilai_investasi_aktif;
$persentase_kas = $total_aset > 0 ? ($saldo_kas / $total_aset * 100) : 0;
$persentase_investasi = $total_aset > 0 ? ($total_nilai_investasi_aktif / $total_aset * 100) : 0;

/* ========================================
   QUERY KEUNTUNGAN & KERUGIAN GLOBAL
======================================== */
$sql_total_keuntungan = "SELECT COALESCE(SUM(jumlah_keuntungan), 0) as total FROM keuntungan_investasi";
$stmt_total_keuntungan = $koneksi->query($sql_total_keuntungan);
$total_keuntungan_global = (float)$stmt_total_keuntungan->fetch()['total'];

$sql_total_kerugian = "SELECT COALESCE(SUM(jumlah_kerugian), 0) as total FROM kerugian_investasi";
$stmt_total_kerugian = $koneksi->query($sql_total_kerugian);
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
    $breakdown_kategori[$kategori]['kerugian'] += $inv['total_kerugian'];
    $breakdown_kategori[$kategori]['count']++;
}
uasort($breakdown_kategori, fn($a, $b) => $b['nilai'] - $a['nilai']);

/* ========================================
   BREAKDOWN SUMBER
======================================== */
$sql_sumber_keuntungan = "
    SELECT sumber_keuntungan, COUNT(*) as jumlah, SUM(jumlah_keuntungan) as total
    FROM keuntungan_investasi GROUP BY sumber_keuntungan ORDER BY total DESC
";
$sumber_keuntungan_stats = $koneksi->query($sql_sumber_keuntungan)->fetchAll();

$sql_sumber_kerugian = "
    SELECT sumber_kerugian, COUNT(*) as jumlah, SUM(jumlah_kerugian) as total
    FROM kerugian_investasi GROUP BY sumber_kerugian ORDER BY total DESC
";
$sumber_kerugian_stats = $koneksi->query($sql_sumber_kerugian)->fetchAll();

$total_transaksi_keuntungan = array_sum(array_column($sumber_keuntungan_stats, 'jumlah'));
$total_transaksi_kerugian = array_sum(array_column($sumber_kerugian_stats, 'jumlah'));

/* ========================================
   RECENT DATA
======================================== */
$investasi_list = array_slice($investasi_all, 0);
$cash_transactions = get_recent_cash_transactions($koneksi);
$sales_list = get_sale_transactions($koneksi);
$cash_by_category = get_cash_by_category($koneksi);

$keuntungan_list = $koneksi->query("
    SELECT ki.*, i.judul_investasi, k.nama_kategori
    FROM keuntungan_investasi ki
    JOIN investasi i ON ki.investasi_id = i.id
    JOIN kategori k ON ki.kategori_id = k.id
    ORDER BY ki.tanggal_keuntungan DESC 
")->fetchAll();

$kerugian_list = $koneksi->query("
    SELECT kr.*, i.judul_investasi, k.nama_kategori
    FROM kerugian_investasi kr
    JOIN investasi i ON kr.investasi_id = i.id
    JOIN kategori k ON kr.kategori_id = k.id
    ORDER BY kr.tanggal_kerugian DESC 
")->fetchAll();

$flash = $_SESSION['_flash'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
    <meta name="description" content="Dashboard Admin - SAZEN Investment Portfolio Manager Ultimate">
    <title>Dashboard Admin - SAZEN v3.0 Ultimate</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="icon" type="image/png" sizes="64x64" href="/Luminark_Holdings.png">
</head>
<body>

    <!-- Loading Screen -->
    <div class="loading-screen" id="loadingScreen">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-text">Memuat Dashboard...</div>
        </div>
    </div>

    <!-- Sidebar -->
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

    <!-- Main Content -->
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

        <!-- Flash Messages -->
        <?php if ($flash): ?>
            <div class="flash-message flash-<?= $flash['type'] ?>" id="flashMessage">
                <div class="flash-icon">
                    <i class="fas fa-<?= $flash['type'] == 'success' ? 'check-circle' : ($flash['type'] == 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
                </div>
                <div class="flash-content">
                    <div class="flash-text"><?= htmlspecialchars($flash['message']) ?></div>
                </div>
                <button class="flash-close" onclick="closeFlash()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <div class="container">

            <!-- Statistics Overview (8 Cards) -->
            <section class="stats-overview">
                <div class="stats-grid-enhanced">
                    <!-- Saldo Kas -->
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

                    <!-- Modal Investasi -->
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

                    <!-- Nilai Investasi -->
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

                    <!-- Total Aset -->
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

                    <!-- Total Keuntungan -->
                    <div class="stat-card stat-success">
                        <div class="stat-header">
                            <div class="stat-icon"><i class="fas fa-arrow-trend-up"></i></div>
                        </div>
                        <div class="stat-body">
                            <div class="stat-label">Total Keuntungan</div>
                            <div class="stat-value positive"><?= format_currency($total_keuntungan_global) ?></div>
                            <div class="stat-footer"><?= $total_transaksi_keuntungan ?> Transaksi</div>
                        </div>
                    </div>

                    <!-- Total Kerugian -->
                    <div class="stat-card stat-danger">
                        <div class="stat-header">
                            <div class="stat-icon"><i class="fas fa-arrow-trend-down"></i></div>
                        </div>
                        <div class="stat-body">
                            <div class="stat-label">Total Kerugian</div>
                            <div class="stat-value negative"><?= format_currency($total_kerugian_global) ?></div>
                            <div class="stat-footer"><?= $total_transaksi_kerugian ?> Transaksi</div>
                        </div>
                    </div>

                    <!-- Net Profit -->
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

                    <!-- Total Penjualan (NEW) -->
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

            <!-- Asset Allocation -->
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

            <!-- Quick Actions -->
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

            <!-- Cash Balance Overview -->
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

            <!-- Category Breakdown -->
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

            <!-- Analytics Grid -->
            <div class="dashboard-grid">
                <!-- Source Breakdown -->
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

            <!-- Recent Data Grid -->
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
                                                <a href="admin/view_cash.php?id=<?= $cash['id'] ?>"class="btn-icon" title="Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="admin/edit_cash.php?id=<?= $cash['id'] ?>" class="btn-icon" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="admin/delete_cash.php?id=<?= $cash['id'] ?>" class="btn-icon danger" title="Hapus" onclick="return confirm('Yakin hapus transaksi ini?')">
                                                <i class="fas fa-trash"></i>
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

    <!-- Scripts -->
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

        // Load sidebar state
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }

        // Close sidebar on mobile when clicking outside
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

        // Auto-hide flash message
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

        // Add animation to stat cards
        const statCards = document.querySelectorAll('.stat-card');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.style.animation = 'fadeInUp 0.5s ease forwards';
                    }, index * 50);
                }
            });
        }, { threshold: 0.1 });

        statCards.forEach(card => observer.observe(card));

        // Counter animation for stat values
        function animateValue(element, start, end, duration) {
            const range = end - start;
            const increment = range / (duration / 16);
            let current = start;
            
            const timer = setInterval(() => {
                current += increment;
                if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                    current = end;
                    clearInterval(timer);
                }
                
                const formatted = new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 2
                }).format(current);
                
                element.textContent = formatted;
            }, 16);
        }

        // Trigger counter animation when stat cards are visible
        const statValues = document.querySelectorAll('.stat-value');
        const valueObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !entry.target.classList.contains('animated')) {
                    entry.target.classList.add('animated');
                    const text = entry.target.textContent.replace(/[^\d,]/g, '').replace(',', '.');
                    const value = parseFloat(text) || 0;
                    entry.target.textContent = 'Rp 0,00';
                    setTimeout(() => {
                        animateValue(entry.target, 0, value, 2000);
                    }, 300);
                }
            });
        }, { threshold: 0.5 });

        statValues.forEach(value => valueObserver.observe(value));

        // Smooth scroll for internal links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // Add ripple effect to buttons
        document.querySelectorAll('.action-card, .btn-icon').forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                ripple.classList.add('ripple');
                this.appendChild(ripple);

                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;

                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';

                setTimeout(() => ripple.remove(), 600);
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                alert('Search functionality coming soon!');
            }
            
            if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                e.preventDefault();
                toggleSidebar();
            }
        });

        console.log('%c SAZEN Investment Manager v3.0 ULTIMATE ', 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 16px; padding: 10px; border-radius: 5px;');
        console.log('%c Dashboard ULTIMATE loaded successf<?php
/**
 * SAZEN Investment Portfolio Manager v3.1
 * ULTIMATE Dashboard - WITH AUTO CALCULATE INTEGRATION
 * 
 * UPDATED v3.1:
 * ✅ Integrated auto_calculate_investment.php
 * ✅ Added recalculate button for manual refresh
 * ✅ Shows last calculation timestamp
 * ✅ Enhanced stats with real-time data
 */

session_start();
require_once "config/koneksi.php";
require_once "config/functions.php";
require_once "config/auto_calculate_investment.php"; // ✅ NEW: Auto-calc integration

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

// ✅ NEW: Handle manual recalculation request
if (isset($_POST['recalculate_all'])) {
    try {
        $batch_result = batch_recalculate_all_investments($koneksi);
        if ($batch_result['success']) {
            set_flash_message('success', "✅ Recalculate berhasil! {$batch_result['updated_count']} investasi diupdate dalam " . 
                             number_format($batch_result['execution_time'], 2) . " detik");
        } else {
            set_flash_message('error', "❌ Gagal recalculate: " . $batch_result['error']);
        }
    } catch (Exception $e) {
        set_flash_message('error', "❌ Error: " . $e->getMessage());
    }
    header("Location: dashboard.php");
    exit;
}

// Get flash messages
function get_all_flash_messages(): array
{
    if (empty($_SESSION['_flash'])) {
        return [];
    }
    $messages = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $messages;
}

$flash_messages = get_all_flash_messages();

/* ========================================
   STATISTIK GLOBAL & KAS
======================================== */
// Cash Balance
$cash_balance = get_cash_balance($koneksi);
$saldo_kas = $cash_balance ? (float)$cash_balance['saldo_akhir'] : 0;
$total_kas_masuk = $cash_balance ? (float)$cash_balance['total_masuk'] : 0;
$total_kas_keluar = $cash_balance ? (float)$cash_balance['total_keluar'] : 0;

// Sales Statistics
$sales_stats = get_sales_statistics($koneksi);
$total_sales = $sales_stats ? (float)$sales_stats['total_penjualan'] : 0;
$sales_profit_loss = $sales_stats ? (float)$sales_stats['total_profit_loss'] : 0;
$avg_roi_sales = $sales_stats ? (float)$sales_stats['avg_roi'] : 0;

/* ========================================
   QUERY INVESTASI (DENGAN STATUS)
   ✅ UPDATED: Using v_investasi_summary view
======================================== */
$sql_investasi_all = "
    SELECT * FROM v_investasi_summary
    ORDER BY tanggal_investasi DESC
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
$total_kerugian_aktif = array_reduce($investasi_aktif, fn($carry, $inv) => $carry + $inv['total_kerugian'], 0);

// Total Aset
$total_aset = $saldo_kas + $total_nilai_investasi_aktif;
$persentase_kas = $total_aset > 0 ? ($saldo_kas / $total_aset * 100) : 0;
$persentase_investasi = $total_aset > 0 ? ($total_nilai_investasi_aktif / $total_aset * 100) : 0;

/* ========================================
   QUERY KEUNTUNGAN & KERUGIAN GLOBAL
======================================== */
$sql_total_keuntungan = "SELECT COALESCE(SUM(jumlah_keuntungan), 0) as total FROM keuntungan_investasi";
$stmt_total_keuntungan = $koneksi->query($sql_total_keuntungan);
$total_keuntungan_global = (float)$stmt_total_keuntungan->fetch()['total'];

$sql_total_kerugian = "SELECT COALESCE(SUM(jumlah_kerugian), 0) as total FROM kerugian_investasi";
$stmt_total_kerugian = $koneksi->query($sql_total_kerugian);
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
    $breakdown_kategori[$kategori]['kerugian'] += $inv['total_kerugian'];
    $breakdown_kategori[$kategori]['count']++;
}
uasort($breakdown_kategori, fn($a, $b) => $b['nilai'] - $a['nilai']);

/* ========================================
   BREAKDOWN SUMBER
======================================== */
$sql_sumber_keuntungan = "
    SELECT sumber_keuntungan, COUNT(*) as jumlah, SUM(jumlah_keuntungan) as total
    FROM keuntungan_investasi GROUP BY sumber_keuntungan ORDER BY total DESC
";
$sumber_keuntungan_stats = $koneksi->query($sql_sumber_keuntungan)->fetchAll();

$sql_sumber_kerugian = "
    SELECT sumber_kerugian, COUNT(*) as jumlah, SUM(jumlah_kerugian) as total
    FROM kerugian_investasi GROUP BY sumber_kerugian ORDER BY total DESC
";
$sumber_kerugian_stats = $koneksi->query($sql_sumber_kerugian)->fetchAll();

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
    ORDER BY ki.tanggal_keuntungan DESC 
    LIMIT 10
")->fetchAll();

$kerugian_list = $koneksi->query("
    SELECT kr.*, i.judul_investasi, k.nama_kategori
    FROM kerugian_investasi kr
    JOIN investasi i ON kr.investasi_id = i.id
    JOIN kategori k ON kr.kategori_id = k.id
    ORDER BY kr.tanggal_kerugian DESC 
    LIMIT 10
")->fetchAll();

// ✅ NEW: Get last update timestamp
$sql_last_update = "SELECT MAX(updated_at) as last_update FROM investasi WHERE status = 'aktif'";
$last_update = $koneksi->query($sql_last_update)->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
    <meta name="description" content="Dashboard Admin - SAZEN Investment Portfolio Manager Ultimate">
    <title>Dashboard Admin - SAZEN v3.1 Ultimate</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="icon" type="image/png" sizes="64x64" href="/Luminark_Holdings.png">
    
    <style>
        /* ✅ NEW: Recalculate button styles */
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
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .btn-recalculate:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .btn-recalculate:active {
            transform: translateY(0);
        }
        
        .btn-recalculate i {
            font-size: 16px;
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
        
        @media (max-width: 768px) {
            .recalculate-section {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }
            
            .recalculate-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .btn-recalculate {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <!-- Loading Screen -->
    <div class="loading-screen" id="loadingScreen">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-text">Memuat Dashboard...</div>
        </div>
    </div>

    <!-- Sidebar -->
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

    <!-- Main Content -->
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

        <!-- Flash Messages -->
        <?php foreach ($flash_messages as $type => $message): ?>
            <div class="flash-message flash-<?= $type ?>" id="flashMessage<?= $type ?>">
                <div class="flash-icon">
                    <i class="fas fa-<?= $type == 'success' ? 'check-circle' : ($type == 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
                </div>
                <div class="flash-content">
                    <div class="flash-text"><?= nl2br(htmlspecialchars($message)) ?></div>
                </div>
                <button class="flash-close" onclick="closeFlash('<?= $type ?>')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endforeach; ?>

        <div class="container">

            <!-- ✅ NEW: Recalculate Section -->
            <section class="recalculate-section">
                <div class="recalculate-info">
                    <h3><i class="fas fa-calculator"></i> Auto Calculate System</h3>
                    <p>Sistem menghitung ulang nilai investasi otomatis setiap ada transaksi. Anda juga bisa refresh manual.</p>
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

            <!-- Statistics Overview (8 Cards) -->
            <section class="stats-overview">
                <div class="stats-grid-enhanced">
                    <!-- Saldo Kas -->
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

                    <!-- Modal Investasi -->
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

                    <!-- Nilai Investasi -->
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

                    <!-- Total Aset -->
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

                    <!-- Total Keuntungan -->
                    <div class="stat-card stat-success">
                        <div class="stat-header">
                            <div class="stat-icon"><i class="fas fa-arrow-trend-up"></i></div>
                        </div>
                        <div class="stat-body">
                            <div class="stat-label">Total Keuntungan</div>
                            <div class="stat-value positive"><?= format_currency($total_keuntungan_global) ?></div>
                            <div class="stat-footer"><?= $total_transaksi_keuntungan ?> Transaksi</div>
                        </div>
                    </div>

                    <!-- Total Kerugian -->
                    <div class="stat-card stat-danger">
                        <div class="stat-header">
                            <div class="stat-icon"><i class="fas fa-arrow-trend-down"></i></div>
                        </div>
                        <div class="stat-body">
                            <div class="stat-label">Total Kerugian</div>
                            <div class="stat-value negative"><?= format_currency($total_kerugian_global) ?></div>
                            <div class="stat-footer"><?= $total_transaksi_kerugian ?> Transaksi</div>
                        </div>
                    </div>

                    <!-- Net Profit -->
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

                    <!-- Total Penjualan -->
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

            <!-- Asset Allocation -->
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

            <!-- Quick Actions -->
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

            <!-- Cash Balance Overview -->
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

            <!-- Category Breakdown -->
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

            <!-- Analytics Grid -->
            <div class="dashboard-grid">
                <!-- Source Breakdown -->
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

            <!-- Recent Data Grid -->
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
                                            <a href="admin/delete_cash.php?id=<?= $cash['id'] ?>" class="btn-icon danger" title="Hapus" onclick="return confirm('Yakin hapus transaksi ini?')">
                                                <i class="fas fa-trash"></i>
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

    <!-- Scripts -->
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

        // Load sidebar state
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }

        // Close sidebar on mobile when clicking outside
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                    sidebar.classList.remove('mobile-open');
                }
            }
        });

        // Flash Message
        function closeFlash(type) {
            const flash = document.getElementById('flashMessage' + type);
            if (flash) {
                flash.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => flash.remove(), 300);
            }
        }

        // Auto-hide flash messages
        setTimeout(() => {
            document.querySelectorAll('.flash-message').forEach(flash => {
                flash.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => flash.remove(), 300);
            });
        }, 5000);

        // Refresh button animation
        document.querySelectorAll('.refresh-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                this.querySelector('i').style.animation = 'spin 1s linear';
                setTimeout(() => {
                    this.querySelector('i').style.animation = '';
                }, 1000);
            });
        });

        // ✅ NEW: Recalculate button animation
        document.querySelectorAll('.btn-recalculate').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (confirm('Recalculate semua investasi aktif?')) {
                    this.querySelector('i').style.animation = 'spin 2s linear infinite';
                    this.disabled = true;
                    this.querySelector('span').textContent = 'Calculating...';
                } else {
                    e.preventDefault();
                }
            });
        });

        // Add animation to stat cards
        const statCards = document.querySelectorAll('.stat-card');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.style.animation = 'fadeInUp 0.5s ease forwards';
                    }, index * 50);
                }
            });
        }, { threshold: 0.1 });

        statCards.forEach(card => observer.observe(card));

        // Add ripple effect to buttons
        document.querySelectorAll('.action-card, .btn-icon, .btn-recalculate').forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                ripple.classList.add('ripple');
                this.appendChild(ripple);

                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;

                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';

                setTimeout(() => ripple.remove(), 600);
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                alert('Search functionality coming soon!');
            }
            
            if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                e.preventDefault();
                toggleSidebar();
            }
        });

        console.log('%c SAZEN Investment Manager v3.1 ', 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 16px; padding: 10px; border-radius: 5px;');
        console.log('%c Dashboard v3.1 with Auto Calculate loaded! ', 'color: #10b981; font-size: 12px; font-weight: bold;');
    </script>
</body>
</html>ully! ', 'color: #10b981; font-size: 12px; font-weight: bold;');
    </script>
</body>
</html>
