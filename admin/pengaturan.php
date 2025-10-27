<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * Halaman Pengaturan & Konfigurasi
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
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$email = $_SESSION['email'];

// Logout handler
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: auth.php");
    exit;
}

/* ========================================
   HANDLE FORM SUBMISSIONS
======================================== */

// Update Profile
if (isset($_POST['update_profile'])) {
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    
    if (empty($new_username) || empty($new_email)) {
        set_flash_message('error', 'Username dan Email tidak boleh kosong!');
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        set_flash_message('error', 'Format email tidak valid!');
    } else {
        try {
            $stmt = $koneksi->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
            $stmt->execute([$new_username, $new_email, $user_id]);
            
            $_SESSION['username'] = $new_username;
            $_SESSION['email'] = $new_email;
            
            set_flash_message('success', 'Profile berhasil diperbarui!');
            header("Location: pengaturan.php");
            exit;
        } catch (PDOException $e) {
            set_flash_message('error', 'Gagal memperbarui profile: ' . $e->getMessage());
        }
    }
}

// Change Password
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        set_flash_message('error', 'Semua field password harus diisi!');
    } elseif ($new_password !== $confirm_password) {
        set_flash_message('error', 'Password baru tidak cocok!');
    } elseif (strlen($new_password) < 6) {
        set_flash_message('error', 'Password minimal 6 karakter!');
    } else {
        try {
            // Verify current password
            $stmt = $koneksi->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (password_verify($current_password, $user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $koneksi->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                
                set_flash_message('success', 'Password berhasil diubah!');
                header("Location: pengaturan.php");
                exit;
            } else {
                set_flash_message('error', 'Password lama tidak sesuai!');
            }
        } catch (PDOException $e) {
            set_flash_message('error', 'Gagal mengubah password: ' . $e->getMessage());
        }
    }
}

// Add Category
if (isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    
    if (empty($category_name)) {
        set_flash_message('error', 'Nama kategori tidak boleh kosong!');
    } else {
        try {
            $stmt = $koneksi->prepare("INSERT INTO kategori (nama_kategori) VALUES (?)");
            $stmt->execute([$category_name]);
            set_flash_message('success', 'Kategori berhasil ditambahkan!');
            header("Location: pengaturan.php");
            exit;
        } catch (PDOException $e) {
            set_flash_message('error', 'Gagal menambahkan kategori: ' . $e->getMessage());
        }
    }
}

