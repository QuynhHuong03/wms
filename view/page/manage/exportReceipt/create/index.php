<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include_once(__DIR__ . "/../../../../../controller/cRequest.php");
include_once(__DIR__ . "/../../../../../controller/cInventory.php");
include_once(__DIR__ . "/../../../../../controller/cWarehouse.php");

// Lấy request_id từ URL
$request_id = $_GET['request_id'] ?? null;

if (!$request_id) {
    $_SESSION['flash_request_error'] = 'Không tìm thấy mã phiếu yêu cầu!';
    header("Location: ../../index.php?page=goodsReceiptRequest");
    exit();
}

$cRequest = new CRequest();
$cInventory = new CInventory();
$cWarehouse = new CWarehouse();

// Lấy thông tin phiếu yêu cầu
$request = $cRequest->getRequestById($request_id);

if (!$request) {
    $_SESSION['flash_request_error'] = 'Không tìm thấy phiếu yêu cầu!';
    header("Location: ../../index.php?page=goodsReceiptRequest");
    exit();
}

// Kiểm tra status phải là 3 (đã xác nhận đủ hàng)
if ($request['status'] != 3) {
    $_SESSION['flash_request_error'] = 'Phiếu chưa được xác nhận đủ hàng!';
    header("Location: ../../index.php?page=goodsReceiptRequest");
    exit();
}

// Xử lý tạo phiếu xuất khi submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['login']['user_id'] ?? 'U001';
    
    // Sinh mã phiếu xuất
    include_once(__DIR__ . "/../../../../../model/connect.php");
    $p = new clsKetNoi();
    $con = $p->moKetNoi();
    if (!$con) {
        $_SESSION['flash_request_error'] = 'Lỗi kết nối database!';
        header("Location: ../../index.php?page=goodsReceiptRequest");
        exit();
    }
    
    $transCol = $con->selectCollection('transactions');
    $lastExport = $transCol->findOne(
        ['transaction_type' => 'export'],
        ['sort' => ['transaction_id' => -1]]
    );
    
    $exportId = 'EX0001';
    if ($lastExport && isset($lastExport['transaction_id'])) {
        $lastId = $lastExport['transaction_id'];
        if (preg_match('/(\d+)$/', $lastId, $m)) {
            $num = intval($m[1]) + 1;
            $exportId = 'EX' . str_pad($num, 4, '0', STR_PAD_LEFT);
        }
    }
    
    // Tạo phiếu xuất
    $exportDoc = [
        'transaction_id' => $exportId,
        'transaction_type' => 'export',
        'type' => 'transfer',
        'warehouse_id' => $request['source_warehouse_id'], // Kho xuất (Kho Tổng)
        'destination_warehouse_id' => $request['warehouse_id'], // Kho nhận (Chi nhánh)
        'request_id' => $request_id,
        'created_by' => $user_id,
        'created_at' => new MongoDB\BSON\UTCDateTime(),
        'status' => 0, // 0: Chờ xác nhận, 1: Đã xác nhận
        'details' => $request['details'],
        'note' => $_POST['note'] ?? "Phiếu xuất kho cho yêu cầu $request_id"
    ];
    
    try {
        $result = $transCol->insertOne($exportDoc);
        
        if ($result->getInsertedCount() > 0) {
            // Trừ inventory kho xuất theo FIFO (First In First Out)
            $inventoryCol = $con->selectCollection('inventory');
            $allProductsProcessed = true;
            
            foreach ($request['details'] as $item) {
                $productId = $item['product_id'];
                $qty = (int)($item['quantity'] ?? 0);
                $factor = (int)($item['conversion_factor'] ?? 1);
                $neededQty = $qty * $factor; // Tổng số lượng cần trừ
                
                // Lấy các lô hàng theo thứ tự FIFO (received_at tăng dần - nhập sớm nhất trước)
                $inventoryEntries = $inventoryCol->find(
                    [
                        'warehouse_id' => $request['source_warehouse_id'],
                        'product_id' => $productId,
                        'qty' => ['$gt' => 0]
                    ],
                    [
                        'sort' => ['received_at' => 1], // Sắp xếp theo ngày nhập tăng dần (FIFO)
                        'limit' => 100
                    ]
                )->toArray();
                
                $remainingQty = $neededQty;
                
                // Trừ dần từng lô hàng theo FIFO
                foreach ($inventoryEntries as $entry) {
                    if ($remainingQty <= 0) break;
                    
                    $entryQty = (int)($entry['qty'] ?? 0);
                    $entryId = $entry['_id'];
                    
                    if ($entryQty >= $remainingQty) {
                        // Lô này đủ để trừ hết
                        $inventoryCol->updateOne(
                            ['_id' => $entryId],
                            ['$inc' => ['qty' => -$remainingQty]]
                        );
                        $remainingQty = 0;
                    } else {
                        // Lô này không đủ, trừ hết và chuyển sang lô tiếp theo
                        $inventoryCol->updateOne(
                            ['_id' => $entryId],
                            ['$set' => ['qty' => 0]]
                        );
                        $remainingQty -= $entryQty;
                    }
                }
                
                // Kiểm tra xem có trừ đủ không
                if ($remainingQty > 0) {
                    $allProductsProcessed = false;
                    error_log("WARNING: Không đủ inventory để trừ sản phẩm $productId. Còn thiếu: $remainingQty");
                }
            }
            
            // Cập nhật status phiếu yêu cầu thành 6 (completed)
            include_once(__DIR__ . "/../../../../../model/mRequest.php");
            $mRequest = new MRequest();
            $mRequest->updateRequest($request_id, [
                'status' => 6,
                'completed_at' => new MongoDB\BSON\UTCDateTime(),
                'export_id' => $exportId,
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]);
            
            $p->dongKetNoi($con);
            
            if ($allProductsProcessed) {
                $_SESSION['flash_request'] = "✅ Tạo phiếu xuất $exportId thành công! Đã trừ inventory theo FIFO. Phiếu yêu cầu đã hoàn thành.";
            } else {
                $_SESSION['flash_request'] = "⚠️ Tạo phiếu xuất $exportId thành công nhưng một số sản phẩm không đủ tồn kho để trừ!";
            }
            header("Location: ../../index.php?page=goodsReceiptRequest");
            exit();
        } else {
            throw new Exception("Không thể tạo phiếu xuất!");
        }
    } catch (Exception $e) {
        $p->dongKetNoi($con);
        $_SESSION['flash_request_error'] = 'Lỗi tạo phiếu xuất: ' . $e->getMessage();
    }
}

