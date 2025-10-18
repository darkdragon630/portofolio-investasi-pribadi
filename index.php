<?php
require_once 'config.php';

// Query investasi dengan keuntungan dan kerugian
$sql_investasi = "
    SELECT i.id, i.judul_investasi, i.deskripsi, i.jumlah, i.tanggal_investasi, i.bukti_file,
           k.nama_kategori,
           COALESCE(SUM(ki.jumlah_keuntungan), 0) as total_keuntungan_item,
           COALESCE(SUM(kr.jumlah_kerugian), 0) as total_kerugian_item
    FROM investasi i
    JOIN kategori k ON i.kategori_id = k.id
    LEFT JOIN keuntungan_investasi ki ON i.id = ki.investasi_id
    LEFT JOIN kerugian_investasi kr ON i.id = kr.investasi_id
    GROUP BY i.id
    ORDER BY i.tanggal_investasi DESC
";
$stmt = $koneksi->query($sql_investasi);
$investasi = $stmt->fetchAll();

// Statistik keseluruhan
$sql_stats = "
    SELECT 
        COALESCE(SUM(i.jumlah), 0) as total_investasi,
        COALESCE(SUM(ki.jumlah_keuntungan), 0) as total_keuntungan,
        COALESCE(SUM(kr.jumlah_kerugian), 0) as total_kerugian,
        (COALESCE(SUM(i.jumlah), 0) + COALESCE(SUM(ki.jumlah_keuntungan), 0) - COALESCE(SUM(kr.jumlah_kerugian), 0)) as total_nilai
    FROM investasi i
    LEFT JOIN keuntungan_investasi ki ON i.id = ki.investasi_id
    LEFT JOIN kerugian_investasi kr ON i.id = kr.investasi_id
";
$stmt_stats = $koneksi->query($sql_stats);
$stats = $stmt_stats->fetch();

$total_investasi = (float)$stats['total_investasi'];
$total_keuntungan = (float)$stats['total_keuntungan'];
$total_kerugian = (float)$stats['total_kerugian'];
$total_nilai = (float)$stats['total_nilai'];

