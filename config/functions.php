<?php
/**
 * SAZEN Investment Portfolio Manager v3.0
 * Additional Helper Functions
 * 
 * File ini adalah ADDON untuk koneksi.php
 * Berisi fungsi-fungsi khusus untuk:
 * - Cash Balance Management
 * - Transaksi Jual Investasi
 * - Utility Functions
 * 
 * CATATAN: 
 * - Fungsi upload file sudah ada di koneksi.php
 * - Menggunakan JSON-based storage (base64) dari koneksi.php
 * - Fungsi handle_file_upload_to_db() digunakan untuk cash & sales
 */

// ========================================
// CASH BALANCE FUNCTIONS
// ========================================

/**
 * Get current cash balance summary
 * @param PDO $koneksi Database connection
 * @return array
 */
function get_cash_balance($koneksi) {
    try {
        $sql = "SELECT 
                    SUM(CASE WHEN tipe = 'masuk' THEN jumlah ELSE 0 END) as total_masuk,
                    SUM(CASE WHEN tipe = 'keluar' THEN jumlah ELSE 0 END) as total_keluar,
                    (SUM(CASE WHEN tipe = 'masuk' THEN jumlah ELSE 0 END) - 
                     SUM(CASE WHEN tipe = 'keluar' THEN jumlah ELSE 0 END)) as saldo_akhir,
                    COUNT(*) as total_transaksi
                FROM cash_balance";
        
        $stmt = $koneksi->query($sql);
        $result = $stmt->fetch();
        
        // Return default values if no data
        if (!$result || $result['total_transaksi'] == 0) {
            return [
                'total_masuk' => 0,
                'total_keluar' => 0,
                'saldo_akhir' => 0,
                'total_transaksi' => 0
            ];
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error get_cash_balance: " . $e->getMessage());
        return [
            'total_masuk' => 0,
            'total_keluar' => 0,
            'saldo_akhir' => 0,
            'total_transaksi' => 0
        ];
    }
}

/**
 * Add cash transaction
 * @param PDO $koneksi Database connection
 * @param array $data Transaction data
 * @return bool
 */
function add_cash_transaction($koneksi, $data) {
    try {
        $sql = "INSERT INTO cash_balance 
                (tanggal, judul, tipe, jumlah, kategori, keterangan, referensi_id, bukti_file) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $koneksi->prepare($sql);
        return $stmt->execute([
            $data['tanggal'],
            $data['judul'],
            $data['tipe'],
            $data['jumlah'],
            $data['kategori'],
            $data['keterangan'] ?? null,
            $data['referensi_id'] ?? null,
            $data['bukti_file'] ?? null
        ]);
    } catch (PDOException $e) {
        error_log("Error add_cash_transaction: " . $e->getMessage());
        return false;
    }
}

/**
 * Get cash balance by category
 * @param PDO $koneksi Database connection
 * @return array
 */
function get_cash_by_category($koneksi) {
    try {
        $sql = "SELECT 
                    kategori,
                    SUM(CASE WHEN tipe = 'masuk' THEN jumlah ELSE 0 END) as total_masuk,
                    SUM(CASE WHEN tipe = 'keluar' THEN jumlah ELSE 0 END) as total_keluar,
                    (SUM(CASE WHEN tipe = 'masuk' THEN jumlah ELSE 0 END) - 
                     SUM(CASE WHEN tipe = 'keluar' THEN jumlah ELSE 0 END)) as saldo,
                    COUNT(*) as jumlah_transaksi
                FROM cash_balance
                GROUP BY kategori
                ORDER BY saldo DESC";
        
        $stmt = $koneksi->query($sql);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error get_cash_by_category: " . $e->getMessage());
        return [];
    }
}

/**
 * Get recent cash transactions
 * @param PDO $koneksi Database connection
 * @param int $limit Number of records
 * @return array
 */
function get_recent_cash_transactions($koneksi, $limit = 10) {
    try {
        $sql = "SELECT * FROM cash_balance 
                ORDER BY tanggal DESC, created_at DESC 
                LIMIT ?";
        
        $stmt = $koneksi->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error get_recent_cash_transactions: " . $e->getMessage());
        return [];
    }
}

/**
 * Get cash transaction by ID
 * @param PDO $koneksi Database connection
 * @param int $id Transaction ID
 * @return array|null
 */
function get_cash_transaction_by_id($koneksi, $id) {
    try {
        $sql = "SELECT * FROM cash_balance WHERE id = ?";
        $stmt = $koneksi->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error get_cash_transaction_by_id: " . $e->getMessage());
        return null;
    }
}

/**
 * Update cash transaction
 * @param PDO $koneksi Database connection
 * @param int $id Transaction ID
 * @param array $data Updated data
 * @return bool
 */
function update_cash_transaction($koneksi, $id, $data) {
    try {
        $sql = "UPDATE cash_balance SET 
                tanggal = ?, judul = ?, tipe = ?, jumlah = ?, 
                kategori = ?, keterangan = ?, bukti_file = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
        
        $stmt = $koneksi->prepare($sql);
        return $stmt->execute([
            $data['tanggal'],
            $data['judul'],
            $data['tipe'],
            $data['jumlah'],
            $data['kategori'],
            $data['keterangan'] ?? null,
            $data['bukti_file'] ?? null,
            $id
        ]);
    } catch (PDOException $e) {
        error_log("Error update_cash_transaction: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete cash transaction
 * @param PDO $koneksi Database connection
 * @param int $id Transaction ID
 * @return bool
 */
function delete_cash_transaction($koneksi, $id) {
    try {
        $sql = "DELETE FROM cash_balance WHERE id = ?";
        $stmt = $koneksi->prepare($sql);
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log("Error delete_cash_transaction: " . $e->getMessage());
        return false;
    }
}

// ========================================
// TRANSAKSI JUAL FUNCTIONS
// ========================================

/**
 * Add sale transaction with automatic calculations
 * @param PDO $koneksi Database connection
 * @param array $data Sale data
 * @return array Result with success status and details
 */
function add_sale_transaction($koneksi, $data) {
    try {
        $koneksi->beginTransaction();
        
        // Get investment data dengan agregasi keuntungan/kerugian
        $sql_inv = "SELECT 
                        i.jumlah as modal,
                        COALESCE(SUM(ku.jumlah_keuntungan), 0) as total_keuntungan,
                        COALESCE(SUM(kr.jumlah_kerugian), 0) as total_kerugian
                    FROM investasi i
                    LEFT JOIN keuntungan_investasi ku ON i.id = ku.investasi_id
                    LEFT JOIN kerugian_investasi kr ON i.id = kr.investasi_id
                    WHERE i.id = ?
                    GROUP BY i.id, i.jumlah";
        
        $stmt_inv = $koneksi->prepare($sql_inv);
        $stmt_inv->execute([$data['investasi_id']]);
        $inv = $stmt_inv->fetch();
        
        if (!$inv) {
            throw new Exception("Investasi tidak ditemukan");
        }
        
        // Calculate profit/loss
        $harga_beli = (float)$inv['modal'];
        $total_keuntungan = (float)$inv['total_keuntungan'];
        $total_kerugian = (float)$inv['total_kerugian'];
        $harga_jual = (float)$data['harga_jual'];
        
        $profit_loss = $harga_jual - $harga_beli;
        $roi_persen = $harga_beli > 0 ? (($profit_loss / $harga_beli) * 100) : 0;
        
        // Insert sale transaction
        $sql = "INSERT INTO transaksi_jual 
                (investasi_id, tanggal_jual, harga_jual, harga_beli, 
                 total_keuntungan, total_kerugian, profit_loss, roi_persen, 
                 keterangan, bukti_file) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $koneksi->prepare($sql);
        $stmt->execute([
            $data['investasi_id'],
            $data['tanggal_jual'],
            $harga_jual,
            $harga_beli,
            $total_keuntungan,
            $total_kerugian,
            $profit_loss,
            $roi_persen,
            $data['keterangan'] ?? null,
            $data['bukti_file'] ?? null
        ]);
        
        // Get judul investasi untuk cash balance
        $sql_judul = "SELECT judul_investasi FROM investasi WHERE id = ?";
        $stmt_judul = $koneksi->prepare($sql_judul);
        $stmt_judul->execute([$data['investasi_id']]);
        $judul_inv = $stmt_judul->fetchColumn();
        
        // Auto-create cash balance entry (kas masuk dari hasil jual)
        $sql_cash = "INSERT INTO cash_balance 
                     (tanggal, judul, tipe, jumlah, kategori, referensi_id, keterangan) 
                     VALUES (?, ?, 'masuk', ?, 'hasil_jual', ?, ?)";
        
        $stmt_cash = $koneksi->prepare($sql_cash);
        $stmt_cash->execute([
            $data['tanggal_jual'],
            'Hasil Penjualan - ' . $judul_inv,
            $harga_jual,
            $data['investasi_id'],
            sprintf('Profit/Loss: %s | ROI: %.2f%%', format_currency($profit_loss), $roi_persen)
        ]);
        
        // Update status investasi menjadi 'terjual'
        $sql_update = "UPDATE investasi 
                       SET status = 'terjual', tanggal_status_update = ?
                       WHERE id = ?";
        
        $stmt_update = $koneksi->prepare($sql_update);
        $stmt_update->execute([$data['tanggal_jual'], $data['investasi_id']]);
        
        $koneksi->commit();
        
        return [
            'success' => true,
            'profit_loss' => $profit_loss,
            'roi_persen' => $roi_persen,
            'harga_beli' => $harga_beli,
            'harga_jual' => $harga_jual
        ];
        
    } catch (Exception $e) {
        $koneksi->rollBack();
        error_log("Error add_sale_transaction: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get sale transactions with investment details
 * @param PDO $koneksi Database connection
 * @param int|null $limit Number of records
 * @return array
 */
function get_sale_transactions($koneksi, $limit = null) {
    try {
        $sql = "SELECT 
                    tj.*,
                    i.judul_investasi,
                    k.nama_kategori,
                    CASE 
                        WHEN tj.profit_loss > 0 THEN 'profit'
                        WHEN tj.profit_loss < 0 THEN 'loss'
                        ELSE 'break_even'
                    END as status_transaksi
                FROM transaksi_jual tj
                JOIN investasi i ON tj.investasi_id = i.id
                JOIN kategori k ON i.kategori_id = k.id
                ORDER BY tj.tanggal_jual DESC";
        
        if ($limit) {
            $sql .= " LIMIT ?";
            $stmt = $koneksi->prepare($sql);
            $stmt->execute([$limit]);
        } else {
            $stmt = $koneksi->query($sql);
        }
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error get_sale_transactions: " . $e->getMessage());
        return [];
    }
}

/**
 * Get sale transaction by ID
 * @param PDO $koneksi Database connection
 * @param int $id Transaction ID
 * @return array|null
 */
function get_sale_transaction($koneksi, $id) {
    try {
        $sql = "SELECT 
                    tj.*,
                    i.judul_investasi,
                    k.nama_kategori,
                    CASE 
                        WHEN tj.profit_loss > 0 THEN 'profit'
                        WHEN tj.profit_loss < 0 THEN 'loss'
                        ELSE 'break_even'
                    END as status_transaksi
                FROM transaksi_jual tj
                JOIN investasi i ON tj.investasi_id = i.id
                JOIN kategori k ON i.kategori_id = k.id
                WHERE tj.id = ?";
        
        $stmt = $koneksi->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error get_sale_transaction: " . $e->getMessage());
        return null;
    }
}

/**
 * Calculate total sales statistics
 * @param PDO $koneksi Database connection
 * @return array
 */
function get_sales_statistics($koneksi) {
    try {
        $sql = "SELECT 
                    COUNT(*) as total_transaksi,
                    COALESCE(SUM(harga_jual), 0) as total_penjualan,
                    COALESCE(SUM(harga_beli), 0) as total_modal,
                    COALESCE(SUM(profit_loss), 0) as total_profit_loss,
                    COALESCE(AVG(roi_persen), 0) as avg_roi,
                    SUM(CASE WHEN profit_loss > 0 THEN 1 ELSE 0 END) as transaksi_profit,
                    SUM(CASE WHEN profit_loss < 0 THEN 1 ELSE 0 END) as transaksi_loss,
                    SUM(CASE WHEN profit_loss = 0 THEN 1 ELSE 0 END) as transaksi_break_even
                FROM transaksi_jual";
        
        $stmt = $koneksi->query($sql);
        $result = $stmt->fetch();
        
        if (!$result || $result['total_transaksi'] == 0) {
            return [
                'total_transaksi' => 0,
                'total_penjualan' => 0,
                'total_modal' => 0,
                'total_profit_loss' => 0,
                'avg_roi' => 0,
                'transaksi_profit' => 0,
                'transaksi_loss' => 0,
                'transaksi_break_even' => 0
            ];
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error get_sales_statistics: " . $e->getMessage());
        return [
            'total_transaksi' => 0,
            'total_penjualan' => 0,
            'total_modal' => 0,
            'total_profit_loss' => 0,
            'avg_roi' => 0,
            'transaksi_profit' => 0,
            'transaksi_loss' => 0,
            'transaksi_break_even' => 0
        ];
    }
}

/**
 * Get active investments (untuk dropdown di form jual)
 * @param PDO $koneksi Database connection
 * @return array
 */
function get_active_investments($koneksi) {
    try {
        $sql = "SELECT 
                    i.*,
                    k.nama_kategori,
                    COALESCE(SUM(ku.jumlah_keuntungan), 0) as total_keuntungan,
                    COALESCE(SUM(kr.jumlah_kerugian), 0) as total_kerugian,
                    (i.jumlah + COALESCE(SUM(ku.jumlah_keuntungan), 0) - COALESCE(SUM(kr.jumlah_kerugian), 0)) as nilai_buku
                FROM investasi i
                JOIN kategori k ON i.kategori_id = k.id
                LEFT JOIN keuntungan_investasi ku ON i.id = ku.investasi_id
                LEFT JOIN kerugian_investasi kr ON i.id = kr.investasi_id
                WHERE i.status = 'aktif'
                GROUP BY i.id
                ORDER BY i.tanggal_investasi DESC";
        
        $stmt = $koneksi->query($sql);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error get_active_investments: " . $e->getMessage());
        return [];
    }
}

// ========================================
// UTILITY FUNCTIONS
// ========================================

/**
 * Get investment status breakdown
 * @param PDO $koneksi Database connection
 * @return array
 */
function get_investment_status_breakdown($koneksi) {
    try {
        $sql = "SELECT 
                    status,
                    COUNT(*) as jumlah,
                    SUM(jumlah) as total_nilai
                FROM investasi
                GROUP BY status
                ORDER BY jumlah DESC";
        
        $stmt = $koneksi->query($sql);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error get_investment_status_breakdown: " . $e->getMessage());
        return [];
    }
}

/**
 * Get monthly performance (untuk grafik)
 * @param PDO $koneksi Database connection
 * @param int|null $year Year (default current year)
 * @return array
 */
function get_monthly_performance($koneksi, $year = null) {
    try {
        if (!$year) {
            $year = date('Y');
        }
        
        $sql = "SELECT 
                    MONTH(tanggal_investasi) as bulan,
                    DATE_FORMAT(tanggal_investasi, '%M') as nama_bulan,
                    COUNT(*) as jumlah_investasi,
                    SUM(jumlah) as total_investasi
                FROM investasi
                WHERE YEAR(tanggal_investasi) = ?
                GROUP BY MONTH(tanggal_investasi), DATE_FORMAT(tanggal_investasi, '%M')
                ORDER BY MONTH(tanggal_investasi)";
        
        $stmt = $koneksi->prepare($sql);
        $stmt->execute([$year]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error get_monthly_performance: " . $e->getMessage());
        return [];
    }
}

/**
 * Validate required fields
 * @param array $data Input data
 * @param array $required_fields List of required field names
 * @return array List of errors
 */
function validate_required($data, $required_fields) {
    $errors = [];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $field_name = ucfirst(str_replace('_', ' ', $field));
            $errors[] = "$field_name harus diisi";
        }
    }
    return $errors;
}

function require_login(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header('Location: /admin/auth.php'); // sesuaikan path
        exit;
    }
}
?>
