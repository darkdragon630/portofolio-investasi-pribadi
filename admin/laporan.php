<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * Laporan & Analytics Dashboard
 */

session_start();
require_once "../config/koneksi.php";
require_once "../config/functions.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

// Get user info
$username = $_SESSION['username'];
$email = $_SESSION['email'];

// Logout handler
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: auth.php");
    exit;
}

// Get flash message
$flash = get_flash_message();

// Date Range Filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-01-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

/* ========================================
   STATISTIK GLOBAL
======================================== */
// Cash Balance
$cash_balance = get_cash_balance($koneksi);
$saldo_kas = $cash_balance ? (float)$cash_balance['saldo_akhir'] : 0;

// Sales Statistics
$sales_stats = get_sales_statistics($koneksi);
$total_sales = $sales_stats ? (float)$sales_stats['total_penjualan'] : 0;
$sales_profit_loss = $sales_stats ? (float)$sales_stats['total_profit_loss'] : 0;

// Investment Statistics
$sql_investasi_all = "
    SELECT 
        i.id,
        i.judul_investasi,
        i.jumlah as modal_investasi,
        i.tanggal_investasi,
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
    WHERE i.tanggal_investasi BETWEEN :start_date AND :end_date
    ORDER BY i.tanggal_investasi DESC
";
$stmt = $koneksi->prepare($sql_investasi_all);
$stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$investasi_all = $stmt->fetchAll();

$investasi_aktif = array_filter($investasi_all, fn($inv) => ($inv['status'] ?? 'aktif') === 'aktif');
$total_modal = array_reduce($investasi_aktif, fn($carry, $inv) => $carry + $inv['modal_investasi'], 0);
$total_nilai_investasi = array_reduce($investasi_aktif, fn($carry, $inv) => $carry + $inv['nilai_sekarang'], 0);

// Total Keuntungan & Kerugian
$sql_keuntungan = "SELECT COALESCE(SUM(jumlah_keuntungan), 0) as total FROM keuntungan_investasi 
                   WHERE tanggal_keuntungan BETWEEN :start_date AND :end_date";
$stmt = $koneksi->prepare($sql_keuntungan);
$stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$total_keuntungan = (float)$stmt->fetch()['total'];

$sql_kerugian = "SELECT COALESCE(SUM(jumlah_kerugian), 0) as total FROM kerugian_investasi 
                 WHERE tanggal_kerugian BETWEEN :start_date AND :end_date";
$stmt = $koneksi->prepare($sql_kerugian);
$stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$total_kerugian = (float)$stmt->fetch()['total'];

$net_profit = $total_keuntungan - $total_kerugian;
$total_aset = $saldo_kas + $total_nilai_investasi;
$roi_global = $total_modal > 0 ? ($net_profit / $total_modal * 100) : 0;

/* ========================================
   BREAKDOWN PER KATEGORI
======================================== */
$sql_kategori = "
    SELECT 
        k.nama_kategori,
        COUNT(DISTINCT i.id) as jumlah_investasi,
        COALESCE(SUM(i.jumlah), 0) as total_modal,
        COALESCE(SUM(ku_agg.total_keuntungan), 0) as total_keuntungan,
        COALESCE(SUM(kr_agg.total_kerugian), 0) as total_kerugian,
        (COALESCE(SUM(i.jumlah), 0) + COALESCE(SUM(ku_agg.total_keuntungan), 0) - COALESCE(SUM(kr_agg.total_kerugian), 0)) as nilai_total
    FROM kategori k
    LEFT JOIN investasi i ON k.id = i.kategori_id AND i.status = 'aktif'
    LEFT JOIN (
        SELECT investasi_id, SUM(jumlah_keuntungan) AS total_keuntungan
        FROM keuntungan_investasi GROUP BY investasi_id
    ) ku_agg ON i.id = ku_agg.investasi_id
    LEFT JOIN (
        SELECT investasi_id, SUM(jumlah_kerugian) AS total_kerugian
        FROM kerugian_investasi GROUP BY investasi_id
    ) kr_agg ON i.id = kr_agg.investasi_id
    GROUP BY k.id, k.nama_kategori
    HAVING jumlah_investasi > 0
    ORDER BY nilai_total DESC
