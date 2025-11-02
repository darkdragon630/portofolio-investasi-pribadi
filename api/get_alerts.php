<?php
header('Content-Type: application/json');
require_once "../config/koneksi.php";
require_once "../config/auto_calculate_investment.php";

$alerts = get_investment_alerts($koneksi);
echo json_encode($alerts, JSON_PRETTY_PRINT);
