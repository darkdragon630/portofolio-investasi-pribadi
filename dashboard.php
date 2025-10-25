<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * Admin Dashboard - Updated with Cash Balance & Sales
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
$flash = get_flash_message();

/* ========================================
   STATISTIK GLOBAL
======================================== */
$sql_stats = "
    SELECT 
        COUNT(DISTINCT i.id) as total_portofolio,
        COALESCE(SUM(i.jumlah), 0) as total_investasi,
        COALESCE(SUM(ku_agg.total_keuntungan), 0) as total_keuntungan,
        COALESCE(SUM(kr_agg.total_kerugian), 0) as total_kerugian,
        (COALESCE(SUM(i.jumlah), 0) + COALESCE(SUM(ku_agg.total_keuntungan), 0) - COALESCE(SUM(kr_agg.total_kerugian), 0)) as total_nilai,
        COALESCE(SUM(ku_agg.jumlah_transaksi), 0) as total_transaksi_keuntungan,
        COALESCE(SUM(kr_agg.jumlah_transaksi), 0) as total_transaksi_kerugian,
        COUNT(DISTINCT k.id) as total_kategori
    FROM investasi i
    LEFT JOIN kategori k ON i.kategori_id = k.id
    LEFT JOIN (
        SELECT 
            investasi_id,
            SUM(jumlah_keuntungan) AS total_keuntungan,
            COUNT(*) AS jumlah_transaksi
        FROM keuntungan_investasi
        GROUP BY investasi_id
    ) ku_agg ON i.id = ku_agg.investasi_id
    LEFT JOIN (
        SELECT 
            investasi_id,
            SUM(jumlah_kerugian) AS total_kerugian,
            COUNT(*) AS jumlah_transaksi
        FROM kerugian_investasi
        GROUP BY investasi_id
    ) kr_agg ON i.id = kr_agg.investasi_id
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

// Get Cash Balance
$cash_balance = get_cash_balance($koneksi);
$saldo_kas = $cash_balance ? (float)$cash_balance['saldo_akhir'] : 0;

// Get Sales Statistics
$sales_stats = get_sales_statistics($koneksi);
$total_sales = $sales_stats ? (float)$sales_stats['total_penjualan'] : 0;
$sales_profit_loss = $sales_stats ? (float)$sales_stats['total_profit_loss'] : 0;

/* ========================================
   RECENT DATA
======================================== */
// Recent Investments
$sql_investasi = "
    SELECT 
        i.id,
        i.judul_investasi,
        i.deskripsi,
        i.jumlah as modal_investasi,
        i.tanggal_investasi,
        i.status,
        k.nama_kategori,
        COALESCE(ku_agg.total_keuntungan, 0) as total_keuntungan,
        COALESCE(kr_agg.total_kerugian, 0) as total_kerugian
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
    LIMIT 6
";
$investasi_list = $koneksi->query($sql_investasi)->fetchAll();

// Recent Cash Transactions
$cash_transactions = get_recent_cash_transactions($koneksi, 6);

// Recent Sales
$sales_list = get_sale_transactions($koneksi, 6);

