<?php
/**
 * SAZEN Investment Portfolio Manager
 * Migration Script: Convert Raw Binary Bukti Files to Metadata Format
 * 
 * Usage: php migrate_binary_bukti.php
 * 
 * This script will:
 * 1. Find all raw binary files in database
 * 2. Convert them to: {"metadata"}|||base64_encoded_data
 * 3. Backup original data before update
 */

require_once 'config/koneksi.php';

// Configuration
$DRY_RUN = false; // Set to true to test without updating
$BACKUP_TABLE = true; // Create backup before update

echo "=== SAZEN Binary Bukti Migration Script ===\n";
echo "Dry Run Mode: " . ($DRY_RUN ? "YES (no changes)" : "NO (will update)") . "\n";
echo "Backup: " . ($BACKUP_TABLE ? "YES" : "NO") . "\n\n";

// Statistics
$stats = [
    'investasi_checked' => 0,
    'investasi_migrated' => 0,
    'keuntungan_checked' => 0,
    'keuntungan_migrated' => 0,
    'kerugian_checked' => 0,
    'kerugian_migrated' => 0,
    'errors' => 0
];

try {
    // Start transaction
    $koneksi->beginTransaction();
    
    // ============================================
    // BACKUP TABLES (Optional but recommended)
    // ============================================
    if ($BACKUP_TABLE && !$DRY_RUN) {
        echo "📦 Creating backup tables...\n";
        
        $backup_suffix = date('Ymd_His');
        
        $koneksi->exec("CREATE TABLE IF NOT EXISTS investasi_backup_$backup_suffix AS SELECT * FROM investasi");
        $koneksi->exec("CREATE TABLE IF NOT EXISTS keuntungan_investasi_backup_$backup_suffix AS SELECT * FROM keuntungan_investasi");
        $koneksi->exec("CREATE TABLE IF NOT EXISTS kerugian_investasi_backup_$backup_suffix AS SELECT * FROM kerugian_investasi");
        
        echo "✓ Backup created: *_backup_$backup_suffix\n\n";
    }
    
    // ============================================
    // MIGRATE INVESTASI
    // ============================================
    echo "📊 Checking investasi table...\n";
    
    $stmt = $koneksi->query("
        SELECT id, judul_investasi, bukti_file, tanggal_investasi
        FROM investasi 
        WHERE LENGTH(bukti_file) > 0 
          AND bukti_file NOT LIKE '{%'
          AND bukti_file NOT LIKE '%|||%'
    ");
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stats['investasi_checked'] = count($rows);
    
    echo "Found {$stats['investasi_checked']} investasi with raw binary\n";
    
    foreach ($rows as $row) {
        echo "  Processing investasi ID {$row['id']}: {$row['judul_investasi']}...\n";
        
        try {
            $new_format = migrate_binary_file($row['bukti_file'], 'investasi', $row['id'], $row['tanggal_investasi']);
            
            if ($new_format && !$DRY_RUN) {
                $update = $koneksi->prepare("UPDATE investasi SET bukti_file = ? WHERE id = ?");
                $update->execute([$new_format, $row['id']]);
                echo "    ✓ Migrated successfully\n";
                $stats['investasi_migrated']++;
            } elseif ($new_format) {
                echo "    ✓ Would migrate (dry run)\n";
                $stats['investasi_migrated']++;
            } else {
                echo "    ✗ Failed to migrate\n";
                $stats['errors']++;
            }
        } catch (Exception $e) {
            echo "    ✗ Error: " . $e->getMessage() . "\n";
            $stats['errors']++;
        }
    }
    
    echo "\n";
    
    // ============================================
    // MIGRATE KEUNTUNGAN
    // ============================================
    echo "📊 Checking keuntungan_investasi table...\n";
    
    $stmt = $koneksi->query("
        SELECT id, judul_keuntungan, investasi_id, bukti_file, tanggal_keuntungan
        FROM keuntungan_investasi 
        WHERE LENGTH(bukti_file) > 0 
          AND bukti_file NOT LIKE '{%'
          AND bukti_file NOT LIKE '%|||%'
    ");
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stats['keuntungan_checked'] = count($rows);
    
    echo "Found {$stats['keuntungan_checked']} keuntungan with raw binary\n";
    
    foreach ($rows as $row) {
        echo "  Processing keuntungan ID {$row['id']}: {$row['judul_keuntungan']}...\n";
        
        try {
            $new_format = migrate_binary_file($row['bukti_file'], 'keuntungan', $row['id'], $row['tanggal_keuntungan']);
            
            if ($new_format && !$DRY_RUN) {
                $update = $koneksi->prepare("UPDATE keuntungan_investasi SET bukti_file = ? WHERE id = ?");
                $update->execute([$new_format, $row['id']]);
                echo "    ✓ Migrated successfully\n";
                $stats['keuntungan_migrated']++;
            } elseif ($new_format) {
                echo "    ✓ Would migrate (dry run)\n";
                $stats['keuntungan_migrated']++;
            } else {
                echo "    ✗ Failed to migrate\n";
                $stats['errors']++;
            }
        } catch (Exception $e) {
            echo "    ✗ Error: " . $e->getMessage() . "\n";
            $stats['errors']++;
        }
    }
    
    echo "\n";
    
    // ============================================
    // MIGRATE KERUGIAN
    // ============================================
    echo "📊 Checking kerugian_investasi table...\n";
    
    $stmt = $koneksi->query("
        SELECT id, judul_kerugian, investasi_id, bukti_file, tanggal_kerugian
        FROM kerugian_investasi 
        WHERE LENGTH(bukti_file) > 0 
          AND bukti_file NOT LIKE '{%'
          AND bukti_file NOT LIKE '%|||%'
    ");
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stats['kerugian_checked'] = count($rows);
    
    echo "Found {$stats['kerugian_checked']} kerugian with raw binary\n";
    
    foreach ($rows as $row) {
        echo "  Processing kerugian ID {$row['id']}: {$row['judul_kerugian']}...\n";
        
        try {
            $new_format = migrate_binary_file($row['bukti_file'], 'kerugian', $row['id'], $row['tanggal_kerugian']);
            
            if ($new_format && !$DRY_RUN) {
                $update = $koneksi->prepare("UPDATE kerugian_investasi SET bukti_file = ? WHERE id = ?");
                $update->execute([$new_format, $row['id']]);
                echo "    ✓ Migrated successfully\n";
                $stats['kerugian_migrated']++;
            } elseif ($new_format) {
                echo "    ✓ Would migrate (dry run)\n";
                $stats['kerugian_migrated']++;
            } else {
                echo "    ✗ Failed to migrate\n";
                $stats['errors']++;
            }
        } catch (Exception $e) {
            echo "    ✗ Error: " . $e->getMessage() . "\n";
            $stats['errors']++;
        }
    }
    
    echo "\n";
    
    // Commit transaction
    if (!$DRY_RUN) {
        $koneksi->commit();
        echo "✓ Transaction committed\n\n";
    } else {
        $koneksi->rollBack();
        echo "ℹ Dry run - no changes made\n\n";
    }
    
    // ============================================
    // SUMMARY
    // ============================================
    echo "=== Migration Summary ===\n";
    echo "Investasi: {$stats['investasi_migrated']}/{$stats['investasi_checked']} migrated\n";
    echo "Keuntungan: {$stats['keuntungan_migrated']}/{$stats['keuntungan_checked']} migrated\n";
    echo "Kerugian: {$stats['kerugian_migrated']}/{$stats['kerugian_checked']} migrated\n";
    echo "Errors: {$stats['errors']}\n";
    
    $total_migrated = $stats['investasi_migrated'] + $stats['keuntungan_migrated'] + $stats['kerugian_migrated'];
    echo "\nTotal migrated: $total_migrated files\n";
    
    if ($stats['errors'] > 0) {
        echo "\n⚠ WARNING: {$stats['errors']} errors occurred\n";
    } else {
        echo "\n✓ All files migrated successfully!\n";
    }
    
} catch (Exception $e) {
    $koneksi->rollBack();
    echo "✗ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

/**
 * Migrate binary file to metadata|||base64 format
 */
function migrate_binary_file($binary_data, $type, $id, $upload_date) {
    if (empty($binary_data)) {
        return null;
    }
    
    // Detect file type by magic number
    $hex = bin2hex(substr($binary_data, 0, 4));
    
    if ($hex === 'ffd8ffe0' || $hex === 'ffd8ffe1' || substr($hex, 0, 4) === 'ffd8') {
        $ext = 'jpeg';
        $mime = 'image/jpeg';
    } elseif ($hex === '89504e47') {
        $ext = 'png';
        $mime = 'image/png';
    } elseif (substr($binary_data, 0, 4) === '%PDF') {
        $ext = 'pdf';
        $mime = 'application/pdf';
    } else {
        echo "    ⚠ Unknown file type (hex: $hex)\n";
        return null;
    }
    
    $size = strlen($binary_data);
    $original_name = "{$type}_{$id}_{$upload_date}.$ext";
    $original_name = str_replace([':', ' '], ['', '_'], $original_name); // Clean filename
    
    // Create metadata JSON
    $metadata = json_encode([
        'original_name' => $original_name,
        'extension' => $ext,
        'size' => $size,
        'mime_type' => $mime,
        'uploaded_at' => $upload_date,
        'migrated_from_binary' => true
    ], JSON_UNESCAPED_SLASHES);
    
    if ($metadata === false) {
        throw new Exception("JSON encoding failed for metadata");
    }
    
    // Encode binary to base64
    $base64 = base64_encode($binary_data);
    
    // Combine: metadata|||base64
    $new_format = $metadata . '|||' . $base64;
    
    echo "    → Original size: " . format_size($size) . "\n";
    echo "    → New format size: " . format_size(strlen($new_format)) . "\n";
    echo "    → File type: $mime\n";
    
    return $new_format;
}

/**
 * Format file size
 */
function format_size($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

echo "\n=== Migration Complete ===\n";
