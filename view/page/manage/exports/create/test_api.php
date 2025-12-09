<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

header('Content-Type: application/json; charset=utf-8');

try {
    include_once(__DIR__ . "/../../../../../model/connect.php");
    
    $p = new clsKetNoi();
    $con = $p->moKetNoi();
    
    if (!$con) {
        throw new Exception('Connection failed');
    }
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Connection successful',
        'database' => 'Connected'
    ]);
    ob_end_flush();
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    ob_end_flush();
}
