# Cấu trúc bảng cơ sở dữ liệu - WMS

## 1. Bảng Users (Người dùng)
**Mục đích**: Quản lý thông tin người dùng hệ thống

| Cột | Kiểu dữ liệu | Mô tả | Ràng buộc |
|-----|--------------|-------|-----------|
| _id | ObjectId | ID MongoDB | PRIMARY KEY |
| user_id | string | Mã người dùng | UNIQUE, NOT NULL |
| name | string | Tên người dùng | NOT NULL |
| email | string | Email | UNIQUE, NOT NULL |
| password | string | Mật khẩu (đã mã hóa) | NOT NULL |
| gender | string | Giới tính | |
| phone | string | Số điện thoại | |
| role_id | int | ID vai trò | FOREIGN KEY → roles.role_id |
| warehouse_id | int | ID kho | FOREIGN KEY → warehouses.warehouse_id |
| status | int | Trạng thái (active/inactive) | DEFAULT 1 |
| first_login | bool | Đăng nhập lần đầu | DEFAULT true |
| created_at | UTCDateTime | Ngày tạo | |
| updated_at | UTCDateTime | Ngày cập nhật | |

**Indexes**: user_id, email, role_id, warehouse_id

---

## 2. Bảng Roles (Vai trò)
**Mục đích**: Định nghĩa các vai trò và quyền hạn

| Cột | Kiểu dữ liệu | Mô tả | Ràng buộc |
|-----|--------------|-------|-----------|
| _id | ObjectId | ID MongoDB | PRIMARY KEY |
| role_id | int | Mã vai trò | UNIQUE, NOT NULL |
| role_name | string | Tên vai trò | NOT NULL |
| description | string | Mô tả vai trò | |

**Indexes**: role_id

**Dữ liệu mẫu**:
- 1: Admin
- 2: Warehouse Manager
- 3: Warehouse Staff
- 4: Branch Manager

---

## 3. Bảng Warehouses (Kho)
**Mục đích**: Quản lý thông tin kho hàng

| Cột | Kiểu dữ liệu | Mô tả | Ràng buộc |
|-----|--------------|-------|-----------|
| _id | ObjectId | ID MongoDB | PRIMARY KEY |
| warehouse_id | string | Mã kho | UNIQUE, NOT NULL |
| warehouse_name | string | Tên kho | NOT NULL |
| address | object | Địa chỉ kho | |
| address.street | string | Đường | |
| address.city | string | Thành phố | |
| address.province | string | Tỉnh | |
| address.postal_code | string | Mã bưu điện | |
| status | int | Trạng thái | DEFAULT 1 |
| warehouse_type | int | Loại kho | FOREIGN KEY → warehouse_types.type_id |
| created_at | string | Ngày tạo | |
| updated_at | string | Ngày cập nhật | |

**Indexes**: warehouse_id, warehouse_type, status

---

## 4. Bảng Warehouse_Types (Loại kho)
**Mục đích**: Phân loại các loại kho

| Cột | Kiểu dữ liệu | Mô tả | Ràng buộc |
|-----|--------------|-------|-----------|
| _id | ObjectId | ID MongoDB | PRIMARY KEY |
| type_id | int | Mã loại kho | UNIQUE, NOT NULL |
| type_name | string | Tên loại kho | NOT NULL |
| description | string | Mô tả | |

**Dữ liệu mẫu**:
- 1: Kho tổng (Central Warehouse)
- 2: Kho chi nhánh (Branch Warehouse)

---

## 5. Bảng Categories (Danh mục sản phẩm)
**Mục đích**: Phân loại sản phẩm

| Cột | Kiểu dữ liệu | Mô tả | Ràng buộc |
|-----|--------------|-------|-----------|
| _id | ObjectId | ID MongoDB | PRIMARY KEY |
| category_id | string | Mã danh mục | UNIQUE, NOT NULL |
| category_name | string | Tên danh mục | NOT NULL |
| category_code | string | Mã code | |
| description | string | Mô tả | |
| status | int | Trạng thái | DEFAULT 1 |
| created_at | UTCDateTime | Ngày tạo | |
| updated_at | UTCDateTime | Ngày cập nhật | |