// Profits & Losses
$keuntungan_list = $koneksi->query("
    SELECT ki.*, i.judul_investasi, k.nama_kategori
    FROM keuntungan_investasi ki
    JOIN investasi i ON ki.investasi_id = i.id
    JOIN kategori k ON ki.kategori_id = k.id
    ORDER BY ki.tanggal_keuntungan DESC LIMIT 6
")->fetchAll();

$kerugian_list = $koneksi->query("
    SELECT kr.*, i.judul_investasi, k.nama_kategori
    FROM kerugian_investasi kr
    JOIN investasi i ON kr.investasi_id = i.id
    JOIN kategori k ON kr.kategori_id = k.id
    ORDER BY kr.tanggal_kerugian DESC LIMIT 6
")->fetchAll();

/* ========================================
   CATEGORY & SOURCE STATS
======================================== */
$kategori_stats = $koneksi->query("
    SELECT 
        k.id, k.nama_kategori,
        COUNT(DISTINCT i.id) as jumlah_investasi,
        COALESCE(SUM(i.jumlah), 0) as total_investasi,
        COALESCE(SUM(ku_agg.total_keuntungan), 0) as total_keuntungan,
        COALESCE(SUM(kr_agg.total_kerugian), 0) as total_kerugian
    FROM kategori k
    LEFT JOIN investasi i ON k.id = i.kategori_id
    LEFT JOIN (SELECT investasi_id, SUM(jumlah_keuntungan) AS total_keuntungan 
               FROM keuntungan_investasi GROUP BY investasi_id) ku_agg ON i.id = ku_agg.investasi_id
    LEFT JOIN (SELECT investasi_id, SUM(jumlah_kerugian) AS total_kerugian 
               FROM kerugian_investasi GROUP BY investasi_id) kr_agg ON i.id = kr_agg.investasi_id
    GROUP BY k.id, k.nama_kategori
    HAVING jumlah_investasi > 0
    ORDER BY total_investasi DESC
")->fetchAll();

$cash_by_category = get_cash_by_category($koneksi);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - SAZEN v3.0</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

            <!-- Statistics Overview -->
            <section class="stats-overview">
                <div class="stats-grid">
                    <div class="stat-card stat-primary">
                        <div class="stat-header">
                            <div class="stat-icon"><i class="fas fa-briefcase"></i></div>
                            <div class="stat-trend positive">
                                <i class="fas fa-arrow-up"></i>
                                <span><?= $stats['total_portofolio'] ?></span>
                            </div>
                        </div>
                        <div class="stat-body">
                            <div class="stat-label">Total Investasi</div>
                            <div class="stat-value"><?= format_currency($total_investasi) ?></div>
                            <div class="stat-footer">
                                <span><?= $stats['total_portofolio'] ?> Portofolio Aktif</span>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card stat-success">
                        <div class="stat-header">
                            <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                            <div class="stat-trend <?= $saldo_kas >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $saldo_kas >= 0 ? 'up' : 'down' ?>"></i>
                            </div>
                        </div>
                        <div class="stat-body">
                            <div class="stat-label">Saldo Kas</div>
                            <div class="stat-value <?= $saldo_kas >= 0 ? 'positive' : 'negative' ?>"><?= format_currency($saldo_kas) ?></div>
                            <div class="stat-footer">
                                <span>Masuk: <?= format_currency($cash_balance ? $cash_balance['total_masuk'] : 0) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card stat-warning">
                        <div class="stat-header">
                            <div class="stat-icon"><i class="fas fa-handshake"></i></div>
                            <div class="stat-trend <?= $sales_profit_loss >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $sales_profit_loss >= 0 ? 'up' : 'down' ?>"></i>
                                <span><?= number_format($sales_stats ? $sales_stats['avg_roi'] : 0, 1) ?>%</span>
                            </div>
                        </div>
                        <div class="stat-body">
                            <div class="stat-label">Total Penjualan</div>
                            <div class="stat-value"><?= format_currency($total_sales) ?></div>
                            <div class="stat-footer">
                                <span>Profit: <?= format_currency($sales_profit_loss) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card stat-info">
                        <div class="stat-header">
                            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                            <div class="stat-trend <?= $roi_global >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $roi_global >= 0 ? 'up' : 'down' ?>"></i>
                                <span><?= number_format(abs($roi_global), 1) ?>%</span>
                            </div>
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
                            <p>Proses penjualan investasi</p>
                        </div>
                    </a>

                    <a href="admin/cash_balance.php" class="action-card">
                        <div class="action-icon success">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="action-content">
                            <h3>Kelola Kas</h3>
                            <p>Catat transaksi kas</p>
                        </div>
                    </a>

                    <a href="admin/laporan.php" class="action-card">
                        <div class="action-icon info">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="action-content">
                            <h3>Lihat Laporan</h3>
                            <p>Analisis & laporan</p>
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

        console.log('%c SAZEN Investment Manager v3.0 ', 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 16px; padding: 10px; border-radius: 5px;');
        console.log('%c Dashboard loaded successfully! ', 'color: #10b981; font-size: 12px; font-weight: bold;');
    </script>
</body>
</html>
