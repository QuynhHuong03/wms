<?php
include_once(__DIR__ . "/../../../../controller/clocation.php");
include_once(__DIR__ . "/../../../../model/mInventory.php");

if (session_status() === PHP_SESSION_NONE) session_start();

$c = new CLocation();
$mInventory = new MInventory();
$locations = $c->listLocations();
// Normalize
if ($locations === false) $locations = [];

// Get current warehouse ID
$warehouseId = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? '';

// Load inventory data to get actual quantities
$inventoryData = [];
if ($warehouseId) {
    try {
        $allInventory = $mInventory->getInventoryByWarehouse($warehouseId);
        foreach ($allInventory as $inv) {
            $zoneId = $inv['zone_id'] ?? '';
            $rackId = $inv['rack_id'] ?? '';
            $binId = $inv['bin_id'] ?? '';
            $qty = isset($inv['qty']) ? (int)$inv['qty'] : 0;
            
            if ($zoneId && $rackId && $binId) {
                $key = $zoneId . '|' . $rackId . '|' . $binId;
                if (!isset($inventoryData[$key])) {
                    $inventoryData[$key] = 0;
                }
                $inventoryData[$key] += $qty;
            }
        }
    } catch (Exception $e) {
        error_log('Error loading inventory: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quản lý Location</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        /* MODERN ADMIN DASHBOARD STYLES */
        :root{
            --bg:#f8fafc;
            --card:#ffffff;
            --muted:#64748b;
            --primary:#3b82f6;
            --primary-dark:#2563eb;
            --accent:#10b981;
            --accent-dark:#059669;
            --danger:#ef4444;
            --warning:#f59e0b;
            --border:#e2e8f0;
            --shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
        }
        
        * { box-sizing: border-box; }
        
        html, body { 
            height: 100%; 
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg);
            color: #1e293b;
            line-height: 1.6;
        }
        
        .panel {
            background: var(--card);
            border-radius: 16px;
            padding: 28px;
            box-shadow: var(--shadow);
            max-width: 1400px;
            margin: 0 auto 24px;
            border: 1px solid var(--border);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border);
            flex-wrap: wrap;
        }
        
        .header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 28px;
            color: #0f172a;
            letter-spacing: -0.5px;
        }
        
        .header-info {
            flex: 1;
            min-width: 300px;
        }
        
        .muted {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
        }
        
        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f1f5f9;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            margin-top: 8px;
            margin-right: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .info-badge.status-empty { background: #d1fae5; border-color: #86efac; color: #065f46; }
        .info-badge.status-partial { background: #fef3c7; border-color: #fcd34d; color: #92400e; }
        .info-badge.status-full { background: #fee2e2; border-color: #fca5a5; color: #991b1b; }
        
        .controls {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .btn {
            background: var(--primary);
            color: #fff;
            padding: 10px 18px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn:hover {
            background: var(--primary-dark);
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
            transform: translateY(-1px);
        }
        
        .btn.secondary {
            background: var(--accent);
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
        }
        
        .btn.secondary:hover {
            background: var(--accent-dark);
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
        }
        
        .btn.small {
            padding: 6px 12px;
            font-size: 13px;
            border-radius: 6px;
        }
        
        .btn.ghost {
            background: transparent;
            color: var(--primary);
            box-shadow: none;
            border: 1.5px solid var(--primary);
        }
        
        .btn.ghost:hover {
            background: #eff6ff;
            transform: translateY(-1px);
        }
        
        .zones {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
            margin-top: 24px;
        }
        
        @media(max-width: 1200px) { .zones { grid-template-columns: repeat(2, 1fr); } }
        @media(max-width: 800px) { .zones { grid-template-columns: 1fr; } }
        
        .zone {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
        }
        
        .zone:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        .zone h4 {
            margin: 0 0 8px;
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .zone-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }
        
        .rack-row {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-top: 16px;
        }
        
        .rack {
            border: 2px dashed #cbd5e1;
            padding: 14px;
            border-radius: 10px;
            background: #f8fafc;
            transition: all 0.2s ease;
        }
        
        .rack:hover {
            background: #f1f5f9;
            border-color: var(--primary);
        }
        
        .rack h5 {
            margin: 0 0 10px;
            font-size: 15px;
            font-weight: 600;
            color: #334155;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .rack-meta {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 10px;
        }
        
        .bins {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 110px));
            gap: 10px;
            justify-content: start;
        }
        
        .bin {
            border: 2px solid var(--border);
            border-radius: 10px;
            padding: 12px 10px;
            text-align: center;
            background: #fff;
            transition: all 0.2s ease;
            min-height: 140px;
            width: 110px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 4px;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        
        .bin:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        .bin[data-status="empty"] {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border-color: #10b981;
            color: #065f46;
        }
        
        .bin[data-status="partial"] {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-color: #f59e0b;
            color: #92400e;
        }
        
        .bin[data-status="full"] {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border-color: #ef4444;
            color: #991b1b;
        }
        
        .bin .code {
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 4px;
        }
        
        .bin .meta {
            font-size: 11px;
            color: inherit;
            opacity: 0.8;
        }
        
        /* Form styles */
        input[type="text"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
            font-family: inherit;
        }
        
        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 14px;
            color: #334155;
        }
        
        small.muted {
            color: var(--muted);
            font-size: 13px;
        }
    </style>
    <style>
        /* Modal & toast styles */
        .modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:1200}
        .modal[aria-hidden="false"]{display:flex}
        .modal-backdrop{position:fixed;inset:0;background:rgba(11,18,32,0.45)}
        .modal-panel{background:#fff;padding:16px;border-radius:10px;z-index:1210;max-width:480px;width:94%;box-shadow:0 12px 32px rgba(2,6,23,0.2)}
        #toastContainer{position:fixed;right:18px;bottom:18px;z-index:1300}
        .toast{background:#111827;color:#fff;padding:10px 14px;border-radius:8px;margin-top:8px;box-shadow:0 8px 20px rgba(2,6,23,0.12)}
        .toast.success{background:#059669}
        .toast.error{background:#dc2626}
        /* Status option styling */
        .status-option{position:relative}
        .status-option:hover{background:#f9fafb}
        .status-option input[type="radio"]:checked + div{font-weight:700}
        .status-option[data-status="empty"]:has(input:checked){background:#d1fae5;border-color:#10b981}
        .status-option[data-status="partial"]:has(input:checked){background:#fef3c7;border-color:#f59e0b}
        .status-option[data-status="full"]:has(input:checked){background:#fecaca;border-color:#dc2626}
    </style>
</head>
<body>
    <!-- Toast container -->
    <div id="toastContainer" aria-live="polite" aria-atomic="true"></div>

    <!-- Add Bin Modal -->
    <div id="addBinModal" class="modal" role="dialog" aria-hidden="true">
        <div class="modal-backdrop" onclick="closeAddBinModal()"></div>
        <div class="modal-panel" role="document" aria-labelledby="addBinTitle" style="max-width:640px">
            <h3 id="addBinTitle">Thêm Bin Mới</h3>
            <form id="addBinForm" onsubmit="return submitAddBinModal(event)">
                <input type="hidden" id="modalZoneId">
                <input type="hidden" id="modalRackId">
                
                <!-- Vị trí bin sẽ được thêm vào -->
                <div style="margin-bottom:16px;padding:12px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb">
                    <div style="font-size:13px;color:#6b7280;margin-bottom:4px">Thêm bin vào vị trí:</div>
                    <div style="font-weight:600" id="modalBinLocation">Zone / Rack</div>
                </div>
                
                <div style="margin-bottom:16px">
                    <label style="display:block;margin-bottom:8px;font-weight:600">
                        <i class="fas fa-box"></i> Tên Bin:
                    </label>
                    <input id="modalBinName" type="text" placeholder="Ví dụ: Z1/R1/B1, Bin hàng điện tử" style="width:100%;padding:10px;border:2px solid #e5e7eb;border-radius:8px;font-size:14px">
                    <div style="font-size:12px;color:#6b7280;margin-top:4px">Mã bin sẽ được tự động tạo theo format Zone/Rack/Bin</div>
                </div>
                
                <!-- Kích thước bin -->
                <div style="margin-bottom:16px">
                    <label style="display:block;margin-bottom:8px;font-weight:600">
                        <i class="fas fa-ruler-combined"></i> Kích thước bin (cm)
                    </label>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
                        <div>
                            <label style="font-size:12px;color:#6b7280;margin-bottom:4px;display:block">Chiều rộng</label>
                            <input id="modalBinWidth" type="number" step="0.1" min="0" placeholder="50" style="width:100%;padding:8px;border:2px solid #e5e7eb;border-radius:6px;font-size:14px">
                        </div>
                        <div>
                            <label style="font-size:12px;color:#6b7280;margin-bottom:4px;display:block">Chiều sâu</label>
                            <input id="modalBinDepth" type="number" step="0.1" min="0" placeholder="40" style="width:100%;padding:8px;border:2px solid #e5e7eb;border-radius:6px;font-size:14px">
                        </div>
                        <div>
                            <label style="font-size:12px;color:#6b7280;margin-bottom:4px;display:block">Chiều cao</label>
                            <input id="modalBinHeight" type="number" step="0.1" min="0" placeholder="30" style="width:100%;padding:8px;border:2px solid #e5e7eb;border-radius:6px;font-size:14px">
                        </div>
                    </div>
                    <div style="margin-top:6px;font-size:12px;color:#6b7280;display:flex;align-items:center;gap:4px">
                        <i class="fas fa-info-circle"></i>
                        Kích thước mặc định được lấy từ cài đặt hoặc bin cùng zone
                    </div>
                </div>
                
                <!-- Sức chứa -->
                <div style="margin-bottom:16px">
                    <label style="display:block;margin-bottom:8px;font-weight:600">
                        <i class="fas fa-percentage"></i> Sức chứa hiện tại (0-100%)
                    </label>
                    <input id="modalBinCurrentCapacity" type="number" min="0" max="100" value="0" readonly style="width:100%;padding:10px;border:2px solid #d1d5db;border-radius:6px;background:#f9fafb;color:#6b7280;cursor:not-allowed;font-size:16px;font-weight:600;text-align:center">
                    <input type="hidden" id="modalBinMaxCapacity" value="100">
                    <div style="margin-top:6px;font-size:12px;color:#6b7280;display:flex;align-items:center;gap:4px">
                        <i class="fas fa-lock"></i>
                        Sức chứa được tính tự động khi có sản phẩm trong bin
                    </div>
                </div>
                
                <!-- Tùy chọn nâng cao -->
                <div style="margin-bottom:16px;padding:12px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                        <i class="fas fa-cog" style="color:#0369a1"></i>
                        <span style="font-weight:600;color:#0369a1">Tùy chọn nâng cao</span>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:6px">
                        <label style="display:flex;align-items:center;cursor:pointer;padding:6px;border-radius:4px;transition:background 0.2s" onmouseover="this.style.background='rgba(3,105,161,0.05)'" onmouseout="this.style.background='transparent'">
                            <input id="modalAutoRack" type="checkbox" style="margin-right:8px;cursor:pointer">
                            <span style="font-size:13px;color:#0c4a6e">
                                Tự động chọn rack tiếp theo khi rack hiện tại đầy
                            </span>
                        </label>
                        <label style="display:flex;align-items:center;cursor:pointer;padding:6px;border-radius:4px;transition:background 0.2s" onmouseover="this.style.background='rgba(3,105,161,0.05)'" onmouseout="this.style.background='transparent'">
                            <input id="modalForce" type="checkbox" style="margin-right:8px;cursor:pointer">
                            <span style="font-size:13px;color:#0c4a6e">
                                Bỏ qua giới hạn và tạo bin (Force create)
                            </span>
                        </label>
                    </div>
                </div>
                
                <div style="padding:10px;background:#fef3c7;border-left:4px solid #f59e0b;border-radius:6px;margin-bottom:16px;font-size:12px">
                    <div style="display:flex;align-items:start;gap:8px">
                        <!-- <i class="fas fa-lightbulb" style="color:#d97706;margin-top:2px"></i> -->
                        <div style="color:#78350f;line-height:1.5">
                            <strong>Lưu ý:</strong> Mã bin (B1, B2, B3...) và code đầy đủ (Zone-Rack-Bin) sẽ được hệ thống tự động tạo theo thứ tự.
                        </div>
                    </div>
                </div>
                
                <div style="display:flex;gap:8px;justify-content:flex-end">
                    <button type="button" class="btn small ghost" onclick="closeAddBinModal()">
                        <i class="fas fa-times"></i> Hủy
                    </button>
                    <button type="submit" class="btn small">
                        <i class="fas fa-plus"></i> Thêm Bin
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Bin Modal (Name + Status + Dimensions + Capacity) -->
    <div id="editBinModal" class="modal" role="dialog" aria-hidden="true">
        <div class="modal-backdrop" onclick="closeEditBinModal()"></div>
        <div class="modal-panel" role="document" style="max-width:560px">
            <h3>Chỉnh sửa Bin</h3>
            <form id="editBinForm" onsubmit="return submitEditBin(event)">
                <input type="hidden" id="editBinZoneId">
                <input type="hidden" id="editBinRackId">
                <input type="hidden" id="editBinBinId">
                
                <div style="margin-bottom:16px;padding:12px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb">
                    <div style="font-size:13px;color:#6b7280;margin-bottom:4px">Vị trí:</div>
                    <div style="font-weight:600" id="editBinLocation"></div>
                </div>
                
                <div style="margin-bottom:16px">
                    <label style="display:block;margin-bottom:8px;font-weight:600">
                        Tên Bin:
                    </label>
                    <input type="text" id="editBinName" class="form-control" placeholder="Nhập tên bin" style="width:100%;padding:10px;border:2px solid #e5e7eb;border-radius:8px;font-size:14px">
                    <div style="font-size:12px;color:#6b7280;margin-top:4px">Ví dụ: Bin hàng điện tử, Kệ sách, v.v.</div>
                </div>
                
                <!-- Kích thước bin -->
                <div style="margin-bottom:16px">
                    <label style="display:block;margin-bottom:8px;font-weight:600">Kích thước bin (cm)</label>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
                        <div>
                            <label class="muted" style="font-size:12px">Chiều rộng</label>
                            <input id="editBinWidth" type="number" step="0.1" min="0" placeholder="0" style="width:100%;padding:8px;border:2px solid #e5e7eb;border-radius:6px">
                        </div>
                        <div>
                            <label class="muted" style="font-size:12px">Chiều sâu</label>
                            <input id="editBinDepth" type="number" step="0.1" min="0" placeholder="0" style="width:100%;padding:8px;border:2px solid #e5e7eb;border-radius:6px">
                        </div>
                        <div>
                            <label class="muted" style="font-size:12px">Chiều cao</label>
                            <input id="editBinHeight" type="number" step="0.1" min="0" placeholder="0" style="width:100%;padding:8px;border:2px solid #e5e7eb;border-radius:6px">
                        </div>
                    </div>
                </div>
                
                <!-- Sức chứa (readonly - tự động tính) -->
                <div style="margin-bottom:16px">
                    <label style="display:block;margin-bottom:8px;font-weight:600">Sức chứa hiện tại (0-100%)</label>
                    <input id="editBinCurrentCapacity" type="number" min="0" max="100" value="0" readonly style="width:100%;padding:10px;border:2px solid #d1d5db;border-radius:6px;background:#f9fafb;color:#6b7280;cursor:not-allowed;font-size:16px;font-weight:600;text-align:center">
                    <input type="hidden" id="editBinMaxCapacity" value="100">
                    <div style="margin-top:6px;font-size:12px;color:#6b7280;display:flex;align-items:center;gap:4px">
                        Sức chứa được tính tự động dựa trên thể tích sản phẩm trong bin
                    </div>
                </div>
                
                <div style="margin-bottom:16px;padding:12px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                        <span style="font-weight:600;color:#0369a1">Tự động tính toán</span>
                    </div>
                    <div style="font-size:12px;color:#0c4a6e;line-height:1.5">
                        <strong>Sức chứa:</strong> Được tính tự động từ 0-100% dựa trên thể tích sản phẩm so với thể tích bin.<br>
                        <strong>Trạng thái:</strong> Cập nhật tự động theo % chiếm dụng:<br>
                        • Empty (0%) | Partial (1-79%) | Full (≥80%)
                    </div>
                </div>
                
                <div style="display:flex;gap:8px;justify-content:flex-end">
                    <button type="button" class="btn small ghost" onclick="closeEditBinModal()">Hủy</button>
                    <button type="submit" class="btn small">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Quantity Modal -->
    <div id="editQuantityModal" class="modal" role="dialog" aria-hidden="true">
        <div class="modal-backdrop" onclick="closeEditQuantityModal()"></div>
        <div class="modal-panel" role="document" style="max-width:420px">
            <h3>Chỉnh sửa số lượng</h3>
            <form id="editQuantityForm" onsubmit="return submitEditQuantity(event)">
                <input type="hidden" id="editQtyZoneId">
                <input type="hidden" id="editQtyRackId">
                <input type="hidden" id="editQtyBinId">
                
                <div style="margin-bottom:16px;padding:12px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb">
                    <div style="font-size:13px;color:#6b7280;margin-bottom:4px">Vị trí:</div>
                    <div style="font-weight:600" id="editQtyLocation"></div>
                </div>
                
                <div style="margin-bottom:16px">
                    <label style="display:block;margin-bottom:8px;font-weight:600">
                        Số lượng hiện tại (từ Inventory):
                    </label>
                    <div style="padding:12px;background:#dbeafe;border:2px solid #3b82f6;border-radius:8px;font-size:18px;font-weight:700;color:#1e40af;text-align:center" id="editQtyCurrentQty">
                        0
                    </div>
                </div>
                
                <div style="margin-bottom:16px">
                    <label style="display:block;margin-bottom:8px;font-weight:600">
                        Số lượng mới:
                        <span style="color:#dc2626;font-weight:400;font-size:13px">(sẽ cập nhật vào Inventory)</span>
                    </label>
                    <input type="number" id="editQtyNewValue" class="form-control" min="0" placeholder="Nhập số lượng mới" required style="width:100%;padding:10px;border:2px solid #e5e7eb;border-radius:8px;font-size:16px">
                </div>
                
                <div style="padding:10px;background:#fef3c7;border-left:4px solid #f59e0b;border-radius:6px;margin-bottom:16px;font-size:13px">
                    <strong>Lưu ý:</strong> Thao tác này sẽ cập nhật số lượng trong bảng <code>inventory</code>. Không ảnh hưởng đến <code>warehouse_structure</code>.
                </div>
                
                <div style="display:flex;gap:8px;justify-content:flex-end">
                    <button type="button" class="btn small ghost" onclick="closeEditQuantityModal()">Hủy</button>
                    <button type="submit" class="btn small">Lưu</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Zone Modal -->
    <div id="addZoneModal" class="modal" role="dialog" aria-hidden="true">
        <div class="modal-backdrop" onclick="closeAddZoneModal()"></div>
        <div class="modal-panel" role="document" style="max-width:540px">
            <h3>Thêm Zone Mới</h3>
            <form id="addZoneForm" onsubmit="return submitAddZone(event)">
                <div style="margin-bottom:16px">
                    <label style="display:block;margin-bottom:8px;font-weight:600">
                        <i class="fas fa-tag"></i> Zone ID:
                    </label>
                    <input type="text" id="addZoneId" class="form-control" placeholder="Ví dụ: Z1, Z2, Z3" required style="width:100%;padding:10px;border:2px solid #e5e7eb;border-radius:8px;font-size:14px">
                    <div style="font-size:12px;color:#6b7280;margin-top:4px">Mã định danh duy nhất cho zone (ví dụ: Z1, Z2, Z3)</div>
                </div>
                
                <div style="margin-bottom:16px">
                    <label style="display:block;margin-bottom:8px;font-weight:600">
                        <i class="fas fa-warehouse"></i> Tên Zone:
                    </label>
                    <input type="text" id="addZoneName" class="form-control" placeholder="Ví dụ: Khu A, Khu vực lưu trữ" required style="width:100%;padding:10px;border:2px solid #e5e7eb;border-radius:8px;font-size:14px">
                    <div style="font-size:12px;color:#6b7280;margin-top:4px">Tên mô tả cho zone</div>
                </div>
                
                <div style="margin-bottom:16px">
                    <label style="display:block;margin-bottom:8px;font-weight:600">
                        <i class="fas fa-align-left"></i> Mô tả (tùy chọn):
                    </label>
                    <textarea id="addZoneDescription" class="form-control" placeholder="Nhập mô tả về zone này" rows="3" style="width:100%;padding:10px;border:2px solid #e5e7eb;border-radius:8px;font-size:14px"></textarea>
                    <div style="font-size:12px;color:#6b7280;margin-top:4px">Thông tin bổ sung về zone (không bắt buộc)</div>
                </div>
                
                <div style="padding:12px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;margin-bottom:16px">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                        <i class="fas fa-info-circle" style="color:#0369a1"></i>
                        <span style="font-weight:600;color:#0369a1">Lưu ý</span>
                    </div>
                    <div style="font-size:12px;color:#0c4a6e;line-height:1.5">
                        • Zone mới sẽ được tạo với cấu trúc rỗng (chưa có rack và bin)<br>
                        • Bạn có thể thêm rack và bin sau khi zone được tạo<br>
                        • Tối đa 3 zones được phép trong hệ thống
                    </div>
                </div>
                
                <div style="display:flex;gap:8px;justify-content:flex-end">
                    <button type="button" class="btn small ghost" onclick="closeAddZoneModal()">Hủy</button>
                    <button type="submit" class="btn small">Thêm Zone</button>
                </div>
            </form>
        </div>
    </div>
	<div class="panel">
		<div class="header">
			<div class="header-info">
				<h2>Quản lý Vị trí Kho (Zone/Rack/Bin)</h2>
				<div class="muted" style="margin-top: 8px;">
					<strong>Giới hạn cấu trúc:</strong> Tối đa 3 Zones | 4 Racks/Zone | 10 Bins/Rack
				</div>
				<div style="margin-top: 10px;">
					<div class="info-badge" style="background:#e0f2fe;border-color:#7dd3fc;color:#0c4a6e">
						Sức chứa tính theo thể tích sản phẩm
					</div>
				</div>
				<div style="margin-top: 8px;">
					<span class="info-badge status-empty">Empty (0%)</span>
					<span class="info-badge status-partial">Partial (1-79%)</span>
					<span class="info-badge status-full">Full (≥80%)</span>
				</div>
			</div>
			<div class="controls">
                <button class="btn small" id="addZoneBtn">
					<i class="fas fa-plus"></i> Thêm Zone
				</button>
			</div>
		</div>

		<div class="zones" id="zonesContainer">
			<?php if (empty($locations)): ?>
				<div class="muted">Chưa có zone nào.</div>
			<?php else: ?>
				<?php foreach ($locations as $loc): ?>
                <?php
                    // Normalize keys safely
                    $zone_id = $loc['zone_id'] ?? $loc['_id'] ?? '';
                    $zone_name = $loc['name'] ?? $loc['zone_name'] ?? '';
                    $warehouse_name = $loc['warehouse']['name'] ?? ($loc['warehouse']['name'] ?? '');
                    $description = $loc['description'] ?? '';
                    $racks = $loc['racks'] ?? [];
                ?>
                <div class="zone" data-zone="<?=htmlspecialchars($zone_id)?>">
                    <div class="zone-header">
                        <div>
                            <h4>
                                <?=htmlspecialchars($zone_name)?>
                            </h4>
                            <small class="muted" style="font-weight:600;color:#475569">
                                ID: <?=htmlspecialchars($zone_id)?>
                            </small>
                            <div class="muted" style="margin-top:4px">
                                📍 <?=htmlspecialchars($warehouse_name)?>
                            </div>
                            <?php if($description): ?>
                            <p style="margin:6px 0 0;font-size:13px;color:#64748b"><?=htmlspecialchars($description)?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <button class="btn small ghost" onclick="deleteZone('<?=htmlspecialchars($zone_id)?>')" title="Xóa Zone">
                                <i class="fas fa-times-circle"></i>
                            </button>
                        </div>
                    </div>

                    <div class="rack-row">
                        <?php
                            // Map racks by rack_id to ensure correct placement (R1..R4) even if some are missing or out of order
                            $racksById = [];
                            if (is_array($racks)) {
                                foreach ($racks as $rr) {
                                    $rid0 = $rr['rack_id'] ?? null;
                                    if ($rid0) $racksById[$rid0] = $rr;
                                }
                            }
                        ?>
                        <?php for ($i=0;$i<4;$i++): ?>
                            <?php
                                $expectedId = 'R'.($i+1);
                                $r = $racksById[$expectedId] ?? null;
                                $rack_id = $expectedId;
                                $rack_name = $r['name'] ?? ('Rack '.($i+1));
                                $bins = $r['bins'] ?? [];
                            ?>
                            <div class="rack" data-rack="<?= htmlspecialchars($rack_id) ?>">
                                <div style="display:flex;justify-content:space-between;align-items:center">
                                    <h5 style="margin:0;">
                                        <?= htmlspecialchars($rack_name) ?>
                                    </h5>
                                    <div>
                                        <button class="btn small ghost" onclick="deleteRack('<?=htmlspecialchars($zone_id)?>','<?=htmlspecialchars($rack_id)?>')">Xóa Rack</button>
                                    </div>
                                </div>
                                <?php if ($r): ?>
                                    <div class="muted">Bins: <?=count($bins)?></div>
                                    <div class="bins">
                                        <?php for ($b=0;$b<10;$b++): $bin = $bins[$b] ?? null; ?>
                                            <?php
                                                $bin_id = $bin['bin_id'] ?? '';
                                                $code = $bin['code'] ?? '';
                                                $bin_name = $bin['name'] ?? '';
                                                
                                                // Get quantity from inventory data
                                                $locationKey = $zone_id . '|' . $rack_id . '|' . $bin_id;
                                                $quantity = isset($inventoryData[$locationKey]) ? $inventoryData[$locationKey] : 0;
                                                
                                                // Calculate status dynamically based on current_capacity and quantity
                                                $currentCap = isset($bin['current_capacity']) ? (int)$bin['current_capacity'] : 0;
                                                if ($quantity == 0) {
                                                    $status = 'empty';
                                                } elseif ($currentCap >= 80) {
                                                    $status = 'full';
                                                } else {
                                                    $status = 'partial';
                                                }
                                                
                                                // Prefer displaying explicit name; else show code; else fallback to bin_id
                                                $bin_title = $bin_name !== '' ? $bin_name : ($code !== '' ? $code : ($bin_id !== '' ? $bin_id : 'Bin'));
                                            ?>
                                                            <div class="bin" data-bin="<?= htmlspecialchars($bin_id) ?>" data-status="<?= htmlspecialchars($status) ?>">
                                                <?php if ($bin): ?>
                                                    <div>
                                                        <strong><?=htmlspecialchars($bin_title)?></strong>
                                                    </div>
                                                    <?php
                                                        // Display dimensions
                                                        $dims = $bin['dimensions'] ?? [];
                                                        $width = isset($dims['width']) ? (float)$dims['width'] : 0;
                                                        $depth = isset($dims['depth']) ? (float)$dims['depth'] : 0;
                                                        $height = isset($dims['height']) ? (float)$dims['height'] : 0;
                                                        $hasDims = ($width > 0 || $depth > 0 || $height > 0);
                                                        $dimsText = $hasDims ? "{$width}×{$depth}×{$height} cm" : '-';
                                                        
                                                        // Calculate occupancy percentage based on volume
                                                        $occupancyPercent = 0;
                                                        $occupancyText = '-';
                                                        $occupancyColor = '#6b7280';
                                                        
                                                        // Nếu không có sản phẩm, luôn hiển thị màu xanh (empty)
                                                        if ($quantity == 0) {
                                                            $occupancyPercent = 0;
                                                            $occupancyText = '0%';
                                                            $occupancyColor = '#10b981'; // Green - empty
                                                        } elseif ($hasDims && $quantity > 0) {
                                                            // Calculate bin volume (cm³)
                                                            $binVolume = $width * $depth * $height;
                                                            
                                                            // Try to calculate product volume from inventory
                                                            // This is a simplified calculation - ideally should fetch product dimensions
                                                            // For now, use current_capacity as percentage if set
                                                            $currentCap = isset($bin['current_capacity']) ? (int)$bin['current_capacity'] : 0;
                                                            
                                                            if ($currentCap > 0) {
                                                                $occupancyPercent = min(100, $currentCap);
                                                                $occupancyText = $occupancyPercent . '%';
                                                                
                                                                // Color based on occupancy
                                                                if ($occupancyPercent >= 80) {
                                                                    $occupancyColor = '#dc2626'; // Red - nearly full
                                                                } elseif ($occupancyPercent >= 50) {
                                                                    $occupancyColor = '#f59e0b'; // Orange - half full
                                                                } else {
                                                                    $occupancyColor = '#10b981'; // Green - plenty of space
                                                                }
                                                            }
                                                        }
                                                    ?>
                                                    <div class="muted" style="font-size:11px" title="Kích thước (cm)"> <?=htmlspecialchars($dimsText)?></div>
                                                    <div class="muted" style="font-size:11px;color:<?=htmlspecialchars($occupancyColor)?>;font-weight:700" title="% chiếm dụng thể tích">
                                                         <?=htmlspecialchars($occupancyText)?>
                                                    </div>
                                                                    <div class="muted" style="cursor:pointer;color:#2563eb;font-weight:600;font-size:11px" onclick="editBinQuantity('<?=htmlspecialchars($zone_id)?>','<?=htmlspecialchars($rack_id)?>','<?=htmlspecialchars($bin_id)?>',<?=htmlspecialchars($quantity)?>)" title="Click để sửa số lượng">
                                                         Qty: <?=htmlspecialchars($quantity)?>
                                                    </div>
                                                    <div style="font-size:10px;font-weight:600;margin-top:2px;padding:2px 4px;border-radius:4px;background:rgba(255,255,255,0.7);color:<?=htmlspecialchars($occupancyColor)?>" title="Trạng thái tự động dựa vào % chiếm dụng">
                                                        <?php
                                                        $statusLabel = $status;
                                                        if ($status === 'empty') $statusLabel = '🟢 Empty';
                                                        elseif ($status === 'partial') $statusLabel = '🟠 Partial';
                                                        elseif ($status === 'full') $statusLabel = '🔴 Full';
                                                        ?>
                                                        <?=htmlspecialchars($statusLabel)?>
                                                    </div>
                                                    <div style="margin-top:6px">
                                                        <?php $bin_numeric_id = isset($bin['id']) ? $bin['id'] : ''; ?>
                                                        <button class="btn small" style="background:#93c5fd;color:#1e3a8a" onclick="editBinName('<?=htmlspecialchars($zone_id)?>','<?=htmlspecialchars($rack_id)?>','<?=htmlspecialchars($bin_id)?>','<?=htmlspecialchars($bin_name)?>')" title="Chỉnh sửa"><i class="fas fa-pen"></i></button>
                                                        <?php if ($quantity == 0): ?>
                                                        <button class="btn small" style="background:#fde68a;color:#92400e" onclick="clearBinProduct('<?=htmlspecialchars($zone_id)?>','<?=htmlspecialchars($rack_id)?>','<?=htmlspecialchars($bin_id)?>')" title="Xóa sản phẩm"><i class="fas fa-times"></i></button>
                                                        <button class="btn small" style="background:#fca5a5;color:#7f1d1d" onclick="deleteBin('<?=htmlspecialchars($zone_id)?>','<?=htmlspecialchars($rack_id)?>','<?=htmlspecialchars($bin_id)?>','<?=htmlspecialchars((string)$bin_numeric_id)?>')" title="Xóa bin"><i class="fas fa-minus-circle"></i></button>
                                                        <?php else: ?>
                                                        <button class="btn small" style="background:#e5e7eb;color:#9ca3af;cursor:not-allowed" disabled title="Không thể xóa bin có sản phẩm (Qty: <?=htmlspecialchars($quantity)?>)"><i class="fas fa-minus-circle"></i></button>
                                                        <?php endif; ?>
                                                    </div>
                                                        <?php else: ?>
                                                    <div class="muted">Trống</div>
                                                    <div style="margin-top:6px">
                                                        <button class="btn small" onclick="openAddBinModal('<?=htmlspecialchars($zone_id)?>','<?=htmlspecialchars($rack_id)?>')">+ Bin</button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                    <?php else: ?>
                                    <div class="muted">Rack trống</div>
                                    <div style="margin-top:8px">
                                        <!-- Open modal flow for adding bin -->
                                        <button class="btn small" onclick="openAddBinModal('<?=htmlspecialchars($zone_id)?>','<?=htmlspecialchars($rack_id)?>')">+ Thêm Bin</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>

	<!-- Matrix editor: choose number of zones, then racks per zone, then bins per rack -->
	<!-- Only show matrix editor if no zones exist yet -->
	<?php $hasZones = !empty($locations) && count($locations) > 0; ?>
	<div class="panel" style="margin-top:20px;<?= $hasZones ? 'display:none' : '' ?>">
		<h3 style="margin-top:0;color:#0f172a;font-size:22px;font-weight:700;display:flex;align-items:center;gap:10px">
			<i class="fas fa-th" style="color:var(--primary)"></i>
			Tạo cấu trúc kho (Matrix)
		</h3>
		<p class="muted" style="margin-bottom:20px">Thiết lập cấu trúc Zone → Rack → Bin cho kho hàng mới</p>
		
		<!-- Default dimensions and capacity for all bins -->
		<div style="margin-bottom:16px;padding:14px;background:#f0f9ff;border:2px solid #0284c7;border-radius:10px">
			<h4 style="margin:0 0 12px;color:#0c4a6e;font-size:15px">Kích thước & Sức chứa mặc định cho tất cả Bin</h4>
			<div style="display:grid;grid-template-columns:repeat(3,1fr) 2fr;gap:12px;align-items:end">
				<div>
					<label style="font-weight:600;font-size:13px;margin-bottom:4px;display:block">Chiều rộng (cm)</label>
					<input id="defaultBinWidth" type="number" step="0.1" min="0" value="50" placeholder="50" style="width:100%;padding:8px;border:2px solid #0284c7;border-radius:6px;font-size:14px">
				</div>
				<div>
					<label style="font-weight:600;font-size:13px;margin-bottom:4px;display:block">Chiều sâu (cm)</label>
					<input id="defaultBinDepth" type="number" step="0.1" min="0" value="40" placeholder="40" style="width:100%;padding:8px;border:2px solid #0284c7;border-radius:6px;font-size:14px">
				</div>
				<div>
					<label style="font-weight:600;font-size:13px;margin-bottom:4px;display:block">Chiều cao (cm)</label>
					<input id="defaultBinHeight" type="number" step="0.1" min="0" value="30" placeholder="30" style="width:100%;padding:8px;border:2px solid #0284c7;border-radius:6px;font-size:14px">
				</div>
				<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
					<div>
						<label style="font-weight:600;font-size:13px;margin-bottom:4px;display:block">Sức chứa tối đa</label>
						<input id="defaultBinMaxCapacity" type="number" min="0" value="100" placeholder="100" style="width:100%;padding:8px;border:2px solid #0284c7;border-radius:6px;font-size:14px">
					</div>
					<div>
						<label style="font-weight:600;font-size:13px;margin-bottom:4px;display:block">Sức chứa hiện tại</label>
						<input id="defaultBinCurrentCapacity" type="number" min="0" value="0" placeholder="0" style="width:100%;padding:8px;border:2px solid #0284c7;border-radius:6px;font-size:14px">
					</div>
				</div>
			</div>
			<div style="margin-top:8px;font-size:12px;color:#0369a1">
				<strong>Lưu ý:</strong> Tất cả bin được tạo từ matrix sẽ có kích thước và sức chứa giống nhau theo giá trị trên.
			</div>
		</div>
		
		<div style="display:flex;gap:12px;align-items:center;margin-bottom:12px;flex-wrap:wrap">
            <label style="font-weight:600">Số Zone (1-3):</label>
			<input id="numZones" type="number" value="1" min="1" max="3" style="width:80px;padding:8px;border:2px solid #e5e7eb;border-radius:6px">
			<label style="font-weight:600;margin-left:12px">Racks mỗi Zone (1-4):</label>
			<input id="numRacks" type="number" value="1" min="1" max="4" style="width:80px;padding:8px;border:2px solid #e5e7eb;border-radius:6px">
			<label style="font-weight:600;margin-left:12px">Bins mỗi Rack (1-10):</label>
			<input id="numBins" type="number" value="1" min="1" max="10" style="width:80px;padding:8px;border:2px solid #e5e7eb;border-radius:6px">
			<button class="btn" id="applyZones">Tạo cấu hình</button>
            <button class="btn" id="previewBtn">Xem trước</button>
            <button class="btn secondary" id="saveAllBtn">Lưu tất cả</button>
		</div>
		
		<div style="margin:12px 0;padding:10px;background:#fef3c7;border:1px solid #fbbf24;border-radius:8px;font-size:13px">
			<strong>Cấu trúc kho:</strong>
			<div style="margin-top:6px;color:#78350f">
				• <strong>Zone:</strong> Khu vực - Tối đa 3 zones<br>
				• <strong>Rack:</strong> Kệ - Tối đa 4 racks/zone<br>
				• <strong>Bin:</strong> Ngăn - Tối đa 10 bins/rack
			</div>
		</div>

		<!-- Zone configs generated above -->
		<div id="zoneConfigs" style="margin-bottom:12px"></div>
		<div id="matrixAreaPreview" style="margin-bottom:12px"></div>

		<!-- Grid generator removed per request (preview only) -->
	</div>

	<script>
		// Đổi sang đường dẫn đúng tới file process.php (relative với /view/page/manage/index.php)
		const API = 'locations/process.php';
		console.log('Location API ->', new URL(API, window.location.href).href);

		function post(data){
			return fetch(API, {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)})
				.then(async r=>{
					const text = await r.text();
					try {
						return JSON.parse(text);
					} catch (err) {
						// Return an object so callers can show the raw HTML/text for debugging
						return { success: false, message: 'Server returned non-JSON response', status: r.status, raw: text };
					}
				})
				.catch(err=>({ success: false, message: 'Network error: ' + (err.message || err) }));
		}

		// Add Zone button removed; use matrix "Lưu tất cả Zone" to create zones.

		function addRackPrompt(zone_id, rack_id){
			const name = prompt('Tên rack:','Rack');
			if(!name) return;
            post({action:'add_rack',zone_id,rack_id,name}).then(res=>{alert(JSON.stringify(res)); if(res.success) location.reload();});
		}

        function addBinPrompt(zone_id, rack_id){
            const bin_name = prompt('Tên bin (tùy chọn):', '');
            ensureRackThenAddBin(zone_id, rack_id, '', '', { bin_name });
        }

        // When user clicks an empty rack area, create the rack first (if needed) then add a first bin
        function addBinDirect(zone_id, rack_id){
            const bin_name = prompt('Tên bin (tùy chọn):', '');
            ensureRackThenAddBin(zone_id, rack_id, '', '', { bin_name });
        }

        // Helper: ensure rack exists (try manual model add first), then add bin.
        // opts can include {force:true, debug:true, bin_name, dimensions:{width,depth,height}, current_capacity, max_capacity}
        function ensureRackThenAddBin(zone_id, rack_id, bin_id, code, opts){
            opts = opts || {};
            // First attempt: call the manual model endpoint to ensure rack is present
            let rackName = 'Rack ' + rack_id;
            const m = /^R(\d+)/i.exec(rack_id);
            if (m) { rackName = 'Rack ' + parseInt(m[1], 10); }
            const manualPayload = { action: 'add_rack_manual', zone_id, rack_id, name: rackName };
            if (opts.force) manualPayload.force = true;
            return post(manualPayload).then(manualRes => {
                // If manual succeeded, proceed to add bin
                const binPayload = { action: 'add_bin', zone_id, rack_id };
                if (opts.bin_name) binPayload.bin_name = opts.bin_name;
                if (opts.force) binPayload.force = true;
                if (opts.debug) binPayload.debug = true;
                if (opts.dimensions) binPayload.dimensions = opts.dimensions;
                if (opts.current_capacity !== undefined) binPayload.current_capacity = opts.current_capacity;
                if (opts.max_capacity !== undefined) binPayload.max_capacity = opts.max_capacity;
                return post(binPayload).then(res => { showToast(res); if (res.success) location.reload(); return res; });
            }).catch(() => {
                // If manual endpoint failed (network or server error), fall back to the original controller path
                const fallbackPayload = { action: 'add_rack', zone_id, rack_id, name: rack_id };
                if (opts.force) fallbackPayload.force = true;
                return post(fallbackPayload).then(rackRes => {
                    const binPayload = { action: 'add_bin', zone_id, rack_id };
                    if (opts.bin_name) binPayload.bin_name = opts.bin_name;
                    if (opts.force) binPayload.force = true;
                    if (opts.debug) binPayload.debug = true;
                    if (opts.dimensions) binPayload.dimensions = opts.dimensions;
                    if (opts.current_capacity !== undefined) binPayload.current_capacity = opts.current_capacity;
                    if (opts.max_capacity !== undefined) binPayload.max_capacity = opts.max_capacity;
                    return post(binPayload).then(res => { showToast(res); if (res.success) location.reload(); return res; });
                }).catch(err => {
                    // If both attempts fail, still try add_bin directly as last resort
                    const binPayload = { action: 'add_bin', zone_id, rack_id };
                    if (opts.bin_name) binPayload.bin_name = opts.bin_name;
                    if (opts.force) binPayload.force = true;
                    if (opts.debug) binPayload.debug = true;
                    if (opts.dimensions) binPayload.dimensions = opts.dimensions;
                    if (opts.current_capacity !== undefined) binPayload.current_capacity = opts.current_capacity;
                    if (opts.max_capacity !== undefined) binPayload.max_capacity = opts.max_capacity;
                    return post(binPayload).then(res => { showToast(res); if (res.success) location.reload(); return res; });
                });
            });
        }

        // Modal helpers
        function openAddBinModal(zone_id, rack_id){
            document.getElementById('modalZoneId').value = zone_id;
            document.getElementById('modalRackId').value = rack_id;
            
            // Update location display
            const locationEl = document.getElementById('modalBinLocation');
            if (locationEl) {
                locationEl.textContent = `${zone_id} / ${rack_id}`;
            }
            
            // Count only ACTUAL bins (with data-bin attribute that's not empty) in this rack
            const rackEl = document.querySelector(`.zone[data-zone="${zone_id}"] .rack[data-rack="${rack_id}"]`);
            let nextBinNum = 1;
            let existingWidth = 0;
            let existingDepth = 0;
            let existingHeight = 0;
            
            if (rackEl) {
                // Count bins that have actual bin_id (not just empty placeholders)
                const existingBins = rackEl.querySelectorAll('.bin[data-bin]');
                const actualBins = [];
                existingBins.forEach(binEl => {
                    const binId = binEl.getAttribute('data-bin');
                    if (binId && binId.trim() !== '') {
                        actualBins.push(binEl);
                    }
                });
                nextBinNum = actualBins.length + 1;
                
                // Try to get dimensions from first existing bin in this zone
                const zoneEl = document.querySelector(`.zone[data-zone="${zone_id}"]`);
                if (zoneEl) {
                    const firstBin = zoneEl.querySelector('.bin[data-bin]');
                    if (firstBin) {
                        // Extract dimensions from displayed text (e.g., "📏 50×40×30 cm")
                        const dimText = firstBin.textContent || '';
                        const dimMatch = dimText.match(/📏\s*([\d.]+)×([\d.]+)×([\d.]+)/);
                        if (dimMatch) {
                            existingWidth = parseFloat(dimMatch[1]);
                            existingDepth = parseFloat(dimMatch[2]);
                            existingHeight = parseFloat(dimMatch[3]);
                        }
                    }
                }
            }
            
            const binId = 'B' + nextBinNum;
            // Name format: ZoneID/RackID/BinID (e.g., Z3/R1/B5)
            const autoName = `${zone_id}/${rack_id}/${binId}`;
            const nm = document.getElementById('modalBinName'); 
            if (nm) nm.value = autoName;
            
            // Use dimensions from existing bins in zone, or fall back to defaults
            let defaultWidth = existingWidth > 0 ? existingWidth : parseFloat(document.getElementById('defaultBinWidth')?.value || 50);
            let defaultDepth = existingDepth > 0 ? existingDepth : parseFloat(document.getElementById('defaultBinDepth')?.value || 40);
            let defaultHeight = existingHeight > 0 ? existingHeight : parseFloat(document.getElementById('defaultBinHeight')?.value || 30);
            
            const widthEl = document.getElementById('modalBinWidth'); if (widthEl) widthEl.value = defaultWidth;
            const depthEl = document.getElementById('modalBinDepth'); if (depthEl) depthEl.value = defaultDepth;
            const heightEl = document.getElementById('modalBinHeight'); if (heightEl) heightEl.value = defaultHeight;
            
            // Reset capacity fields
            const currentCapEl = document.getElementById('modalBinCurrentCapacity'); if (currentCapEl) currentCapEl.value = '0';
            const maxCapEl = document.getElementById('modalBinMaxCapacity'); if (maxCapEl) maxCapEl.value = '100';
            
            // Reset checkboxes
            document.getElementById('modalAutoRack').checked = false;
            document.getElementById('modalForce').checked = false;
            
            const m = document.getElementById('addBinModal'); m.setAttribute('aria-hidden','false');
        }
        function closeAddBinModal(){ document.getElementById('addBinModal').setAttribute('aria-hidden','true'); }
        function submitAddBinModal(e){
            e.preventDefault();
            const zone_id = document.getElementById('modalZoneId').value;
            const rack_id = document.getElementById('modalRackId').value;
            const bin_name = (document.getElementById('modalBinName')?.value || '').trim();
            const auto_rack = document.getElementById('modalAutoRack').checked;
            const force = document.getElementById('modalForce').checked;
            
            // Collect dimension data
            const width = parseFloat(document.getElementById('modalBinWidth')?.value || 0);
            const depth = parseFloat(document.getElementById('modalBinDepth')?.value || 0);
            const height = parseFloat(document.getElementById('modalBinHeight')?.value || 0);
            
            // Collect capacity data
            const current_capacity = parseInt(document.getElementById('modalBinCurrentCapacity')?.value || 0);
            const max_capacity = parseInt(document.getElementById('modalBinMaxCapacity')?.value || 0);
            
            closeAddBinModal();
            if (auto_rack) {
                // gửi trực tiếp add_bin với auto_rack để server chọn rack kế tiếp
                post({ 
                    action: 'add_bin', 
                    zone_id, 
                    rack_id: 'auto', 
                    bin_name, 
                    auto_rack: true, 
                    force: !!force,
                    dimensions: { width, depth, height },
                    current_capacity,
                    max_capacity
                }).then(res => { showToast(res); if (res.success) location.reload(); });
            } else {
                ensureRackThenAddBin(zone_id, rack_id, '', '', {
                    force: !!force, 
                    debug:false, 
                    bin_name,
                    dimensions: { width, depth, height },
                    current_capacity,
                    max_capacity
                });
            }
            return false;
        }

        // Toast helper: accepts server response object or custom {success,message}
        function showToast(res){
            const obj = (res && typeof res === 'object') ? res : { success: false, message: String(res) };
            const div = document.createElement('div'); div.className = 'toast ' + (obj.success ? 'success' : 'error');
            div.textContent = (obj.message || (obj.success ? 'Thành công' : 'Lỗi'));
            document.getElementById('toastContainer').appendChild(div);
            setTimeout(()=>{ div.style.opacity = '0'; setTimeout(()=>div.remove(),400); }, 3000);
        }

		function deleteZone(zone_id){ if(!confirm('Xóa zone?')) return; post({action:'delete_zone',zone_id}).then(res=>{alert(JSON.stringify(res)); if(res.success) location.reload();}); }
        function deleteBin(zone_id,rack_id,bin_id,id){
            if(!confirm('Xóa bin?')) return;
            const payload = { action:'delete_bin' };
            if (id) { payload.id = id; }
            else { payload.zone_id = zone_id; payload.rack_id = rack_id; payload.bin_id = bin_id; }
            post(payload).then(res=>{alert(JSON.stringify(res)); if(res.success) location.reload();});
        }

        function deleteRack(zone_id, rack_id){
            if(!confirm('Xóa rack này cùng toàn bộ bin bên trong?')) return;
            post({action:'delete_rack', zone_id, rack_id}).then(res=>{ alert(JSON.stringify(res)); if(res.success) location.reload(); });
        }

        function editBinName(zone_id, rack_id, bin_id, current_name){
            // Get current bin data including status
            fetch(API, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'get_bin_data', zone_id, rack_id, bin_id})
            })
            .then(res => res.json())
            .then(data => {
                const current_status = data.status || 'empty';
                openEditBinModal(zone_id, rack_id, bin_id, current_name, current_status);
            })
            .catch(() => {
                // Fallback: open with default status
                openEditBinModal(zone_id, rack_id, bin_id, current_name, 'empty');
            });
        }

        async function openEditBinModal(zone_id, rack_id, bin_id, current_name, current_status){
            // Set basic info first
            document.getElementById('editBinZoneId').value = zone_id;
            document.getElementById('editBinRackId').value = rack_id;
            document.getElementById('editBinBinId').value = bin_id;
            document.getElementById('editBinLocation').textContent = zone_id + ' / ' + rack_id + ' / ' + bin_id;
            document.getElementById('editBinName').value = current_name || '';
            
            // Open modal immediately so user sees it loading
            document.getElementById('editBinModal').setAttribute('aria-hidden', 'false');
            
            // Fetch full bin data including dimensions and capacity
            try {
                const response = await fetch(API, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'get_bin_data', zone_id, rack_id, bin_id})
                });
                const data = await response.json();
                console.log('📦 Bin data received:', data);
                
                if (data.success) {
                    // Set dimensions - if 0, use default values (50×40×30 cm for standard warehouse bin)
                    const dims = data.dimensions || {};
                    console.log('📏 Dimensions from DB (raw):', dims);
                    console.log('📏 Dimensions types:', {
                        width: typeof dims.width,
                        depth: typeof dims.depth,
                        height: typeof dims.height
                    });
                    
                    // Parse with explicit handling
                    let dbWidth = 0;
                    let dbDepth = 0;
                    let dbHeight = 0;
                    
                    if (dims.width !== undefined && dims.width !== null && dims.width !== '') {
                        dbWidth = parseFloat(dims.width);
                        if (isNaN(dbWidth)) dbWidth = 0;
                    }
                    if (dims.depth !== undefined && dims.depth !== null && dims.depth !== '') {
                        dbDepth = parseFloat(dims.depth);
                        if (isNaN(dbDepth)) dbDepth = 0;
                    }
                    if (dims.height !== undefined && dims.height !== null && dims.height !== '') {
                        dbHeight = parseFloat(dims.height);
                        if (isNaN(dbHeight)) dbHeight = 0;
                    }
                    
                    console.log('📏 Parsed dimensions:', {width: dbWidth, depth: dbDepth, height: dbHeight});
                    
                    // Check if bin has no dimensions set
                    const hasNoDimensions = (dbWidth === 0 && dbDepth === 0 && dbHeight === 0);
                    
                    if (hasNoDimensions) {
                        console.log('⚠️ Bin has no dimensions, applying defaults...');
                        
                        // Standard warehouse bin dimensions (cm)
                        let defaultWidth = 50;
                        let defaultDepth = 40;
                        let defaultHeight = 30;
                        
                        // Try to get from matrix editor inputs if available
                        const widthInput = document.getElementById('defaultBinWidth');
                        const depthInput = document.getElementById('defaultBinDepth');
                        const heightInput = document.getElementById('defaultBinHeight');
                        
                        if (widthInput && widthInput.value) {
                            const val = parseFloat(widthInput.value);
                            if (val > 0) defaultWidth = val;
                        }
                        if (depthInput && depthInput.value) {
                            const val = parseFloat(depthInput.value);
                            if (val > 0) defaultDepth = val;
                        }
                        if (heightInput && heightInput.value) {
                            const val = parseFloat(heightInput.value);
                            if (val > 0) defaultHeight = val;
                        }
                        
                        // Set the values
                        document.getElementById('editBinWidth').value = defaultWidth;
                        document.getElementById('editBinDepth').value = defaultDepth;
                        document.getElementById('editBinHeight').value = defaultHeight;
                        
                        console.log('✨ Applied default dimensions:', {width: defaultWidth, depth: defaultDepth, height: defaultHeight});
                    } else {
                        // Use actual dimensions from database
                        document.getElementById('editBinWidth').value = dbWidth;
                        document.getElementById('editBinDepth').value = dbDepth;
                        document.getElementById('editBinHeight').value = dbHeight;
                        console.log('✅ Using existing dimensions from DB:', {width: dbWidth, depth: dbDepth, height: dbHeight});
                    }
                    
                    // Set capacity
                    const currentCap = parseInt(data.current_capacity) || 0;
                    const maxCap = parseInt(data.max_capacity) || 0;
                    
                    document.getElementById('editBinCurrentCapacity').value = currentCap;
                    
                    if (maxCap === 0) {
                        // Standard warehouse bin capacity
                        let defaultMaxCap = 100;
                        
                        // Try to get from matrix editor input if available
                        const maxCapInput = document.getElementById('defaultBinMaxCapacity');
                        if (maxCapInput && maxCapInput.value) {
                            const val = parseInt(maxCapInput.value);
                            if (val > 0) defaultMaxCap = val;
                        }
                        
                        document.getElementById('editBinMaxCapacity').value = defaultMaxCap;
                        console.log('✨ Applied default max capacity:', defaultMaxCap);
                    } else {
                        document.getElementById('editBinMaxCapacity').value = maxCap;
                        console.log('✅ Using existing max capacity from DB:', maxCap);
                    }
                    
                    // Status is automatically calculated, no need to set radio buttons
                } else {
                    console.warn('❌ Failed to fetch bin data:', data.message);
                    // Set default values
                    document.getElementById('editBinWidth').value = 50;
                    document.getElementById('editBinDepth').value = 40;
                    document.getElementById('editBinHeight').value = 30;
                    document.getElementById('editBinCurrentCapacity').value = 0;
                    document.getElementById('editBinMaxCapacity').value = 100;
                }
            } catch (error) {
                console.error('❌ Error fetching bin data:', error);
                // Set default values on error
                document.getElementById('editBinWidth').value = 50;
                document.getElementById('editBinDepth').value = 40;
                document.getElementById('editBinHeight').value = 30;
                document.getElementById('editBinCurrentCapacity').value = 0;
                document.getElementById('editBinMaxCapacity').value = 100;
            }
        }

        function closeEditBinModal(){
            document.getElementById('editBinModal').setAttribute('aria-hidden', 'true');
        }

        function submitEditBin(e){
            e.preventDefault();
            const zone_id = document.getElementById('editBinZoneId').value;
            const rack_id = document.getElementById('editBinRackId').value;
            const bin_id = document.getElementById('editBinBinId').value;
            const new_name = document.getElementById('editBinName').value.trim();
            
            // Collect dimensions
            const width = parseFloat(document.getElementById('editBinWidth').value || 0);
            const depth = parseFloat(document.getElementById('editBinDepth').value || 0);
            const height = parseFloat(document.getElementById('editBinHeight').value || 0);
            
            // Collect capacity
            const current_capacity = parseInt(document.getElementById('editBinCurrentCapacity').value || 0);
            const max_capacity = parseInt(document.getElementById('editBinMaxCapacity').value || 0);
            
            closeEditBinModal();
            showToast({success: true, message: 'Đang cập nhật...'});
            
            post({
                action: 'update_bin_full',
                zone_id,
                rack_id,
                bin_id,
                name: new_name,
                dimensions: { width, depth, height },
                current_capacity,
                max_capacity
            }).then(res => {
                showToast(res);
                if(res.success) {
                    setTimeout(() => location.reload(), 600);
                }
            });
            
            return false;
        }

        function clearBinProduct(zone_id, rack_id, bin_id){
            if(!confirm('Xóa sản phẩm khỏi bin này? (chỉ xóa khi số lượng = 0)')) return;
            post({action:'clear_bin_product',zone_id,rack_id,bin_id}).then(res=>{
                showToast(res); 
                if(res.success) location.reload();
            });
        }

        function editBinQuantity(zone_id, rack_id, bin_id, current_qty){
            document.getElementById('editQtyZoneId').value = zone_id;
            document.getElementById('editQtyRackId').value = rack_id;
            document.getElementById('editQtyBinId').value = bin_id;
            document.getElementById('editQtyLocation').textContent = zone_id + ' / ' + rack_id + ' / ' + bin_id;
            document.getElementById('editQtyCurrentQty').textContent = current_qty;
            document.getElementById('editQtyNewValue').value = current_qty;
            document.getElementById('editQuantityModal').setAttribute('aria-hidden', 'false');
        }

        function closeEditQuantityModal(){
            document.getElementById('editQuantityModal').setAttribute('aria-hidden', 'true');
        }

        function submitEditQuantity(e){
            e.preventDefault();
            const zone_id = document.getElementById('editQtyZoneId').value;
            const rack_id = document.getElementById('editQtyRackId').value;
            const bin_id = document.getElementById('editQtyBinId').value;
            const new_qty = parseInt(document.getElementById('editQtyNewValue').value || '0', 10);
            
            if(new_qty < 0){
                alert('Số lượng phải >= 0');
                return false;
            }
            
            closeEditQuantityModal();
            showToast({success: true, message: 'Đang cập nhật số lượng...'});
            
            post({action:'update_bin_quantity', zone_id, rack_id, bin_id, quantity: new_qty}).then(res=>{
                showToast(res); 
                if(res.success) {
                    setTimeout(() => location.reload(), 600);
                }
            });
            
            return false;
        }

        // Recalculate bin occupancy for all bins
        async function recalculateAllBinOccupancy() {
            if (!confirm('Tính lại % chiếm dụng cho tất cả bin dựa trên inventory thực tế?\n\nQuá trình này sẽ:\n• Tính toán thể tích sản phẩm trong mỗi bin\n• So sánh với thể tích bin\n• Cập nhật % chiếm dụng (0-100%)\n\nQuá trình có thể mất vài giây.')) return;
            
            showToast({success: true, message: 'Đang tính toán capacity cho tất cả bins...'});
            
            try {
                // Get warehouse ID from session
                const warehouseId = '<?php echo $_SESSION['login']['warehouse_id'] ?? ''; ?>';
                
                if (!warehouseId) {
                    showToast({success: false, message: 'Không xác định được warehouse ID'});
                    return;
                }
                
                const response = await fetch('recalculate_capacities.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        warehouse_id: warehouseId
                    })
                });
                
                const data = await response.json();
                
                if (data && data.success) {
                    const stats = data.stats || {};
                    const msg = `Hoàn tất!\n\n` +
                        `Tổng bins: ${stats.total_bins || 0}\n` +
                        `Bins có hàng: ${stats.bins_with_inventory || 0}\n` +
                        `Bins đã cập nhật: ${stats.updated_bins || 0}`;
                    
                    showToast({success: true, message: msg});
                    
                    // Reload after 2 seconds to show updated capacities
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showToast({success: false, message: data.error || 'Tính toán thất bại'});
                }
            } catch (error) {
                console.error('Error recalculating occupancy:', error);
                showToast({success: false, message: 'Lỗi khi tính toán: ' + error.message});
            }
        }

		// Matrix editor (grid generator removed) — preview-only mode retained below

		// --- Create zone configs ---
		document.getElementById('applyZones').addEventListener('click', ()=>{
			const n = parseInt(document.getElementById('numZones').value)||1;
			const r = parseInt(document.getElementById('numRacks').value)||1;
			const b = parseInt(document.getElementById('numBins').value)||1;
			if(n<1||n>3){alert('Số zone phải trong [1,3]');return}
			if(r<1||r>4){alert('Số racks phải trong [1,4]');return}
			if(b<1||b>10){alert('Số bins phải trong [1,10]');return}
			
			const container = document.getElementById('zoneConfigs');
			container.innerHTML = '';
			for(let i=0;i<n;i++){
				const div = document.createElement('div');
				div.style.border='2px solid #3b82f6'; 
				div.style.padding='14px'; 
				div.style.marginBottom='14px';
				div.style.borderRadius='10px';
				div.style.background='linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%)';
				div.innerHTML = `
					<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;padding-bottom:10px;border-bottom:2px dashed #bfdbfe">
						<strong style="font-size:15px;color:#1e40af">Zone ${i+1}</strong>
						<span style="background:#3b82f6;color:#fff;padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600">
							${r} racks × ${b} bins = ${r*b} total bins
						</span>
					</div>
					<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px">
						<div>
							<label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;color:#475569">Zone ID:</label>
							<input type="text" class="zid" placeholder="Z${i+1}" value="Z${i+1}" style="width:100%;padding:8px;border:2px solid #93c5fd;border-radius:6px;font-size:14px;font-weight:600">
						</div>
						<div>
							<label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;color:#475569">Zone name:</label>
							<input type="text" class="zname" placeholder="Zone ${i+1}" value="Zone ${i+1}" style="width:100%;padding:8px;border:2px solid #93c5fd;border-radius:6px;font-size:14px">
						</div>
					</div>
					<div style="margin-top:12px;padding:10px;background:rgba(255,255,255,0.7);border-radius:6px;font-size:13px;color:#64748b">
						<strong style="color:#1e40af">📊 Cấu trúc:</strong>
						<div style="margin-top:6px;display:flex;gap:16px;flex-wrap:wrap">
							<span>🏢 <strong>${r}</strong> Racks (R1-R${r})</span>
							<span>📦 <strong>${b}</strong> Bins per rack (B1-B${b})</span>
						</div>
					</div>
					<input type="hidden" class="nracks" value="${r}">
					<input type="hidden" class="nbins" value="${b}">
				`;
				container.appendChild(div);
			}
		});

        // Add Zone Modal handlers
        function openAddZoneModal(){
            document.getElementById('addZoneId').value = '';
            document.getElementById('addZoneName').value = '';
            document.getElementById('addZoneDescription').value = '';
            document.getElementById('addZoneModal').setAttribute('aria-hidden', 'false');
        }

        function closeAddZoneModal(){
            document.getElementById('addZoneModal').setAttribute('aria-hidden', 'true');
        }

        async function submitAddZone(e){
            e.preventDefault();
            const zid = document.getElementById('addZoneId').value.trim();
            const zname = document.getElementById('addZoneName').value.trim();
            const zdesc = document.getElementById('addZoneDescription').value.trim();
            
            if(!zid || !zname){
                alert('Vui lòng nhập đầy đủ Zone ID và Tên Zone');
                return false;
            }
            
            closeAddZoneModal();
            showToast({success: true, message: 'Đang tạo zone mới...'});
            
            const res = await post({ 
                action: 'add_zone', 
                zone_id: zid, 
                name: zname, 
                description: zdesc,
                racks: [] 
            });
            
            showToast(res);
            if(res.success) {
                setTimeout(() => location.reload(), 600);
            }
            
            return false;
        }

        document.getElementById('addZoneBtn').addEventListener('click', ()=>{
            openAddZoneModal();
        });

		document.getElementById('previewBtn').addEventListener('click', ()=>{
			const configs = Array.from(document.querySelectorAll('#zoneConfigs > div'));
			if(configs.length===0){alert('Hãy tạo cấu hình zone trước');return}
			const preview = document.getElementById('matrixAreaPreview'); 
			preview.innerHTML='<h4 style="margin-bottom:12px">Xem trước cấu trúc kho</h4>';
			
			configs.forEach((cfg, zi)=>{
				const zid = cfg.querySelector('.zid').value.trim()||('Z'+(zi+1));
				const zname = cfg.querySelector('.zname').value.trim()||('Zone '+(zi+1));
				const nr = parseInt(cfg.querySelector('.nracks').value)||1;
				const nb = parseInt(cfg.querySelector('.nbins').value)||1;

				const zoneWrap = document.createElement('div');
				zoneWrap.style.border='2px solid #3b82f6';
				zoneWrap.style.padding='12px';
				zoneWrap.style.marginBottom='12px';
				zoneWrap.style.borderRadius='8px';
				zoneWrap.style.background='#eff6ff';
				
				const title = document.createElement('div'); 
				title.innerHTML = `<strong style="color:#1e40af">${zname} (${zid})</strong> - ${nr} racks × ${nb} bins/rack = <strong>${nr*nb} bins total</strong>`;
				zoneWrap.appendChild(title);

				const racksContainer = document.createElement('div');
				racksContainer.style.display='grid';
				racksContainer.style.gridTemplateColumns='repeat(auto-fit, minmax(200px, 1fr))';
				racksContainer.style.gap='10px';
				racksContainer.style.marginTop='10px';

				for(let r=0; r<nr; r++){
					const rackDiv = document.createElement('div'); 
					rackDiv.style.border='1px solid #10b981';
					rackDiv.style.padding='8px';
					rackDiv.style.borderRadius='6px';
					rackDiv.style.background='#d1fae5';
					rackDiv.innerHTML = `<div style="font-weight:600;margin-bottom:6px;color:#065f46">Rack R${r+1}</div>`;
					
					const binsLine = document.createElement('div');
					binsLine.style.display='flex';
					binsLine.style.gap='4px';
					binsLine.style.flexWrap='wrap';
					
					for(let b=0; b<nb; b++){
						const binSpan = document.createElement('span');
						binSpan.style.display='inline-block';
						binSpan.style.padding='4px 8px';
						binSpan.style.background='#fff';
						binSpan.style.border='1px solid #d97706';
						binSpan.style.borderRadius='3px';
						binSpan.style.fontSize='11px';
						binSpan.style.fontWeight='600';
						binSpan.textContent = 'B'+(b+1);
						binsLine.appendChild(binSpan);
					}
					rackDiv.appendChild(binsLine);
					racksContainer.appendChild(rackDiv);
				}

				zoneWrap.appendChild(racksContainer);
				preview.appendChild(zoneWrap);
			});
		});

        document.getElementById('saveAllBtn').addEventListener('click', async ()=>{
            const btn = document.getElementById('saveAllBtn');
            const originalText = btn.textContent;
            btn.disabled = true; btn.textContent = 'Đang lưu...';
            try {
                const configs = Array.from(document.querySelectorAll('#zoneConfigs > div'));
                if(configs.length===0){ alert('Hãy tạo cấu hình zone trước'); btn.disabled = false; btn.textContent = originalText; return; }

                // Build all zones in one payload and save once
                const zones = [];
                let totalRacks = 0; let totalBins = 0;
                configs.forEach((cfg, zi) => {
                    const zid = (cfg.querySelector('.zid')?.value || ('Z'+(zi+1))).trim();
                    const zname = (cfg.querySelector('.zname')?.value || ('Zone '+(zi+1))).trim();
                    let nr = parseInt(cfg.querySelector('.nracks')?.value || '1', 10);
                    let nb = parseInt(cfg.querySelector('.nbins')?.value || '1', 10);
                    
                    // Validate and constrain values
                    if (!Number.isInteger(nr) || nr < 1) nr = 1;
                    if (!Number.isInteger(nb) || nb < 1) nb = 1;
                    nr = Math.min(Math.max(nr,1), 4);  // Max 4 racks
                    nb = Math.min(Math.max(nb,1), 10);  // Max 10 bins

                    // Get default dimensions and capacity
                    const defaultWidth = parseFloat(document.getElementById('defaultBinWidth')?.value || 0);
                    const defaultDepth = parseFloat(document.getElementById('defaultBinDepth')?.value || 0);
                    const defaultHeight = parseFloat(document.getElementById('defaultBinHeight')?.value || 0);
                    const defaultMaxCap = parseInt(document.getElementById('defaultBinMaxCapacity')?.value || 0);
                    const defaultCurrentCap = parseInt(document.getElementById('defaultBinCurrentCapacity')?.value || 0);

                    const racks = [];
                    for (let r = 0; r < nr; r++) {
                        const rack_id = 'R' + (r+1);
                        const bins = [];
                        
                        for (let b = 0; b < nb; b++) {
                            const bin_id = 'B' + (b+1);
                            const code = `${zid}-R${r+1}-B${b+1}`;
                            bins.push({ 
                                bin_id, 
                                code,
                                quantity: 0, 
                                status: 'empty', 
                                product: null,
                                dimensions: {
                                    width: defaultWidth,
                                    depth: defaultDepth,
                                    height: defaultHeight
                                },
                                current_capacity: defaultCurrentCap,
                                max_capacity: defaultMaxCap
                            });
                            totalBins++;
                        }
                        
                        racks.push({ 
                            rack_id, 
                            name: 'Rack ' + (r+1),
                            bins 
                        });
                    }
                    totalRacks += nr;
                    zones.push({ 
                        zone_id: zid, 
                        name: zname, 
                        description: '', 
                        racks 
                    });
                });

                showToast({ success: true, message: `Đang lưu ${zones.length} zones, ${totalRacks} racks, ${totalBins} bins...` });
                const res = await post({ action: 'save_location', zones });
                if (res && res.success) {
                    showToast({ success: true, message: `Lưu thành công ${zones.length} zone` });
                    setTimeout(()=>location.reload(), 600);
                } else {
                    const msg = res && res.message ? res.message : 'Không rõ lỗi';
                    showToast({ success: false, message: 'Lưu thất bại: ' + msg });
                    alert('Lưu thất bại: ' + msg);
                }
            } catch (err) {
                alert('Lỗi khi lưu: ' + (err.message || err));
            } finally {
                btn.disabled = false; btn.textContent = originalText;
            }
        });

        // Normalize rack names button (optional - only attach if button exists)
        const normalizeBtn = document.getElementById('normalizeBtn');
        if (normalizeBtn) {
            normalizeBtn.addEventListener('click', async ()=>{
                const res = await post({ action: 'normalize_rack_names' });
                showToast(res);
                if (res && res.success) setTimeout(()=>location.reload(), 500);
            });
        }

		async function saveZone(zoneData) {
  try {
    const res = await fetch('./process.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'save_zone', zone: zoneData })
    });
    const text = await res.text();
    // Try parse JSON, if not JSON show full response for debugging
    try {
      const data = JSON.parse(text);
      console.log('response JSON:', data);
      return data;
    } catch (e) {
      console.error('Server returned non-JSON response:', text);
      return { success: false, message: 'Server returned non-JSON response', raw: text };
    }
  } catch (err) {
    console.error('Network error:', err);
    return { success:false, message: err.message };
  }
}

// Example call (thử lưu minimal payload)
document.addEventListener('DOMContentLoaded', () => {
  const sample = { _id: 'Z1', name: 'Zone A - test', warehouse: {id:'W1'}, description:'test', created_at: new Date().toISOString(), racks: [] };
  // uncomment to test automatically:
  // saveZone(sample).then(r=>alert(JSON.stringify(r)));
});
	</script>
</body>
</html>