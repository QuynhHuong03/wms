| **Tên use case:** | Tạo phiếu xuất kho |
|-------------------------------------|-------------------------------------|
| **Mô tả sơ lược:** | Cho phép người dùng tạo phiếu xuất kho để ghi nhận việc xuất hàng hóa ra khỏi kho. Người dùng chọn kho xuất, thêm các sản phẩm cần xuất cùng số lượng. Sau khi tạo, phiếu xuất sẽ ở trạng thái "Chờ duyệt" để chờ phê duyệt. |
| **Actor chính:** | Nhân viên kho, Quản lý kho |
| **Actor phụ:** | Không |
| **Tiền điều kiện (Pre-condition):** | - Người dùng đã đăng nhập thành công vào hệ thống<br>- Người dùng có quyền tạo phiếu xuất<br>- Tồn tại ít nhất một kho trong hệ thống<br>- Tồn tại sản phẩm trong kho |

| **Hậu điều kiện (Post-condition):** |
|-------------------------------------|
| - Phiếu xuất mới được tạo trong hệ thống với trạng thái "Chờ duyệt" |
| - Thông tin phiếu xuất được lưu vào cơ sở dữ liệu |
| - Phiếu xuất hiển thị trong danh sách phiếu xuất chờ duyệt |

| **Luồng sự kiện chính (Main flow):** |  |
|--------------------------------------|--|
| **Người dùng** | **Hệ thống** |
| 1. Truy cập chức năng "Tạo phiếu xuất" | 2. Hiển thị form tạo phiếu xuất với các trường: Loại phiếu, Kho xuất, Mô tả, Danh sách sản phẩm |
| 3. Chọn loại phiếu xuất (xuất bán, xuất hủy, xuất điều chuyển,...) | 4. Ghi nhận loại phiếu |
| 5. Chọn kho xuất từ danh sách kho | 6. Hiển thị danh sách kho có quyền xuất |
| 7. Nhập thông tin sản phẩm: mã sản phẩm, số lượng xuất |  |
| 8. Nhấn "Thêm sản phẩm vào danh sách" | 9. Kiểm tra tồn kho sản phẩm |
|  | 10. Nếu đủ hàng: Thêm sản phẩm vào danh sách phiếu xuất |
|  | 11. Hiển thị danh sách sản phẩm đã thêm với thông tin: Tên, Số lượng, Đơn vị, Tồn kho hiện tại |
| 12. Lặp lại bước 7-11 cho các sản phẩm khác (nếu có) |  |
| 13. Nhập mô tả/ghi chú cho phiếu xuất (tùy chọn) |  |
| 14. Nhấn nút "Tạo phiếu xuất" | 15. Kiểm tra tính hợp lệ của dữ liệu (các trường bắt buộc, số lượng > 0) |
|  | 16. Tạo mã phiếu xuất tự động |
|  | 17. Lưu thông tin phiếu xuất vào cơ sở dữ liệu với trạng thái "Chờ duyệt" |
|  | 18. Hiển thị thông báo "Tạo phiếu xuất thành công" cùng mã phiếu |

| **Luồng sự kiện thay thế (Alternate flow):** |
|----------------------------------------------|
| **10.1. Không đủ hàng trong kho** |
| &nbsp;&nbsp;&nbsp;&nbsp;1. Hệ thống hiển thị thông báo lỗi "Số lượng tồn kho không đủ. Tồn hiện tại: [X] [đơn vị]" |
| &nbsp;&nbsp;&nbsp;&nbsp;2. Không thêm sản phẩm vào danh sách |
| &nbsp;&nbsp;&nbsp;&nbsp;3. Quay lại bước 7 để người dùng nhập lại hoặc chọn sản phẩm khác |
| **15.1. Dữ liệu không hợp lệ khi tạo phiếu** |
| &nbsp;&nbsp;&nbsp;&nbsp;1. Hệ thống kiểm tra và phát hiện lỗi (thiếu thông tin bắt buộc, số lượng ≤ 0, chưa có sản phẩm nào) |
| &nbsp;&nbsp;&nbsp;&nbsp;2. Hiển thị thông báo lỗi cụ thể |
| &nbsp;&nbsp;&nbsp;&nbsp;3. Yêu cầu người dùng sửa lại thông tin |
| &nbsp;&nbsp;&nbsp;&nbsp;4. Quay lại bước 14 |
