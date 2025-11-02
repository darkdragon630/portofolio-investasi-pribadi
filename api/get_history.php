<?php
header('Content-Type: application/json');
require_once "../config/koneksi.php";
require_once "../config/auto_calculate_investment.php";

$id = (int)($_GET['id'] ?? 0);
$days = (int)($_GET['days'] ?? 30);

if ($id <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

$history = get_daily_snapshot_history($koneksi, $id, $days);
echo json_encode($history, JSON_PRETTY_PRINT);
