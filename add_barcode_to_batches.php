 <?php
/**
 * Script thêm barcode cho các batch hiện có
 */

require_once 'model/connect.php';

$p = new clsKetNoi();
$con = $p->moKetNoi();

if (!$con) {
    die("Không thể kết nối MongoDB\n");
}

$col = $con->selectCollection('batches');

echo "=== UPDATING BATCHES WITH BARCODES ===\n\n";

$batches = $col->find([]);
$count = 0;

foreach ($batches as $batch) {
    $batchCode = $batch['batch_code'] ?? null;
    
    if (!$batchCode) {
        echo "❌ Batch without batch_code, skipping\n";
        continue;
    }
    
    // Kiểm tra xem đã có barcode chưa
    if (isset($batch['barcode']) && !empty($batch['barcode'])) {
        echo "✓ $batchCode: Already has barcode ({$batch['barcode']})\n";
        continue;
    }
    
    // Sử dụng batch_code làm barcode (đơn giản nhất)
    $barcode = $batchCode;
    
    // Update
    $result = $col->updateOne(
        ['batch_code' => $batchCode],
        ['$set' => ['barcode' => $barcode]]
    );
    
    if ($result->getModifiedCount() > 0) {
        echo "✅ $batchCode: Added barcode ($barcode)\n";
        $count++;
    } else {
        echo "⚠️ $batchCode: No changes\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "Total batches updated: $count\n";
echo "\n✅ You can now test the system at:\n";
echo "http://localhost:82/KLTN-main/batch_lookup.html\n";
echo "\nTry these barcodes:\n";

// Show some sample barcodes
$samples = $col->find([], ['limit' => 5, 'sort' => ['created_at' => -1]]);
foreach ($samples as $s) {
    echo "- " . ($s['barcode'] ?? $s['batch_code']) . " (" . ($s['product_name'] ?? 'N/A') . ")\n";
}

$p->dongKetNoi($con);
?>
