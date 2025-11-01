<?php
/**
 * SAZEN Investment Portfolio Manager v3.1
 * Auto-Calculate Investment Values & Daily Tracking
 * 
 * FITUR BARU v3.1:
 * ✅ Auto-update nilai investasi setiap kali ada keuntungan/kerugian baru
 * ✅ Tracking perubahan harian (Hari 1, Hari 2, Hari 3...)
 * ✅ Deteksi status naik/turun otomatis
 * ✅ Log semua perubahan nilai
 * ✅ Statistik performa bulanan
 * ✅ Proteksi NULL dan error handling lengkap
 * 
 * @version 3.1.0
 * @author SAAZ
 */

require_once "koneksi.php";

// ========================================
// 1. FUNGSI UTAMA: AUTO RECALCULATE
// ========================================

/**
 * Recalculate investment values after profit/loss changes
 * Called automatically after insert/update/delete keuntungan/kerugian
 * 
 * @param PDO $koneksi Database connection
 * @param int|null $investasi_id Specific investment ID (null = recalc all)
 * @return array Result with success status and details
 */
function auto_recalculate_investment($koneksi, $investasi_id = null) {
    try {
        $koneksi->beginTransaction();
        
        // Determine which investments to update
        $investments = [];
        if ($investasi_id !== null) {
            // Single investment
            $sql = "SELECT id, judul_investasi, jumlah as modal_investasi, 
                           tanggal_investasi, status
                    FROM investasi WHERE id = ?";
            $stmt = $koneksi->prepare($sql);
            $stmt->execute([$investasi_id]);
            $inv = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($inv) {
                $investments[] = $inv;
            } else {
                throw new Exception("Investment ID $investasi_id not found");
            }
        } else {
            // All active investments
            $sql = "SELECT id, judul_investasi, jumlah as modal_investasi, 
                           tanggal_investasi, status
                    FROM investasi 
                    WHERE status IN ('aktif', 'terjual')
                    ORDER BY id";
            $stmt = $koneksi->query($sql);
            $investments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $results = [];
        $global_total_keuntungan = 0;
        $global_total_kerugian = 0;
        $global_total_nilai = 0;
        
        foreach ($investments as $inv) {
            $inv_id = $inv['id'];
            $modal = (float)$inv['modal_investasi'];
            
            // Get total keuntungan
            $sql_keuntungan = "SELECT IFNULL(SUM(jumlah_keuntungan), 0) as total
                               FROM keuntungan_investasi WHERE investasi_id = ?";
            $stmt_k = $koneksi->prepare($sql_keuntungan);
            $stmt_k->execute([$inv_id]);
            $total_keuntungan = (float)$stmt_k->fetchColumn();
            
            // Get total kerugian
            $sql_kerugian = "SELECT IFNULL(SUM(jumlah_kerugian), 0) as total
                             FROM kerugian_investasi WHERE investasi_id = ?";
            $stmt_kr = $koneksi->prepare($sql_kerugian);
            $stmt_kr->execute([$inv_id]);
            $total_kerugian = (float)$stmt_kr->fetchColumn();
            
            // Calculate current value and ROI
            $nilai_sekarang = $modal + $total_keuntungan - $total_kerugian;
            $roi_persen = $modal > 0 ? ((($nilai_sekarang - $modal) / $modal) * 100) : 0;
            
            // Update investasi table (for quick access without view)
            $sql_update = "UPDATE investasi 
                          SET updated_at = CURRENT_TIMESTAMP
                          WHERE id = ?";
            $stmt_upd = $koneksi->prepare($sql_update);
            $stmt_upd->execute([$inv_id]);
            
            // Log the change
            log_investment_change($koneksi, $inv_id, 'recalculation', null, 
                                 $nilai_sekarang, $total_keuntungan - $total_kerugian,
                                 "Auto-recalculate: Modal=$modal, Profit=$total_keuntungan, Loss=$total_kerugian");
            
            // Update daily snapshot if today's data exists
            update_daily_snapshot($koneksi, $inv_id, $nilai_sekarang, 
                                 $total_keuntungan, $total_kerugian);
            
            // Accumulate global stats
            if ($inv['status'] === 'aktif') {
                $global_total_keuntungan += $total_keuntungan;
                $global_total_kerugian += $total_kerugian;
                $global_total_nilai += $nilai_sekarang;
            }
            
            $results[$inv_id] = [
                'judul' => $inv['judul_investasi'],
                'modal' => $modal,
                'total_keuntungan' => $total_keuntungan,
                'total_kerugian' => $total_kerugian,
                'nilai_sekarang' => $nilai_sekarang,
                'roi_persen' => $roi_persen,
                'status' => $inv['status']
            ];
        }
        
        $koneksi->commit();
        
        return [
            'success' => true,
            'updated_count' => count($results),
            'investments' => $results,
            'global_stats' => [
                'total_keuntungan' => $global_total_keuntungan,
                'total_kerugian' => $global_total_kerugian,
                'total_nilai' => $global_total_nilai,
                'net_profit' => $global_total_keuntungan - $global_total_kerugian
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        if ($koneksi->inTransaction()) {
            $koneksi->rollBack();
        }
        error_log("Auto Recalculate Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// ========================================
// 2. DAILY SNAPSHOT TRACKING
// ========================================

/**
 * Create or update daily snapshot for investment
 * Tracks day-by-day changes (Hari 1, Hari 2, Hari 3...)
 * 
 * @param PDO $koneksi Database connection
 * @param int $investasi_id Investment ID
 * @param float $nilai_akhir Current value at end of day
 * @param float $total_keuntungan_harian Today's profit
 * @param float $total_kerugian_harian Today's loss
 * @return bool Success status
 */
function update_daily_snapshot($koneksi, $investasi_id, $nilai_akhir, 
                               $total_keuntungan_harian = 0, $total_kerugian_harian = 0) {
    try {
        // Get investment start date
        $sql = "SELECT tanggal_investasi, jumlah FROM investasi WHERE id = ?";
        $stmt = $koneksi->prepare($sql);
        $stmt->execute([$investasi_id]);
        $inv = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$inv) return false;
        
        $tanggal_investasi = $inv['tanggal_investasi'];
        $modal = (float)$inv['jumlah'];
        $today = date('Y-m-d');
        
        // Calculate day number (Hari ke-X)
        $start = new DateTime($tanggal_investasi);
        $end = new DateTime($today);
        $hari_ke = $start->diff($end)->days + 1;
        
        // Get yesterday's value for comparison
        $sql_yesterday = "SELECT nilai_akhir FROM investasi_snapshot_harian
                         WHERE investasi_id = ? 
                         AND tanggal_snapshot < ?
                         ORDER BY tanggal_snapshot DESC LIMIT 1";
        $stmt_y = $koneksi->prepare($sql_yesterday);
        $stmt_y->execute([$investasi_id, $today]);
        $yesterday = $stmt_y->fetch(PDO::FETCH_ASSOC);
        $nilai_awal = $yesterday ? (float)$yesterday['nilai_akhir'] : $modal;
        
        // Calculate changes
        $perubahan_nilai = $nilai_akhir - $nilai_awal;
        $persentase_perubahan = $nilai_awal > 0 ? (($perubahan_nilai / $nilai_awal) * 100) : 0;
        $roi_kumulatif = $modal > 0 ? ((($nilai_akhir - $modal) / $modal) * 100) : 0;
        
        // Determine status
        $status_perubahan = 'stabil';
        if ($perubahan_nilai > 0.01) {
            $status_perubahan = 'naik';
        } elseif ($perubahan_nilai < -0.01) {
            $status_perubahan = 'turun';
        }
        
        // Insert or update snapshot
        $sql_insert = "INSERT INTO investasi_snapshot_harian 
                      (investasi_id, tanggal_snapshot, hari_ke, nilai_awal, nilai_akhir,
                       perubahan_nilai, persentase_perubahan, total_keuntungan_harian,
                       total_kerugian_harian, status_perubahan, roi_kumulatif)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE
                      nilai_akhir = VALUES(nilai_akhir),
                      perubahan_nilai = VALUES(perubahan_nilai),
                      persentase_perubahan = VALUES(persentase_perubahan),
                      total_keuntungan_harian = VALUES(total_keuntungan_harian),
                      total_kerugian_harian = VALUES(total_kerugian_harian),
                      status_perubahan = VALUES(status_perubahan),
                      roi_kumulatif = VALUES(roi_kumulatif)";
        
        $stmt_ins = $koneksi->prepare($sql_insert);
        $stmt_ins->execute([
            $investasi_id, $today, $hari_ke, $nilai_awal, $nilai_akhir,
            $perubahan_nilai, $persentase_perubahan, $total_keuntungan_harian,
            $total_kerugian_harian, $status_perubahan, $roi_kumulatif
        ]);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Update Daily Snapshot Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get daily snapshot history for investment
 * 
 * @param PDO $koneksi Database connection
 * @param int $investasi_id Investment ID
 * @param int $limit Number of days to retrieve
 * @return array Snapshot history
 */
function get_daily_snapshot_history($koneksi, $investasi_id, $limit = 30) {
    try {
        $sql = "SELECT * FROM investasi_snapshot_harian
                WHERE investasi_id = ?
                ORDER BY tanggal_snapshot DESC
                LIMIT ?";
        
        $stmt = $koneksi->prepare($sql);
        $stmt->bindValue(1, $investasi_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Get Daily Snapshot Error: " . $e->getMessage());
        return [];
    }
}

// ========================================
// 3. CHANGE LOGGING
// ========================================

/**
 * Log investment value changes
 * 
 * @param PDO $koneksi Database connection
 * @param int $investasi_id Investment ID
 * @param string $tipe_perubahan Type: keuntungan|kerugian|penjualan|koreksi|recalculation
 * @param int|null $referensi_id Reference ID from source table
 * @param float $nilai_sesudah New value
 * @param float $selisih Change amount
 * @param string $keterangan Description
 * @return bool Success status
 */
function log_investment_change($koneksi, $investasi_id, $tipe_perubahan, 
                               $referensi_id, $nilai_sesudah, $selisih, $keterangan = '') {
    try {
        // Get previous value
        $sql_prev = "SELECT nilai_sesudah FROM investasi_change_log
                     WHERE investasi_id = ?
                     ORDER BY created_at DESC LIMIT 1";
        $stmt_prev = $koneksi->prepare($sql_prev);
        $stmt_prev->execute([$investasi_id]);
        $prev = $stmt_prev->fetch(PDO::FETCH_ASSOC);
        $nilai_sebelum = $prev ? (float)$prev['nilai_sesudah'] : 0;
        
        // Insert log
        $sql = "INSERT INTO investasi_change_log 
               (investasi_id, tipe_perubahan, referensi_id, nilai_sebelum,
                nilai_sesudah, selisih, keterangan)
               VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $koneksi->prepare($sql);
        return $stmt->execute([
            $investasi_id, $tipe_perubahan, $referensi_id,
            $nilai_sebelum, $nilai_sesudah, $selisih, $keterangan
        ]);
        
    } catch (Exception $e) {
        error_log("Log Investment Change Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get change log history
 * 
 * @param PDO $koneksi Database connection
 * @param int $investasi_id Investment ID
 * @param int $limit Number of records
 * @return array Change log
 */
function get_change_log($koneksi, $investasi_id, $limit = 50) {
    try {
        $sql = "SELECT * FROM investasi_change_log
                WHERE investasi_id = ?
                ORDER BY created_at DESC
                LIMIT ?";
        
        $stmt = $koneksi->prepare($sql);
        $stmt->bindValue(1, $investasi_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Get Change Log Error: " . $e->getMessage());
        return [];
    }
}

// ========================================
// 4. MONTHLY PERFORMANCE STATS
// ========================================

/**
 * Update monthly performance statistics
 * Should be called at end of month or on-demand
 * 
 * @param PDO $koneksi Database connection
 * @param int|null $investasi_id Specific investment (null = all)
 * @param string|null $bulan_tahun Month in YYYY-MM format (null = current month)
 * @return bool Success status
 */
function update_monthly_performance($koneksi, $investasi_id = null, $bulan_tahun = null) {
    try {
        if (!$bulan_tahun) {
            $bulan_tahun = date('Y-m');
        }
        
        // Get investments to process
        $investments = [];
        if ($investasi_id) {
            $investments[] = $investasi_id;
        } else {
            $sql = "SELECT id FROM investasi WHERE status IN ('aktif', 'terjual')";
            $stmt = $koneksi->query($sql);
            $investments = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
        }
        
        foreach ($investments as $inv_id) {
            // Get monthly profits
            $sql_profit = "SELECT 
                          IFNULL(SUM(jumlah_keuntungan), 0) as total,
                          COUNT(*) as jumlah
                          FROM keuntungan_investasi
                          WHERE investasi_id = ? 
                          AND DATE_FORMAT(tanggal_keuntungan, '%Y-%m') = ?";
            $stmt_p = $koneksi->prepare($sql_profit);
            $stmt_p->execute([$inv_id, $bulan_tahun]);
            $profit_data = $stmt_p->fetch(PDO::FETCH_ASSOC);
            
            // Get monthly losses
            $sql_loss = "SELECT 
                        IFNULL(SUM(jumlah_kerugian), 0) as total,
                        COUNT(*) as jumlah
                        FROM kerugian_investasi
                        WHERE investasi_id = ? 
                        AND DATE_FORMAT(tanggal_kerugian, '%Y-%m') = ?";
            $stmt_l = $koneksi->prepare($sql_loss);
            $stmt_l->execute([$inv_id, $bulan_tahun]);
            $loss_data = $stmt_l->fetch(PDO::FETCH_ASSOC);
            
            $total_profit = (float)$profit_data['total'];
            $total_loss = (float)$loss_data['total'];
            $net_profit = $total_profit - $total_loss;
            
            // Get investment modal for ROI calculation
            $sql_modal = "SELECT jumlah FROM investasi WHERE id = ?";
            $stmt_m = $koneksi->prepare($sql_modal);
            $stmt_m->execute([$inv_id]);
            $modal = (float)$stmt_m->fetchColumn();
            $roi_bulan = $modal > 0 ? (($net_profit / $modal) * 100) : 0;
            
            // Get min/max values from daily snapshots
            $sql_minmax = "SELECT 
                          MAX(nilai_akhir) as nilai_tertinggi,
                          MIN(nilai_akhir) as nilai_terendah,
                          STDDEV(perubahan_nilai) as volatilitas
                          FROM investasi_snapshot_harian
                          WHERE investasi_id = ?
                          AND DATE_FORMAT(tanggal_snapshot, '%Y-%m') = ?";
            $stmt_mm = $koneksi->prepare($sql_minmax);
            $stmt_mm->execute([$inv_id, $bulan_tahun]);
            $minmax = $stmt_mm->fetch(PDO::FETCH_ASSOC);
            
            // Insert or update stats
            $sql_insert = "INSERT INTO investasi_performance_stats
                          (investasi_id, bulan_tahun, total_keuntungan_bulan, total_kerugian_bulan,
                           net_profit_bulan, roi_bulan, jumlah_transaksi_keuntungan,
                           jumlah_transaksi_kerugian, nilai_tertinggi, nilai_terendah, volatilitas)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE
                          total_keuntungan_bulan = VALUES(total_keuntungan_bulan),
                          total_kerugian_bulan = VALUES(total_kerugian_bulan),
                          net_profit_bulan = VALUES(net_profit_bulan),
                          roi_bulan = VALUES(roi_bulan),
                          jumlah_transaksi_keuntungan = VALUES(jumlah_transaksi_keuntungan),
                          jumlah_transaksi_kerugian = VALUES(jumlah_transaksi_kerugian),
                          nilai_tertinggi = VALUES(nilai_tertinggi),
                          nilai_terendah = VALUES(nilai_terendah),
                          volatilitas = VALUES(volatilitas)";
            
            $stmt_ins = $koneksi->prepare($sql_insert);
            $stmt_ins->execute([
                $inv_id, $bulan_tahun, $total_profit, $total_loss, $net_profit, $roi_bulan,
                (int)$profit_data['jumlah'], (int)$loss_data['jumlah'],
                (float)($minmax['nilai_tertinggi'] ?? 0),
                (float)($minmax['nilai_terendah'] ?? 0),
                (float)($minmax['volatilitas'] ?? 0)
            ]);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Update Monthly Performance Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get monthly performance stats
 * 
 * @param PDO $koneksi Database connection
 * @param int $investasi_id Investment ID
 * @param int $months Number of months to retrieve
 * @return array Performance stats
 */
function get_monthly_performance($koneksi, $investasi_id, $months = 12) {
    try {
        $sql = "SELECT * FROM investasi_performance_stats
                WHERE investasi_id = ?
                ORDER BY bulan_tahun DESC
                LIMIT ?";
        
        $stmt = $koneksi->prepare($sql);
        $stmt->bindValue(1, $investasi_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $months, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Get Monthly Performance Error: " . $e->getMessage());
        return [];
    }
}

// ========================================
// 5. HELPER FUNCTIONS
// ========================================

/**
 * Get investment performance summary
 * 
 * @param PDO $koneksi Database connection
 * @param int $investasi_id Investment ID
 * @return array Performance summary with trends
 */
function get_investment_performance_summary($koneksi, $investasi_id) {
    try {
        // Current value from view
        $sql_current = "SELECT * FROM v_investasi_summary WHERE id = ?";
        $stmt = $koneksi->prepare($sql_current);
        $stmt->execute([$investasi_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current) {
            return null;
        }
        
        // Last 7 days trend
        $history_7d = get_daily_snapshot_history($koneksi, $investasi_id, 7);
        
        // Calculate 7-day trend
        $trend_7d = 'stabil';
        $change_7d = 0;
        if (count($history_7d) >= 2) {
            $latest = $history_7d[0];
            $oldest = end($history_7d);
            $change_7d = (float)$latest['nilai_akhir'] - (float)$oldest['nilai_akhir'];
            $trend_7d = $change_7d > 0 ? 'naik' : ($change_7d < 0 ? 'turun' : 'stabil');
        }
        
        // Last 30 days stats
        $history_30d = get_daily_snapshot_history($koneksi, $investasi_id, 30);
        $naik_count = 0;
        $turun_count = 0;
        foreach ($history_30d as $day) {
            if ($day['status_perubahan'] === 'naik') $naik_count++;
            if ($day['status_perubahan'] === 'turun') $turun_count++;
        }
        
        // Monthly performance
        $monthly = get_monthly_performance($koneksi, $investasi_id, 6);
        
        return [
            'current_value' => $current,
            'trend_7_days' => [
                'status' => $trend_7d,
                'change' => $change_7d,
                'percentage' => count($history_7d) > 0 ? 
                    ((float)$history_7d[0]['perubahan_nilai']) : 0
            ],
            'last_30_days' => [
                'days_up' => $naik_count,
                'days_down' => $turun_count,
                'days_stable' => count($history_30d) - $naik_count - $turun_count,
                'history' => $history_30d
            ],
            'monthly_stats' => $monthly,
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        error_log("Get Performance Summary Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Batch recalculate all investments
 * Should be run via cron job daily
 * 
 * @param PDO $koneksi Database connection
 * @return array Batch result
 */
function batch_recalculate_all_investments($koneksi) {
    $start_time = microtime(true);
    
    $result = auto_recalculate_investment($koneksi, null);
    
    // Update monthly stats for current month
    update_monthly_performance($koneksi);
    
    $execution_time = microtime(true) - $start_time;
    
    error_log("Batch Recalculate Complete: {$result['updated_count']} investments in " . 
              number_format($execution_time, 2) . " seconds");
    
    return [
        'success' => $result['success'],
        'updated_count' => $result['updated_count'],
        'execution_time' => $execution_time,
        'global_stats' => $result['global_stats'],
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

// ========================================
// 6. TRIGGER FUNCTIONS - AUTO CALL
// ========================================

/**
 * Trigger after adding profit (keuntungan)
 * Call this in upload_keuntungan.php after successful insert
 * 
 * @param PDO $koneksi Database connection
 * @param int $keuntungan_id Profit record ID
 * @return array Result
 */
function trigger_after_profit_added($koneksi, $keuntungan_id) {
    try {
        // Get profit details
        $sql = "SELECT investasi_id, jumlah_keuntungan, tanggal_keuntungan
                FROM keuntungan_investasi WHERE id = ?";
        $stmt = $koneksi->prepare($sql);
        $stmt->execute([$keuntungan_id]);
        $profit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$profit) {
            throw new Exception("Profit record not found");
        }
        
        $investasi_id = $profit['investasi_id'];
        $jumlah = (float)$profit['jumlah_keuntungan'];
        
        // Recalculate investment
        $recalc_result = auto_recalculate_investment($koneksi, $investasi_id);
        
        if (!$recalc_result['success']) {
            throw new Exception("Recalculation failed: " . $recalc_result['error']);
        }
        
        // Log the change
        $new_value = $recalc_result['investments'][$investasi_id]['nilai_sekarang'];
        log_investment_change($koneksi, $investasi_id, 'keuntungan', $keuntungan_id,
                             $new_value, $jumlah, 
                             "Profit added: " . format_currency($jumlah));
        
        // Update daily snapshot
        update_daily_snapshot($koneksi, $investasi_id, $new_value, $jumlah, 0);
        
        return [
            'success' => true,
            'message' => 'Investment recalculated after profit added',
            'new_value' => $new_value,
            'roi' => $recalc_result['investments'][$investasi_id]['roi_persen']
        ];
        
    } catch (Exception $e) {
        error_log("Trigger After Profit Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Trigger after adding loss (kerugian)
 * Call this in upload_kerugian.php after successful insert
 * 
 * @param PDO $koneksi Database connection
 * @param int $kerugian_id Loss record ID
 * @return array Result
 */
function trigger_after_loss_added($koneksi, $kerugian_id) {
    try {
        // Get loss details
        $sql = "SELECT investasi_id, jumlah_kerugian, tanggal_kerugian
                FROM kerugian_investasi WHERE id = ?";
        $stmt = $koneksi->prepare($sql);
        $stmt->execute([$kerugian_id]);
        $loss = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$loss) {
            throw new Exception("Loss record not found");
        }
        
        $investasi_id = $loss['investasi_id'];
        $jumlah = (float)$loss['jumlah_kerugian'];
        
        // Recalculate investment
        $recalc_result = auto_recalculate_investment($koneksi, $investasi_id);
        
        if (!$recalc_result['success']) {
            throw new Exception("Recalculation failed: " . $recalc_result['error']);
        }
        
        // Log the change
        $new_value = $recalc_result['investments'][$investasi_id]['nilai_sekarang'];
        log_investment_change($koneksi, $investasi_id, 'kerugian', $kerugian_id,
                             $new_value, -$jumlah, 
                             "Loss added: " . format_currency($jumlah));
        
        // Update daily snapshot
        update_daily_snapshot($koneksi, $investasi_id, $new_value, 0, $jumlah);
        
        return [
            'success' => true,
            'message' => 'Investment recalculated after loss added',
            'new_value' => $new_value,
            'roi' => $recalc_result['investments'][$investasi_id]['roi_persen']
        ];
        
    } catch (Exception $e) {
        error_log("Trigger After Loss Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Trigger after updating profit/loss
 * Call this in edit pages after successful update
 * 
 * @param PDO $koneksi Database connection
 * @param int $investasi_id Investment ID
 * @param string $type 'profit' or 'loss'
 * @return array Result
 */
function trigger_after_transaction_updated($koneksi, $investasi_id, $type = 'profit') {
    try {
        // Recalculate investment
        $recalc_result = auto_recalculate_investment($koneksi, $investasi_id);
        
        if (!$recalc_result['success']) {
            throw new Exception("Recalculation failed: " . $recalc_result['error']);
        }
        
        $new_value = $recalc_result['investments'][$investasi_id]['nilai_sekarang'];
        
        // Log the change
        log_investment_change($koneksi, $investasi_id, 'koreksi', null,
                             $new_value, 0, 
                             ucfirst($type) . " transaction updated");
        
        // Update daily snapshot
        update_daily_snapshot($koneksi, $investasi_id, $new_value);
        
        return [
            'success' => true,
            'message' => 'Investment recalculated after update',
            'new_value' => $new_value
        ];
        
    } catch (Exception $e) {
        error_log("Trigger After Update Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Trigger after deleting profit/loss
 * Call this in delete pages after successful deletion
 * 
 * @param PDO $koneksi Database connection
 * @param int $investasi_id Investment ID
 * @param string $type 'profit' or 'loss'
 * @param float $amount Deleted amount
 * @return array Result
 */
function trigger_after_transaction_deleted($koneksi, $investasi_id, $type, $amount) {
    try {
        // Recalculate investment
        $recalc_result = auto_recalculate_investment($koneksi, $investasi_id);
        
        if (!$recalc_result['success']) {
            throw new Exception("Recalculation failed: " . $recalc_result['error']);
        }
        
        $new_value = $recalc_result['investments'][$investasi_id]['nilai_sekarang'];
        
        // Log the change
        $change_amount = $type === 'profit' ? -$amount : $amount;
        log_investment_change($koneksi, $investasi_id, 'koreksi', null,
                             $new_value, $change_amount, 
                             ucfirst($type) . " deleted: " . format_currency($amount));
        
        // Update daily snapshot
        update_daily_snapshot($koneksi, $investasi_id, $new_value);
        
        return [
            'success' => true,
            'message' => 'Investment recalculated after deletion',
            'new_value' => $new_value
        ];
        
    } catch (Exception $e) {
        error_log("Trigger After Delete Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// ========================================
// 7. DASHBOARD HELPER FUNCTIONS
// ========================================

/**
 * Get global statistics with trend analysis
 * Enhanced version of existing get_global_stats
 * 
 * @param PDO $koneksi Database connection
 * @return array Global stats with trends
 */
function get_enhanced_global_stats($koneksi) {
    try {
        // Current stats from view
        $sql = "SELECT * FROM v_statistik_global LIMIT 1";
        $stmt = $koneksi->query($sql);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get all investments for detailed analysis
        $sql_all = "SELECT * FROM v_investasi_summary ORDER BY roi_persen DESC";
        $stmt_all = $koneksi->query($sql_all);
        $all_investments = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
        
        // Categorize by performance
        $top_performers = [];
        $under_performers = [];
        $stable_performers = [];
        
        foreach ($all_investments as $inv) {
            $roi = (float)$inv['roi_persen'];
            if ($roi >= 10) {
                $top_performers[] = $inv;
            } elseif ($roi < 0) {
                $under_performers[] = $inv;
            } else {
                $stable_performers[] = $inv;
            }
        }
        
        // Get today's changes
        $today = date('Y-m-d');
        $sql_today = "SELECT 
                     COUNT(DISTINCT investasi_id) as investments_changed,
                     SUM(CASE WHEN status_perubahan = 'naik' THEN 1 ELSE 0 END) as naik,
                     SUM(CASE WHEN status_perubahan = 'turun' THEN 1 ELSE 0 END) as turun
                     FROM investasi_snapshot_harian
                     WHERE tanggal_snapshot = ?";
        $stmt_today = $koneksi->prepare($sql_today);
        $stmt_today->execute([$today]);
        $today_stats = $stmt_today->fetch(PDO::FETCH_ASSOC);
        
        return [
            'global' => $current,
            'performance_breakdown' => [
                'top_performers' => [
                    'count' => count($top_performers),
                    'investments' => array_slice($top_performers, 0, 5) // Top 5
                ],
                'under_performers' => [
                    'count' => count($under_performers),
                    'investments' => array_slice($under_performers, 0, 5)
                ],
                'stable' => [
                    'count' => count($stable_performers)
                ]
            ],
            'today_changes' => $today_stats,
            'total_active' => count($all_investments),
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        error_log("Enhanced Global Stats Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get investment alerts
 * Returns investments that need attention
 * 
 * @param PDO $koneksi Database connection
 * @return array Alerts
 */
function get_investment_alerts($koneksi) {
    try {
        $alerts = [];
        
        // Get all active investments
        $sql = "SELECT * FROM v_investasi_summary";
        $stmt = $koneksi->query($sql);
        $investments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($investments as $inv) {
            $roi = (float)$inv['roi_persen'];
            $id = $inv['id'];
            
            // Alert: Large loss (ROI < -20%)
            if ($roi < -20) {
                $alerts[] = [
                    'type' => 'danger',
                    'investasi_id' => $id,
                    'judul' => $inv['judul_investasi'],
                    'message' => 'Kerugian besar: ROI ' . number_format($roi, 2) . '%',
                    'action' => 'Pertimbangkan untuk cut loss'
                ];
            }
            
            // Alert: Excellent performance (ROI > 50%)
            if ($roi > 50) {
                $alerts[] = [
                    'type' => 'success',
                    'investasi_id' => $id,
                    'judul' => $inv['judul_investasi'],
                    'message' => 'Performa sangat baik: ROI ' . number_format($roi, 2) . '%',
                    'action' => 'Pertimbangkan untuk take profit'
                ];
            }
            
            // Alert: Declining trend (last 7 days)
            $history = get_daily_snapshot_history($koneksi, $id, 7);
            $decline_count = 0;
            foreach ($history as $day) {
                if ($day['status_perubahan'] === 'turun') $decline_count++;
            }
            
            if ($decline_count >= 5) {
                $alerts[] = [
                    'type' => 'warning',
                    'investasi_id' => $id,
                    'judul' => $inv['judul_investasi'],
                    'message' => 'Trend menurun: ' . $decline_count . ' dari 7 hari terakhir turun',
                    'action' => 'Monitor ketat'
                ];
            }
        }
        
        return $alerts;
        
    } catch (Exception $e) {
        error_log("Get Investment Alerts Error: " . $e->getMessage());
        return [];
    }
}

// ========================================
// 8. MAINTENANCE & UTILITY
// ========================================

/**
 * Clean old snapshots (keep last 90 days only)
 * Should be run monthly via cron
 * 
 * @param PDO $koneksi Database connection
 * @param int $keep_days Number of days to keep
 * @return int Number of deleted records
 */
function cleanup_old_snapshots($koneksi, $keep_days = 90) {
    try {
        $cutoff_date = date('Y-m-d', strtotime("-$keep_days days"));
        
        $sql = "DELETE FROM investasi_snapshot_harian 
                WHERE tanggal_snapshot < ?";
        $stmt = $koneksi->prepare($sql);
        $stmt->execute([$cutoff_date]);
        
        $deleted = $stmt->rowCount();
        error_log("Cleanup: Deleted $deleted old snapshot records");
        
        return $deleted;
        
    } catch (Exception $e) {
        error_log("Cleanup Snapshots Error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Initialize snapshots for existing investments
 * Run once after database migration
 * 
 * @param PDO $koneksi Database connection
 * @return array Result
 */
function initialize_snapshots_for_existing_investments($koneksi) {
    try {
        $sql = "SELECT id FROM investasi WHERE status = 'aktif'";
        $stmt = $koneksi->query($sql);
        $investments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $initialized = 0;
        foreach ($investments as $inv) {
            $result = auto_recalculate_investment($koneksi, $inv['id']);
            if ($result['success']) {
                $initialized++;
            }
        }
        
        return [
            'success' => true,
            'initialized' => $initialized,
            'total' => count($investments)
        ];
        
    } catch (Exception $e) {
        error_log("Initialize Snapshots Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// ========================================
// EOF - Auto Calculate Investment v3.1
// ========================================