// ROI
$roi = $total_investasi > 0 ? (($total_keuntungan - $total_kerugian) / $total_investasi) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portofolio Investasi - SAAZ v2</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #1e293b;
            --light: #f1f5f9;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #1e293b;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header-title {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }
        
        .header-subtitle {
            color: #64748b;
            font-size: 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            max-width: 1200px;
            margin: -3rem auto 2rem;
            padding: 0 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .stat-card.investment .stat-icon {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .stat-card.profit .stat-icon {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .stat-card.loss .stat-icon {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .stat-card.total .stat-icon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .investments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .investment-card {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .investment-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .card-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .card-category {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            font-size: 0.875rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .amount-display {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .profit-loss-info {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background: var(--light);
            border-radius: 0.5rem;
        }
        
        .profit-info, .loss-info {
            flex: 1;
        }
        
        .profit-info {
            color: var(--success);
        }
        
        .loss-info {
            color: var(--danger);
        }
        
        .info-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-size: 1.125rem;
            font-weight: 600;
        }
        
        .card-date {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        
        .card-description {
            color: #475569;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        
        .card-actions {
            display: flex;
            gap: 0.75rem;
        }
        
        .btn {
            flex: 1;
            padding: 0.75rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .empty-icon {
            font-size: 4rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .empty-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .empty-description {
            color: #64748b;
            margin-bottom: 2rem;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(5px);
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 1rem;
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            padding: 2rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255,255,255,0.2);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .modal-close:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .detail-section {
            margin-bottom: 2rem;
        }
        
        .detail-label {
            font-weight: 600;
            color: #64748b;
            font-size: 0.875rem;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }
        
        .detail-value {
            font-size: 1.125rem;
            color: var(--dark);
            margin-bottom: 1rem;
        }
        
        .bukti-image {
            width: 100%;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
                margin-top: -2rem;
            }
            
            .investments-grid {
                grid-template-columns: 1fr;
            }
            
            .header-title {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="header-title">
                <i class="fas fa-chart-line"></i> Portofolio Investasi SAAZ
            </h1>
            <p class="header-subtitle">
                <i class="fas fa-sync-alt"></i> Dashboard investasi pribadi dengan tracking lengkap
            </p>
        </div>
    </header>

    <div class="stats-grid">
        <div class="stat-card investment">
            <div class="stat-icon"><i class="fas fa-wallet"></i></div>
            <div class="stat-label">Total Investasi</div>
            <div class="stat-value">Rp <?= number_format($total_investasi, 0, ',', '.') ?></div>
        </div>
        
        <div class="stat-card profit">
            <div class="stat-icon"><i class="fas fa-arrow-trend-up"></i></div>
            <div class="stat-label">Total Keuntungan</div>
            <div class="stat-value">Rp <?= number_format($total_keuntungan, 0, ',', '.') ?></div>
        </div>
        
        <div class="stat-card loss">
            <div class="stat-icon"><i class="fas fa-arrow-trend-down"></i></div>
            <div class="stat-label">Total Kerugian</div>
            <div class="stat-value">Rp <?= number_format($total_kerugian, 0, ',', '.') ?></div>
        </div>
        
        <div class="stat-card total">
            <div class="stat-icon"><i class="fas fa-chart-pie"></i></div>
            <div class="stat-label">Total Nilai (ROI: <?= number_format($roi, 2) ?>%)</div>
            <div class="stat-value">Rp <?= number_format($total_nilai, 0, ',', '.') ?></div>
        </div>
    </div>

    <main class="main-content">
        <?php if ($investasi): ?>
            <div class="investments-grid">
                <?php foreach ($investasi as $item): 
                    $net_profit = $item['total_keuntungan_item'] - $item['total_kerugian_item'];
                    $net_color = $net_profit >= 0 ? 'var(--success)' : 'var(--danger)';
                ?>
                    <div class="investment-card">
                        <div class="card-header">
                            <h2 class="card-title"><?= htmlspecialchars($item['judul_investasi']) ?></h2>
                            <span class="card-category">
                                <i class="fas fa-tag"></i> <?= htmlspecialchars($item['nama_kategori']) ?>
                            </span>
                        </div>
                        
                        <div class="card-body">
                            <div class="amount-display">
                                Rp <?= number_format($item['jumlah'], 0, ',', '.') ?>
                            </div>
                            
                            <div class="profit-loss-info">
                                <div class="profit-info">
                                    <div class="info-label">Keuntungan</div>
                                    <div class="info-value">+<?= number_format($item['total_keuntungan_item'], 0, ',', '.') ?></div>
                                </div>
                                <div class="loss-info">
                                    <div class="info-label">Kerugian</div>
                                    <div class="info-value">-<?= number_format($item['total_kerugian_item'], 0, ',', '.') ?></div>
                                </div>
                            </div>
                            
                            <div style="text-align: center; padding: 0.75rem; background: <?= $net_color ?>; color: white; border-radius: 0.5rem; margin-bottom: 1rem; font-weight: 600;">
                                Net: <?= $net_profit >= 0 ? '+' : '' ?><?= number_format($net_profit, 0, ',', '.') ?>
                            </div>
                            
                            <div class="card-date">
                                <i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($item['tanggal_investasi'])) ?>
                            </div>
                            
                            <?php if ($item['deskripsi']): ?>
                                <div class="card-description">
                                    <?= nl2br(htmlspecialchars(substr($item['deskripsi'], 0, 100))) ?>
                                    <?= strlen($item['deskripsi']) > 100 ? '...' : '' ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-actions">
                                <button class="btn btn-primary" onclick="showDetail(<?= $item['id'] ?>)">
                                    <i class="fas fa-eye"></i> Detail
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-chart-line"></i></div>
                <h3 class="empty-title">Belum Ada Data Investasi</h3>
                <p class="empty-description">Mulai tambahkan investasi melalui dashboard admin</p>
            </div>
        <?php endif; ?>
    </main>

    <!-- Modal Detail -->
    <div class="modal" id="detailModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Detail Investasi</h2>
                <button class="modal-close" onclick="closeDetail()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary);"></i>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showDetail(id) {
            const modal = document.getElementById('detailModal');
            modal.classList.add('show');
            
            fetch(`get_detail.php?id=${id}`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('modalBody').innerHTML = `
                        <div class="detail-section">
                            <div class="detail-label">Judul Investasi</div>
                            <div class="detail-value">${data.judul_investasi}</div>
                        </div>
                        
                        <div class="detail-section">
                            <div class="detail-label">Kategori</div>
                            <div class="detail-value">${data.nama_kategori}</div>
                        </div>
                        
                        <div class="detail-section">
                            <div class="detail-label">Jumlah Investasi</div>
                            <div class="detail-value">Rp ${new Intl.NumberFormat('id-ID').format(data.jumlah)}</div>
                        </div>
                        
                        <div class="detail-section">
                            <div class="detail-label">Tanggal Investasi</div>
                            <div class="detail-value">${new Date(data.tanggal_investasi).toLocaleDateString('id-ID')}</div>
                        </div>
                        
                        ${data.deskripsi ? `
                        <div class="detail-section">
                            <div class="detail-label">Deskripsi</div>
                            <div class="detail-value">${data.deskripsi.replace(/\n/g, '<br>')}</div>
                        </div>
                        ` : ''}
                        
                        ${data.bukti_file ? `
                        <div class="detail-section">
                            <div class="detail-label">Bukti Investasi</div>
                            <img src="bukti_investasi/${data.bukti_file}" alt="Bukti" class="bukti-image">
                        </div>
                        ` : '<p style="text-align: center; color: #94a3b8;">Tidak ada bukti yang diupload</p>'}
                    `;
                })
                .catch(err => {
                    document.getElementById('modalBody').innerHTML = `
                        <p style="text-align: center; color: var(--danger);">Gagal memuat data</p>
                    `;
                });
        }
        
        function closeDetail() {
            document.getElementById('detailModal').classList.remove('show');
        }
        
        // Close modal saat klik di luar
        document.getElementById('detailModal').addEventListener('click', function(e) {
            if (e.target === this) closeDetail();
        });
    </script>
</body>
</html>