// Lấy thông tin kho
$sourceWarehouse = $cWarehouse->getWarehouseById($request['source_warehouse_id']);
$destWarehouse = $cWarehouse->getWarehouseById($request['warehouse_id']);
?>

<style>
  .export-container {max-width:1200px;margin:30px auto;background:#fff;padding:30px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.1);}
  .export-header {border-bottom:3px solid #ff9800;padding-bottom:20px;margin-bottom:30px;}
  .export-header h2 {color:#ff9800;margin:0;font-size:28px;}
  .export-header .subtitle {color:#666;font-size:14px;margin-top:8px;}
  
  .info-section {background:#fff8e1;padding:20px;border-radius:8px;margin-bottom:25px;border-left:4px solid #ff9800;}
  .info-section h3 {color:#ff9800;font-size:18px;margin-bottom:15px;}
  .info-row {display:grid;grid-template-columns:200px 1fr;gap:10px;margin-bottom:12px;font-size:14px;}
  .info-row strong {color:#495057;}
  
  .products-section {margin-top:30px;}
  .products-section h3 {color:#ff9800;font-size:18px;margin-bottom:15px;border-bottom:2px solid #ff9800;padding-bottom:8px;}
  .products-table {width:100%;border-collapse:collapse;margin-top:15px;box-shadow:0 2px 8px rgba(0,0,0,0.05);}
  .products-table th,.products-table td {padding:12px;border:1px solid #dee2e6;text-align:left;font-size:14px;}
  .products-table th {background:#ff9800;color:#fff;font-weight:600;text-align:center;}
  .products-table tbody tr:hover {background:#fff8e1;}
  .products-table td:nth-child(1) {text-align:center;width:50px;}
  .products-table td:nth-child(3),.products-table td:nth-child(4),.products-table td:nth-child(5) {text-align:center;}
  
  .note-section {margin-top:25px;}
  .note-section label {display:block;font-weight:600;margin-bottom:10px;color:#333;}
  .note-section textarea {width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:14px;min-height:100px;font-family:inherit;}
  
  .actions {margin-top:30px;display:flex;gap:15px;justify-content:flex-end;}
  .btn {padding:12px 30px;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;transition:0.3s;}
  .btn-submit {background:#ff9800;color:#fff;}
  .btn-submit:hover {background:#f57c00;}
  .btn-cancel {background:#6c757d;color:#fff;}
  .btn-cancel:hover {background:#5a6268;}
  
  .warning-box {background:#fff3cd;border:1px solid #ffc107;padding:15px;border-radius:8px;margin-bottom:20px;color:#856404;}
  .warning-box strong {color:#d97706;}
</style>

<div class="export-container">
  <div class="export-header">
    <h2>📤 Tạo phiếu xuất kho</h2>
    <div class="subtitle">Từ phiếu yêu cầu: <?= htmlspecialchars($request_id) ?></div>
  </div>

  <div class="warning-box">
    <strong>⚠️ Lưu ý:</strong> Sau khi tạo phiếu xuất, hàng sẽ được trừ khỏi kho <strong><?= htmlspecialchars($sourceWarehouse['name'] ?? $request['source_warehouse_id']) ?></strong> và phiếu yêu cầu sẽ chuyển sang trạng thái "Hoàn thành".
  </div>

  <!-- Thông tin xuất nhập -->
  <div class="info-section">
    <h3>🏪 Thông tin xuất nhập kho</h3>
    <div class="info-row">
      <strong>Kho xuất hàng:</strong>
      <span><?= htmlspecialchars($sourceWarehouse['name'] ?? $request['source_warehouse_id']) ?> (<?= htmlspecialchars($request['source_warehouse_id']) ?>)</span>
    </div>
    <div class="info-row">
      <strong>Kho nhận hàng:</strong>
      <span><?= htmlspecialchars($destWarehouse['name'] ?? $request['warehouse_id']) ?> (<?= htmlspecialchars($request['warehouse_id']) ?>)</span>
    </div>
    <div class="info-row">
      <strong>Loại phiếu:</strong>
      <span>Chuyển kho (Transfer)</span>
    </div>
    <div class="info-row">
      <strong>Phiếu yêu cầu gốc:</strong>
      <span><?= htmlspecialchars($request_id) ?></span>
    </div>
  </div>

  <form method="POST">
    <!-- Danh sách sản phẩm -->
    <div class="products-section">
      <h3>📦 Danh sách sản phẩm xuất kho</h3>
      <table class="products-table">
        <thead>
          <tr>
            <th>STT</th>
            <th>Sản phẩm</th>
            <th>Số lượng</th>
            <th>Đơn vị</th>
            <th>Quy đổi</th>
            <th>Tồn kho hiện tại</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $stt = 1;
          $details = $request['details'] ?? [];
          foreach ($details as $item):
            $productId = $item['product_id'];
            $productName = $item['product_name'] ?? 'N/A';
            $sku = $item['sku'] ?? $productId;
            $qty = (int)($item['quantity'] ?? 0);
            $unit = $item['unit'] ?? 'cái';
            $factor = (int)($item['conversion_factor'] ?? 1);
            $baseUnit = $item['base_unit'] ?? 'cái';
            
            // Lấy tồn kho hiện tại
            $currentStock = $cInventory->getTotalStockByProduct($request['source_warehouse_id'], $productId);
            
            $factorDisplay = '';
            if ($factor != 1) {
              $factorDisplay = "x" . number_format($factor, 0);
            }
          ?>
          <tr>
            <td><?= $stt++ ?></td>
            <td>
              <strong><?= htmlspecialchars($productName) ?></strong><br>
              <small style="color:#6c757d;">SKU: <?= htmlspecialchars($sku) ?></small>
            </td>
            <td><strong style="color:#ff9800;"><?= number_format($qty) ?></strong></td>
            <td><?= htmlspecialchars($unit) ?></td>
            <td>
              <?php if (!empty($factorDisplay)): ?>
                <span style="color:#007bff;font-weight:600;"><?= $factorDisplay ?></span>
              <?php else: ?>
                <span style="color:#999;">-</span>
              <?php endif; ?>
            </td>
            <td>
              <strong style="color:<?= $currentStock >= ($qty * $factor) ? '#28a745' : '#dc3545' ?>;">
                <?= number_format($currentStock) ?> <?= htmlspecialchars($baseUnit) ?>
              </strong>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Ghi chú -->
    <div class="note-section">
      <label for="note">📝 Ghi chú (tùy chọn):</label>
      <textarea name="note" id="note" placeholder="Nhập ghi chú cho phiếu xuất kho...">Phiếu xuất kho cho yêu cầu <?= htmlspecialchars($request_id) ?></textarea>
    </div>

    <!-- Nút hành động -->
    <div class="actions">
      <a href="../../index.php?page=goodsReceiptRequest" class="btn btn-cancel">
        <i class="fa-solid fa-xmark"></i> Hủy
      </a>
      <button type="submit" class="btn btn-submit" onclick="return confirm('Xác nhận tạo phiếu xuất kho và trừ hàng khỏi kho?');">
        <i class="fa-solid fa-check"></i> Tạo phiếu xuất
      </button>
    </div>
  </form>
</div>
