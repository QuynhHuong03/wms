<?php
// receive.php

// Lấy barcode từ URL (?code=...)
$barcode = isset($_GET['code']) ? trim($_GET['code']) : '';

if ($barcode !== '') {
    // File log nằm cùng thư mục
    $logFile = __DIR__ . "/barcode_log.txt";

    // Ghi barcode kèm thời gian
    $line = date("Y-m-d H:i:s") . " - " . $barcode . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

    // Trả phản hồi ra màn hình
    echo "Đã nhận barcode: " . htmlspecialchars($barcode);
} else {
    echo "Chưa nhận được barcode!";
}