**Indexes**: category_id, status

---

## 6. Bảng Suppliers (Nhà cung cấp)
**Mục đích**: Quản lý thông tin nhà cung cấp

| Cột | Kiểu dữ liệu | Mô tả | Ràng buộc |
|-----|--------------|-------|-----------|
| _id | ObjectId | ID MongoDB | PRIMARY KEY |
| supplier_id | string | Mã nhà cung cấp | UNIQUE, NOT NULL |
| supplier_name | string | Tên nhà cung cấp | NOT NULL |
| contact | string | Người liên hệ | |
| email | string | Email | |
| phone | string | Số điện thoại | |
| address | string | Địa chỉ | |
| status | int | Trạng thái | DEFAULT 1 |
| created_at | string | Ngày tạo | |
| updated_at | string | Ngày cập nhật | |

**Indexes**: supplier_id, status

---

## 7. Bảng Products (Sản phẩm)
**Mục đích**: Quản lý thông tin sản phẩm

| Cột | Kiểu dữ liệu | Mô tả | Ràng buộc |
|-----|--------------|-------|-----------|
| _id | ObjectId | ID MongoDB | PRIMARY KEY |
| sku | string | Mã SKU | UNIQUE, NOT NULL |
| product_name | string | Tên sản phẩm | NOT NULL |
| barcode | string | Mã vạch | UNIQUE |
| category | object | Thông tin danh mục | |
| category.category_id | string | ID danh mục | FOREIGN KEY → categories.category_id |
| category.category_name | string | Tên danh mục | |
| supplier | object | Thông tin nhà cung cấp | |
| supplier.supplier_id | string | ID nhà cung cấp | FOREIGN KEY → suppliers.supplier_id |
| supplier.supplier_name | string | Tên nhà cung cấp | |
| baseUnit | string | Đơn vị cơ bản | NOT NULL |
| conversionUnits | array | Đơn vị chuyển đổi | |
| package_dimensions | object | Kích thước đóng gói | |
| package_dimensions.length | float | Chiều dài (cm) | |
| package_dimensions.width | float | Chiều rộng (cm) | |
| package_dimensions.height | float | Chiều cao (cm) | |
| package_weight | float | Trọng lượng (kg) | |
| volume_per_unit | float | Thể tích/đơn vị | |
| stackable | bool | Có thể xếp chồng | DEFAULT true |
| max_stack_height | int | Độ cao xếp tối đa | |
| min_stock | int | Tồn kho tối thiểu | DEFAULT 0 |
| purchase_price | float | Giá mua | |
| status | int | Trạng thái | DEFAULT 1 |
| image | string | Đường dẫn ảnh | |
| created_at | string | Ngày tạo | |
| updated_at | string | Ngày cập nhật | |

**Indexes**: sku, barcode, category.category_id, supplier.supplier_id, status

---

## 8. Bảng Locations (Vị trí kho)
**Mục đích**: Quản lý cấu trúc vị trí trong kho

| Cột | Kiểu dữ liệu | Mô tả | Ràng buộc |
|-----|--------------|-------|-----------|
| _id | string | ID vị trí | PRIMARY KEY |
| warehouse | object | Thông tin kho | |
| warehouse.warehouse_id | string | ID kho | FOREIGN KEY → warehouses.warehouse_id |
| warehouse.warehouse_name | string | Tên kho | |
| zones | array | Danh sách zone | |
| name | string | Tên vị trí | |
| description | string | Mô tả | |
| created_at | string | Ngày tạo | |
| updated_at | string | Ngày cập nhật | |

**Indexes**: warehouse.warehouse_id

---

## 9. Bảng Zones (Khu vực)
**Mục đích**: Quản lý các khu vực trong kho

