<?php
$code = $argv[1] ?? 'LH0005';
ob_start();
$_GET['barcode'] = $code;
include __DIR__ . '/view/page/manage/receipts/get_barcode_or_batch.php';
$raw = ob_get_clean();
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "Raw response:\n" . $raw . "\n";
    exit(1);
}
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
