<?php
$_GET['barcode'] = $argv[1] ?? 'LH0005';
include __DIR__ . '/view/page/manage/receipts/get_barcode_or_batch.php';
