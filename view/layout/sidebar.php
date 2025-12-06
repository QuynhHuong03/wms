<?php
// Nếu chưa đăng nhập thì quay lại login
if (!isset($_SESSION["login"]) || empty($_SESSION["login"])) {
    header("Location: index.php?page=login");
    exit();
}

$users = $_SESSION["login"];

// Kiểm tra nếu $users không phải là array (có thể là __PHP_Incomplete_Class)
if (!is_array($users)) {
    // Xóa session và redirect về login
    unset($_SESSION["login"]);
    header("Location: index.php?page=login");
    exit();
}

$currentPage = $_GET['page'] ?? '';
$roleId = $users['role_id'] ?? 0;
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
body {
    margin: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
    background-color: #f4f7fa; 
}

.sidebar {
    width: 360px; 
    background-color: #ffffff;
    color: #1f2937;
    min-height: 100vh;
    padding: 10px 15px 0 15px;
    border-right: 1px solid #e5e7eb;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
    transition: width 0.3s ease, transform 0.3s ease;
    position: relative;
}

.sidebar-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f3f4f6;
}
.sidebar-header img {
    width: 150px;
    height: auto;
    object-fit: contain;
    margin-bottom: 5px;
}
.sidebar h3 {
    font-size: 1.15rem;
    font-weight: 600;
    color: #111827;
    margin: 0;
}

.menu-links {
    flex-grow: 1;
    overflow-y: auto;
    padding-right: 5px;
}

.menu-links::-webkit-scrollbar {
    width: 6px;
}
.menu-links::-webkit-scrollbar-thumb {
    background: #e0e7eb;
    border-radius: 10px;
}
.menu-links::-webkit-scrollbar-thumb:hover {
    background: #c3ced4;
}

.menu-links a, .submenu-toggle {
    display: flex;
    align-items: center;
    padding: 12px 12px;
    color: #4b5563; 
    font-size: 0.95rem;
    font-weight: 500;
    text-decoration: none;
    border-radius: 8px;
    margin-bottom: 4px;
    transition: background-color 0.2s, color 0.2s;
    cursor: pointer;
}

.menu-links a i, .submenu-toggle i:first-child {
    width: 20px;
    text-align: center;
    margin-right: 15px;
    font-size: 1rem;
}

.menu-links a:hover,
.menu-links a.active,
.submenu-toggle:hover {
    background-color: #eef3f9;
    color: #2563eb;
}

.menu-links a.active {
    font-weight: 600;
}

.submenu {
    margin-bottom: 4px;
}
.submenu-toggle {
    justify-content: space-between;
}
.submenu .submenu-items {
    display: none;
    padding: 5px 0 5px 0;
}
.submenu.open .submenu-items {
    display: block;
}
.submenu .submenu-items a {
    font-size: 0.9rem;
    padding: 8px 12px 8px 50px;
    font-weight: 400;
}
.submenu .submenu-items a.active {
    background-color: #e0e9f4;
    color: #1d4ed8;
    font-weight: 500;
}

.arrow {
    font-size: 0.7rem;
    color: #9ca3af;
    transition: transform 0.3s ease;
}
.submenu.open .arrow {
    transform: rotate(180deg);
}
.submenu-toggle:hover .arrow {
    color: #2563eb;
}

.sidebar-footer {
    border-top: 1px solid #f3f4f6;
    padding: 15px 0;
    background: #ffffff;
    display: flex;
    align-items: center;
}
.footer-link {
    display: flex;
    align-items: center;
    text-decoration: none;
    color: #111;
    flex: 1;
    transition: background-color 0.2s;
    padding: 5px 10px;
    border-radius: 8px;
    overflow: hidden;
}
.footer-link:hover {
    background-color: #f7f7f7;
}

.avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e5e7eb; 
    background: #fff;
    flex-shrink: 0; 
}
.user-info {
    display: flex;
    flex-direction: column;
    margin-left: 10px;
    overflow: hidden;
    flex: 1;
}
.user-info span {
    font-size: 0.9rem;
    color: #1f2937;
    font-weight: 600;
    white-space: nowrap;
    text-overflow: ellipsis;
    overflow: hidden;
}
.user-info small {
    font-size: 0.8rem;
    color: #6b7280;
    white-space: nowrap;
    text-overflow: ellipsis;
    overflow: hidden;
}

