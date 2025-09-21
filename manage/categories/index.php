<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include_once(__DIR__ . '/../../../../controller/cCategories.php');
$p = new CCategories();
$tblCategory = $p->getAllCategories();
?>

<style>
body {
    font-family: Arial, sans-serif;
    background-color: #f9f9f9;
    margin: 0;
    padding: 0;
}

.container.qlnl {
    width: 100%;
    max-width: 1400px;
    margin: 20px auto;
    background: #ffffff;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    padding: 20px;
}

button {
    border: none;
    border-radius: 10px;
    cursor: pointer;
    background-color: #3b82f6;
    color: white;
    font-weight: bold;
    transition: background-color 0.3s ease;
}

button a {
    text-decoration: none;
    color: white;
}

.header-categories {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    margin: 20px 0;
}

.header-left h3 {
    margin: 0;
    color: #333;
}

.header-left p {
    margin: 4px 0 0;
    color: #666;
    font-size: 14px;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.qlnl-search-container {
    position: relative;
}

.qlnl-search {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    border: 1px solid #ddd;
    background: #fff;
}

.qlnl-search input {
    border: none;
    outline: none;
    font-size: 14px;
    padding: 4px 6px;
    width: 200px;
}

#searchResult {
    position: absolute;
    top: 110%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    max-height: 250px;
    overflow-y: auto;
    display: none;
    z-index: 99;
}

.btn-add-category {
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    cursor: pointer;
    font-weight: bold;
    transition: background 0.3s;
}

.btn-add-category a {
    color: white;
    text-decoration: none;
}

.btn-add-category:hover {
    background: #2563eb;
}

@media (max-width: 600px) {
    .header-categories {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    .header-right {
        width: 100%;
        justify-content: flex-start;
        gap: 8px;
    }
    .qlnl-search input {
        width: 150px;
    }
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

thead {
    background-color: #3b82f6;
    color: white;
    font-size: 16px;
    text-align: left;
}

thead th {
    padding: 10px;
    position: sticky;
    top: 0;
    z-index: 10;
}

tbody tr:nth-child(odd) {
    background-color: #f2f2f2;
}

tbody tr:nth-child(even) {
    background-color: #ffffff;
}

tbody td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
}

.status-active {
    background-color: #d1fae5;
    color: #065f46;
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    display: inline-block;
}

.status-inactive {
    background-color: #fee2e2;
    color: #991b1b;
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    display: inline-block;
}

td a {
    color: #3b82f6;
    text-decoration: none;
    font-size: 18px;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    top: 0; left: 0;
    width: 100%; height: 100%;
}

.modal-overlay {
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.4);
    z-index: 1000;
}

.modal-content {
    position: relative;
    z-index: 1001;
    max-width: 400px;
    margin: 100px auto;
    background: #fff;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    text-align: center;
}

.fade-out {
    animation: fadeOut 0.6s forwards;
}
@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; height: 0; padding: 0; margin: 0; }
}
</style>

