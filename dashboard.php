<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * Admin Dashboard
 */

session_start();
require_once "config/koneksi.php";

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
    log_security_event("LOGOUT", "User: $username");
    session_destroy();
    header("Location: admin/auth.php");
    exit;
}

// Get flash message
$flash = get_flash_message();

/* ========================================
   STATISTIK GLOBAL
======================================== */
$sql_stats = "
    SELECT 
        COUNT(DISTINCT i.id) as total_portofolio,
        COALESCE(SUM(i.jumlah), 0) as total_investasi,
        COALESCE(SUM(ku.jumlah_keuntungan), 0) as total_keuntungan,
        COALESCE(SUM(kr.jumlah_kerugian), 0) as total_kerugian,
        (COALESCE(SUM(i.jumlah), 0) + COALESCE(SUM(ku.jumlah_keuntungan), 0) - COALESCE(SUM(kr.jumlah_kerugian), 0)) as total_nilai,
        COUNT(DISTINCT ku.id) as total_transaksi_keuntungan,
        COUNT(DISTINCT kr.id) as total_transaksi_kerugian,
        COUNT(DISTINCT k.id) as total_kategori
    FROM investasi i
    LEFT JOIN keuntungan_investasi ku ON i.id = ku.investasi_id
    LEFT JOIN kerugian_investasi kr ON i.id = kr.investasi_id
    LEFT JOIN kategori k ON i.kategori_id = k.id
";
$stmt_stats = $koneksi->query($sql_stats);
$stats = $stmt_stats->fetch();

// Calculate additional metrics
$total_investasi = (float)$stats['total_investasi'];
$total_keuntungan = (float)$stats['total_keuntungan'];
$total_kerugian = (float)$stats['total_kerugian'];
$total_nilai = (float)$stats['total_nilai'];
$net_profit = $total_keuntungan - $total_kerugian;
$roi_global = $total_investasi > 0 ? ($net_profit / $total_investasi * 100) : 0;

// Calculate trend percentages (compare with previous period if you have historical data)
// For now, we'll show trend only if there's actual data
$investasi_trend = $total_investasi > 0 ? ($total_keuntungan / $total_investasi * 100) : 0;
$keuntungan_trend = $total_keuntungan > 0 ? 100 : 0; // Placeholder - can be calculated from historical data
$kerugian_trend = $total_kerugian > 0 ? 100 : 0; // Placeholder - can be calculated from historical data

/* ========================================
   INVESTASI TERBARU
======================================== */
$sql_investasi = "
    SELECT 
        i.id,
        i.judul_investasi,
        i.deskripsi,
        i.jumlah as modal_investasi,
        i.tanggal_investasi,
        i.bukti_file,
        k.nama_kategori,
        COALESCE(SUM(ku.jumlah_keuntungan), 0) as total_keuntungan,
        COALESCE(SUM(kr.jumlah_kerugian), 0) as total_kerugian,
        (i.jumlah + COALESCE(SUM(ku.jumlah_keuntungan), 0) - COALESCE(SUM(kr.jumlah_kerugian), 0)) as nilai_sekarang
    FROM investasi i
    LEFT JOIN keuntungan_investasi ku ON i.id = ku.investasi_id
    LEFT JOIN kerugian_investasi kr ON i.id = kr.investasi_id
    JOIN kategori k ON i.kategori_id = k.id
    GROUP BY i.id, i.judul_investasi, i.deskripsi, i.jumlah, i.tanggal_investasi, i.bukti_file, k.nama_kategori
    ORDER BY i.tanggal_investasi DESC
    LIMIT 6
";
$stmt_investasi = $koneksi->query($sql_investasi);
$investasi_list = $stmt_investasi->fetchAll();

/* ========================================
   KEUNTUNGAN TERBARU
======================================== */
$sql_keuntungan = "
    SELECT 
        ki.id,
        ki.judul_keuntungan,
        ki.jumlah_keuntungan,
        ki.persentase_keuntungan,
        ki.tanggal_keuntungan,
        ki.sumber_keuntungan,
        ki.status,
        i.judul_investasi,
        k.nama_kategori
    FROM keuntungan_investasi ki
    JOIN investasi i ON ki.investasi_id = i.id
    JOIN kategori k ON ki.kategori_id = k.id
    ORDER BY ki.tanggal_keuntungan DESC
    LIMIT 6
";
$stmt_keuntungan = $koneksi->query($sql_keuntungan);
$keuntungan_list = $stmt_keuntungan->fetchAll();