.logout-icon {
    color: #9ca3af;
    font-size: 1.1rem;
    padding: 10px;
    margin-left: 10px;
    border-radius: 40%;
    transition: color 0.2s, background-color 0.2s;
    flex-shrink: 0;
}
.logout-icon:hover {
    color: #ef4444;
    background-color: #fee2e2;
}

.menu-toggle {
    display: none;
    position: fixed;
    top: 15px;
    left: 15px;
    background: #2563eb;
    color: white;
    border: none;
    padding: 10px 14px;
    font-size: 20px;
    cursor: pointer;
    z-index: 1001;
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(37, 99, 235, 0.4);
    transition: transform 0.3s;
}

.overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 999;
}
.overlay.active {
    display: block;
}

@media (max-width: 768px) {
    .sidebar {
        width: 280px;
        position: fixed;
        top: 0;
        left: 0;
        transform: translateX(-100%);
        z-index: 1000;
    }
    .sidebar.active {
        transform: translateX(0);
    }
    .menu-toggle {
        display: block;
    }
}
</style>

<button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
<div class="overlay" id="overlay"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../../../img/logo1.png" alt="Logo">
        <h3>Quản Lý Kho Hàng</h3>
    </div>

    <div class="menu-links">


        <?php if ($roleId == 1): ?>
            <div class="submenu" id="submenu-user">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <span><i class="fas fa-users"></i> Quản lý người dùng</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </a>
                <div class="submenu-items">
                    <a href="index.php?page=users" class="<?= $currentPage=='users'?'active':'' ?>"><i class="fas fa-user"></i> Tài khoản</a>
                    <a href="index.php?page=roles" class="<?= $currentPage=='roles'?'active':'' ?>"><i class="fas fa-user-shield"></i> Vai trò</a>
                </div>
            </div>

            <a href="index.php?page=warehouse" class="<?= $currentPage=='warehouse'?'active':'' ?>"><i class="fas fa-warehouse"></i> Quản lý kho</a>
            <a href="index.php?page=supplier" class="<?= $currentPage=='supplier'?'active':'' ?>"><i class="fas fa-truck"></i> Nhà cung cấp</a>
            
            <div class="submenu" id="submenu-product">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <span><i class="fas fa-boxes"></i> Quản lý sản phẩm</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </a>
                <div class="submenu-items">
                    <a href="index.php?page=categories" class="<?= $currentPage=='categories'?'active':'' ?>">
                        <i class="fas fa-tags"></i> Loại sản phẩm
                    </a>
                    <a href="index.php?page=products" class="<?= $currentPage=='products'?'active':'' ?>">
                        <i class="fas fa-box"></i> Sản phẩm
                    </a>
                </div>
            </div>

            <div class="submenu" id="submenu-product">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <span><i class="fas fa-chart-line"></i> Báo cáo thống kê</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </a>
                <div class="submenu-items">
                    <a href="index.php?page=report/InboundStatistics" class="<?= $currentPage=='categories'?'active':'' ?>">
                        <i class="fas fa-tags"></i> Thống kê phiếu nhập
                    </a>
                    <a href="index.php?page=report/OutboundStatistics" class="<?= $currentPage=='products'?'active':'' ?>">
                        <i class="fas fa-box"></i> Thống kê phiếu xuất
                    </a>
                    <a href="index.php?page=report/InventoryStatistics" class="<?= $currentPage=='products'?'active':'' ?>">
                        <i class="fas fa-box"></i> Thống kê tồn kho
                    </a>
                </div>
            </div>
            
        <?php elseif ($roleId == 2): ?>
            <a href="index.php?page=supplier" class="<?= $currentPage=='supplier'?'active':'' ?>"><i class="fas fa-truck"></i> Nhà cung cấp</a>
            
            <div class="submenu" id="submenu-product">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <span><i class="fas fa-boxes"></i> Quản lý sản phẩm</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </a>
                <div class="submenu-items">
                    <a href="index.php?page=categories" class="<?= $currentPage=='categories'?'active':'' ?>">
                        <i class="fas fa-tags"></i> Loại sản phẩm
                    </a>
                    <a href="index.php?page=products" class="<?= $currentPage=='products'?'active':'' ?>">
                        <i class="fas fa-box"></i> Sản phẩm
                    </a>
                </div>
            </div>

            <div class="submenu" id="submenu-receipts-t">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <span><i class="fa-solid fa-file-import"></i> Quản lý nhập kho</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </a>
                <div class="submenu-items">
                    <a href="index.php?page=receipts" class="<?= $currentPage=='receipts'?'active':'' ?>"><i class="fa-solid fa-file-circle-plus"></i> Tạo phiếu nhập kho</a>
                    <a href="index.php?page=receipts/approve" class="<?= $currentPage=='receipts/approve'?'active':'' ?>"><i class="fa-solid fa-clipboard-check"></i> Duyệt phiếu nhập kho</a>
                </div>
            </div>
            <a href="index.php?page=goodsReceiptRequest" class="<?= $currentPage=='locations'?'active':'' ?>"><i class="fa-solid fa-clipboard-check"></i> Quản lý phiếu yêu cầu </a>
            
            <div class="submenu" id="submenu-exports-t">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <span><i class="fas fa-file-export"></i> Quản lý xuất kho</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </a>
                <div class="submenu-items">
                    <a href="index.php?page=exports" class="<?= $currentPage=='exports'?'active':'' ?>"><i class="fa-solid fa-list-check"></i> Danh sách phiếu xuất kho</a>
                </div>
            </div>
            
            <div class="submenu" id="submenu-inventory-sheets-t">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <span><i class="fas fa-clipboard-list"></i> Quản lý kiểm kê</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </a>
                <div class="submenu-items">
                    <a href="index.php?page=inventory/createInventory_sheet" class="<?= $currentPage=='inventory/createInventory_sheet'?'active':'' ?>"><i class="fa-solid fa-file-circle-plus"></i> Tạo phiếu kiểm kê</a>
                    <a href="index.php?page=inventory/inventory_sheets" class="<?= $currentPage=='inventory/inventory_sheets'?'active':'' ?>"><i class="fa-solid fa-clipboard-check"></i> Quản lý phiếu</a>
                </div>
            </div>
            <a href="index.php?page=batches" class="<?= $currentPage=='locations'?'active':'' ?>"><i class="fa-solid fa-box-open"></i> Quản lý lô hàng </a>
            
            <a href="index.php?page=locations" class="<?= $currentPage=='locations'?'active':'' ?>"><i class="fas fa-warehouse"></i> Sơ đồ vị trí kho </a>
            <a href="index.php?page=inventory" class="<?= $currentPage=='inventory'?'active':'' ?>"><i class="fa-solid fa-box-open"></i> Xem tồn kho </a>
            <a href="index.php?page=report" class="<?= $currentPage=='report'?'active':'' ?>"><i class="fas fa-chart-line"></i> Báo cáo thống kê</a>

        <?php elseif ($roleId == 3): ?>
            <div class="submenu" id="submenu-receipts-nvt">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <span><i class="fa-solid fa-file-circle-plus"></i> Quản lý nhập kho</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </a>
                <div class="submenu-items">
                    <a href="index.php?page=receipts" class="<?= $currentPage=='receipts'?'active':'' ?>"><i class="fa-solid fa-file-circle-plus"></i> Tạo phiếu nhập kho</a>
                    <a href="index.php?page=receipts/approve" class="<?= $currentPage=='receipts/approve'?'active':'' ?>"><i class="fa-solid fa-file-lines"></i> Danh sách phiếu nhập kho</a>
                </div>
            </div>
            <div class="submenu" id="submenu-exports-nvt">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <span><i class="fas fa-file-export"></i> Quản lý xuất kho</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </a>
                <div class="submenu-items">
                    <a href="index.php?page=exports" class="<?= $currentPage=='exports'?'active':'' ?>"><i class="fa-solid fa-file-lines"></i> Danh sách phiếu xuất kho</a>
                </div>
            </div>
            <div class="submenu" id="submenu-inventory-sheets-t">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <span><i class="fas fa-clipboard-list"></i> Quản lý kiểm kê</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </a>
                <div class="submenu-items">
                    <a href="index.php?page=inventory/createInventory_sheet" class="<?= $currentPage=='inventory/createInventory_sheet'?'active':'' ?>"><i class="fa-solid fa-file-circle-plus"></i> Tạo phiếu kiểm kê</a>
                    <a href="index.php?page=inventory/inventory_sheets" class="<?= $currentPage=='inventory/inventory_sheets'?'active':'' ?>"><i class="fa-solid fa-clipboard-check"></i> Quản lý phiếu</a>
                </div>
            </div>
            <a href="index.php?page=inventory" class="<?= $currentPage=='inventory'?'active':'' ?>"><i class="fas fa-boxes"></i> Xem tồn kho</a>
            <a href="index.php?page=report" class="<?= $currentPage=='report'?'active':'' ?>"><i class="fas fa-chart-line"></i> Báo cáo tổng</a>

        <?php elseif ($roleId == 4): ?>
            <div class="submenu" id="submenu-receipts-cnh">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <span><i class="fa-solid fa-file-circle-plus"></i> Phiếu nhập kho chi nhánh</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </a>
                <div class="submenu-items">
                    <a href="index.php?page=receipts" class="<?= $currentPage=='receipts'?'active':'' ?>"><i class="fa-solid fa-file-circle-plus"></i> Tạo phiếu nhập kho</a>
                    <a href="index.php?page=receipts/approve" class="<?= $currentPage=='receipts/approve'?'active':'' ?>"><i class="fa-solid fa-file-lines"></i> Duyệt phiếu nhập kho</a>
                </div>
            </div>
            <div class="submenu" id="submenu-exports-cnh">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <span><i class="fas fa-file-export"></i> Phiếu xuất kho chi nhánh</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </a>
                <div class="submenu-items">
                    <a href="index.php?page=exports" class="<?= $currentPage=='exports'?'active':'' ?>"><i class="fa-solid fa-clipboard-check"></i>Danh sách phiếu xuất</a>
                </div>
            </div>
            <div class="submenu" id="submenu-request-cnh">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <span><i class="fa-solid fa-file-circle-plus"></i> Phiếu yêu cầu nhập hàng</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </a>
                <div class="submenu-items">
                    <a href="index.php?page=goodsReceiptRequest/createReceipt" class="<?= $currentPage=='goodsReceiptRequest/createReceipt'?'active':'' ?>"><i class="fa-solid fa-file-circle-plus"></i> Tạo phiếu yêu cầu</a>
                    <a href="index.php?page=goodsReceiptRequest" class="<?= $currentPage=='goodsReceiptRequest'?'active':'' ?>"><i class="fa-solid fa-file-lines"></i> Danh sách phiếu yêu cầu</a>
                </div>
            </div>
            <a href="index.php?page=locations" class="<?= $currentPage=='locations'?'active':'' ?>"><i class="fas fa-boxes"></i> Sơ đồ vị trí kho </a>
            <a href="index.php?page=inventory" class="<?= $currentPage=='inventory'?'active':'' ?>"><i class="fas fa-boxes"></i> Xem tồn kho </a>
            <a href="index.php?page=report" class="<?= $currentPage=='report'?'active':'' ?>"><i class="fas fa-chart-line"></i> Báo cáo thống kê</a>

        <?php elseif ($roleId == 5): ?>
            <div class="submenu" id="submenu-receipts-nvcn">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <span><i class="fa-solid fa-file-circle-plus"></i> Phiếu nhập kho chi nhánh</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </a>
                <div class="submenu-items">
                    <a href="index.php?page=receipts" class="<?= $currentPage=='receipts'?'active':'' ?>"><i class="fa-solid fa-file-circle-plus"></i> Tạo phiếu nhập kho</a>
                    <a href="index.php?page=receipts/approve" class="<?= $currentPage=='receipts/approve'?'active':'' ?>"><i class="fa-solid fa-file-lines"></i> Danh sách phiếu nhập kho</a>
                </div>
            </div>
            <div class="submenu" id="submenu-exports-nvcn">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <span><i class="fas fa-file-export"></i> Phiếu xuất kho chi nhánh</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </a>
                <div class="submenu-items">
                    <a href="index.php?page=exports" class="<?= $currentPage=='exports'?'active':'' ?>"><i class="fa-solid fa-file-lines"></i> Danh sách phiếu xuất kho</a>
                </div>
            </div>
            <div class="submenu" id="submenu-request-nvcn">
                <a href="javascript:void(0)" class="submenu-toggle">
                    <span><i class="fa-solid fa-file-circle-plus"></i> Phiếu yêu cầu nhập hàng</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </a>
                <div class="submenu-items">
                    <a href="index.php?page=goodsReceiptRequest/createReceipt" class="<?= $currentPage=='goodsReceiptRequest/createReceipt'?'active':'' ?>"><i class="fa-solid fa-file-circle-plus"></i> Tạo phiếu yêu cầu</a>
                    <a href="index.php?page=goodsReceiptRequest" class="<?= $currentPage=='goodsReceiptRequest'?'active':'' ?>"><i class="fa-solid fa-file-lines"></i> Danh sách phiếu yêu cầu</a>
                </div>
            </div>
            <a href="index.php?page=inventory" class="<?= $currentPage=='inventory'?'active':'' ?>"><i class="fas fa-boxes"></i> Xem tồn kho </a>
            <a href="index.php?page=report" class="<?= $currentPage=='report'?'active':'' ?>"><i class="fas fa-chart-line"></i> Báo cáo tổng</a>

        <?php endif; ?>

    </div> <div class="sidebar-footer">
        <a href="index.php?page=profile" class="footer-link">
            <img 
                src="<?= isset($users['avatar']) && $users['avatar'] != '' 
                      ? htmlspecialchars($users['avatar']) 
                      : 'https://ui-avatars.com/api/?name=' . urlencode($users['name'] ?? 'User') . '&background=2563eb&color=fff&size=128' ?>" 
                alt="Avatar" 
                class="avatar">
            <div class="user-info">
                <span><?= htmlspecialchars($users['name'] ?? ($users['email'] ?? 'Người dùng')) ?></span>
                <small>Vai trò: ID <?= htmlspecialchars($roleId) ?></small>
                <?php
                    // Try common keys for warehouse information stored in the session user object
                    $warehouseId = $users['warehouse_id'] ?? $users['warehouse'] ?? null;
                    $warehouseName = $users['warehouse_name'] ?? $users['warehouse_title'] ?? null;
                ?>
                <small>Kho: <?= $warehouseName ? htmlspecialchars($warehouseName) : ($warehouseId ? 'ID ' . htmlspecialchars($warehouseId) : 'Chưa chọn') ?></small>
            </div>
        </a>
        <a href="../logout/index.php" class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</div>