| Cột | Kiểu dữ liệu | Mô tả | Ràng buộc |
|-----|--------------|-------|-----------|
| _id | ObjectId | ID MongoDB | PRIMARY KEY |
| zone_id | string | Mã zone | UNIQUE, NOT NULL |
| name | string | Tên zone | NOT NULL |
| level | int | Cấp độ | |
| warehouse_id | string | ID kho | FOREIGN KEY → warehouses.warehouse_id |
| description | string | Mô tả | |
| racks | array | Danh sách rack | |
| created_at | string | Ngày tạo | |
| updated_at | string | Ngày cập nhật | |

**Indexes**: zone_id, warehouse_id

---

## 10. Bảng Racks (Giá kệ)
**Mục đích**: Quản lý giá kệ trong zone

| Cột | Kiểu dữ liệu | Mô tả | Ràng buộc |
|-----|--------------|-------|-----------|
| _id | ObjectId | ID MongoDB | PRIMARY KEY |
| rack_id | string | Mã rack | UNIQUE, NOT NULL |
| name | string | Tên rack | NOT NULL |
| zone_id | string | ID zone | FOREIGN KEY → zones.zone_id |
| level | int | Cấp độ | |
| shelves | array | Danh sách kệ | |
| created_at | string | Ngày tạo | |
| updated_at | string | Ngày cập nhật | |

**Indexes**: rack_id, zone_id

---

## 11. Bảng Bins (Ngăn/Ô lưu trữ)
**Mục đích**: Quản lý ô lưu trữ nhỏ nhất trong kho

| Cột | Kiểu dữ liệu | Mô tả | Ràng buộc |
|-----|--------------|-------|-----------|
| _id | ObjectId | ID MongoDB | PRIMARY KEY |
| id | int | ID số | UNIQUE |
| bin_id | string | Mã bin | UNIQUE, NOT NULL |
| code | string | Mã code | |
| rack_id | string | ID rack | FOREIGN KEY → racks.rack_id |
| dimensions | object | Kích thước | |
| dimensions.length | float | Chiều dài | |
| dimensions.width | float | Chiều rộng | |
| dimensions.height | float | Chiều cao | |
| capacity | int | Sức chứa | |
| quantity | int | Số lượng hiện tại | DEFAULT 0 |
| current_capacity | float | % sức chứa hiện tại | DEFAULT 0 |
| status | string | Trạng thái (empty/occupied/full) | DEFAULT 'empty' |
| product_id | string | ID sản phẩm đang chứa | FOREIGN KEY → products.sku |
| created_at | string | Ngày tạo | |
| updated_at | string | Ngày cập nhật | |

**Indexes**: bin_id, rack_id, product_id, status

---

## 12. Bảng Inventory (Tồn kho)
**Mục đích**: Theo dõi số lượng tồn kho thực tế

| Cột | Kiểu dữ liệu | Mô tả | Ràng buộc |
|-----|--------------|-------|-----------|
| _id | ObjectId | ID MongoDB | PRIMARY KEY |
| warehouse_id | string | ID kho | FOREIGN KEY → warehouses.warehouse_id |
| product_id | string | ID sản phẩm | FOREIGN KEY → products.sku |
| product_sku | string | SKU sản phẩm | |
| product_name | string | Tên sản phẩm | |
| qty | float | Số lượng | DEFAULT 0 |
| zone_id | string | ID zone | FOREIGN KEY → zones.zone_id |
| rack_id | string | ID rack | FOREIGN KEY → racks.rack_id |
| bin_id | string | ID bin | FOREIGN KEY → bins.bin_id |
| receipt_id | string | ID phiếu nhập | |
| received_at | UTCDateTime | Ngày nhập | |

**Indexes**: warehouse_id + product_id (Composite), zone_id, rack_id, bin_id

**Unique Constraint**: (warehouse_id, product_id, bin_id)

---

## 13. Bảng Batches (Lô hàng)
**Mục đích**: Quản lý các lô hàng nhập kho