/* ========================================
   KERUGIAN TERBARU
======================================== */
$sql_kerugian = "
    SELECT 
        kr.id,
        kr.judul_kerugian,
        kr.jumlah_kerugian,
        kr.persentase_kerugian,
        kr.tanggal_kerugian,
        kr.sumber_kerugian,
        kr.status,
        i.judul_investasi,
        k.nama_kategori
    FROM kerugian_investasi kr
    JOIN investasi i ON kr.investasi_id = i.id
    JOIN kategori k ON kr.kategori_id = k.id
    ORDER BY kr.tanggal_kerugian DESC
    LIMIT 6
";
$stmt_kerugian = $koneksi->query($sql_kerugian);
$kerugian_list = $stmt_kerugian->fetchAll();

/* ========================================
   STATISTIK PER KATEGORI
======================================== */
$sql_kategori = "
    SELECT 
        k.id,
        k.nama_kategori,
        COUNT(DISTINCT i.id) as jumlah_investasi,
        COALESCE(SUM(i.jumlah), 0) as total_investasi,
        COALESCE(SUM(ku.jumlah_keuntungan), 0) as total_keuntungan,
        COALESCE(SUM(kr.jumlah_kerugian), 0) as total_kerugian,
        (COALESCE(SUM(i.jumlah), 0) + COALESCE(SUM(ku.jumlah_keuntungan), 0) - COALESCE(SUM(kr.jumlah_kerugian), 0)) as total_nilai
    FROM kategori k
    LEFT JOIN investasi i ON k.id = i.kategori_id
    LEFT JOIN keuntungan_investasi ku ON i.id = ku.investasi_id
    LEFT JOIN kerugian_investasi kr ON i.id = kr.investasi_id
    GROUP BY k.id, k.nama_kategori
    HAVING jumlah_investasi > 0
    ORDER BY total_nilai DESC
";
$stmt_kategori = $koneksi->query($sql_kategori);
$kategori_stats = $stmt_kategori->fetchAll();

/* ========================================
   BREAKDOWN SUMBER
======================================== */
$sql_sumber_keuntungan = "
    SELECT 
        sumber_keuntungan,
        COUNT(*) as jumlah,
        SUM(jumlah_keuntungan) as total
    FROM keuntungan_investasi
    GROUP BY sumber_keuntungan
    ORDER BY total DESC
";
$stmt_sumber_keuntungan = $koneksi->query($sql_sumber_keuntungan);
$sumber_keuntungan_stats = $stmt_sumber_keuntungan->fetchAll();

$sql_sumber_kerugian = "
    SELECT 
        sumber_kerugian,
        COUNT(*) as jumlah,
        SUM(jumlah_kerugian) as total
    FROM kerugian_investasi
    GROUP BY sumber_kerugian
    ORDER BY total DESC
";
$stmt_sumber_kerugian = $koneksi->query($sql_sumber_kerugian);
$sumber_kerugian_stats = $stmt_sumber_kerugian->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Dashboard Admin - SAZEN Investment Portfolio Manager">
    <title>Dashboard Admin - SAZEN v3.0</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/dashboard.css">
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
            
            <div class="nav-divider">
                <span>Manajemen Data</span>
            </div>
            
            <a href="admin/upload.php" class="nav-item">
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
            
            <div class="nav-divider">
                <span>Lainnya</span>
            </div>
            
            <a href="#" class="nav-item">
                <i class="fas fa-chart-bar"></i>
                <span class="nav-text">Laporan</span>
            </a>
            <a href="#" class="nav-item">
                <i class="fas fa-cog"></i>
                <span class="nav-text">Pengaturan</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
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
        
        <!-- Header -->
        <header class="content-header">
            <div class="header-left">
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="header-title">
                    <h1>Dashboard Admin</h1>
                    <p>Selamat datang kembali, <strong><?= htmlspecialchars($username) ?></strong></p>
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

