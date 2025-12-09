<?php
session_start();
include_once(__DIR__ . "/../../../../../controller/cWarehouse.php");

// Handle both normal submit and JS-triggered submit (no button name)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $warehouse_id   = trim($_POST["warehouse_id"] ?? '');
    $warehouse_name = trim($_POST["warehouse_name"] ?? '');
    $province       = trim($_POST["province_name"] ?? '');
    $ward           = trim($_POST["ward_name"] ?? '');
    $street         = trim($_POST["street"] ?? '');
    $status         = $_POST["status"] ?? '';

    $errors = [];

    // Validate dữ liệu
    if ($province === '') {
        $errors[] = "Tỉnh/Thành phố không được để trống.";
    }

    if ($ward === '') {
        $errors[] = "Phường/Xã không được để trống.";
    }

    if ($street === '') {
        $errors[] = "Tên đường không được để trống.";
    }

    if ($status === '') {
        $errors[] = "Vui lòng chọn trạng thái.";
    }

    if (count($errors) > 0) {
        // Hiển thị thông báo lỗi và dừng xử lý
        echo "<script>
            alert('Lỗi: " . implode("\\n", $errors) . "');
            setTimeout(() => { window.location.href = '../index.php?id=$warehouse_id'; }, 1000);
        </script>";
        exit();
    }

    // Ghép địa chỉ đầy đủ
    $address = "$street, $ward, $province";

    // Chuẩn hóa dữ liệu update theo Model: truyền mảng $data
    $data = [
        'warehouse_name' => $warehouse_name,
        // Lưu địa chỉ theo cấu trúc object để tương thích hiển thị
        'address' => [
            'street' => $street,
            'ward' => $ward,
            'province' => $province
        ],
        'status' => (int)$status
    ];

    $cWarehouse = new CWarehouse();
    $result = $cWarehouse->updateWarehouse($warehouse_id, $data);

    if ($result) {
        // Chuyển hướng về danh sách kho với thông báo thành công
        echo "<script>
            window.location.href = '/kltn/view/page/manage/index.php?page=warehouse&msg=updated';
        </script>";
        exit();
    } else {
        // Hiển thị thông báo thất bại và chuyển hướng
        echo "<script>
            alert('Cập nhật kho thất bại. Vui lòng thử lại.');
            setTimeout(() => { window.location.href = '/kltn/view/page/manage/index.php?page=warehouse/updateWarehouse&id=$warehouse_id'; }, 1000);
        </script>";
        exit();
    }
}

// Nếu không phải POST request, chuyển về trang danh sách kho
header("Location: /kltn/view/page/manage/index.php?page=warehouse");
exit();
?>