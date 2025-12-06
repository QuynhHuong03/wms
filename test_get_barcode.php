<?php
parse_str('barcode=LH0005', $_GET);
ob_start();
include __DIR__ . '/view/page/manage/receipts/get_barcode_or_batch.php';
$out = ob_get_clean();
echo $out;