// Delete Category
if (isset($_GET['delete_category'])) {
    $category_id = (int)$_GET['delete_category'];
    
    try {
        // Check if category has investments
        $stmt = $koneksi->prepare("SELECT COUNT(*) as count FROM investasi WHERE kategori_id = ?");
        $stmt->execute([$category_id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            set_flash_message('error', 'Kategori tidak dapat dihapus karena masih digunakan oleh investasi!');
        } else {
            $stmt = $koneksi->prepare("DELETE FROM kategori WHERE id = ?");
            $stmt->execute([$category_id]);
            set_flash_message('success', 'Kategori berhasil dihapus!');
        }
        header("Location: pengaturan.php");
        exit;
    } catch (PDOException $e) {
        set_flash_message('error', 'Gagal menghapus kategori: ' . $e->getMessage());
    }
}

// Update App Settings
if (isset($_POST['update_settings'])) {
    $currency_format = $_POST['currency_format'] ?? 'Rp';
    $date_format = $_POST['date_format'] ?? 'd/m/Y';
    $items_per_page = (int)($_POST['items_per_page'] ?? 10);
    
    try {
        // Save to session or database
        $_SESSION['app_settings'] = [
            'currency_format' => $currency_format,
            'date_format' => $date_format,
            'items_per_page' => $items_per_page
        ];
        
        set_flash_message('success', 'Pengaturan aplikasi berhasil diperbarui!');
        header("Location: pengaturan.php");
        exit;
    } catch (Exception $e) {
        set_flash_message('error', 'Gagal menyimpan pengaturan: ' . $e->getMessage());
    }
}

// Export Data to CSV
if (isset($_POST['export_data'])) {
    $export_type = $_POST['export_type'] ?? 'all';
    
    try {
        $filename = 'sazen_export_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel UTF-8 support
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        if ($export_type === 'investasi' || $export_type === 'all') {
            fputcsv($output, ['=== INVESTASI ===']);
            fputcsv($output, ['ID', 'Judul', 'Kategori', 'Jumlah', 'Tanggal', 'Status']);
            
            $stmt = $koneksi->query("
                SELECT i.id, i.judul_investasi, k.nama_kategori, i.jumlah, 
                       i.tanggal_investasi, i.status
                FROM investasi i
                JOIN kategori k ON i.kategori_id = k.id
                ORDER BY i.tanggal_investasi DESC
            ");
            
            while ($row = $stmt->fetch()) {
                fputcsv($output, $row);
            }
            fputcsv($output, []);
        }
        
        if ($export_type === 'keuntungan' || $export_type === 'all') {
            fputcsv($output, ['=== KEUNTUNGAN ===']);
            fputcsv($output, ['ID', 'Judul', 'Investasi', 'Jumlah', 'Tanggal', 'Sumber']);
            
            $stmt = $koneksi->query("
                SELECT ki.id, ki.judul_keuntungan, i.judul_investasi, 
                       ki.jumlah_keuntungan, ki.tanggal_keuntungan, ki.sumber_keuntungan
                FROM keuntungan_investasi ki
                JOIN investasi i ON ki.investasi_id = i.id
                ORDER BY ki.tanggal_keuntungan DESC
            ");
            
            while ($row = $stmt->fetch()) {
                fputcsv($output, $row);
            }
            fputcsv($output, []);
        }
        
        fclose($output);
        exit;
    } catch (Exception $e) {
        set_flash_message('error', 'Gagal export data: ' . $e->getMessage());
    }
}

// Clear All Data (with confirmation)
if (isset($_POST['clear_all_data']) && $_POST['confirm_clear'] === 'HAPUS SEMUA') {
    try {
        $koneksi->beginTransaction();
        
        // Delete in correct order to respect foreign keys
        $koneksi->exec("DELETE FROM keuntungan_investasi");
        $koneksi->exec("DELETE FROM kerugian_investasi");
        $koneksi->exec("DELETE FROM transaksi_jual");
        $koneksi->exec("DELETE FROM investasi");
        $koneksi->exec("DELETE FROM cash_balance");
        
        $koneksi->commit();
        
        set_flash_message('success', 'Semua data berhasil dihapus!');
        header("Location: pengaturan.php");
        exit;
    } catch (PDOException $e) {
        $koneksi->rollBack();
        set_flash_message('error', 'Gagal menghapus data: ' . $e->getMessage());
    }
}

/* ========================================
   GET DATA FOR DISPLAY
======================================== */

// Get all categories
$categories = $koneksi->query("SELECT * FROM kategori ORDER BY nama_kategori ASC")->fetchAll();

// Get statistics
$sql_stats = "
    SELECT 
        (SELECT COUNT(*) FROM investasi) as total_investasi,
        (SELECT COUNT(*) FROM investasi WHERE status = 'aktif') as investasi_aktif,
        (SELECT COUNT(*) FROM keuntungan_investasi) as total_keuntungan_entries,
        (SELECT COUNT(*) FROM kerugian_investasi) as total_kerugian_entries,
        (SELECT COUNT(*) FROM transaksi_jual) as total_penjualan,
        (SELECT COUNT(*) FROM cash_balance) as total_cash_transactions,
        (SELECT COUNT(*) FROM kategori) as total_categories
";
$stats = $koneksi->query($sql_stats)->fetch();

// Get app settings
$app_settings = $_SESSION['app_settings'] ?? [
    'currency_format' => 'Rp',
    'date_format' => 'd/m/Y',
    'items_per_page' => 10
];

// Get recent activities
$recent_activities = $koneksi->query("
    (SELECT 'investasi' as type, judul_investasi as title, tanggal_investasi as date 
     FROM investasi ORDER BY tanggal_investasi DESC LIMIT 5)
    UNION ALL
    (SELECT 'keuntungan' as type, judul_keuntungan as title, tanggal_keuntungan as date 
     FROM keuntungan_investasi ORDER BY tanggal_keuntungan DESC LIMIT 5)
    UNION ALL
    (SELECT 'kerugian' as type, judul_kerugian as title, tanggal_kerugian as date 
     FROM kerugian_investasi ORDER BY tanggal_kerugian DESC LIMIT 5)
    ORDER BY date DESC
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - SAZEN v3.0</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        .settings-grid {
            display: grid;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .settings-section {
            background: var(--surface-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 2rem;
        }

        .settings-section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .settings-section-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-gradient);
            color: white;
            font-size: 1.25rem;
        }

        .settings-section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .form-group {
            margin-bottom: 1.5rem;
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

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.9375rem;
            cursor: pointer;
            transition: all var(--transition-fast);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .category-list {
            display: grid;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .category-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: var(--surface-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            transition: all var(--transition-fast);
        }

        .category-item:hover {
            background: var(--surface-hover);
            border-color: var(--primary-color);
        }

        .category-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .category-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            background: var(--surface-primary);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .btn-icon:hover {
            background: var(--danger-color);
            border-color: var(--danger-color);
            color: white;
        }

        .stats-list {
            display: grid;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            background: var(--surface-secondary);
            border-radius: var(--radius-md);
        }

        .stat-label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        .stat-value {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.125rem;
        }

        .danger-zone {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.05));
            border: 2px solid rgba(239, 68, 68, 0.3);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            margin-top: 2rem;
        }

        .danger-zone-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--danger-color);
            font-weight: 700;
            font-size: 1.125rem;
            margin-bottom: 1rem;
        }

        .danger-zone-warning {
            background: rgba(239, 68, 68, 0.1);
            padding: 1rem;
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            margin-bottom: 1rem;
            font-size: 0.9375rem;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--surface-primary);
            border-radius: var(--radius-xl);
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            box-shadow: var(--shadow-xl);
        }

        .modal-header {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .modal-body {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        @media (max-width: 768px) {
            .settings-section {
                padding: 1.5rem;
            }

            .modal-content {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>

    <!-- Loading Screen -->
    <div class="loading-screen" id="loadingScreen">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-text">Memuat Pengaturan...</div>
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
            
            <a href="laporan.php" class="nav-item">
                <i class="fas fa-chart-bar"></i>
                <span class="nav-text">Laporan</span>
            </a>
            <a href="pengaturan.php" class="nav-item active">
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
                    <h1>Pengaturan</h1>
                    <p>Kelola konfigurasi aplikasi</p>
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
        <?= flash('success') ?>
        <?= flash('error') ?>
        <?= flash('warning') ?>
        <?= flash('info') ?>

        <div class="container">

            <div class="settings-grid">

                <!-- Profile Settings -->
                <section class="settings-section">
                    <div class="settings-section-header">
                        <div class="settings-section-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <h2 class="settings-section-title">Profil Pengguna</h2>
                    </div>

                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-input" value="<?= htmlspecialchars($username) ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($email) ?>" required>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Simpan Perubahan
                        </button>
                    </form>
                </section>

                <!-- Change Password -->
                <section class="settings-section">
                    <div class="settings-section-header">
                        <div class="settings-section-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h2 class="settings-section-title">Ubah Password</h2>
                    </div>

                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Password Lama</label>
                            <input type="password" name="current_password" class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Password Baru</label>
                            <input type="password" name="new_password" class="form-input" minlength="6" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" name="confirm_password" class="form-input" minlength="6" required>
                        </div>

                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-key"></i>
                            Ubah Password
                        </button>
                    </form>
                </section>

                <!-- Category Management -->
                <section class="settings-section">
                    <div class="settings-section-header">
                        <div class="settings-section-icon">
                            <i class="fas fa-tags"></i>
                        </div>
                        <h2 class="settings-section-title">Kelola Kategori</h2>
                    </div>

                    <form method="POST" style="margin-bottom: 1.5rem;">
                        <div class="form-group">
                            <label class="form-label">Tambah Kategori Baru</label>
                            <div style="display: flex; gap: 0.75rem;">
                                <input type="text" name="category_name" class="form-input" placeholder="Nama kategori..." required>
                                <button type="submit" name="add_category" class="btn btn-success">
                                    <i class="fas fa-plus"></i>
                                    Tambah
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="category-list">
                        <?php foreach ($categories as $category): ?>
                            <div class="category-item">
                                <span class="category-name"><?= htmlspecialchars($category['nama_kategori']) ?></span>
                                <div class="category-actions">
                                    <button onclick="deleteCategory(<?= $category['id'] ?>, '<?= htmlspecialchars($category['nama_kategori']) ?>')" class="btn-icon" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Database Statistics -->
                <section class="settings-section">
                    <div class="settings-section-header">
                        <div class="settings-section-icon">
                            <i class="fas fa-database"></i>
                        </div>
                        <h2 class="settings-section-title">Statistik Database</h2>
                    </div>

                    <div class="stats-list">
                        <div class="stat-item">
                            <span class="stat-label">Total Investasi</span>
                            <span class="stat-value"><?= $stats['total_investasi'] ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Investasi Aktif</span>
                            <span class="stat-value"><?= $stats['investasi_aktif'] ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Record Keuntungan</span>
                            <span class="stat-value"><?= $stats['total_keuntungan_entries'] ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Record Kerugian</span>
                            <span class="stat-value"><?= $stats['total_kerugian_entries'] ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Total Penjualan</span>
                            <span class="stat-value"><?= $stats['total_penjualan'] ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Transaksi Kas</span>
                            <span class="stat-value"><?= $stats['total_cash_transactions'] ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Total Kategori</span>
                            <span class="stat-value"><?= $stats['total_categories'] ?></span>
                        </div>
                    </div>
                </section>

                <!-- App Settings -->
                <section class="settings-section">
                    <div class="settings-section-header">
                        <div class="settings-section-icon">
                            <i class="fas fa-sliders"></i>
                        </div>
                        <h2 class="settings-section-title">Pengaturan Aplikasi</h2>
                    </div>

                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Format Mata Uang</label>
                            <select name="currency_format" class="form-input">
                                <option value="Rp" <?= $app_settings['currency_format'] === 'Rp' ? 'selected' : '' ?>>Rupiah (Rp)</option>
                                <option value="$" <?= $app_settings['currency_format'] === ' ? 'selected' : '' ?>>Dollar ($)</option>
                                <option value="€" <?= $app_settings['currency_format'] === '€' ? 'selected' : '' ?>>Euro (€)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Format Tanggal</label>
                            <select name="date_format" class="form-input">
                                <option value="d/m/Y" <?php echo $app_settings['date_format'] === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                <option value="m/d/Y" <?php echo $app_settings['date_format'] === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                <option value="Y-m-d" <?php echo $app_settings['date_format'] === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                <option value="d M Y" <?php echo $app_settings['date_format'] === 'd M Y' ? 'selected' : ''; ?>>DD Mon YYYY</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Item per Halaman</label>
                            <select name="items_per_page" class="form-input">
                                <option value="10" <?php echo $app_settings['items_per_page'] == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $app_settings['items_per_page'] == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $app_settings['items_per_page'] == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $app_settings['items_per_page'] == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>

                        <button type="submit" name="update_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Simpan Pengaturan
                        </button>
                    </form>
                </section>

                <!-- Export Data -->
                <section class="settings-section">
                    <div class="settings-section-header">
                        <div class="settings-section-icon">
                            <i class="fas fa-file-export"></i>
                        </div>
                        <h2 class="settings-section-title">Export Data</h2>
                    </div>

                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                        Export data Anda ke format CSV untuk backup atau analisis eksternal.
                    </p>

                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Pilih Data yang Akan Diexport</label>
                            <select name="export_type" class="form-input">
                                <option value="all">Semua Data</option>
                                <option value="investasi">Investasi Saja</option>
                                <option value="keuntungan">Keuntungan Saja</option>
                                <option value="kerugian">Kerugian Saja</option>
                                <option value="penjualan">Penjualan Saja</option>
                                <option value="kas">Kas Saja</option>
                            </select>
                        </div>

                        <button type="submit" name="export_data" class="btn btn-success">
                            <i class="fas fa-download"></i>
                            Export ke CSV
                        </button>
                    </form>
                </section>

                <!-- Recent Activities -->
                <section class="settings-section">
                    <div class="settings-section-header">
                        <div class="settings-section-icon">
                            <i class="fas fa-clock-rotate-left"></i>
                        </div>
                        <h2 class="settings-section-title">Aktivitas Terbaru</h2>
                    </div>

                    <?php if (count($recent_activities) > 0): ?>
                        <div class="category-list">
                            <?php foreach ($recent_activities as $activity): 
                                $icon = match($activity['type']) {
                                    'investasi' => 'briefcase',
                                    'keuntungan' => 'arrow-trend-up',
                                    'kerugian' => 'arrow-trend-down',
                                    default => 'circle'
                                };
                                $color = match($activity['type']) {
                                    'investasi' => '#667eea',
                                    'keuntungan' => '#10b981',
                                    'kerugian' => '#ef4444',
                                    default => '#64748b'
                                };
                            ?>
                                <div class="category-item">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: <?= $color ?>20; color: <?= $color ?>;">
                                            <i class="fas fa-<?= $icon ?>"></i>
                                        </div>
                                        <div>
                                            <div class="category-name"><?= htmlspecialchars($activity['title']) ?></div>
                                            <div style="font-size: 0.813rem; color: var(--text-muted);">
                                                <?= ucfirst($activity['type']) ?> • <?= date('d M Y', strtotime($activity['date'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--text-muted); text-align: center; padding: 2rem;">Belum ada aktivitas</p>
                    <?php endif; ?>
                </section>

                <!-- Danger Zone -->
                <section class="settings-section">
                    <div class="settings-section-header">
                        <div class="settings-section-icon" style="background: var(--danger-color);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h2 class="settings-section-title">Danger Zone</h2>
                    </div>

                    <div class="danger-zone">
                        <div class="danger-zone-header">
                            <i class="fas fa-triangle-exclamation"></i>
                            Hapus Semua Data
                        </div>
                        <div class="danger-zone-warning">
                            <strong>PERINGATAN:</strong> Aksi ini akan menghapus SEMUA data investasi, keuntungan, kerugian, penjualan, dan transaksi kas. Data yang dihapus TIDAK DAPAT dikembalikan!
                            <br><br>
                            Pastikan Anda sudah membuat backup sebelum melanjutkan.
                        </div>
                        <button onclick="openClearDataModal()" class="btn btn-danger">
                            <i class="fas fa-trash-alt"></i>
                            Hapus Semua Data
                        </button>
                    </div>
                </section>

            </div>

        </div>
    </main>

    <!-- Delete Category Confirmation Modal -->
    <div class="modal" id="deleteCategoryModal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-exclamation-triangle" style="color: var(--danger-color);"></i>
                Konfirmasi Hapus Kategori
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus kategori <strong id="categoryNameToDelete"></strong>?</p>
                <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.5rem;">
                    Kategori hanya dapat dihapus jika tidak digunakan oleh investasi manapun.
                </p>
            </div>
            <div class="modal-actions">
                <button onclick="closeCategoryModal()" class="btn btn-primary">
                    <i class="fas fa-times"></i>
                    Batal
                </button>
                <a href="#" id="confirmDeleteCategory" class="btn btn-danger">
                    <i class="fas fa-trash"></i>
                    Hapus
                </a>
            </div>
        </div>
    </div>

    <!-- Clear All Data Confirmation Modal -->
    <div class="modal" id="clearDataModal">
        <div class="modal-content">
            <div class="modal-header" style="color: var(--danger-color);">
                <i class="fas fa-skull-crossbones"></i>
                KONFIRMASI PENGHAPUSAN DATA
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 1rem;"><strong>PERINGATAN KERAS!</strong></p>
                <p>Anda akan menghapus SEMUA data berikut:</p>
                <ul style="margin: 1rem 0; padding-left: 1.5rem; color: var(--text-secondary);">
                    <li>Semua investasi</li>
                    <li>Semua keuntungan</li>
                    <li>Semua kerugian</li>
                    <li>Semua penjualan</li>
                    <li>Semua transaksi kas</li>
                </ul>
                <p style="color: var(--danger-color); font-weight: 700;">Data yang dihapus TIDAK DAPAT dikembalikan!</p>
                
                <form method="POST" id="clearDataForm">
                    <div class="form-group" style="margin-top: 1.5rem;">
                        <label class="form-label">Ketik <strong>HAPUS SEMUA</strong> untuk konfirmasi:</label>
                        <input type="text" name="confirm_clear" class="form-input" id="confirmClearInput" placeholder="HAPUS SEMUA" required>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button onclick="closeClearDataModal()" class="btn btn-primary">
                    <i class="fas fa-times"></i>
                    Batal
                </button>
                <button onclick="submitClearData()" class="btn btn-danger" id="confirmClearButton" disabled>
                    <i class="fas fa-trash-alt"></i>
                    Hapus Semua Data
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
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

        // Flash Message Auto-close
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.animation = 'slideOut 0.3s ease';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
        });

        // Delete Category Modal
        function deleteCategory(id, name) {
            document.getElementById('categoryNameToDelete').textContent = name;
            document.getElementById('confirmDeleteCategory').href = 'pengaturan.php?delete_category=' + id;
            document.getElementById('deleteCategoryModal').classList.add('active');
        }

        function closeCategoryModal() {
            document.getElementById('deleteCategoryModal').classList.remove('active');
        }

        // Clear All Data Modal
        function openClearDataModal() {
            document.getElementById('clearDataModal').classList.add('active');
            document.getElementById('confirmClearInput').value = '';
            document.getElementById('confirmClearButton').disabled = true;
        }

        function closeClearDataModal() {
            document.getElementById('clearDataModal').classList.remove('active');
            document.getElementById('confirmClearInput').value = '';
            document.getElementById('confirmClearButton').disabled = true;
        }

        // Enable/disable clear button based on confirmation text
        document.getElementById('confirmClearInput').addEventListener('input', function() {
            const button = document.getElementById('confirmClearButton');
            button.disabled = this.value !== 'HAPUS SEMUA';
        });

        function submitClearData() {
            const confirmText = document.getElementById('confirmClearInput').value;
            if (confirmText === 'HAPUS SEMUA') {
                const form = document.getElementById('clearDataForm');
                form.innerHTML += '<input type="hidden" name="clear_all_data" value="1">';
                form.submit();
            }
        }

        // Close modals on ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeCategoryModal();
                closeClearDataModal();
            }
        });

        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeCategoryModal();
                    closeClearDataModal();
                }
            });
        });

        // Password validation
        const newPasswordInput = document.querySelector('input[name="new_password"]');
        const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');

        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', function() {
                if (this.value !== newPasswordInput.value) {
                    this.setCustomValidity('Password tidak cocok!');
                } else {
                    this.setCustomValidity('');
                }
            });

            newPasswordInput.addEventListener('input', function() {
                if (confirmPasswordInput.value !== '' && confirmPasswordInput.value !== this.value) {
                    confirmPasswordInput.setCustomValidity('Password tidak cocok!');
                } else {
                    confirmPasswordInput.setCustomValidity('');
                }
            });
        }

        // Confirmation before leaving page with unsaved changes
        let formChanged = false;
        document.querySelectorAll('input, textarea').forEach(input => {
            input.addEventListener('change', () => {
                formChanged = true;
            });
        });

        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', () => {
                formChanged = false;
            });
        });

        window.addEventListener('beforeunload', (e) => {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Animate settings sections on scroll
        const settingsSections = document.querySelectorAll('.settings-section');
        const sectionObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.style.animation = 'fadeInUp 0.5s ease forwards';
                        entry.target.style.opacity = '1';
                    }, index * 100);
                }
            });
        }, { threshold: 0.1 });

        settingsSections.forEach(section => {
            section.style.opacity = '0';
            sectionObserver.observe(section);
        });

        // Add animation keyframes
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);

        // Export button loading state
        document.querySelector('button[name="export_data"]')?.addEventListener('click', function() {
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengexport Data...';
        });

        console.log('%c SAZEN Pengaturan v3.0 ', 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 16px; padding: 10px; border-radius: 5px;');
    </script>
</body>
</html>