| Cột | Kiểu dữ liệu | Mô tả | Ràng buộc |
|-----|--------------|-------|-----------|
| _id | ObjectId | ID MongoDB | PRIMARY KEY |
| batch_code | string | Mã lô | UNIQUE, NOT NULL |
| product_id | string | ID sản phẩm | FOREIGN KEY → products.sku |
| product_sku | string | SKU sản phẩm | |
| quantity_imported | float | Số lượng nhập | NOT NULL |
| quantity_remaining | float | Số lượng còn lại | |
| warehouse_id | string | ID kho | FOREIGN KEY → warehouses.warehouse_id |
| import_date | UTCDateTime | Ngày nhập | NOT NULL |
| unit_price | float | Đơn giá | |
| receipt_id | string | ID phiếu nhập | |
| transaction_id | string | ID giao dịch | FOREIGN KEY → transactions.transaction_id |
| status | string | Trạng thái (active/depleted/expired) | DEFAULT 'active' |
| barcode | string | Mã vạch lô | |
| created_at | UTCDateTime | Ngày tạo | |
| updated_at | UTCDateTime | Ngày cập nhật | |

**Indexes**: batch_code, product_id, warehouse_id, transaction_id, status

---

## 14. Bảng Batch_Locations (Vị trí lô hàng)
**Mục đích**: Theo dõi vị trí cụ thể của từng lô hàng

| Cột | Kiểu dữ liệu | Mô tả | Ràng buộc |
|-----|--------------|-------|-----------|
| _id | ObjectId | ID MongoDB | PRIMARY KEY |
| batch_code | string | Mã lô | FOREIGN KEY → batches.batch_code |
| product_id | string | ID sản phẩm | FOREIGN KEY → products.sku |
| warehouse_id | string | ID kho | FOREIGN KEY → warehouses.warehouse_id |
| zone_id | string | ID zone | FOREIGN KEY → zones.zone_id |
| rack_id | string | ID rack | FOREIGN KEY → racks.rack_id |
| bin_id | string | ID bin | FOREIGN KEY → bins.bin_id |
| quantity | float | Số lượng tại vị trí | |
| import_date | UTCDateTime | Ngày nhập | |
| created_at | UTCDateTime | Ngày tạo | |

**Indexes**: batch_code, warehouse_id + bin_id (Composite)

**Unique Constraint**: (batch_code, bin_id)

---

## 15. Bảng Transactions (Giao dịch/Phiếu)
**Mục đích**: Quản lý các phiếu nhập/xuất/chuyển kho

| Cột | Kiểu dữ liệu | Mô tả | Ràng buộc |
|-----|--------------|-------|-----------|
| _id | ObjectId | ID MongoDB | PRIMARY KEY |
| transaction_id | string | Mã giao dịch | UNIQUE, NOT NULL |
| type | string | Loại phiếu (import/export/transfer) | NOT NULL |
| transaction_type | string | Loại giao dịch chi tiết | |
| warehouse_id | string | ID kho | FOREIGN KEY → warehouses.warehouse_id |
| source_warehouse_id | string | ID kho nguồn (nếu chuyển kho) | FOREIGN KEY → warehouses.warehouse_id |
| export_id | ObjectId | ID phiếu xuất liên quan | |
| supplier_id | string | ID nhà cung cấp | FOREIGN KEY → suppliers.supplier_id |
| status | int | Trạng thái (0: pending, 1: approved, 2: rejected) | DEFAULT 0 |
| created_by | string | Người tạo | FOREIGN KEY → users.user_id |
| created_at | UTCDateTime | Ngày tạo | |
| approved_by | string | Người duyệt | FOREIGN KEY → users.user_id |
| approved_at | UTCDateTime | Ngày duyệt | |
| note | string | Ghi chú | |
| details | array | Chi tiết sản phẩm | |
| total_amount | float | Tổng tiền | |
| allocations | array | Phân bổ kho (cho yêu cầu) | |

**Indexes**: transaction_id, warehouse_id, source_warehouse_id, supplier_id, status, created_at

