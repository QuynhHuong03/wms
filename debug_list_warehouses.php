<?php
// Quick debug endpoint: prints raw warehouses and what dashboard returns for warehousesSummary
ini_set('display_errors', 1);
error_reporting(E_ALL);

// include autoloads / classes
require_once __DIR__ . '/controller/cDashboard.php';
require_once __DIR__ . '/model/mWarehouse.php';

$c = new CDashboard();
// get full dashboard data for admin (roleId = 1)
$data = $c->getDashboardData(1, null);
$summary = $data['warehousesSummary'] ?? [];

$mw = new MWarehouse();
$all = $mw->getAllWarehouses();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'timestamp' => date(DATE_ATOM),
    'count_all' => is_array($all) ? count($all) : 0,
    'count_summary' => is_array($summary) ? count($summary) : 0,
    'all' => $all,
    'summary' => $summary
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

exit;