";
$kategori_breakdown = $koneksi->query($sql_kategori)->fetchAll();

/* ========================================
   MONTHLY PERFORMANCE
======================================== */
$sql_monthly = "
    SELECT 
        DATE_FORMAT(tanggal_keuntungan, '%Y-%m') as bulan,
        SUM(jumlah_keuntungan) as total_keuntungan
    FROM keuntungan_investasi
    WHERE tanggal_keuntungan BETWEEN :start_date AND :end_date
    GROUP BY bulan
    ORDER BY bulan ASC
";
$stmt = $koneksi->prepare($sql_monthly);
$stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$monthly_profit = $stmt->fetchAll();

$sql_monthly_loss = "
    SELECT 
        DATE_FORMAT(tanggal_kerugian, '%Y-%m') as bulan,
        SUM(jumlah_kerugian) as total_kerugian
    FROM kerugian_investasi
    WHERE tanggal_kerugian BETWEEN :start_date AND :end_date
    GROUP BY bulan
    ORDER BY bulan ASC
";
$stmt = $koneksi->prepare($sql_monthly_loss);
$stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$monthly_loss = $stmt->fetchAll();

/* ========================================
   TOP PERFORMERS
======================================== */
$sql_top = "
    SELECT 
        i.judul_investasi,
        k.nama_kategori,
        i.jumlah as modal,
        COALESCE(ku_agg.total_keuntungan, 0) as keuntungan,
        COALESCE(kr_agg.total_kerugian, 0) as kerugian,
        (COALESCE(ku_agg.total_keuntungan, 0) - COALESCE(kr_agg.total_kerugian, 0)) as net_profit,
        CASE 
            WHEN i.jumlah > 0 THEN ((COALESCE(ku_agg.total_keuntungan, 0) - COALESCE(kr_agg.total_kerugian, 0)) / i.jumlah * 100)
            ELSE 0
        END as roi
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
    WHERE i.status = 'aktif'
    ORDER BY roi DESC
    LIMIT 10
";
$top_performers = $koneksi->query($sql_top)->fetchAll();

