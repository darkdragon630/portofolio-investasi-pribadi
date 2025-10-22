<?php
require_once 'config/koneksi.php';

// Ambil statistik global
$sql_stats = "SELECT * FROM v_statistik_global";
$stmt_stats = $koneksi->query($sql_stats);
$stats = $stmt_stats->fetch();

// Ambil semua investasi dengan summary
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
        (i.jumlah + COALESCE(SUM(ku.jumlah_keuntungan), 0) - COALESCE(SUM(kr.jumlah_kerugian), 0)) as nilai_sekarang,
        CASE 
            WHEN i.jumlah > 0 THEN ((COALESCE(SUM(ku.jumlah_keuntungan), 0) - COALESCE(SUM(kr.jumlah_kerugian), 0)) / i.jumlah * 100)
            ELSE 0 
        END as roi_persen
    FROM investasi i
    LEFT JOIN keuntungan_investasi ku ON i.id = ku.investasi_id
    LEFT JOIN kerugian_investasi kr ON i.id = kr.investasi_id
    JOIN kategori k ON i.kategori_id = k.id
    GROUP BY i.id, i.judul_investasi, i.deskripsi, i.jumlah, i.tanggal_investasi, i.bukti_file, k.nama_kategori
    ORDER BY i.tanggal_investasi DESC
";
$stmt_investasi = $koneksi->query($sql_investasi);
$investasi_list = $stmt_investasi->fetchAll();

// Ambil semua kategori untuk filter
$sql_kategori = "SELECT * FROM kategori ORDER BY nama_kategori";
$stmt_kategori = $koneksi->query($sql_kategori);
$kategori_list = $stmt_kategori->fetchAll();

// Ambil keuntungan terbaru (5 teratas) - DENGAN STATUS
$sql_keuntungan = "
    SELECT 
        ki.id,
        ki.judul_keuntungan,
        ki.jumlah_keuntungan,
        ki.tanggal_keuntungan,
        ki.sumber_keuntungan,
        ki.status,
        i.judul_investasi,
        k.nama_kategori
    FROM keuntungan_investasi ki
    JOIN investasi i ON ki.investasi_id = i.id
    JOIN kategori k ON ki.kategori_id = k.id
    ORDER BY ki.tanggal_keuntungan DESC
    LIMIT 5
";
$stmt_keuntungan = $koneksi->query($sql_keuntungan);
$keuntungan_list = $stmt_keuntungan->fetchAll();

// Ambil kerugian terbaru (5 teratas) - DENGAN STATUS
$sql_kerugian = "
    SELECT 
        kr.id,
        kr.judul_kerugian,
        kr.jumlah_kerugian,
        kr.tanggal_kerugian,
        kr.sumber_kerugian,
        kr.status,
        i.judul_investasi,
        k.nama_kategori
    FROM kerugian_investasi kr
    JOIN investasi i ON kr.investasi_id = i.id
    JOIN kategori k ON kr.kategori_id = k.id
    ORDER BY kr.tanggal_kerugian DESC
    LIMIT 5
";
$stmt_kerugian = $koneksi->query($sql_kerugian);
$kerugian_list = $stmt_kerugian->fetchAll();

