<?php
require_once '../config/koneksi.php';

try {
    $stmt = $koneksi->prepare("INSERT INTO users (username, email, password, created_at, failed_attempts, locked_until)
                               VALUES (?, ?, ?, NOW(), 0, NULL)");
    $stmt->execute(['testuser', 'test@example.com', password_hash('12345678', PASSWORD_BCRYPT)]);
    echo "Inserted UID = " . $koneksi->lastInsertId();
} catch (PDOException $e) {
    echo "Insert failed: " . $e->getMessage();
}