/* ========================================
   CASH FLOW SUMMARY
======================================== */
$cash_by_category = get_cash_by_category($koneksi);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan & Analisis - SAZEN v3.0</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        /* Additional Styles for Laporan */
        .filter-section {
            background: var(--surface-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }

        .form-group {
            flex: 1;
            min-width: 200px;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--surface-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            color: var(--text-primary);
            font-size: 0.9375rem;
            transition: all var(--transition-fast);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: var(--surface-hover);
        }

        .btn-primary {
            padding: 0.75rem 2rem;
            background: var(--primary-color);
            border: none;
            border-radius: var(--radius-md);
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            padding: 0.75rem 2rem;
            background: var(--surface-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            color: var(--text-primary);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .btn-secondary:hover {
            background: var(--surface-hover);
        }

        .chart-container {
            background: var(--surface-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .performance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .performance-table th {
            background: var(--surface-secondary);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .performance-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .performance-table tr:hover {
            background: var(--surface-secondary);
        }

        .roi-badge {
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.875rem;
        }

        .roi-positive {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .roi-negative {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .export-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
            }

            .form-group {
                width: 100%;
            }

            .performance-table {
                font-size: 0.875rem;
            }

            .performance-table th,
            .performance-table td {
                padding: 0.75rem 0.5rem;
            }
        }
    </style>
</head>
<body>

    <!-- Loading Screen -->
    <div class="loading-screen" id="loadingScreen">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-text">Memuat Laporan...</div>
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
            <a href="../dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span class="nav-text">Dashboard</span>
            </a>
            <a href="../index.php" class="nav-item" target="_blank">
                <i class="fas fa-eye"></i>
                <span class="nav-text">Lihat Portfolio</span>
            </a>
            
            <div class="nav-divider"><span>Manajemen Data</span></div>
            
            <a href="upload_investasi.php" class="nav-item">
                <i class="fas fa-plus-circle"></i>
                <span class="nav-text">Tambah Investasi</span>
            </a>
            <a href="upload_keuntungan.php" class="nav-item">
                <i class="fas fa-arrow-trend-up"></i>
                <span class="nav-text">Tambah Keuntungan</span>
            </a>
            <a href="upload_kerugian.php" class="nav-item">
                <i class="fas fa-arrow-trend-down"></i>
                <span class="nav-text">Tambah Kerugian</span>
            </a>
            <a href="transaksi_jual.php" class="nav-item">
                <i class="fas fa-handshake"></i>
                <span class="nav-text">Jual Investasi</span>
            </a>
            <a href="cash_balance.php" class="nav-item">
                <i class="fas fa-wallet"></i>
                <span class="nav-text">Kelola Kas</span>
            </a>
            
            <div class="nav-divider"><span>Lainnya</span></div>
            
            <a href="laporan.php" class="nav-item active">
                <i class="fas fa-chart-bar"></i>
                <span class="nav-text">Laporan</span>
            </a>
            <a href="pengaturan.php" class="nav-item">
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
                    <h1>Laporan & Analisis</h1>
                    <p>Periode: <?= date('d M Y', strtotime($start_date)) ?> - <?= date('d M Y', strtotime($end_date)) ?></p>
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
                    <i class="fas fa-<?= $flash['type'] == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
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

            <!-- Filter Section -->
            <section class="filter-section">
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-input" value="<?= $start_date ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Akhir</label>
                        <input type="date" name="end_date" class="form-input" value="<?= $end_date ?>" required>
                    </div>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <button type="button" class="btn-secondary" onclick="window.location.href='laporan.php'">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </form>
            </section>

            <!-- Export Buttons -->
            <div class="export-buttons">
                <button class="btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Cetak Laporan
                </button>
                <button class="btn-secondary" onclick="exportToExcel()">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
            </div>

            <!-- Summary Statistics -->
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
                            <div class="stat-footer"><?= number_format(($total_aset > 0 ? ($saldo_kas / $total_aset * 100) : 0), 1) ?>% dari total aset</div>
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
                            <div class="stat-value"><?= format_currency($total_modal) ?></div>
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
                            <div class="stat-value"><?= format_currency($total_nilai_investasi) ?></div>
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
                            <div class="stat-value positive"><?= format_currency($total_keuntungan) ?></div>
                            <div class="stat-footer">Periode Terpilih</div>
                        </div>
                    </div>

                    <!-- Total Kerugian -->
                    <div class="stat-card stat-danger">
                        <div class="stat-header">
                            <div class="stat-icon"><i class="fas fa-arrow-trend-down"></i></div>
                        </div>
                        <div class="stat-body">
                            <div class="stat-label">Total Kerugian</div>
                            <div class="stat-value negative"><?= format_currency($total_kerugian) ?></div>
                            <div class="stat-footer">Periode Terpilih</div>
                        </div>
                    </div>

                    <!-- Net Profit -->
                    <div class="stat-card stat-gradient">
                        <div class="stat-header">
                            <div class="stat-icon"><i class="fas fa-trophy"></i></div>
                            <div class="stat-trend <?= $net_profit >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $net_profit >= 0 ? 'up' : 'down' ?>"></i>
                                <span><?= number_format(abs($roi_global), 1) ?>%</span>
                            </div>
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
                                <span><?= $sales_stats ? number_format($sales_stats['avg_roi'], 1) : '0.0' ?>%</span>
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

            <!-- Category Breakdown -->
            <?php if (count($kategori_breakdown) > 0): ?>
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-layer-group"></i>
                        Performance per Kategori
                    </h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="performance-table">
                            <thead>
                                <tr>
                                    <th>Kategori</th>
                                    <th>Jumlah</th>
                                    <th>Modal</th>
                                    <th>Keuntungan</th>
                                    <th>Kerugian</th>
                                    <th>Nilai Total</th>
                                    <th>ROI</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($kategori_breakdown as $kat): 
                                    $roi = $kat['total_modal'] > 0 ? (($kat['total_keuntungan'] - $kat['total_kerugian']) / $kat['total_modal'] * 100) : 0;
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($kat['nama_kategori']) ?></strong></td>
                                    <td><?= $kat['jumlah_investasi'] ?> inv</td>
                                    <td><?= format_currency($kat['total_modal']) ?></td>
                                    <td class="positive">+<?= format_currency($kat['total_keuntungan']) ?></td>
                                    <td class="negative">-<?= format_currency($kat['total_kerugian']) ?></td>
                                    <td><strong><?= format_currency($kat['nilai_total']) ?></strong></td>
                                    <td>
                                        <span class="roi-badge <?= $roi >= 0 ? 'roi-positive' : 'roi-negative' ?>">
                                            <?= $roi >= 0 ? '+' : '' ?><?= number_format($roi, 2) ?>%
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Top Performers -->
            <?php if (count($top_performers) > 0): ?>
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-star"></i>
                        Top 10 Investasi Terbaik
                    </h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="performance-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Judul Investasi</th>
                                    <th>Kategori</th>
                                    <th>Modal</th>
                                    <th>Net Profit</th>
                                    <th>ROI</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_performers as $index => $inv): ?>
                                <tr>
                                    <td><strong><?= $index + 1 ?></strong></td>
                                    <td><?= htmlspecialchars($inv['judul_investasi']) ?></td>
                                    <td><?= htmlspecialchars($inv['nama_kategori']) ?></td>
                                    <td><?= format_currency($inv['modal']) ?></td>
                                    <td class="<?= $inv['net_profit'] >= 0 ? 'positive' : 'negative' ?>">
                                        <?= $inv['net_profit'] >= 0 ? '+' : '' ?><?= format_currency($inv['net_profit']) ?>
                                    </td>
                                    <td>
                                        <span class="roi-badge <?= $inv['roi'] >= 0 ? 'roi-positive' : 'roi-negative' ?>">
                                            <?= $inv['roi'] >= 0 ? '+' : '' ?><?= number_format($inv['roi'], 2) ?>%
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Cash Flow by Category -->
            <?php if (count($cash_by_category) > 0): ?>
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-coins"></i>
                        Cash Flow per Kategori
                    </h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="performance-table">
                            <thead>
                                <tr>
                                    <th>Kategori</th>
                                    <th>Kas Masuk</th>
                                    <th>Kas Keluar</th>
                                    <th>Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cash_by_category as $cash): ?>
                                <tr>
                                    <td><strong><?= ucfirst(str_replace('_', ' ', $cash['kategori'])) ?></strong></td>
                                    <td class="positive">+<?= format_currency($cash['total_masuk']) ?></td>
                                    <td class="negative">-<?= format_currency($cash['total_keluar']) ?></td>
                                    <td class="<?= $cash['saldo'] >= 0 ? 'positive' : 'negative' ?>">
                                        <strong><?= format_currency($cash['saldo']) ?></strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Monthly Performance Chart -->
            <?php if (count($monthly_profit) > 0 || count($monthly_loss) > 0): ?>
            <section class="chart-container">
                <h2 class="section-title">
                    <i class="fas fa-chart-bar"></i>
                    Performance Bulanan
                </h2>
                <canvas id="monthlyChart" style="max-height: 400px;"></canvas>
            </section>
            <?php endif; ?>

        </div>
    </main>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // Loading Screen
        window.addEventListener('load', () => {
            setTimeout(() => {
                document.getElementById('loadingScreen').classList.add('hide');
            }, 500);
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

        // Monthly Performance Chart
        <?php if (count($monthly_profit) > 0 || count($monthly_loss) > 0): ?>
        const monthlyData = {
            profit: <?= json_encode($monthly_profit) ?>,
            loss: <?= json_encode($monthly_loss) ?>
        };

        // Prepare chart data
        const allMonths = new Set();
        monthlyData.profit.forEach(item => allMonths.add(item.bulan));
        monthlyData.loss.forEach(item => allMonths.add(item.bulan));
        const sortedMonths = Array.from(allMonths).sort();

        const profitData = sortedMonths.map(month => {
            const found = monthlyData.profit.find(p => p.bulan === month);
            return found ? parseFloat(found.total_keuntungan) : 0;
        });

        const lossData = sortedMonths.map(month => {
            const found = monthlyData.loss.find(l => l.bulan === month);
            return found ? parseFloat(found.total_kerugian) : 0;
        });

        // Format month labels
        const labels = sortedMonths.map(month => {
            const [year, monthNum] = month.split('-');
            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            return monthNames[parseInt(monthNum) - 1] + ' ' + year;
        });

        // Create chart
        const ctx = document.getElementById('monthlyChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Keuntungan',
                            data: profitData,
                            backgroundColor: 'rgba(16, 185, 129, 0.7)',
                            borderColor: 'rgba(16, 185, 129, 1)',
                            borderWidth: 2
                        },
                        {
                            label: 'Kerugian',
                            data: lossData,
                            backgroundColor: 'rgba(239, 68, 68, 0.7)',
                            borderColor: 'rgba(239, 68, 68, 1)',
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: '#cbd5e1',
                                font: {
                                    size: 12,
                                    family: 'Inter'
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            titleColor: '#f8fafc',
                            bodyColor: '#cbd5e1',
                            borderColor: '#334155',
                            borderWidth: 1,
                            padding: 12,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += new Intl.NumberFormat('id-ID', {
                                            style: 'currency',
                                            currency: 'IDR',
                                            minimumFractionDigits: 0
                                        }).format(context.parsed.y);
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                color: '#334155',
                                drawBorder: false
                            },
                            ticks: {
                                color: '#94a3b8',
                                font: {
                                    size: 11
                                }
                            }
                        },
                        y: {
                            grid: {
                                color: '#334155',
                                drawBorder: false
                            },
                            ticks: {
                                color: '#94a3b8',
                                font: {
                                    size: 11
                                },
                                callback: function(value) {
                                    return new Intl.NumberFormat('id-ID', {
                                        notation: 'compact',
                                        compactDisplay: 'short'
                                    }).format(value);
                                }
                            }
                        }
                    }
                }
            });
        }
        <?php endif; ?>

        // Export to Excel (Simple CSV)
        function exportToExcel() {
            const filename = 'Laporan_SAZEN_<?= date('Y-m-d') ?>.csv';
            
            let csv = 'Laporan Investasi SAZEN\n';
            csv += 'Periode: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?>\n\n';
            
            csv += 'RINGKASAN\n';
            csv += 'Saldo Kas,<?= $saldo_kas ?>\n';
            csv += 'Total Investasi,<?= $total_nilai_investasi ?>\n';
            csv += 'Total Aset,<?= $total_aset ?>\n';
            csv += 'Total Keuntungan,<?= $total_keuntungan ?>\n';
            csv += 'Total Kerugian,<?= $total_kerugian ?>\n';
            csv += 'Net Profit,<?= $net_profit ?>\n';
            csv += 'ROI Global,<?= number_format($roi_global, 2) ?>%\n\n';
            
            <?php if (count($kategori_breakdown) > 0): ?>
            csv += 'PERFORMANCE PER KATEGORI\n';
            csv += 'Kategori,Jumlah,Modal,Keuntungan,Kerugian,Nilai Total,ROI\n';
            <?php foreach ($kategori_breakdown as $kat): 
                $roi = $kat['total_modal'] > 0 ? (($kat['total_keuntungan'] - $kat['total_kerugian']) / $kat['total_modal'] * 100) : 0;
            ?>
            csv += '<?= addslashes($kat['nama_kategori']) ?>,<?= $kat['jumlah_investasi'] ?>,<?= $kat['total_modal'] ?>,<?= $kat['total_keuntungan'] ?>,<?= $kat['total_kerugian'] ?>,<?= $kat['nilai_total'] ?>,<?= number_format($roi, 2) ?>%\n';
            <?php endforeach; ?>
            csv += '\n';
            <?php endif; ?>
            
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }

        // Print styles
        window.addEventListener('beforeprint', () => {
            document.querySelectorAll('.sidebar, .content-header, .filter-section, .export-buttons, .data-actions').forEach(el => {
                el.style.display = 'none';
            });
        });

        window.addEventListener('afterprint', () => {
            document.querySelectorAll('.sidebar, .content-header, .filter-section, .export-buttons, .data-actions').forEach(el => {
                el.style.display = '';
            });
        });

        console.log('%c SAZEN Laporan & Analisis v3.0 ', 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 16px; padding: 10px; border-radius: 5px;');
    </script>
</body>
</html>
