<?php
if (session_status() === PHP_SESSION_NONE) { session_set_cookie_params(['path' =&gt; '/', 'secure' =&gt; false, 'httponly' =&gt; true, 'samesite' =&gt; 'Lax']); session_start(); }
header('Content-Type: application/json; charset=utf-8');
include_once(__DIR__ . '/../../../../controller/cDashboard.php');

$wh = $_GET['warehouse'] ?? null;
$c = new CDashboard();
try {
    $data = $c->fetchCategoryDistribution($wh);
    echo json_encode($data);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['labels'=>[], 'values'=>[], 'error' => $e->getMessage()]);
}