<script>
    const menuToggle = document.getElementById("menuToggle");
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("overlay");

    menuToggle.addEventListener("click", () => {
        sidebar.classList.toggle("active");
        overlay.classList.toggle("active");
    });
    overlay.addEventListener("click", () => {
        sidebar.classList.remove("active");
        overlay.classList.remove("active");
    });

    // Toggle submenu + nhớ trạng thái
    document.querySelectorAll(".submenu-toggle").forEach(toggle => {
        toggle.addEventListener("click", (e) => {
            e.preventDefault();
            const parent = toggle.closest(".submenu");
            const id = parent.id;
            parent.classList.toggle("open");
            localStorage.setItem("submenu_" + id, parent.classList.contains("open") ? "open" : "closed");
        });
    });

    // Khôi phục trạng thái submenu khi load
    window.addEventListener("DOMContentLoaded", () => {
        document.querySelectorAll(".submenu").forEach(menu => {
            const id = menu.id;
            // Kiểm tra trạng thái đã lưu
            if (localStorage.getItem("submenu_" + id) === "open") {
                menu.classList.add("open");
            }
            // Nếu có liên kết con đang active, phải mở menu
            if (menu.querySelector(".submenu-items a.active")) {
                menu.classList.add("open");
            }
        });
    });
</script>