---

## 16. Bảng Transaction_Details (Chi tiết giao dịch)
**Mục đích**: Lưu chi tiết sản phẩm trong mỗi giao dịch

| Cột | Kiểu dữ liệu | Mô tả | Ràng buộc |
|-----|--------------|-------|-----------|
| _id | ObjectId | ID MongoDB | PRIMARY KEY |
| transaction_id | string | ID giao dịch | FOREIGN KEY → transactions.transaction_id |
| product_id | string | ID sản phẩm | FOREIGN KEY → products.sku |
| product_name | string | Tên sản phẩm | |
| quantity | float | Số lượng | NOT NULL |
| unit | string | Đơn vị | |
| unit_price | float | Đơn giá | |
| total_price | float | Thành tiền | |
| batch_code | string | Mã lô (nếu có) | FOREIGN KEY → batches.batch_code |
| zone_id | string | ID zone đích | |
| rack_id | string | ID rack đích | |
| bin_id | string | ID bin đích | |

**Indexes**: transaction_id, product_id, batch_code

---

## 17. Bảng Requests (Yêu cầu hàng)
**Mục đích**: Quản lý yêu cầu hàng từ chi nhánh

| Cột | Kiểu dữ liệu | Mô tả | Ràng buộc |
|-----|--------------|-------|-----------|
| _id | ObjectId | ID MongoDB | PRIMARY KEY |
| transaction_id | string | Mã yêu cầu | UNIQUE, NOT NULL |
| transaction_type | string | Loại = "goods_request" | DEFAULT 'goods_request' |
| warehouse_id | string | ID kho yêu cầu | FOREIGN KEY → warehouses.warehouse_id |
| status | int | Trạng thái | DEFAULT 0 |
| created_by | string | Người tạo | FOREIGN KEY → users.user_id |
| created_at | UTCDateTime | Ngày tạo | |
| approved_by | string | Người duyệt | FOREIGN KEY → users.user_id |
| approved_at | UTCDateTime | Ngày duyệt | |
| details | array | Chi tiết sản phẩm yêu cầu | |

**Note**: Kế thừa từ Transactions, có thể sử dụng chung bảng hoặc tách riêng

**Indexes**: transaction_id, warehouse_id, status

---

## 18. Bảng Inventory_Sheets (Phiếu kiểm kê)
**Mục đích**: Quản lý phiếu kiểm kê tồn kho

| Cột | Kiểu dữ liệu | Mô tả | Ràng buộc |
|-----|--------------|-------|-----------|
| _id | ObjectId | ID MongoDB | PRIMARY KEY |
| sheet_code | string | Mã phiếu kiểm kê | UNIQUE, NOT NULL |
| warehouse_id | string | ID kho | FOREIGN KEY → warehouses.warehouse_id |
| created_by | string | Người tạo | FOREIGN KEY → users.user_id |
| created_at | UTCDateTime | Ngày tạo | |
| status | int | Trạng thái (0: pending, 1: approved, 2: rejected) | DEFAULT 0 |
| items | array | Danh sách sản phẩm kiểm kê | |
| count_date | UTCDateTime | Ngày kiểm kê | |
| approved_by | string | Người duyệt | FOREIGN KEY → users.user_id |
| approved_at | UTCDateTime | Ngày duyệt | |
| locations | object | Vị trí kiểm kê | |

**Indexes**: sheet_code, warehouse_id, status, count_date

---

## 19. Bảng Inventory_Sheet_Items (Chi tiết kiểm kê)
**Mục đích**: Lưu chi tiết sản phẩm trong phiếu kiểm kê

