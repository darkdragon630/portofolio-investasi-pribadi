<?php
header('Content-Type: application/json');
require_once "../config/koneksi.php";
require_once "../config/auto_calculate_investment.php";

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

$summary = get_investment_performance_summary($koneksi, $id);
echo json_encode($summary, JSON_PRETTY_PRINT);
