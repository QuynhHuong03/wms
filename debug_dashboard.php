<?php
require __DIR__ . '/controller/cDashboard.php';
// Run as CLI: php debug_dashboard.php
$cd = new CDashboard();
$data = $cd->getDashboardData(1, null);
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