| Cột | Kiểu dữ liệu | Mô tả | Ràng buộc |
|-----|--------------|-------|-----------|
| _id | ObjectId | ID MongoDB | PRIMARY KEY |
| sheet_code | string | Mã phiếu kiểm kê | FOREIGN KEY → inventory_sheets.sheet_code |
| product_id | string | ID sản phẩm | FOREIGN KEY → products.sku |
| product_name | string | Tên sản phẩm | |
| system_quantity | float | Số lượng hệ thống | |
| actual_quantity | float | Số lượng thực tế | |
| difference | float | Chênh lệch | |
| note | string | Ghi chú | |
| zone_id | string | ID zone | |
| rack_id | string | ID rack | |
| bin_id | string | ID bin | |

**Indexes**: sheet_code, product_id

---

## 20. Bảng Inventory_Movements (Biến động tồn kho)
**Mục đích**: Ghi nhận lịch sử biến động tồn kho

| Cột | Kiểu dữ liệu | Mô tả | Ràng buộc |
|-----|--------------|-------|-----------|
| _id | ObjectId | ID MongoDB | PRIMARY KEY |
| warehouse_id | string | ID kho | FOREIGN KEY → warehouses.warehouse_id |
| product_id | string | ID sản phẩm | FOREIGN KEY → products.sku |
| transaction_id | string | ID giao dịch | FOREIGN KEY → transactions.transaction_id |
| movement_type | string | Loại biến động (in/out/adjust) | NOT NULL |
| qty | float | Số lượng (+/-) | NOT NULL |
| created_by | string | Người tạo | FOREIGN KEY → users.user_id |
| created_at | UTCDateTime | Ngày tạo | |

**Indexes**: warehouse_id + product_id (Composite), transaction_id, created_at

---

## Relationships Summary (Tóm tắt mối quan hệ)

### One-to-Many Relationships:
1. **Roles** → **Users** (1:n)
2. **Warehouses** → **Users** (1:n)
3. **Warehouses** → **Locations** (1:n)
4. **Warehouses** → **Inventory** (1:n)
5. **Categories** → **Products** (1:n)
6. **Suppliers** → **Products** (1:n)
7. **Locations** → **Zones** (1:n)
8. **Zones** → **Racks** (1:n)
9. **Racks** → **Bins** (1:n)
10. **Products** → **Inventory** (1:n)
11. **Products** → **Batches** (1:n)
12. **Batches** → **Batch_Locations** (1:n)
13. **Transactions** → **Transaction_Details** (1:n)
14. **Transactions** → **Inventory_Movements** (1:n)
15. **Users** → **Transactions** (1:n) - created_by
16. **Users** → **Inventory_Sheets** (1:n) - created_by

### Many-to-One Relationships:
1. **Inventory** → **Bins** (n:1)
2. **Batch_Locations** → **Bins** (n:1)

### Inheritance:
1. **Request** extends **Transaction** (IS-A relationship)

---

## Notes (Ghi chú)

### Embedded vs Referenced Documents:
- **Embedded**: address (trong Warehouse), category/supplier (trong Product), package_dimensions
- **Referenced**: Foreign keys giữa các bảng chính

### Array Fields:
- `conversionUnits` trong Products
- `zones` trong Locations
- `racks` trong Zones
- `shelves` trong Racks
- `details` trong Transactions
- `items` trong Inventory_Sheets
- `allocations` trong Transactions

### Status Values:
- **Users**: 0 = inactive, 1 = active
- **Warehouses**: 0 = inactive, 1 = active
- **Categories**: 0 = inactive, 1 = active
- **Suppliers**: 0 = inactive, 1 = active
- **Products**: 0 = inactive, 1 = active
- **Bins**: empty, occupied, full
- **Batches**: active, depleted, expired
- **Transactions**: 0 = pending, 1 = approved, 2 = rejected
- **Inventory_Sheets**: 0 = pending, 1 = approved, 2 = rejected

### Recommended Implementation Order:
1. Roles, Warehouse_Types
2. Warehouses, Suppliers, Categories
3. Users
4. Products
5. Locations, Zones, Racks, Bins
6. Transactions, Transaction_Details
7. Inventory, Batches, Batch_Locations
8. Requests
9. Inventory_Sheets, Inventory_Sheet_Items
10. Inventory_Movements
