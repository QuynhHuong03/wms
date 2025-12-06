<?php
require __DIR__ . '/controller/cDashboard.php';
$cd = new CDashboard();
$data = $cd->getDashboardData(2, 'KHO_CN_04');
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
