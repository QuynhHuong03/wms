# Sơ đồ BPMN Hệ thống Quản lý Kho (WMS)

## Tổng quan

File `bpmn_overall_system.bpmn` mô tả toàn bộ quy trình nghiệp vụ của hệ thống Warehouse Management System (WMS) theo chuẩn BPMN 2.0.

## Cách xem sơ đồ BPMN

Bạn có thể xem và chỉnh sửa file BPMN bằng các công cụ sau:

### 1. **Camunda Modeler** (Khuyến nghị)
- Download tại: https://camunda.com/download/modeler/
- Miễn phí, hỗ trợ đầy đủ BPMN 2.0
- Có thể chỉnh sửa trực quan

### 2. **bpmn.io Online**
- Truy cập: https://demo.bpmn.io/
- Mở file BPMN trực tiếp trên trình duyệt
- Không cần cài đặt

### 3. **VS Code Extension**
- Cài đặt extension: "BPMN Visualization"
- Xem và edit ngay trong VS Code

## Cấu trúc sơ đồ

Sơ đồ được tổ chức thành 5 **Pool/Lane** chính:

### 1. **Admin Pool**
Quản lý hệ thống cấp cao:
- ✅ Quản lý người dùng (thêm, sửa, xóa, phân quyền)
- ✅ Quản lý kho (tạo kho mới, kích hoạt/vô hiệu hóa)
- ✅ Cấu hình hệ thống (backup, restore, đồng bộ)

### 2. **Warehouse Manager Pool**
Quản lý các hoạt động kho:

#### a) **Quản lý sản phẩm**
- Thêm sản phẩm mới
- Cập nhật thông tin sản phẩm
- Quản lý danh mục
- Quản lý nhà cung cấp

#### b) **Quản lý phiếu nhập**
1. Tạo phiếu nhập từ nhà cung cấp
2. Tạo phiếu nhập điều chuyển
3. Duyệt phiếu nhập
4. Nhận hàng và xếp vào vị trí
5. Tạo mã barcode cho batch

#### c) **Quản lý phiếu xuất**
1. Tạo phiếu xuất
2. Duyệt phiếu xuất
3. Lấy hàng từ vị trí
4. Xác nhận xuất kho
5. Cập nhật tồn kho

#### d) **Kiểm kê kho**
1. Tạo phiếu kiểm kê
2. Kiểm đếm thực tế
3. So sánh số liệu
4. Duyệt phiếu kiểm kê
5. Điều chỉnh tồn kho

#### e) **Quản lý vị trí**
- Tạo vị trí/bin mới
- Cập nhật thông tin vị trí
- Xếp hàng vào vị trí
- Di chuyển hàng hóa

#### f) **Xử lý yêu cầu chi nhánh**
1. Nhận yêu cầu từ chi nhánh
2. Duyệt yêu cầu
3. Phân bổ hàng hóa
4. Tạo phiếu điều chuyển

### 3. **Warehouse Staff Pool**
Nhân viên thực thi công việc kho:
- 📦 Nhận hàng nhập kho (kiểm tra, quét barcode, cập nhật)
- 📤 Lấy hàng xuất kho (tìm vị trí, quét, xác nhận)
- 📍 Sắp xếp hàng hóa (xếp vào vị trí, cập nhật location)
- 🔢 Kiểm đếm kho (đếm thực tế, ghi nhận chênh lệch)

### 4. **Branch Manager Pool**
Quản lý kho chi nhánh:

#### a) **Tạo yêu cầu hàng**
1. Kiểm tra tồn kho chi nhánh
2. Tạo phiếu yêu cầu hàng từ kho tổng
3. Chờ phê duyệt từ kho tổng
4. Nhận hàng điều chuyển

#### b) **Theo dõi tồn kho**
- Xem số lượng tồn
- Cảnh báo hết hàng

#### c) **Báo cáo**
- Xuất nhập tồn
- Doanh thu
- Hàng tồn kho

### 5. **System Pool**
Các dịch vụ hệ thống chạy song song:
- 🔐 Xác thực và phân quyền (login, session, kiểm tra quyền)
- 📊 Quản lý Barcode (tạo mã, quét, tra cứu batch)
- 📈 Dashboard và báo cáo (thống kê, top sản phẩm, xuất nhập tồn)
- 🔄 Đồng bộ dữ liệu (backup, replication, data integrity)

## Ký hiệu BPMN được sử dụng

| Ký hiệu | Tên | Ý nghĩa |
|---------|-----|---------|
| ⚪ (xanh) | Start Event | Điểm bắt đầu quy trình |
| ⚪ (đỏ) | End Event | Điểm kết thúc quy trình |
| ◇ | Exclusive Gateway | Lựa chọn một trong nhiều nhánh |
| ◇ | Parallel Gateway | Thực hiện song song nhiều nhánh |
| ▭ | Task | Một công việc đơn lẻ |
| ▭+ | Sub-Process | Quy trình con có thể mở rộng |
| → | Sequence Flow | Luồng tuần tự |

## Luồng nghiệp vụ chính

### Luồng nhập kho (Receipt Flow)
```
Tạo phiếu nhập → Duyệt → Nhận hàng → Xếp vào vị trí → Tạo barcode → Cập nhật tồn kho
```

### Luồng xuất kho (Export Flow)
```
Tạo phiếu xuất → Duyệt → Lấy hàng → Xác nhận xuất → Cập nhật tồn kho
```

### Luồng kiểm kê (Inventory Check Flow)
```
Tạo phiếu → Kiểm đếm → So sánh → Duyệt → Điều chỉnh tồn kho
```

### Luồng yêu cầu chi nhánh (Branch Request Flow)
```
Chi nhánh tạo yêu cầu → Kho tổng duyệt → Phân bổ → Tạo phiếu điều chuyển → Chi nhánh nhận hàng
```

## Vai trò và quyền hạn

| Vai trò | Mã | Quyền hạn chính |
|---------|-----|-----------------|
| Admin | 1 | Quản lý toàn hệ thống, user, kho |
| Warehouse Manager | 2 | Quản lý kho, duyệt phiếu, báo cáo |
| Warehouse Staff | 3 | Nhận/xuất hàng, kiểm đếm, sắp xếp |
| Branch Manager | 4 | Yêu cầu hàng, xem tồn kho chi nhánh |

## Tích hợp với hệ thống

Sơ đồ BPMN này tương ứng với:
- **Controllers**: `controller/cReceipt.php`, `cExport.php`, `cInventorySheet.php`, v.v.
- **Sequence Diagrams**: Các file `.puml` trong thư mục `docs/`
- **Database**: Cấu trúc trong `docs/database_tables.md`

## Mở rộng và tùy chỉnh

Để thêm quy trình mới:
1. Mở file BPMN trong Camunda Modeler
2. Thêm Task/Gateway mới vào Pool tương ứng
3. Kết nối với Sequence Flow
4. Lưu file và commit vào Git

## Liên hệ

Nếu có câu hỏi hoặc đề xuất cải thiện sơ đồ, vui lòng tạo issue hoặc liên hệ team.

---

**Lưu ý**: Sơ đồ này mô tả quy trình nghiệp vụ tổng quan. Để biết chi tiết triển khai kỹ thuật, xem các sequence diagrams và source code.
