<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * Kelola Kas / Cash Management
 */

session_start();
require_once "../config/koneksi.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

$username = $_SESSION['username'];
$email = $_SESSION['email'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah_kas'])) {
        $tipe = $_POST['tipe'];
        $jumlah = str_replace(['.', ','], ['', '.'], $_POST['jumlah']);
        $keterangan = trim($_POST['keterangan']);
        $tanggal = $_POST['tanggal'];
        
        try {
            $sql = "INSERT INTO cash_balance (tipe, jumlah, keterangan, tanggal) 
                    VALUES (:tipe, :jumlah, :keterangan, :tanggal)";
            $stmt = $koneksi->prepare($sql);
            $stmt->execute([
                ':tipe' => $tipe,
                ':jumlah' => $jumlah,
                ':keterangan' => $keterangan,
                ':tanggal' => $tanggal
            ]);
            
            set_flash_message('success', 'Transaksi kas berhasil ditambahkan!');
            header("Location: kelola_kas.php");
            exit;
        } catch (PDOException $e) {
            set_flash_message('error', 'Gagal menambahkan transaksi: ' . $e->getMessage());
        }
    }
    
    if (isset($_POST['delete_kas'])) {
        $id = $_POST['id'];
        try {
            $sql = "DELETE FROM cash_balance WHERE id = :id";
            $stmt = $koneksi->prepare($sql);
            $stmt->execute([':id' => $id]);
            
            set_flash_message('success', 'Transaksi kas berhasil dihapus!');
            header("Location: kelola_kas.php");
            exit;
        } catch (PDOException $e) {
            set_flash_message('error', 'Gagal menghapus transaksi: ' . $e->getMessage());
        }
    }
}

// Get flash message
$flash = get_flash_message();

// Calculate saldo kas
$sql_saldo = "
    SELECT 
        COALESCE(SUM(CASE WHEN tipe = 'masuk' THEN jumlah ELSE 0 END), 0) as total_masuk,
        COALESCE(SUM(CASE WHEN tipe = 'keluar' THEN jumlah ELSE 0 END), 0) as total_keluar,
        COALESCE(SUM(CASE WHEN tipe = 'masuk' THEN jumlah ELSE 0 END), 0) - 
        COALESCE(SUM(CASE WHEN tipe = 'keluar' THEN jumlah ELSE 0 END), 0) as saldo
    FROM cash_balance
";
$stmt_saldo = $koneksi->query($sql_saldo);
$saldo_data = $stmt_saldo->fetch();

$total_masuk = (float)$saldo_data['total_masuk'];
$total_keluar = (float)$saldo_data['total_keluar'];
$saldo_kas = (float)$saldo_data['saldo'];

// Get transaction history
$sql_history = "
    SELECT * FROM cash_balance 
    ORDER BY tanggal DESC, id DESC
    LIMIT 50
";
$stmt_history = $koneksi->query($sql_history);
$history_list = $stmt_history->fetchAll();

