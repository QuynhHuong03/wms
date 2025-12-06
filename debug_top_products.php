<?php
require __DIR__ . '/controller/cDashboard.php';
$cd = new CDashboard();
$top = $cd->getTopMovingProducts(10);
echo json_encode($top, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
