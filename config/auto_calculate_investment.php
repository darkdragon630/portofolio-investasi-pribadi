<?php
/**
 * SAZEN Investment Portfolio Manager v3.1.1
 * Auto-Calculate Investment Values & Daily Tracking
 * 
 * FIXED v3.1.1:
 * ✅ Renamed get_monthly_performance() → get_investment_monthly_stats()
 * ✅ Renamed update_monthly_performance() → update_investment_monthly_stats()
 * ✅ Fixed function collision with functions.php
 * 
 * @version 3.1.1
 * @author SAAZ
 */

require_once "koneksi.php";

// ========================================
// 1. FUNGSI UTAMA: AUTO RECALCULATE
// ========================================

function auto_recalculate_investment($koneksi, $investasi_id = null) {
    try {
        $koneksi->beginTransaction();
        
        $investments = [];
        if ($investasi_id !== null) {
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
            
            $sql_keuntungan = "SELECT IFNULL(SUM(jumlah_keuntungan), 0) as total
                               FROM keuntungan_investasi WHERE investasi_id = ?";
            $stmt_k = $koneksi->prepare($sql_keuntungan);
            $stmt_k->execute([$inv_id]);
            $total_keuntungan = (float)$stmt_k->fetchColumn();
            
            $sql_kerugian = "SELECT IFNULL(SUM(jumlah_kerugian), 0) as total
                             FROM kerugian_investasi WHERE investasi_id = ?";
            $stmt_kr = $koneksi->prepare($sql_kerugian);
            $stmt_kr->execute([$inv_id]);
            $total_kerugian = (float)$stmt_kr->fetchColumn();
            
            $nilai_sekarang = $modal + $total_keuntungan - $total_kerugian;
            $roi_persen = $modal > 0 ? ((($nilai_sekarang - $modal) / $modal) * 100) : 0;
            
            $sql_update = "UPDATE investasi 
                          SET updated_at = CURRENT_TIMESTAMP
                          WHERE id = ?";
            $stmt_upd = $koneksi->prepare($sql_update);
            $stmt_upd->execute([$inv_id]);
            
            log_investment_change($koneksi, $inv_id, 'recalculation', null, 
                                 $nilai_sekarang, $total_keuntungan - $total_kerugian,
                                 "Auto-recalculate: Modal=$modal, Profit=$total_keuntungan, Loss=$total_kerugian");
            
            update_daily_snapshot($koneksi, $inv_id, $nilai_sekarang, 
                                 $total_keuntungan, $total_kerugian);
            
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

function update_daily_snapshot($koneksi, $investasi_id, $nilai_akhir, 
                               $total_keuntungan_harian = 0, $total_kerugian_harian = 0) {
    try {
        $sql = "SELECT tanggal_investasi, jumlah FROM investasi WHERE id = ?";
        $stmt = $koneksi->prepare($sql);
        $stmt->execute([$investasi_id]);
        $inv = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$inv) return false;
        
        $tanggal_investasi = $inv['tanggal_investasi'];
        $modal = (float)$inv['jumlah'];
        $today = date('Y-m-d');
        
        $start = new DateTime($tanggal_investasi);
        $end = new DateTime($today);
        $hari_ke = $start->diff($end)->days + 1;
        
        $sql_yesterday = "SELECT nilai_akhir FROM investasi_snapshot_harian
                         WHERE investasi_id = ? 
                         AND tanggal_snapshot < ?
                         ORDER BY tanggal_snapshot DESC LIMIT 1";
        $stmt_y = $koneksi->prepare($sql_yesterday);
        $stmt_y->execute([$investasi_id, $today]);
        $yesterday = $stmt_y->fetch(PDO::FETCH_ASSOC);
        $nilai_awal = $yesterday ? (float)$yesterday['nilai_akhir'] : $modal;
        
        $perubahan_nilai = $nilai_akhir - $nilai_awal;
        $persentase_perubahan = $nilai_awal > 0 ? (($perubahan_nilai / $nilai_awal) * 100) : 0;
        $roi_kumulatif = $modal > 0 ? ((($nilai_akhir - $modal) / $modal) * 100) : 0;
        
        $status_perubahan = 'stabil';
        if ($perubahan_nilai > 0.01) {
            $status_perubahan = 'naik';
        } elseif ($perubahan_nilai < -0.01) {
            $status_perubahan = 'turun';
        }
        
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

function log_investment_change($koneksi, $investasi_id, $tipe_perubahan, 
                               $referensi_id, $nilai_sesudah, $selisih, $keterangan = '') {
    try {
        $sql_prev = "SELECT nilai_sesudah FROM investasi_change_log
                     WHERE investasi_id = ?
                     ORDER BY created_at DESC LIMIT 1";
        $stmt_prev = $koneksi->prepare($sql_prev);
        $stmt_prev->execute([$investasi_id]);
        $prev = $stmt_prev->fetch(PDO::FETCH_ASSOC);
        $nilai_sebelum = $prev ? (float)$prev['nilai_sesudah'] : 0;
        
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
// *** RENAMED TO AVOID COLLISION ***
// ========================================

/**
 * Update monthly performance statistics for investments
 * RENAMED from update_monthly_performance() to update_investment_monthly_stats()
 */
function update_investment_monthly_stats($koneksi, $investasi_id = null, $bulan_tahun = null) {
    try {
        if (!$bulan_tahun) {
            $bulan_tahun = date('Y-m');
        }
        
        $investments = [];
        if ($investasi_id) {
            $investments[] = $investasi_id;
        } else {
            $sql = "SELECT id FROM investasi WHERE status IN ('aktif', 'terjual')";
            $stmt = $koneksi->query($sql);
            $investments = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
        }
        
        foreach ($investments as $inv_id) {
            $sql_profit = "SELECT 
                          IFNULL(SUM(jumlah_keuntungan), 0) as total,
                          COUNT(*) as jumlah
                          FROM keuntungan_investasi
                          WHERE investasi_id = ? 
                          AND DATE_FORMAT(tanggal_keuntungan, '%Y-%m') = ?";
            $stmt_p = $koneksi->prepare($sql_profit);
            $stmt_p->execute([$inv_id, $bulan_tahun]);
            $profit_data = $stmt_p->fetch(PDO::FETCH_ASSOC);
            
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
            
            $sql_modal = "SELECT jumlah FROM investasi WHERE id = ?";
            $stmt_m = $koneksi->prepare($sql_modal);
            $stmt_m->execute([$inv_id]);
            $modal = (float)$stmt_m->fetchColumn();
            $roi_bulan = $modal > 0 ? (($net_profit / $modal) * 100) : 0;
            
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
        error_log("Update Investment Monthly Stats Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get monthly performance stats for investments
 * RENAMED from get_monthly_performance() to get_investment_monthly_stats()
 */
function get_investment_monthly_stats($koneksi, $investasi_id, $months = 12) {
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
        error_log("Get Investment Monthly Stats Error: " . $e->getMessage());
        return [];
    }
}

// ========================================
// 5. HELPER FUNCTIONS
// ========================================

function get_investment_performance_summary($koneksi, $investasi_id) {
    try {
        $sql_current = "SELECT * FROM v_investasi_summary WHERE id = ?";
        $stmt = $koneksi->prepare($sql_current);
        $stmt->execute([$investasi_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current) {
            return null;
        }
        
        $history_7d = get_daily_snapshot_history($koneksi, $investasi_id, 7);
        
        $trend_7d = 'stabil';
        $change_7d = 0;
        if (count($history_7d) >= 2) {
            $latest = $history_7d[0];
            $oldest = end($history_7d);
            $change_7d = (float)$latest['nilai_akhir'] - (float)$oldest['nilai_akhir'];
            $trend_7d = $change_7d > 0 ? 'naik' : ($change_7d < 0 ? 'turun' : 'stabil');
        }
        
        $history_30d = get_daily_snapshot_history($koneksi, $investasi_id, 30);
        $naik_count = 0;
        $turun_count = 0;
        foreach ($history_30d as $day) {
            if ($day['status_perubahan'] === 'naik') $naik_count++;
            if ($day['status_perubahan'] === 'turun') $turun_count++;
        }
        
        $monthly = get_investment_monthly_stats($koneksi, $investasi_id, 6);
        
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

function batch_recalculate_all_investments($koneksi) {
    $start_time = microtime(true);
    
    $result = auto_recalculate_investment($koneksi, null);
    
    // Updated function call
    update_investment_monthly_stats($koneksi);
    
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
// 6. TRIGGER FUNCTIONS
// ========================================

function trigger_after_profit_added($koneksi, $keuntungan_id) {
    try {
        $sql = "SELECT investasi_id, jumlah_keuntungan, tanggal_keuntungan
                FROM keuntungan_investasi WHERE id = ?";
        $stmt = $koneksi->prepare($sql);
        $stmt->execute([$keuntungan_id]);
        $profit = $stmt->fetch(PDO::FETCH_ASSOC);
        $sql_update = "UPDATE investasi SET updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $koneksi->prepare($sql_update);
        $stmt->execute([$investasi_id]);
        if (!$profit) {
            throw new Exception("Profit record not found");
        }
        
        $investasi_id = $profit['investasi_id'];
        $jumlah = (float)$profit['jumlah_keuntungan'];
        
        $recalc_result = auto_recalculate_investment($koneksi, $investasi_id);
        
        if (!$recalc_result['success']) {
            throw new Exception("Recalculation failed: " . $recalc_result['error']);
        }
        
        $new_value = $recalc_result['investments'][$investasi_id]['nilai_sekarang'];
        log_investment_change($koneksi, $investasi_id, 'keuntungan', $keuntungan_id,
                             $new_value, $jumlah, 
                             "Profit added: " . format_currency($jumlah));
        
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

function trigger_after_loss_added($koneksi, $kerugian_id) {
    try {
        $sql = "SELECT investasi_id, jumlah_kerugian, tanggal_kerugian
                FROM kerugian_investasi WHERE id = ?";
        $stmt = $koneksi->prepare($sql);
        $stmt->execute([$kerugian_id]);
        $loss = $stmt->fetch(PDO::FETCH_ASSOC);
        $sql_update = "UPDATE investasi SET updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $koneksi->prepare($sql_update);
        $stmt->execute([$investasi_id]);
        
        if (!$loss) {
            throw new Exception("Loss record not found");
        }
        
        $investasi_id = $loss['investasi_id'];
        $jumlah = (float)$loss['jumlah_kerugian'];
        
        $recalc_result = auto_recalculate_investment($koneksi, $investasi_id);
        
        if (!$recalc_result['success']) {
            throw new Exception("Recalculation failed: " . $recalc_result['error']);
        }
        
        $new_value = $recalc_result['investments'][$investasi_id]['nilai_sekarang'];
        log_investment_change($koneksi, $investasi_id, 'kerugian', $kerugian_id,
                             $new_value, -$jumlah, 
                             "Loss added: " . format_currency($jumlah));
        
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

function trigger_after_transaction_updated($koneksi, $investasi_id, $type = 'profit') {
    try {
        $recalc_result = auto_recalculate_investment($koneksi, $investasi_id);
        
        if (!$recalc_result['success']) {
            throw new Exception("Recalculation failed: " . $recalc_result['error']);
        }
        
        $new_value = $recalc_result['investments'][$investasi_id]['nilai_sekarang'];
        
        log_investment_change($koneksi, $investasi_id, 'koreksi', null,
                             $new_value, 0, 
                             ucfirst($type) . " transaction updated");
        
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

function trigger_after_transaction_deleted($koneksi, $investasi_id, $type, $amount) {
    try {
        $recalc_result = auto_recalculate_investment($koneksi, $investasi_id);
        
        if (!$recalc_result['success']) {
            throw new Exception("Recalculation failed: " . $recalc_result['error']);
        }
        
        $new_value = $recalc_result['investments'][$investasi_id]['nilai_sekarang'];
        
        $change_amount = $type === 'profit' ? -$amount : $amount;
        log_investment_change($koneksi, $investasi_id, 'koreksi', null,
                             $new_value, $change_amount, 
                             ucfirst($type) . " deleted: " . format_currency($amount));
        
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

function get_enhanced_global_stats($koneksi) {
    try {
        $sql = "SELECT * FROM v_statistik_global LIMIT 1";
        $stmt = $koneksi->query($sql);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $sql_all = "SELECT * FROM v_investasi_summary ORDER BY roi_persen DESC";
        $stmt_all = $koneksi->query($sql_all);
        $all_investments = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
        
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
                    'investments' => array_slice($top_performers, 0, 5)
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

function get_investment_alerts($koneksi) {
    try {
        $alerts = [];
        
        $sql = "SELECT * FROM v_investasi_summary";
        $stmt = $koneksi->query($sql);
        $investments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($investments as $inv) {
            $roi = (float)$inv['roi_persen'];
            $id = $inv['id'];
            
            if ($roi < -20) {
                $alerts[] = [
                    'type' => 'danger',
                    'investasi_id' => $id,
                    'judul' => $inv['judul_investasi'],
                    'message' => 'Kerugian besar: ROI ' . number_format($roi, 2) . '%',
                    'action' => 'Pertimbangkan untuk cut loss'
                ];
            }
            
            if ($roi > 50) {
                $alerts[] = [
                    'type' => 'success',
                    'investasi_id' => $id,
                    'judul' => $inv['judul_investasi'],
                    'message' => 'Performa sangat baik: ROI ' . number_format($roi, 2) . '%',
                    'action' => 'Pertimbangkan untuk take profit'
                ];
            }
            
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
// EOF - Auto Calculate Investment v3.1.1
// ========================================