// Count transactions
$count_masuk = count(array_filter($history_list, fn($h) => $h['tipe'] === 'masuk'));
$count_keluar = count(array_filter($history_list, fn($h) => $h['tipe'] === 'keluar'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kas - SAZEN v3.0</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        .cash-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .cash-stat-card {
            background: var(--surface-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .cash-stat-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-lg);
            font-size: 1.75rem;
            flex-shrink: 0;
        }
        
        .cash-stat-icon.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }
        
        .cash-stat-icon.danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }
        
        .cash-stat-icon.primary {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(240, 147, 251, 0.2));
            color: var(--primary-color);
        }
        
        .cash-stat-content {
            flex: 1;
        }
        
        .cash-stat-label {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .cash-stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .cash-stat-value.positive {
            color: var(--success-color);
        }
        
        .cash-stat-value.negative {
            color: var(--danger-color);
        }
        
        .form-container {
            background: var(--surface-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .form-group label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .form-group label span {
            color: var(--danger-color);
        }
        
        .form-control {
            padding: 0.875rem 1rem;
            background: var(--surface-secondary);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            color: var(--text-primary);
            font-size: 1rem;
            transition: all var(--transition-normal);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-control::placeholder {
            color: var(--text-muted);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        .btn-submit {
            padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: var(--radius-lg);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-normal);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .history-table {
            background: var(--surface-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            overflow: hidden;
        }
        
        .table-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(240, 147, 251, 0.02));
            border-bottom: 1px solid var(--border-color);
        }
        
        .table-header h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .table-body {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .transaction-row {
            display: grid;
            grid-template-columns: auto 1fr auto auto auto;
            gap: 1rem;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            align-items: center;
            transition: all var(--transition-fast);
        }
        
        .transaction-row:hover {
            background: var(--surface-secondary);
        }
        
        .transaction-icon-wrapper {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
            flex-shrink: 0;
        }
        
        .transaction-icon-wrapper.masuk {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }
        
        .transaction-icon-wrapper.keluar {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }
        
        .transaction-info h4 {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .transaction-info p {
            font-size: 0.8125rem;
            color: var(--text-muted);
        }
        
        .transaction-amount {
            font-size: 1.125rem;
            font-weight: 700;
        }
        
        .transaction-amount.masuk {
            color: var(--success-color);
        }
        
        .transaction-amount.keluar {
            color: var(--danger-color);
        }
        
        .transaction-date {
            font-size: 0.875rem;
            color: var(--text-muted);
        }
        
        .transaction-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-delete {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        
        .btn-delete:hover {
            background: var(--danger-color);
            border-color: var(--danger-color);
            color: white;
        }
        
        .type-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-md);
            font-size: 0.8125rem;
            font-weight: 600;
        }
        
        .type-badge.masuk {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }
        
        .type-badge.keluar {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }
        
        @media (max-width: 768px) {
            .transaction-row {
                grid-template-columns: auto 1fr auto;
                gap: 0.75rem;
            }
            
            .transaction-date,
            .type-badge {
                display: none;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

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
            
            <a href="upload.php" class="nav-item">
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
            <a href="kelola_kas.php" class="nav-item active">
                <i class="fas fa-wallet"></i>
                <span class="nav-text">Kelola Kas</span>
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
            <form method="POST" action="../dashboard.php">
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
                    <h1>Kelola Kas</h1>
                    <p>Manajemen Cash Balance</p>
                </div>
            </div>
            <div class="header-right">
                <a href="../dashboard.php" class="refresh-btn" title="Kembali ke Dashboard">
                    <i class="fas fa-arrow-left"></i>
                </a>
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

            <!-- Cash Statistics -->
            <div class="cash-stats-grid">
                <div class="cash-stat-card">
                    <div class="cash-stat-icon success">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div class="cash-stat-content">
                        <div class="cash-stat-label">Total Kas Masuk</div>
                        <div class="cash-stat-value positive"><?= format_currency($total_masuk) ?></div>
                    </div>
                </div>

                <div class="cash-stat-card">
                    <div class="cash-stat-icon danger">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div class="cash-stat-content">
                        <div class="cash-stat-label">Total Kas Keluar</div>
                        <div class="cash-stat-value negative"><?= format_currency($total_keluar) ?></div>
                    </div>
                </div>

                <div class="cash-stat-card">
                    <div class="cash-stat-icon primary">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="cash-stat-content">
                        <div class="cash-stat-label">Saldo Kas</div>
                        <div class="cash-stat-value"><?= format_currency($saldo_kas) ?></div>
                    </div>
                </div>
            </div>

            <!-- Add Transaction Form -->
            <div class="form-container">
                <h2 class="section-title">
                    <i class="fas fa-plus-circle"></i>
                    Tambah Transaksi Kas
                </h2>
                
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="tipe">Tipe Transaksi <span>*</span></label>
                            <select name="tipe" id="tipe" class="form-control" required>
                                <option value="">Pilih Tipe</option>
                                <option value="masuk">Kas Masuk</option>
                                <option value="keluar">Kas Keluar</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="jumlah">Jumlah <span>*</span></label>
                            <input type="text" name="jumlah" id="jumlah" class="form-control" 
                                   placeholder="Contoh: 1000000" required>
                        </div>

                        <div class="form-group">
                            <label for="tanggal">Tanggal <span>*</span></label>
                            <input type="date" name="tanggal" id="tanggal" class="form-control" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 1.5rem;">
                        <label for="keterangan">Keterangan <span>*</span></label>
                        <textarea name="keterangan" id="keterangan" class="form-control" 
                                  placeholder="Masukkan keterangan transaksi..." required></textarea>
                    </div>

                    <button type="submit" name="tambah_kas" class="btn-submit" style="margin-top: 1.5rem;">
                        <i class="fas fa-save"></i>
                        Simpan Transaksi
                    </button>
                </form>
            </div>

            <!-- Transaction History -->
            <div class="history-table">
                <div class="table-header">
                    <h3>
                        <i class="fas fa-history"></i>
                        Riwayat Transaksi (<?= count($history_list) ?> transaksi)
                    </h3>
                </div>
                <div class="table-body">
                    <?php if (count($history_list) > 0): ?>
                        <?php foreach ($history_list as $item): ?>
                            <div class="transaction-row">
                                <div class="transaction-icon-wrapper <?= $item['tipe'] ?>">
                                    <i class="fas fa-<?= $item['tipe'] === 'masuk' ? 'arrow-down' : 'arrow-up' ?>"></i>
                                </div>
                                
                                <div class="transaction-info">
                                    <h4><?= htmlspecialchars($item['keterangan']) ?></h4>
                                    <p><?= date('d M Y', strtotime($item['tanggal'])) ?></p>
                                </div>

                                <span class="type-badge <?= $item['tipe'] ?>">
                                    <i class="fas fa-<?= $item['tipe'] === 'masuk' ? 'plus' : 'minus' ?>"></i>
                                    <?= ucfirst($item['tipe']) ?>
                                </span>
                                
                                <div class="transaction-amount <?= $item['tipe'] ?>">
                                    <?= $item['tipe'] === 'masuk' ? '+' : '-' ?><?= format_currency($item['jumlah']) ?>
                                </div>
                                
                                <div class="transaction-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" name="delete_kas" class="btn-delete" 
                                                onclick="return confirm('Yakin hapus transaksi ini?')" 
                                                title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Belum ada transaksi kas</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <!-- Scripts -->
    <script>
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

        // Flash Message
        function closeFlash() {
            const flash = document.getElementById('flashMessage');
            if (flash) {
                flash.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => flash.remove(), 300);
            }
        }

        setTimeout(closeFlash, 5000);

        // Format currency input
        const jumlahInput = document.getElementById('jumlah');
        jumlahInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d]/g, '');
            if (value) {
                value = parseInt(value).toLocaleString('id-ID');
            }
            e.target.value = value;
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const tipe = document.getElementById('tipe').value;
            const jumlah = document.getElementById('jumlah').value;
            const keterangan = document.getElementById('keterangan').value;
            
            if (!tipe || !jumlah || !keterangan) {
                e.preventDefault();
                alert('Semua field harus diisi!');
                return false;
            }
        });

        console.log('%c Kelola Kas Module ', 'background: #10b981; color: white; font-size: 14px; padding: 8px; border-radius: 4px;');
    </script>
</body>
</html>
