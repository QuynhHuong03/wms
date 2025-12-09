# 📍 Bin Location Management

Hệ thống quản lý vị trí xếp hàng trong kho với thuật toán gợi ý thông minh.

## 🎯 Thuật toán gợi ý nâng cao

Hệ thống sử dụng **rule-based algorithm** đánh giá chất lượng vị trí dựa trên nhiều tiêu chí, **ưu tiên cao nhất cho bin đã chứa cùng loại sản phẩm**.

### Tiêu chí đánh giá (100 điểm)

1. **Cùng loại sản phẩm** (50 điểm) - **ƯU TIÊN CAO NHẤT** ⭐
   - Bin đã chứa cùng sản phẩm = +50 điểm
   - Cho phép xếp chồng thêm để tối ưu không gian
   - Tránh lãng phí bin bằng cách tập trung sản phẩm

2. **Tối ưu dung lượng** (25 điểm)
   - **Với bin cùng sản phẩm:**
     - 85-100% = 25 điểm (tốt nhất - gần đầy, tối ưu)
     - 70-85% = 20 điểm (tốt)
     - 50-70% = 15 điểm (chấp nhận được)
   - **Với bin trống:**
     - 60-85% = 25 điểm (vừa đủ, không lãng phí)
     - 40-60% = 20 điểm (hợp lý)
     - >85% = 15 điểm (ít chỗ cho sau)

3. **Vị trí zone** (15 điểm)
   - Zone thấp hơn = dễ tiếp cận hơn
   - Zone 1 > Zone 2 > Zone 3

4. **Vị trí rack/bin** (7 điểm)
   - Rack, bin thấp hơn = dễ lấy hơn
   - Ưu tiên vị trí thuận tiện

5. **Khả năng xếp chồng** (3 điểm)
   - Sản phẩm có thể xếp chồng = tận dụng chiều cao
   - Tính toán số tầng có thể xếp

### Tính năng nâng cao

- ✅ **Nhận biết bin đã chứa cùng sản phẩm** - ưu tiên tối đa
- ✅ **Tính toán xếp chồng thông minh:**
  - Kiểm tra thuộc tính `stackable` của sản phẩm
  - Tính số lượng có thể xếp thêm dựa trên `max_stack_height`
  - Tính số tầng hiện tại và còn lại
- ✅ **Tránh trộn lẫn sản phẩm:**
  - Không gợi ý bin đã chứa sản phẩm khác
  - Tối ưu quản lý tồn kho theo từng bin
- ✅ Tự động tính toán kích thước theo đơn vị (thùng, cái, v.v.)
- ✅ Kiểm tra vừa vặn (kích thước sản phẩm vs bin)
- ✅ Tính dung lượng còn lại chính xác
- ✅ Top 5 gợi ý tốt nhất với quality score

## 📂 Files

- `recalculate_bin_capacities.php` - Tính toán lại dung lượng các bin trong kho

## 🚀 Sử dụng

Thuật toán được tích hợp trong:
- `view/page/manage/receipts/locate/get_recommendations.php` - API gợi ý vị trí
- `view/page/manage/receipts/locate/index.php` - Giao diện phân bổ sản phẩm

Khi người dùng nhấn nút "Gợi ý vị trí tối ưu (AI)", hệ thống:
1. Lấy thông tin sản phẩm (kích thước, số lượng, đơn vị, stackable, max_stack_height)
2. Tìm tất cả bins trong kho và kiểm tra:
   - **Bin đã chứa cùng sản phẩm** → Nhóm 1
   - Bin trống có thể tích lớn → Nhóm 2
   - Bin có thể xếp thêm để gần đầy → Nhóm 3
   - Loại bỏ bin chứa sản phẩm khác (tránh trộn lẫn)
3. Tính toán số lượng có thể xếp thêm:
   - Nếu bin đã có cùng sản phẩm: tính số tầng còn có thể xếp
   - Nếu bin trống: tính dung lượng tối đa
   - Xem xét cả không gian vật lý và volume capacity
4. Phân loại và sắp xếp theo 3 nhóm:
   - **Nhóm 1:** 5 bin cùng sản phẩm (ưu tiên số lượng xếp được nhiều nhất)
   - **Nhóm 2:** 5 bin còn nhiều thể tích (ưu tiên % trống cao nhất)
   - **Nhóm 3:** 5 bin có thể xếp thêm để full (ưu tiên 80-95% sau khi xếp)
5. Trả về 3 danh sách riêng biệt với lý do ưu tiên

## 📊 Kết quả

- Độ chính xác: **Rất cao** (logic nghiệp vụ tối ưu + ưu tiên cùng sản phẩm)
- Tốc độ: **Nhanh** (không cần ML inference)
- Bảo trì: **Dễ dàng** (logic rõ ràng, dễ điều chỉnh)
- Hiệu quả: **Tối ưu không gian** (xếp chồng thông minh + tập trung sản phẩm)

## 💡 Ví dụ

**Scenario 1: Nhập 30 thùng sản phẩm A đã có trong kho**

**Nhóm 1: Bin cùng sản phẩm** ⭐
- Z1-R1-B2: Có 10/30 thùng, xếp thêm 20 → Full (100%)
- Z1-R2-B5: Có 15/30 thùng, xếp thêm 15 → Full (100%)
- Z2-R1-B1: Có 8/30 thùng, xếp thêm 22 → Full (100%)

**Nhóm 2: Bin còn nhiều thể tích** 📦
- Z1-R1-B8: Trống (0% → 60%), có thể xếp 30 thùng
- Z1-R3-B2: Trống (0% → 60%), có thể xếp 30 thùng
- Z2-R2-B3: 5% đã dùng, có thể xếp 30 thùng

**Nhóm 3: Bin có thể đầy** ✅
- Z1-R2-B7: 45% → 85% (gần đầy, tối ưu)
- Z2-R1-B4: 50% → 90% (gần đầy)
- Z1-R3-B5: 40% → 80% (vừa đủ)

---

**Scenario 2: Nhập 50 cái sản phẩm B mới (chưa có trong kho)**

**Nhóm 1: Bin cùng sản phẩm** ⭐
- *(Trống - chưa có sản phẩm B trong kho)*

**Nhóm 2: Bin còn nhiều thể tích** 📦
- Z1-R1-B10: Trống hoàn toàn (0% → 45%)
- Z1-R2-B12: Trống hoàn toàn (0% → 45%)
- Z2-R1-B5: Trống (0% → 45%)

**Nhóm 3: Bin có thể đầy** ✅
- Z1-R3-B8: 35% → 80% (tối ưu)
- Z2-R2-B6: 40% → 85% (gần đầy)
- Z1-R1-B15: 45% → 90% (gần full)
