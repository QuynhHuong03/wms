<?php
require __DIR__ . '/model/mReceipt.php';
$m = new MReceipt();
$r = $m->getReceiptById('IR0007');
echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