// Hitung ROI global
$total_investasi = $stats['total_investasi'] ?? 0;
$total_keuntungan = $stats['total_keuntungan'] ?? 0;
$total_kerugian = $stats['total_kerugian'] ?? 0;
$total_nilai = $stats['total_nilai'] ?? 0;
$net_profit = $total_keuntungan - $total_kerugian;
$roi_global = $total_investasi > 0 ? ($net_profit / $total_investasi * 100) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Portofolio Investasi Pribadi - Muhammad Burhanudin Syaifullah Azmi">
    <meta name="author" content="SAAZ">
    <title>Portofolio Investasi - Shoutaverse Capital Group v3.0</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Status Badge Styles */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 8px;
        }
        
        .status-badge.realized {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        
        .status-badge.unrealized {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }
        
        .status-badge i {
            font-size: 10px;
        }
        
        /* Transaction Item Enhancement */
        .transaction-item {
            position: relative;
        }
        
        .transaction-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            border-radius: 0 4px 4px 0;
            transition: all 0.3s ease;
        }
        
        .transaction-item.realized::before {
            background: linear-gradient(180deg, #10b981 0%, #059669 100%);
        }
        
        .transaction-item.unrealized::before {
            background: linear-gradient(180deg, #f59e0b 0%, #d97706 100%);
        }
        
        .transaction-content {
            flex: 1;
        }
        
        .transaction-header-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }
        
        /* Detail Modal Status */
        .detail-transaction {
            position: relative;
            border-left: 4px solid transparent;
        }
        
        .detail-transaction.realized {
            border-left-color: #10b981;
            background: linear-gradient(90deg, rgba(16, 185, 129, 0.05) 0%, transparent 100%);
        }
        
        .detail-transaction.unrealized {
            border-left-color: #f59e0b;
            background: linear-gradient(90deg, rgba(245, 158, 11, 0.05) 0%, transparent 100%);
        }
        
        .transaction-status-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    
    <!-- Loading Screen -->
    <div class="loading-screen" id="loadingScreen">
        <div class="loading-content">
            <div class="loading-logo">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="loading-spinner">
                <div class="spinner"></div>
            </div>
            <div class="loading-text">Memuat Portofolio...</div>
            <div class="loading-progress">
                <div class="progress-bar" id="loadingProgress"></div>
            </div>
        </div>
    </div>

    <!-- Header -->
    <header class="header" id="header">
        <div class="header-background">
            <div class="gradient-orb orb-1"></div>
            <div class="gradient-orb orb-2"></div>
            <div class="gradient-orb orb-3"></div>
        </div>
        
        <div class="container">
            <div class="header-content">
                <div class="logo-section">
                    <div class="logo-icon">
                        <i class="fas fa-chart-line"></i>
                        <div class="logo-pulse"></div>
                    </div>
                    <div class="logo-text">
                        <h1 class="logo-title">Shoutaverse Capital Group</h1>
                        <p class="logo-subtitle">Portfolio Manager v3.0</p>
                    </div>
                </div>
                
                <div class="header-info">
                    <h2 class="portfolio-title">
                        <span class="title-main">Portofolio Investasi Pribadi</span>
                        <span class="title-owner">Muhammad Burhanudin Syaifullah Azmi</span>
                    </h2>
                    <p class="portfolio-description">
                        <i class="fas fa-info-circle"></i>
                        Data diperbarui secara real-time dari dashboard admin
                    </p>
                </div>
            </div>
        </div>
        
        <div class="header-wave">
            <svg viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z" opacity=".25"></path>
                <path d="M0,0V15.81C13,36.92,27.64,56.86,47.69,72.05,99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5,22.43-10.89,48-26.93,60.65-49.24V0Z" opacity=".5"></path>
                <path d="M0,0V5.63C149.93,59,314.09,71.32,475.83,42.57c43-7.64,84.23-20.12,127.61-26.46,59-8.63,112.48,12.24,165.56,35.4C827.93,77.22,886,95.24,951.2,90c86.53-7,172.46-45.71,248.8-84.81V0Z"></path>
            </svg>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            
            <!-- Statistics Cards -->
            <section class="stats-section" data-aos="fade-up">
                <div class="stats-grid">
                    <div class="stat-card stat-primary">
                        <div class="stat-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Total Investasi</div>
                            <div class="stat-value" data-value="<?= $total_investasi ?>">
                                <?= format_currency($total_investasi) ?>
                            </div>
                            <div class="stat-sublabel"><?= $stats['total_portofolio'] ?? 0 ?> Portofolio</div>
                        </div>
                        <div class="stat-glow"></div>
                    </div>

                    <div class="stat-card stat-success">
                        <div class="stat-icon">
                            <i class="fas fa-arrow-trend-up"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Total Keuntungan</div>
                            <div class="stat-value positive" data-value="<?= $total_keuntungan ?>">
                                <?= format_currency($total_keuntungan) ?>
                            </div>
                            <div class="stat-sublabel"><?= $stats['total_transaksi_keuntungan'] ?? 0 ?> Transaksi</div>
                        </div>
                        <div class="stat-glow"></div>
                    </div>

                    <div class="stat-card stat-danger">
                        <div class="stat-icon">
                            <i class="fas fa-arrow-trend-down"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Total Kerugian</div>
                            <div class="stat-value negative" data-value="<?= $total_kerugian ?>">
                                <?= format_currency($total_kerugian) ?>
                            </div>
                            <div class="stat-sublabel"><?= $stats['total_transaksi_kerugian'] ?? 0 ?> Transaksi</div>
                        </div>
                        <div class="stat-glow"></div>
                    </div>

                    <div class="stat-card stat-info">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Total Nilai</div>
                            <div class="stat-value" data-value="<?= $total_nilai ?>">
                                <?= format_currency($total_nilai) ?>
                            </div>
                            <div class="stat-sublabel">
                                ROI: 
                                <span class="<?= $roi_global >= 0 ? 'positive' : 'negative' ?>">
                                    <?= number_format($roi_global, 2) ?>%
                                </span>
                            </div>
                        </div>
                        <div class="stat-glow"></div>
                    </div>
                </div>
            </section>

            <!-- Quick Analytics -->
            <section class="analytics-section" data-aos="fade-up" data-aos-delay="100">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-chart-pie"></i>
                        Analisis Cepat
                    </h2>
                </div>
                <div class="analytics-grid">
                    <div class="analytics-card">
                        <div class="analytics-header">
                            <h4>Net Profit</h4>
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="analytics-value <?= $net_profit >= 0 ? 'positive' : 'negative' ?>">
                            <?= format_currency($net_profit) ?>
                        </div>
                        <div class="analytics-footer">
                            Keuntungan - Kerugian
                        </div>
                    </div>

                    <div class="analytics-card">
                        <div class="analytics-header">
                            <h4>Performance</h4>
                            <i class="fas fa-percent"></i>
                        </div>
                        <div class="analytics-value <?= $roi_global >= 0 ? 'positive' : 'negative' ?>">
                            <?= number_format($roi_global, 2) ?>%
                        </div>
                        <div class="analytics-footer">
                            Return on Investment
                        </div>
                    </div>

                    <div class="analytics-card">
                        <div class="analytics-header">
                            <h4>Profit Ratio</h4>
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="analytics-value info">
                            <?php 
                            $profit_ratio = $total_nilai > 0 ? ($total_keuntungan / $total_nilai * 100) : 0;
                            echo number_format($profit_ratio, 2);
                            ?>%
                        </div>
                        <div class="analytics-footer">
                            Keuntungan / Total Nilai
                        </div>
                    </div>
                </div>
            </section>

            <!-- Recent Transactions -->
            <section class="transactions-section" data-aos="fade-up" data-aos-delay="200">
                <div class="transactions-container">
                    <!-- Keuntungan Terbaru -->
                    <div class="transaction-column">
                        <div class="transaction-header">
                            <h3><i class="fas fa-arrow-up"></i> Keuntungan Terbaru</h3>
                        </div>
                        <div class="transaction-list">
                            <?php if (count($keuntungan_list) > 0): ?>
                                <?php foreach ($keuntungan_list as $profit): ?>
                                    <?php 
                                    $isRealized = ($profit['status'] === 'realized');
                                    $statusClass = $isRealized ? 'realized' : 'unrealized';
                                    ?>
                                    <div class="transaction-item profit-item <?= $statusClass ?>">
                                        <div class="transaction-icon">
                                            <i class="fas fa-plus-circle"></i>
                                        </div>
                                        <div class="transaction-content">
                                            <div class="transaction-header-row">
                                                <h4><?= htmlspecialchars($profit['judul_keuntungan']) ?></h4>
                                                <span class="status-badge <?= $statusClass ?>">
                                                    <i class="fas fa-<?= $isRealized ? 'check-circle' : 'clock' ?>"></i>
                                                    <?= $isRealized ? 'Realized' : 'Unrealized' ?>
                                                </span>
                                            </div>
                                            <p><?= htmlspecialchars($profit['judul_investasi']) ?></p>
                                            <span class="transaction-badge"><?= ucfirst(str_replace('_', ' ', $profit['sumber_keuntungan'])) ?></span>
                                        </div>
                                        <div class="transaction-amount positive">
                                            +<?= format_currency($profit['jumlah_keuntungan']) ?>
                                        </div>
                                        <div class="transaction-date">
                                            <?= date('d M Y', strtotime($profit['tanggal_keuntungan'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>Belum ada data keuntungan</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Kerugian Terbaru -->
                    <div class="transaction-column">
                        <div class="transaction-header">
                            <h3><i class="fas fa-arrow-down"></i> Kerugian Terbaru</h3>
                        </div>
                        <div class="transaction-list">
                            <?php if (count($kerugian_list) > 0): ?>
                                <?php foreach ($kerugian_list as $loss): ?>
                                    <?php 
                                    $isRealized = ($loss['status'] === 'realized');
                                    $statusClass = $isRealized ? 'realized' : 'unrealized';
                                    ?>
                                    <div class="transaction-item loss-item <?= $statusClass ?>">
                                        <div class="transaction-icon">
                                            <i class="fas fa-minus-circle"></i>
                                        </div>
                                        <div class="transaction-content">
                                            <div class="transaction-header-row">
                                                <h4><?= htmlspecialchars($loss['judul_kerugian']) ?></h4>
                                                <span class="status-badge <?= $statusClass ?>">
                                                    <i class="fas fa-<?= $isRealized ? 'check-circle' : 'clock' ?>"></i>
                                                    <?= $isRealized ? 'Realized' : 'Unrealized' ?>
                                                </span>
                                            </div>
                                            <p><?= htmlspecialchars($loss['judul_investasi']) ?></p>
                                            <span class="transaction-badge"><?= ucfirst(str_replace('_', ' ', $loss['sumber_kerugian'])) ?></span>
                                        </div>
                                        <div class="transaction-amount negative">
                                            -<?= format_currency($loss['jumlah_kerugian']) ?>
                                        </div>
                                        <div class="transaction-date">
                                            <?= date('d M Y', strtotime($loss['tanggal_kerugian'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>Belum ada data kerugian</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Portfolio Controls -->
            <section class="portfolio-controls" data-aos="fade-up" data-aos-delay="300">
                <div class="controls-header">
                    <h2 class="section-title">
                        <i class="fas fa-briefcase"></i>
                        Daftar Investasi
                    </h2>
                    <div class="view-toggle">
                        <button class="toggle-btn active" data-view="grid" title="Grid View">
                            <i class="fas fa-th"></i>
                        </button>
                        <button class="toggle-btn" data-view="list" title="List View">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                </div>

                <div class="controls-filters">
                    <!-- Search -->
                    <div class="search-container">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" class="search-input" placeholder="Cari investasi...">
                    </div>

                    <!-- Category Filter -->
                    <div class="filter-group">
                        <label for="categoryFilter">
                            <i class="fas fa-filter"></i> Kategori
                        </label>
                        <select id="categoryFilter" class="filter-select">
                            <option value="all">Semua Kategori</option>
                            <?php foreach ($kategori_list as $kat): ?>
                                <option value="<?= htmlspecialchars($kat['nama_kategori']) ?>">
                                    <?= htmlspecialchars($kat['nama_kategori']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Sort -->
                    <div class="filter-group">
                        <label for="sortSelect">
                            <i class="fas fa-sort"></i> Urutkan
                        </label>
                        <select id="sortSelect" class="filter-select">
                            <option value="date-desc">Terbaru</option>
                            <option value="date-asc">Terlama</option>
                            <option value="amount-desc">Nilai Tertinggi</option>
                            <option value="amount-asc">Nilai Terendah</option>
                            <option value="roi-desc">ROI Tertinggi</option>
                            <option value="roi-asc">ROI Terendah</option>
                        </select>
                    </div>
                </div>
            </section>

            <!-- Investment Grid -->
            <section class="investments-section" data-aos="fade-up" data-aos-delay="400">
                <?php if (count($investasi_list) > 0): ?>
                    <div class="investments-grid" id="investmentsGrid">
                        <?php foreach ($investasi_list as $index => $item): ?>
                            <div class="investment-card" 
                                 data-category="<?= htmlspecialchars($item['nama_kategori']) ?>"
                                 data-amount="<?= $item['modal_investasi'] ?>"
                                 data-date="<?= $item['tanggal_investasi'] ?>"
                                 data-title="<?= htmlspecialchars($item['judul_investasi']) ?>"
                                 data-roi="<?= $item['roi_persen'] ?>"
                                 style="animation-delay: <?= $index * 0.05 ?>s">
                                
                                <div class="card-glow"></div>
                                
                                <div class="card-header">
                                    <div class="card-icon">
                                        <i class="fas fa-chart-area"></i>
                                    </div>
                                    <div class="category-badge">
                                        <i class="fas fa-tag"></i>
                                        <?= htmlspecialchars($item['nama_kategori']) ?>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <h3 class="card-title"><?= htmlspecialchars($item['judul_investasi']) ?></h3>
                                    
                                    <?php if (!empty($item['deskripsi'])): ?>
                                        <p class="card-description"><?= nl2br(htmlspecialchars($item['deskripsi'])) ?></p>
                                    <?php endif; ?>

                                    <div class="card-stats">
                                        <div class="stat-item">
                                            <span class="stat-label">Modal</span>
                                            <span class="stat-value"><?= format_currency($item['modal_investasi']) ?></span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-label">Nilai Sekarang</span>
                                            <span class="stat-value"><?= format_currency($item['nilai_sekarang']) ?></span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-label">Keuntungan</span>
                                            <span class="stat-value positive">+<?= format_currency($item['total_keuntungan']) ?></span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-label">Kerugian</span>
                                            <span class="stat-value negative">-<?= format_currency($item['total_kerugian']) ?></span>
                                        </div>
                                    </div>

                                    <div class="card-roi">
                                        <span class="roi-label">ROI</span>
                                        <span class="roi-value <?= $item['roi_persen'] >= 0 ? 'positive' : 'negative' ?>">
                                            <?= number_format($item['roi_persen'], 2) ?>%
                                        </span>
                                    </div>
                                </div>

                                <div class="card-footer">
                                    <div class="card-date">
                                        <i class="fas fa-calendar-alt"></i>
                                        <?= date('d M Y', strtotime($item['tanggal_investasi'])) ?>
                                    </div>
                                    <button class="btn-detail" 
                                            onclick="showInvestmentDetail(<?= $item['id'] ?>)"
                                            data-id="<?= $item['id'] ?>">
                                        <i class="fas fa-eye"></i> Detail
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-portfolio" data-aos="fade-up">
                        <div class="empty-icon">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <h3>Belum Ada Investasi</h3>
                        <p>Data investasi akan muncul di sini setelah ditambahkan</p>
                    </div>
                <?php endif; ?>
            </section>

        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-wave">
            <svg viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z" opacity=".25"></path>
                <path d="M0,0V15.81C13,36.92,27.64,56.86,47.69,72.05,99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5,22.43-10.89,48-26.93,60.65-49.24V0Z" opacity=".5"></path>
                <path d="M0,0V5.63C149.93,59,314.09,71.32,475.83,42.57c43-7.64,84.23-20.12,127.61-26.46,59-8.63,112.48,12.24,165.56,35.4C827.93,77.22,886,95.24,951.2,90c86.53-7,172.46-45.71,248.8-84.81V0Z"></path>
            </svg>
        </div>
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <div class="footer-logo">
                        <i class="fas fa-chart-line"></i>
                        <span>Shoutaverse Capital Group</span>
                    </div>
                    <p class="footer-tagline">Investment Portfolio Manager v3.0</p>
                    <p class="footer-copyright">
                        &copy; <?= date('Y') ?> Muhammad Burhanudin Syaifullah Azmi. All rights reserved.
                    </p>
                </div>

                <div class="footer-links">
                    <div class="footer-column">
                        <h4>Navigasi</h4>
                        <ul>
                            <li><a href="#header"><i class="fas fa-home"></i> Beranda</a></li>
                            <li><a href="#stats"><i class="fas fa-chart-bar"></i> Statistik</a></li>
                            <li><a href="#portfolio"><i class="fas fa-briefcase"></i> Portofolio</a></li>
                        </ul>
                    </div>

                    <div class="footer-column">
                        <h4>Tools</h4>
                        <ul>
                            <li><a href="https://kalkulasiinvest.netlify.app/" target="_blank">
                                <i class="fas fa-calculator"></i> Kalkulator ROI
                            </a></li>
                            <li><a href="#"><i class="fas fa-chart-pie"></i> Analisis</a></li>
                            <li><a href="#"><i class="fas fa-file-export"></i> Export Data</a></li>
                        </ul>
                    </div>

                    <div class="footer-column">
                        <h4>Support</h4>
                        <ul>
                            <li><a href="#"><i class="fas fa-question-circle"></i> FAQ</a></li>
                            <li><a href="#"><i class="fas fa-headset"></i> Bantuan</a></li>
                            <li><a href="#"><i class="fas fa-envelope"></i> Kontak</a></li>
                        </ul>
                    </div>

                    <div class="footer-column">
                        <h4>Info</h4>
                        <p class="footer-info">
                            <i class="fas fa-info-circle"></i>
                            Platform ini digunakan untuk mencatat dan memantau portofolio investasi pribadi secara real-time.
                        </p>
                        <div class="footer-stats">
                            <span><i class="fas fa-database"></i> <?= count($investasi_list) ?> Investasi</span>
                            <span><i class="fas fa-chart-line"></i> <?= count($keuntungan_list) ?> Profit</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <div class="footer-social">
                    <a href="#" aria-label="GitHub"><i class="fab fa-github"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                </div>
                <div class="footer-version">
                    <span class="version-badge">
                        <i class="fas fa-code-branch"></i> Version 3.0.0
                    </span>
                    <span class="update-info">
                        <i class="fas fa-clock"></i> Updated: <?= date('d M Y') ?>
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scroll to Top Button -->
    <button class="scroll-to-top" id="scrollToTop" aria-label="Scroll to top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Investment Detail Modal -->
    <div class="modal" id="investmentModal">
        <div class="modal-overlay" onclick="closeModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Detail Investasi</h3>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="modal-loading">
                    <div class="spinner"></div>
                    <p>Memuat detail...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/landing.js"></script>
    <script>
    // ---------- Loading Screen ----------
    window.addEventListener('load', function () {
        const loadingScreen = document.getElementById('loadingScreen');
        const progressBar = document.getElementById('loadingProgress');

        let progress = 0;
        const interval = setInterval(() => {
            progress += 10;
            progressBar.style.width = progress + '%';

            if (progress >= 100) {
                clearInterval(interval);
                setTimeout(() => {
                    loadingScreen.classList.add('fade-out');
                    setTimeout(() => {
                        loadingScreen.style.display = 'none';
                    }, 500);
                }, 300);
            }
        }, 50);
    });

    // ---------- View Toggle ----------
    document.querySelectorAll('.toggle-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const view = this.dataset.view;
            const grid = document.getElementById('investmentsGrid');

            if (view === 'list') {
                grid.classList.add('list-view');
                grid.classList.remove('grid-view');
            } else {
                grid.classList.add('grid-view');
                grid.classList.remove('list-view');
            }
        });
    });

    // ---------- Filter & Sort ----------
    document.getElementById('searchInput').addEventListener('input', filterInvestments);
    document.getElementById('categoryFilter').addEventListener('change', filterInvestments);
    document.getElementById('sortSelect').addEventListener('change', e => sortInvestments(e.target.value));

    function filterInvestments() {
        const keyword = document.getElementById('searchInput').value.toLowerCase();
        const category = document.getElementById('categoryFilter').value;
        const cards = document.querySelectorAll('.investment-card');

        cards.forEach(card => {
            const title = card.dataset.title.toLowerCase();
            const cardCategory = card.dataset.category;
            const matchSearch = title.includes(keyword);
            const matchCategory = category === 'all' || cardCategory === category;

            card.style.display = matchSearch && matchCategory ? 'block' : 'none';
        });
    }

    function sortInvestments(sortBy) {
        const grid = document.getElementById('investmentsGrid');
        const cards = Array.from(grid.querySelectorAll('.investment-card'));

        cards.sort((a, b) => {
            switch (sortBy) {
                case 'date-desc': return new Date(b.dataset.date) - new Date(a.dataset.date);
                case 'date-asc': return new Date(a.dataset.date) - new Date(b.dataset.date);
                case 'amount-desc': return parseFloat(b.dataset.amount) - parseFloat(a.dataset.amount);
                case 'amount-asc': return parseFloat(a.dataset.amount) - parseFloat(b.dataset.amount);
                case 'roi-desc': return parseFloat(b.dataset.roi) - parseFloat(a.dataset.roi);
                case 'roi-asc': return parseFloat(a.dataset.roi) - parseFloat(b.dataset.roi);
                default: return 0;
            }
        });

        cards.forEach(card => grid.appendChild(card));
    }

    // ---------- Scroll to Top ----------
    const scrollBtn = document.getElementById('scrollToTop');
    window.addEventListener('scroll', () => {
        scrollBtn.classList.toggle('show', window.pageYOffset > 300);
    });
    scrollBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

    // ---------- Modal ----------
    function showInvestmentDetail(id) {
        const modal = document.getElementById('investmentModal');
        const modalBody = document.getElementById('modalBody');
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';

        fetch(`get_investment_detail.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    displayInvestmentDetail(data.investment);
                } else {
                    modalBody.innerHTML = `<div class="modal-error"><i class="fas fa-exclamation-triangle"></i><p>${data.message}</p></div>`;
                }
            })
            .catch(() => {
                modalBody.innerHTML = `<div class="modal-error"><i class="fas fa-exclamation-triangle"></i><p>Terjadi kesalahan saat memuat data</p></div>`;
            });
    }

    function closeModal() {
        const modal = document.getElementById('investmentModal');
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
    document.addEventListener('keydown', e => e.key === 'Escape' && closeModal());

    // ---------- Render Detail with Realized/Unrealized Status ----------
    function displayInvestmentDetail(inv) {
        const modalBody = document.getElementById('modalBody');

        const buktiBlock = (data) => {
            if (!data) return `<div class="detail-no-image"><i class="fas fa-image"></i><p>Tidak ada bukti</p></div>`;
            const { preview_url, is_image, is_pdf, original_name, size_formatted } = data;
            if (is_image) return `<div class="detail-image"><img src="${preview_url}" alt="Bukti" loading="lazy"><p class="file-meta">${original_name} • ${size_formatted}</p></div>`;
            if (is_pdf) return `<div class="detail-document"><a href="${preview_url}" target="_blank" class="btn-download"><i class="fas fa-file-pdf"></i> Lihat PDF – ${original_name}</a><p class="file-meta">${size_formatted}</p></div>`;
            return `<div class="detail-document"><a href="${preview_url}" target="_blank" class="btn-download"><i class="fas fa-paperclip"></i> Unduh Lampiran – ${original_name}</a><p class="file-meta">${size_formatted}</p></div>`;
        };

        const statusBadge = (status) => {
            const isRealized = (status === 'realized');
            return isRealized 
                ? '<span class="status-badge realized"><i class="fas fa-check-circle"></i> Realized</span>'
                : '<span class="status-badge unrealized"><i class="fas fa-clock"></i> Unrealized</span>';
        };

        const investProof = buktiBlock(inv.bukti_data);

        const profitRows = inv.keuntungan.map(k => {
            const isRealized = (k.status === 'realized');
            const statusClass = isRealized ? 'realized' : 'unrealized';
            return `
            <div class="detail-transaction profit ${statusClass}">
                <div class="transaction-status-header">
                    <div class="transaction-info">
                        <strong>${k.judul_keuntungan}</strong>
                        <span>${k.tanggal_keuntungan_formatted}</span>
                    </div>
                    ${statusBadge(k.status)}
                </div>
                <div class="transaction-amount positive">+${k.jumlah_keuntungan_formatted}</div>
                <div style="margin-top: 8px;">
                    <small style="color: #6b7280;">Sumber: ${k.sumber_keuntungan.replace(/_/g, ' ')}</small>
                </div>
                ${k.bukti_data ? buktiBlock(k.bukti_data) : ''}
            </div>`;
        }).join('');

        const lossRows = inv.kerugian.map(k => {
            const isRealized = (k.status === 'realized');
            const statusClass = isRealized ? 'realized' : 'unrealized';
            return `
            <div class="detail-transaction loss ${statusClass}">
                <div class="transaction-status-header">
                    <div class="transaction-info">
                        <strong>${k.judul_kerugian}</strong>
                        <span>${k.tanggal_kerugian_formatted}</span>
                    </div>
                    ${statusBadge(k.status)}
                </div>
                <div class="transaction-amount negative">-${k.jumlah_kerugian_formatted}</div>
                <div style="margin-top: 8px;">
                    <small style="color: #6b7280;">Sumber: ${k.sumber_kerugian.replace(/_/g, ' ')}</small>
                </div>
                ${k.bukti_data ? buktiBlock(k.bukti_data) : ''}
            </div>`;
        }).join('');

        modalBody.innerHTML = `
            <div class="detail-container">
                ${investProof}
                <div class="detail-info">
                    <div class="detail-section">
                        <h4><i class="fas fa-info-circle"></i> Informasi Dasar</h4>
                        <div class="detail-grid">
                            <div class="detail-item"><span class="detail-label">Judul</span><span class="detail-value">${inv.judul_investasi}</span></div>
                            <div class="detail-item"><span class="detail-label">Kategori</span><span class="detail-value">${inv.nama_kategori}</span></div>
                            <div class="detail-item"><span class="detail-label">Tanggal</span><span class="detail-value">${inv.tanggal_investasi_formatted}</span></div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h4><i class="fas fa-wallet"></i> Informasi Keuangan</h4>
                        <div class="detail-grid">
                            <div class="detail-item"><span class="detail-label">Modal</span><span class="detail-value">${inv.modal_investasi_formatted}</span></div>
                            <div class="detail-item"><span class="detail-label">Nilai Sekarang</span><span class="detail-value">${inv.nilai_sekarang_formatted}</span></div>
                            <div class="detail-item"><span class="detail-label">Total Keuntungan</span><span class="detail-value positive">+${inv.total_keuntungan_formatted}</span></div>
                            <div class="detail-item"><span class="detail-label">Total Kerugian</span><span class="detail-value negative">-${inv.total_kerugian_formatted}</span></div>
                            <div class="detail-item"><span class="detail-label">ROI</span><span class="detail-value ${inv.roi_persen >= 0 ? 'positive' : 'negative'}">${inv.roi_persen}%</span></div>
                        </div>
                    </div>

                    ${inv.deskripsi ? `<div class="detail-section"><h4><i class="fas fa-align-left"></i> Deskripsi</h4><p class="detail-description">${inv.deskripsi}</p></div>` : ''}

                    ${inv.keuntungan.length ? `<div class="detail-section"><h4><i class="fas fa-arrow-trend-up"></i> Riwayat Keuntungan (${inv.keuntungan.length})</h4><div class="detail-transactions">${profitRows}</div></div>` : ''}

                    ${inv.kerugian.length ? `<div class="detail-section"><h4><i class="fas fa-arrow-trend-down"></i> Riwayat Kerugian (${inv.kerugian.length})</h4><div class="detail-transactions">${lossRows}</div></div>` : ''}
                </div>
            </div>`;
    }

    // ---------- AOS (Animate on Scroll) ----------
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) entry.target.classList.add('aos-animate');
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
    document.querySelectorAll('[data-aos]').forEach(el => observer.observe(el));

    // ---------- Counter Animation ----------
    function animateCounter(el) {
        const target = parseFloat(el.dataset.value);
        const duration = 2000;
        const increment = target / (duration / 16);
        let current = 0;

        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            el.textContent = new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 2
            }).format(current);
        }, 16);
    }

    const counterObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !entry.target.classList.contains('counted')) {
                entry.target.classList.add('counted');
                animateCounter(entry.target);
            }
        });
    }, { threshold: 0.5 });

    document.querySelectorAll('.stat-value[data-value]').forEach(el => counterObserver.observe(el));
    </script>
</body>
</html>