<body>
    <div class="header-categories">
        <div class="header-left">
            <h3>QUẢN LÝ DANH MỤC SẢN PHẨM</h3>
            <p>Tạo và quản lý danh mục sản phẩm</p>
        </div>

        <div class="header-right">
            <div class="qlnl-search-container">
                <div class="qlnl-search">
                    <i class="fas fa-search" style="color:#888;"></i>
                    <input id="searchInput" type="text" placeholder="Tìm kiếm...">
                </div>
                <div id="searchResult"></div>
            </div>

            <button class="btn-add-category">
                <a href="index.php?page=categories/createCategories">+ Thêm danh mục</a>
            </button>
        </div>
    </div>

    <div class="container qlnl">
        <div style="overflow: auto; height: 500px;">
        <?php if ($tblCategory && is_array($tblCategory)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Mã danh mục</th>
                        <th>Tên danh mục</th>
                        <th>Mã code</th> <!-- Đã có -->
                        <th>Mô tả</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th>Ngày cập nhật</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tblCategory as $c): 
                        $id = $c['category_id'] ?? ($c['_id'] ?? '');
                        $statusClass = $c['status'] == 1 ? 'status-active' : 'status-inactive';
                        $statusText = $c['status'] == 1 ? 'Hoạt động' : 'Ngừng hoạt động';
                    ?>
                    <tr id="row-<?php echo $id; ?>">
                        <td><?php echo $id; ?></td>
                        <td><?php echo $c['category_name'] ?? ''; ?></td>
                        <td><?php echo $c['category_code'] ?? ''; ?></td> <!-- Sửa dòng này -->
                        <td><?php echo $c['description'] ?? ''; ?></td>
                        <td><span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                        <td>
                            <?php
                            if (!empty($c['create_at'])) {
                                if (is_array($c['create_at']) && isset($c['create_at']['$numberLong'])) {
                                    $timestamp = (int) ($c['create_at']['$numberLong'] / 1000);
                                    echo date('Y-m-d H:i:s', $timestamp);
                                } elseif ($c['create_at'] instanceof MongoDB\BSON\UTCDateTime) {
                                    echo $c['create_at']->toDateTime()->format('Y-m-d H:i:s');
                                }
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            if (!empty($c['update_at'])) {
                                if (is_array($c['update_at']) && isset($c['update_at']['$numberLong'])) {
                                    $timestamp = (int) ($c['update_at']['$numberLong'] / 1000);
                                    echo date('Y-m-d H:i:s', $timestamp);
                                } elseif ($c['update_at'] instanceof MongoDB\BSON\UTCDateTime) {
                                    echo $c['update_at']->toDateTime()->format('Y-m-d H:i:s');
                                }
                            } else {
                                echo "-";
                            }
                            ?>
                        </td>
                        <td>
                            <a href="index.php?page=categories/updateCategories&id=<?php echo $id; ?>">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="#" class="btn-delete" data-id="<?php echo $id; ?>" style="margin-left:10px; color:red;">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Không có danh mục nào.</p>
        <?php endif; ?>
        </div>

        <div class="col-md-4" style="padding-top: 20px;">
            <button>
                <a href="index.php?page=manage">Quay lại</a>
            </button>
        </div>
    </div>

    <!-- Modal Xác nhận Xóa -->
<div id="deleteModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:400px; margin:100px auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 4px 8px rgba(0,0,0,0.2); text-align:center;">
        <h3 style="margin-top:0;">Xác nhận xóa</h3>
        <p>Bạn có chắc chắn muốn xóa danh mục này?</p>
        <div style="margin-top:20px;">
            <button id="cancelBtn" style="background:#ccc; color:#333; margin-right:10px; padding:8px 16px; border:none; border-radius:8px; cursor:pointer;">Hủy</button>
            <button id="confirmDeleteBtn" style="background:red; color:white; padding:8px 16px; border:none; border-radius:8px; cursor:pointer;">Xóa</button>
        </div>
    </div>
    <div class="modal-overlay" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4);"></div>
</div>

<script>
const deleteModal = document.getElementById('deleteModal');
const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
const cancelBtn = document.getElementById('cancelBtn');
let deleteCategoryId = null;

// Bắt sự kiện click nút Xóa trên bảng
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function(e){
        e.preventDefault();
        deleteCategoryId = this.dataset.id; // lưu ID danh mục cần xóa
        deleteModal.style.display = 'block'; // hiển thị modal
    });
});

// Hủy xóa
cancelBtn.addEventListener('click', function(){
    deleteModal.style.display = 'none';
    deleteCategoryId = null;
});

// Xác nhận xóa
confirmDeleteBtn.addEventListener('click', function(){
    if(deleteCategoryId){
        fetch('categories/deleteCategories/process.php?id=' + deleteCategoryId)
            .then(response => response.text())
            .then(result => {
                deleteModal.style.display = 'none'; // ẩn modal
                if(result.trim() === "success"){
                    window.location.reload(); // reload lại trang sau khi xóa
                } else {
                    alert("Không thể xóa danh mục!");
                }
            })
            .catch(err => console.error('Lỗi xóa category:', err));
    }
});
</script>

</body>
