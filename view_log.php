<?php
// view_barcode.php

$logFile = __DIR__ . "/barcode_log.txt";
$barcodes = [];

if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ví dụ dòng: "2025-09-24 13:04:27 - Barcode: TEST"
        if (preg_match('/Barcode:\s*(.*)$/', $line, $matches)) {
            $barcodes[] = trim($matches[1]);
        }
    }
}

// Xuất kết quả
if (empty($barcodes)) {
    echo "Chưa có dữ liệu barcode.";
} else {
    echo "<h2>Danh sách barcode</h2>";
    echo "<ul>";
    foreach ($barcodes as $bc) {
        echo "<li>" . htmlspecialchars($bc) . "</li>";
    }
    echo "</ul>";
}