<!-- Statistics Overview -->
            <section class="stats-overview">
                <div class="stats-grid">
                    <div class="stat-card stat-primary">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <?php if ($investasi_trend != 0): ?>
                            <div class="stat-trend <?= $investasi_trend >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $investasi_trend >= 0 ? 'up' : 'down' ?>"></i>
                                <span><?= $investasi_trend >= 0 ? '+' : '' ?><?= number_format($investasi_trend, 1) ?>%</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="stat-body">
                            <div class="stat-label">Total Investasi</div>
                            <div class="stat-value"><?= format_currency($total_investasi) ?></div>
                            <div class="stat-footer">
                                <span><?= $stats['total_portofolio'] ?> Portofolio</span>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card stat-success">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-arrow-trend-up"></i>
                            </div>
                            <?php if ($keuntungan_trend != 0): ?>
                            <div class="stat-trend <?= $keuntungan_trend >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $keuntungan_trend >= 0 ? 'up' : 'down' ?>"></i>
                                <span><?= $keuntungan_trend >= 0 ? '+' : '' ?><?= number_format($keuntungan_trend, 1) ?>%</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="stat-body">
                            <div class="stat-label">Total Keuntungan</div>
                            <div class="stat-value positive"><?= format_currency($total_keuntungan) ?></div>
                            <div class="stat-footer">
                                <span><?= $stats['total_transaksi_keuntungan'] ?> Transaksi</span>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card stat-danger">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-arrow-trend-down"></i>
                            </div>
                            <?php if ($kerugian_trend != 0): ?>
                            <div class="stat-trend <?= $kerugian_trend >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $kerugian_trend >= 0 ? 'up' : 'down' ?>"></i>
                                <span><?= $kerugian_trend >= 0 ? '+' : '' ?><?= number_format($kerugian_trend, 1) ?>%</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="stat-body">
                            <div class="stat-label">Total Kerugian</div>
                            <div class="stat-value negative"><?= format_currency($total_kerugian) ?></div>
                            <div class="stat-footer">
                                <span><?= $stats['total_transaksi_kerugian'] ?> Transaksi</span>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card stat-info">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <?php if ($roi_global != 0): ?>
                            <div class="stat-trend <?= $roi_global >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $roi_global >= 0 ? 'up' : 'down' ?>"></i>
                                <span><?= $roi_global >= 0 ? '+' : '' ?><?= number_format(abs($roi_global), 1) ?>%</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="stat-body">
                            <div class="stat-label">Total Nilai Portfolio</div>
                            <div class="stat-value"><?= format_currency($total_nilai) ?></div>
                            <div class="stat-footer">
                                <span>ROI: <?= number_format($roi_global, 2) ?>%</span>
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
                    <a href="admin/upload.php" class="action-card">
                        <div class="action-icon primary">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="action-content">
                            <h3>Tambah Investasi</h3>
                            <p>Catat investasi baru</p>
                        </div>
                    </a>

                    <a href="admin/upload_keuntungan.php" class="action-card">
                        <div class="action-icon success">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <div class="action-content">
                            <h3>Tambah Keuntungan</h3>
                            <p>Catat keuntungan investasi</p>
                        </div>
                    </a>

                    <a href="admin/upload_kerugian.php" class="action-card">
                        <div class="action-icon danger">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                        <div class="action-content">
                            <h3>Tambah Kerugian</h3>
                            <p>Catat kerugian investasi</p>
                        </div>
                    </a>

                    <a href="index.php" target="_blank" class="action-card">
                        <div class="action-icon info">
                            <i class="fas fa-eye"></i>
                        </div>
                        <div class="action-content">
                            <h3>Lihat Portfolio</h3>
                            <p>Tampilan publik</p>
                        </div>
                    </a>
                </div>
            </section>

            <!-- Analytics Section -->
            <div class="dashboard-grid">
                
                <!-- Category Performance -->
                <?php if (count($kategori_stats) > 0): ?>
                <section class="card category-performance">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-chart-pie"></i>
                            Performa per Kategori
                        </h2>
                        <button class="card-action" title="Refresh">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="category-list">
                            <?php foreach ($kategori_stats as $kat): 
                                $kat_roi = $kat['total_investasi'] > 0 ? (($kat['total_keuntungan'] - $kat['total_kerugian']) / $kat['total_investasi'] * 100) : 0;
                            ?>
                                <div class="category-item">
                                    <div class="category-header">
                                        <div class="category-info">
                                            <h4><?= htmlspecialchars($kat['nama_kategori']) ?></h4>
                                            <span><?= $kat['jumlah_investasi'] ?> investasi</span>
                                        </div>
                                        <div class="category-roi <?= $kat_roi >= 0 ? 'positive' : 'negative' ?>">
                                            <?= number_format($kat_roi, 2) ?>%
                                        </div>
                                    </div>
                                    <div class="category-values">
                                        <div class="value-item">
                                            <span class="label">Investasi</span>
                                            <span class="value"><?= format_currency($kat['total_investasi']) ?></span>
                                        </div>
                                        <div class="value-item">
                                            <span class="label">Keuntungan</span>
                                            <span class="value positive">+<?= format_currency($kat['total_keuntungan']) ?></span>
                                        </div>
                                        <div class="value-item">
                                            <span class="label">Kerugian</span>
                                            <span class="value negative">-<?= format_currency($kat['total_kerugian']) ?></span>
                                        </div>
                                        <div class="value-item">
                                            <span class="label">Nilai Total</span>
                                            <span class="value"><?= format_currency($kat['total_nilai']) ?></span>
                                        </div>
                                    </div>
                                    <div class="category-progress">
                                        <div class="progress-bar" style="width: <?= $total_nilai > 0 ? ($kat['total_nilai'] / $total_nilai * 100) : 0 ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
                <?php endif; ?>

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

            <!-- Recent Data -->
            <div class="recent-data-grid">
                
                <!-- Recent Investments -->
                <section class="card recent-investments">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-briefcase"></i>
                            Investasi Terbaru
                        </h2>
                        <a href="admin/upload.php" class="card-link">
                            Lihat Semua <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (count($investasi_list) > 0): ?>
                            <div class="data-table">
                                <?php foreach ($investasi_list as $inv): ?>
                                    <div class="data-row">
                                        <div class="data-main">
                                            <h4><?= htmlspecialchars($inv['judul_investasi']) ?></h4>
                                            <p><?= htmlspecialchars($inv['nama_kategori']) ?> • <?= date('d M Y', strtotime($inv['tanggal_investasi'])) ?></p>
                                        </div>
                                        <div class="data-value">
                                            <?= format_currency($inv['modal_investasi']) ?>
                                        </div>
                                        <div class="data-actions">
                                            <a href="admin/edit_investasi.php?id=<?= $inv['id'] ?>" class="btn-icon" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="admin/delete_investasi.php?id=<?= $inv['id'] ?>" class="btn-icon danger" title="Hapus" onclick="return confirm('Yakin hapus investasi ini?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
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

                <!-- Recent Profits -->
                <section class="card recent-profits">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-arrow-trend-up"></i>
                            Keuntungan Terbaru
                        </h2>
                        <a href="admin/upload_keuntungan.php" class="card-link">
                            Lihat Semua <i class="fas fa-arrow-right"></i>
                        </a>
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
                        <a href="admin/upload_kerugian.php" class="card-link">
                            Lihat Semua <i class="fas fa-arrow-right"></i>
                        </a>
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
        document.querySelector('.refresh-btn').addEventListener('click', function() {
            this.querySelector('i').style.animation = 'spin 1s linear';
            setTimeout(() => {
                this.querySelector('i').style.animation = '';
            }, 1000);
        });

        // Add animation to stat cards
        const statCards = document.querySelectorAll('.stat-card');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.style.animation = 'fadeInUp 0.5s ease forwards';
                    }, index * 100);
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
                
                // Format as currency
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
            // Ctrl/Cmd + K for search (placeholder)
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                alert('Search functionality coming soon!');
            }
            
            // Ctrl/Cmd + B for toggle sidebar
            if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                e.preventDefault();
                toggleSidebar();
            }
        });

        // Add tooltips to icon buttons
        document.querySelectorAll('[title]').forEach(element => {
            element.addEventListener('mouseenter', function() {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = this.getAttribute('title');
                document.body.appendChild(tooltip);
                
                const rect = this.getBoundingClientRect();
                tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + 'px';
                tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
                
                this._tooltip = tooltip;
            });
            
            element.addEventListener('mouseleave', function() {
                if (this._tooltip) {
                    this._tooltip.remove();
                    delete this._tooltip;
                }
            });
        });

        // Real-time clock (optional)
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('id-ID', { 
                hour: '2-digit', 
                minute: '2-digit',
                second: '2-digit'
            });
            const dateString = now.toLocaleDateString('id-ID', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            // You can display this in header if needed
            console.log(dateString, timeString);
        }
        
        setInterval(updateClock, 1000);
        updateClock();

        // Console welcome message
        console.log('%c SAZEN Investment Manager v3.0 ', 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 16px; padding: 10px; border-radius: 5px;');
        console.log('%c Dashboard loaded successfully! ', 'color: #10b981; font-size: 12px; font-weight: bold;');
    </script>
</body>
</html>