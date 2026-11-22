# Clinical Workflow & Paraclinical Service Contract

> Tài liệu chuẩn hóa state machine và danh mục dịch vụ cận lâm sàng (CLS) cho **Cổng tác nghiệp nhân viên phòng khám (Eden Health)**.
> Đồng bộ trực tiếp với `src/includes/clinical-state-contract.php`, `src/database/migrations/001_dichvu_can_lam_sang.sql` và `src/database/migrations/002_seed_dichvu_can_lam_sang.sql`.

---

## 1. Luồng nghiệp vụ tổng thể (End-to-End)

```text
1. Lễ tân / Tiếp nhận (check-in.html)
   └─ Check-in lịch hẹn hôm nay → tạo dòng trong `hangdoikham` (trangThai = 'Đang chờ')
          ↓
2. Điều dưỡng sàng lọc (dieu-duong.html) [tùy quy trình phòng khám]
   └─ Tiếp nhận ca đã check-in → đo sinh hiệu, ghi dị ứng/cảnh báo → chuyển hàng đợi bác sĩ
          ↓
3. Bác sĩ khám & Chỉ định (ho-so-benh-an.html)
   ├─ Tạo/mở `hosobenhan` (trangThaiQuyTrinh = 'Đang khám')
   ├─ Nếu có chỉ định CLS → tạo chỉ định + đẩy vào `hangdoi_dichvu` theo từng `nhomDichVu`
   └─ Nếu không cần CLS → kết luận, kê đơn (chưa trừ tồn) và hoàn tất hồ sơ
          ↓
4. Bộ phận Cận lâm sàng (can-lam-sang.html?nhom=...)
   ├─ Tiếp nhận từ `hangdoi_dichvu` ('Đang chờ' → 'Đang thực hiện')
   └─ Nhập kết quả + đính kèm ảnh/tệp → trả kết quả ('Có kết quả' / 'Chờ duyệt')
          ↓
5. Bác sĩ xem/duyệt kết quả & Kết luận (ho-so-benh-an.html)
   ├─ Duyệt từng kết quả CLS ('Đã duyệt') hoặc yêu cầu làm lại
   ├─ Chỉ được hoàn tất hồ sơ khi mọi chỉ định bắt buộc đều đã 'Đã duyệt'
   └─ Kê đơn thuốc (trạng thái 'Chờ phát', KHÔNG trừ `thuoc.soLuongTon`) & Hoàn tất hồ sơ
          ↓
6. Dược sĩ phát thuốc (phat-thuoc.html)
   └─ Nhận đơn 'Chờ phát' → đối chiếu tồn kho → phát đủ hoặc một phần → trừ tồn nguyên tử
          ↓
7. Thu ngân & Hóa đơn (thu-ngan.html)
   └─ Tổng hợp phí khám + dịch vụ CLS (không hủy) + thuốc thực phát → thanh toán & in hóa đơn
          ↓
8. Cổng bệnh nhân (ho-so-benh-an-ca-nhan.html)
   └─ Chỉ xem được hồ sơ 'Đã hoàn thành', kết quả CLS 'Đã duyệt', trạng thái phát thuốc & hóa đơn
```

---

## 2. State Machines & Bảng Transition Hợp Lệ

### 2.1. Hồ sơ bệnh án (`hosobenhan`)

Để tương thích ngược hoàn toàn với 100% API hiện hữu (`get-pending-records.php`, `get-completed-records.php`, `get-medical-records.php`), cột `hosobenhan.trangThai` giữ nguyên 2 giá trị legacy (`'Chưa hoàn thành'`, `'Đã hoàn thành'`), đồng thời mở rộng bằng cột `trangThaiQuyTrinh` ở Task 2:

| `trangThaiQuyTrinh` | Ánh xạ `hosobenhan.trangThai` (legacy) | Ý nghĩa |
|---|---|---|
| `Đang khám` | `Chưa hoàn thành` | Bác sĩ vừa mở hồ sơ và đang khám lâm sàng |
| `Chờ thực hiện CLS` | `Chưa hoàn thành` | Đã phát sinh chỉ định CLS chưa hoàn thành |
| `Chờ kết quả` | `Chưa hoàn thành` | Có chỉ định đang thực hiện / chờ trả hết kết quả |
| `Chờ bác sĩ kết luận` | `Chưa hoàn thành` | Toàn bộ chỉ định hoạt động đã có kết quả hoặc đã duyệt, chờ bác sĩ đọc & kết luận |
| `Hoàn tất` | `Đã hoàn thành` | Hồ sơ đã đóng, bác sĩ đã kết luận lâm sàng |
| `Hủy` | `Chưa hoàn thành` + `isDeleted = 1` (hoặc đánh dấu hủy) | Hồ sơ bị hủy trước khi hoàn tất, không hiển thị cho bệnh nhân |

#### Bảng transition hợp lệ (`hosobenhan.trangThaiQuyTrinh`)

| Trạng thái nguồn (`from`) | Trạng thái đích (`to`) | Actor / Permission | Điều kiện kiểm tra (Guard) | Tác dụng phụ (Side effects) |
|---|---|---|---|---|
| `(Khởi tạo)` | `Đang khám` | `bacsi` | Lịch khám hợp lệ và `lichkham.trangThai <> 'Hủy'` | Tạo bản ghi `hosobenhan` với `trangThai = 'Chưa hoàn thành'` |
| `Đang khám` | `Đang khám` | `bacsi` | Lưu nháp nội dung khám / đơn thuốc | Cập nhật `chanDoan`, `dieuTri`, `ghiChu`, `donthuoc` (`Đã kê`), không trừ tồn |
| `Đang khám` | `Chờ thực hiện CLS` | `bacsi` | Có ít nhất 1 chỉ định CLS mới (`Mới tạo` / `Đang chờ`) | Đẩy chi tiết chỉ định sang `hangdoi_dichvu` |
| `Chờ thực hiện CLS` | `Chờ kết quả` | Hệ thống / `cls.*.perform` | Ít nhất 1 chỉ định chuyển `Đang thực hiện` hoặc chưa có đủ kết quả | Ghi nhận tiến độ thực hiện |
| `Chờ thực hiện CLS` | `Chờ bác sĩ kết luận` | Hệ thống / `cls.*.perform` | Mọi chỉ định không bị `Hủy` đã ở `Có kết quả` hoặc `Đã duyệt` | Thông báo bác sĩ có kết quả CLS |
| `Chờ kết quả` | `Chờ thực hiện CLS` | `bacsi` | Bác sĩ tạo thêm chỉ định CLS mới | Đẩy thêm vào `hangdoi_dichvu` |
| `Chờ kết quả` | `Chờ bác sĩ kết luận` | Hệ thống / `cls.*.perform` | Mọi chỉ định không bị `Hủy` đã ở `Có kết quả` hoặc `Đã duyệt` | Thông báo bác sĩ xem kết quả |
| `Chờ bác sĩ kết luận` | `Chờ thực hiện CLS` | `bacsi` | Bác sĩ chỉ định bổ sung hoặc yêu cầu làm lại CLS | Đưa chỉ định tương ứng về hàng đợi |
| `Chờ bác sĩ kết luận` | `Đang khám` | `bacsi` | Bác sĩ mở lại khám lâm sàng trước khi đóng hồ sơ | Cho phép chỉnh nháp tiếp |
| `Đang khám` | `Hoàn tất` | `bacsi` | Đạt **Điều kiện hoàn tất hồ sơ** (Mục 2.1.1) | Đóng hồ sơ, sync lịch khám/hàng đợi khám, đẩy đơn thuốc sang `Chờ phát` |
| `Chờ bác sĩ kết luận` | `Hoàn tất` | `bacsi` | Đạt **Điều kiện hoàn tất hồ sơ** (Mục 2.1.1) | Đóng hồ sơ, sync lịch khám/hàng đợi khám, đẩy đơn thuốc sang `Chờ phát` |
| `Đang khám`, `Chờ thực hiện CLS`, `Chờ kết quả`, `Chờ bác sĩ kết luận` | `Hủy` | `bacsi` / `quantri` | Bắt buộc có `lyDoHuy`; chưa có kết quả hoặc xử lý hủy toàn bộ chỉ định chưa thực hiện | Hủy chỉ định/hàng đợi chưa thực hiện và đơn thuốc `Đã kê` |

#### 2.1.1. Điều kiện hoàn tất hồ sơ (`Hoàn tất` / `Đã hoàn thành`)

Khi bác sĩ gọi hoàn tất hồ sơ, backend bắt buộc thực hiện trong **database transaction** (khóa dòng hồ sơ với `SELECT ... FOR UPDATE`):

1. **Thông tin lâm sàng bắt buộc:** `chanDoan` và `dieuTri` đều khác rỗng.
2. **Ràng buộc chỉ định CLS:**
   - Mọi chi tiết chỉ định có `batBuoc = 1` và `trangThai <> 'Hủy'` **BẮT BUỘC** phải đạt `trangThai = 'Đã duyệt'`.
   - Nếu vẫn còn chỉ định không bắt buộc (`batBuoc = 0`) đang ở `Mới tạo`, `Đang chờ`, `Đang thực hiện` hoặc `Có kết quả`, API trả lỗi `422` yêu cầu bác sĩ duyệt kết quả hoặc hủy chỉ định (có lý do) trước khi hoàn tất, tránh bỏ sót dịch vụ trong hàng đợi/hóa đơn.
3. **Tính bất biến đơn thuốc:** Nếu hồ sơ có đơn thuốc kèm theo (`>= 1` thuốc), chuyển `donthuoc.trangThaiPhat` từ `Đã kê` sang `Chờ phát`. **Tuyệt đối KHÔNG trừ `thuoc.soLuongTon` tại bước này.**
4. **Idempotency (chống đua request):** Nếu hồ sơ đã ở trạng thái `Hoàn tất` (`Đã hoàn thành`), request hoàn tất trùng/đua không tạo thông báo/email lần hai và không sinh biến động trạng thái lặp.
5. **Đồng bộ ngoại vi:** Cập nhật `lichkham.trangThai = 'Hoàn thành'` và `hangdoikham.trangThai = 'Hoàn thành'` (nếu không ở trạng thái `Hủy`), đồng thời kích hoạt thông báo/email hoàn tất hồ sơ.

---

### 2.2. Hàng đợi khám & Sàng lọc điều dưỡng (`hangdoikham`, `sang_loc_dieu_duong`)

| Thực thể | Trạng thái hợp lệ | Transition cho phép | Ghi chú |
|---|---|---|---|
| `hangdoikham` | `Đang chờ`, `Đang khám`, `Hoàn thành`, `Bỏ lỡ`, `Hủy` | `Đang chờ → Đang khám \| Bỏ lỡ \| Hủy`<br>`Đang khám → Hoàn thành \| Hủy`<br>`Bỏ lỡ → Đang chờ \| Hủy` | Giữ nguyên theo ENUM hiện tại |
| `sang_loc_dieu_duong` | `Chờ sàng lọc`, `Đã sàng lọc`, `Đã chuyển bác sĩ` | `Chờ sàng lọc → Đã sàng lọc → Đã chuyển bác sĩ`<br>`Chờ sàng lọc → Đã chuyển bác sĩ` | Điều dưỡng nhập dấu hiệu sinh tồn, dị ứng/cảnh báo; bác sĩ xem read-only trong hồ sơ |

**Quy tắc điều dưỡng (`dieu-duong.html`):**
- Chỉ hiển thị bệnh nhân có `hangdoikham.ngay = CURDATE()` và `hangdoikham.trangThai = 'Đang chờ'` chưa ở trạng thái `Đã chuyển bác sĩ`.
- Ghi log `maNhanVienSangLoc`, `thoiGianSangLoc`, `thoiGianChuyenBacSi` khi chuyển sang hàng đợi bác sĩ.

---

### 2.3. Chỉ định cận lâm sàng (`chi_dinh_chi_tiet`)

| Trạng thái nguồn (`from`) | Trạng thái đích (`to`) | Actor / Permission | Điều kiện / Audit bắt buộc |
|---|---|---|---|
| `(Khởi tạo)` | `Mới tạo` | `bacsi` | Thuộc hồ sơ đang mở (`<> 'Hoàn tất'`, `<> 'Hủy'`), dịch vụ có `isActive = 1` |
| `Mới tạo` | `Đang chờ` | `bacsi` / Hệ thống | Tạo bản ghi tương ứng trong `hangdoi_dichvu` theo `nhomDichVu` |
| `Mới tạo`, `Đang chờ` | `Hủy` | `bacsi` | Bắt buộc có `lyDoHuy`, `nguoiHuy`; đồng bộ `hangdoi_dichvu.trangThai = 'Hủy'` |
| `Đang chờ` | `Đang thực hiện` | Nhân viên đúng quyền `dichvu.quyenThucHien` | Chỉ nhận ca thuộc nhóm dịch vụ được phân quyền |
| `Đang thực hiện` | `Có kết quả` | Nhân viên đúng quyền `dichvu.quyenThucHien` | Đã có bản ghi kết quả gửi duyệt (`ket_qua_can_lam_sang.trangThai = 'Chờ duyệt'`) |
| `Có kết quả` | `Đã duyệt` | `bacsi` (bác sĩ chỉ định hoặc có quyền duyệt) | Ghi nhận `nguoiDuyet`, `thoiGianDuyet`; mở khóa hiển thị cho bệnh nhân |
| `Có kết quả` | `Đang thực hiện` | `bacsi` | Yêu cầu KTV làm lại/bổ sung; bắt buộc có `lyDoYeuCauLamLai`; kết quả chuyển `Yêu cầu làm lại` |

---

### 2.4. Hàng đợi dịch vụ cận lâm sàng (`hangdoi_dichvu`)

Tách biệt hoàn toàn khỏi `hangdoikham` (vốn gắn với bác sĩ). STT được cấp theo `(nhomDichVu, ngay, soThuTu)`.

| Trạng thái nguồn (`from`) | Trạng thái đích (`to`) | Actor / Permission | Tác dụng phụ |
|---|---|---|---|
| `(Khởi tạo)` | `Đang chờ` | `bacsi` / `staff.reception` | Sinh `soThuTu` an toàn bằng transaction + `FOR UPDATE` theo `(nhomDichVu, ngay)` |
| `Đang chờ` | `Đang thực hiện` | KTV nhóm tương ứng | Ghi `maNhanVienThucHien`, `thoiGianBatDau`; chỉ định chuyển `Đang thực hiện` |
| `Đang chờ` | `Bỏ lỡ` | KTV nhóm tương ứng | Đánh dấu bệnh nhân vắng khi gọi |
| `Bỏ lỡ` | `Đang chờ` | KTV / Lễ tân | Đưa lại vào danh sách chờ trong ngày |
| `Đang thực hiện` | `Hoàn thành` | KTV nhóm tương ứng | Khi trả kết quả (`Chờ duyệt`); ghi `thoiGianHoanThanh` |
| `Đang chờ`, `Bỏ lỡ` | `Hủy` | `bacsi` / KTV | Chỉ khi chỉ định tương ứng bị `Hủy` (có lý do) |

---

### 2.5. Kết quả cận lâm sàng (`ket_qua_can_lam_sang`)

| Trạng thái nguồn (`from`) | Trạng thái đích (`to`) | Actor / Permission | Quy tắc nghiệp vụ |
|---|---|---|---|
| `(Khởi tạo)` | `Nháp` | KTV nhóm tương ứng | KTV lưu tạm chỉ số/nhận xét/tệp đính kèm trước khi gửi |
| `Nháp` | `Chờ duyệt` | KTV nhóm tương ứng | Ghi nhận người thực hiện & thời điểm trả kết quả; chuyển chỉ định sang `Có kết quả` |
| `Yêu cầu làm lại` | `Chờ duyệt` | KTV nhóm tương ứng | Lưu lịch sử phiên bản chỉnh sửa kết quả (`ket_qua_lich_su`) |
| `Chờ duyệt` | `Đã duyệt` | `bacsi` | Khóa kết quả không cho KTV tự sửa; **cho phép hiển thị cho bệnh nhân** |
| `Chờ duyệt` | `Yêu cầu làm lại` | `bacsi` | Bắt buộc có `lyDoTuChoi`; trả chỉ định về `Đang thực hiện` và mở lại hàng đợi nếu cần |

---

### 2.6. Đơn thuốc & Phát thuốc (`donthuoc`, `phieu_phat_thuoc`)

Cột `donthuoc.trangThaiPhat` quản lý vòng đời cấp phát thuốc tại quầy dược:

| Trạng thái nguồn (`from`) | Trạng thái đích (`to`) | Actor / Permission | Quy tắc nghiệp vụ & Tồn kho |
|---|---|---|---|
| `(Khởi tạo)` | `Đã kê` | `bacsi` | Lưu đơn nháp hoặc khi hồ sơ chưa `Hoàn tất`. **KHÔNG trừ `thuoc.soLuongTon`.** |
| `Đã kê` | `Chờ phát` | `bacsi` (khi hoàn tất hồ sơ) | Đơn sẵn sàng tại hàng đợi quầy dược (`phat-thuoc.html`). **Vẫn KHÔNG trừ tồn kho.** |
| `Chờ phát` | `Đã phát một phần` | `duocsi` (`pharmacy.dispense`) | Dược sĩ phát `0 < soLuongThucPhat < soLuongKe` (có thể do thiếu tồn hoặc bệnh nhân nhận trước). Trừ tồn nguyên tử theo số thực phát. |
| `Chờ phát` | `Đã phát đủ` | `duocsi` (`pharmacy.dispense`) | Dược sĩ phát đủ 100% số lượng kê của mọi thuốc. Trừ tồn nguyên tử theo số thực phát. |
| `Đã phát một phần` | `Đã phát đủ` | `duocsi` (`pharmacy.dispense`) | Phát nốt phần còn lại (`soLuongConLai`). Trừ tồn phần phát thêm. |
| `Đã kê`, `Chờ phát` | `Hủy` | `bacsi` / `duocsi` | Hủy đơn trước khi phát, bắt buộc có lý do; không biến động kho. |

**Quy tắc an toàn phát thuốc:**
- Không bao giờ cho phép `thuoc.soLuongTon < 0` (khóa dòng `thuoc` bằng `SELECT ... FOR UPDATE`).
- Không cho phát vượt số lượng còn lại của dòng đơn (`soLuongDaPhat + soLuongPhatLanNay <= soLuongKe`).
- Chống gửi lặp bằng `clientRequestId` (`UNIQUE` trên `phieu_phat_thuoc`): request trùng trả về phiếu phát cũ, không trừ kho lần hai.

---

## 3. Catalog Dịch Vụ Cận Lâm Sàng (`dichvu_can_lam_sang`)

### 3.1. Nhóm dịch vụ & Quyền thực hiện

| `nhomDichVu` | `tenNhom` | `quyenThucHien` | URL màn hình dùng chung | Nhóm nhân viên phụ trách |
|---|---|---|---|---|
| `xet-nghiem` | Xét nghiệm | `cls.xet_nghiem.perform` | `can-lam-sang.html?nhom=xet-nghiem` | KTV Xét nghiệm |
| `chan-doan-hinh-anh` | Chẩn đoán hình ảnh | `cls.cdha.perform` | `can-lam-sang.html?nhom=chan-doan-hinh-anh` | KTV Chẩn đoán hình ảnh |
| `noi-soi` | Nội soi | `cls.noi_soi.perform` | `can-lam-sang.html?nhom=noi-soi` | KTV Nội soi / ĐD thủ thuật |

### 3.2. Cấu trúc bảng `dichvu_can_lam_sang`

| Cột | Kiểu | Mô tả |
|---|---|---|
| `maDichVu` | `INT(11) AI PK` | Khóa chính nội bộ |
| `maCode` | `VARCHAR(32) UNIQUE` | Mã nghiệp vụ ổn định (ví dụ: `XN_CTM`, `CDHA_XQ_NGUC`, `NS_DA_DAY`) |
| `tenDichVu` | `VARCHAR(150)` | Tên dịch vụ cận lâm sàng |
| `nhomDichVu` | `ENUM('xet-nghiem','chan-doan-hinh-anh','noi-soi')` | Nhóm chuyên môn |
| `tenNhom` | `VARCHAR(100)` | Tên hiển thị nhóm chuyên môn |
| `quyenThucHien` | `VARCHAR(64)` | Mã permission yêu cầu để tiếp nhận & trả kết quả |
| `donViTinh` | `VARCHAR(30)` | Đơn vị tính (`Lần`, `Mẫu`, `Phim`, `Bộ`) |
| `gia` | `DECIMAL(12,2)` | Đơn giá dịch vụ (VND) |
| `thoiGianDuKienPhut` | `SMALLINT UNSIGNED` | Thời gian dự kiến thực hiện (phút) |
| `chuanBiTruocKhiLam` | `TEXT NULL` | Hướng dẫn chuẩn bị cho bệnh nhân (nhịn ăn, nhịn tiểu...) |
| `mauKetQuaMacDinh` | `LONGTEXT (JSON) NULL` | Template chỉ số / trường cấu trúc mặc định khi KTV nhập kết quả |
| `moTa` | `TEXT NULL` | Mô tả phạm vi kỹ thuật |
| `isActive` | `TINYINT(1)` | `1` = đang cung cấp (bác sĩ chọn được), `0` = ngừng áp dụng |
| `createdAt`, `updatedAt` | `DATETIME` | Thời điểm tạo / cập nhật |

### 3.3. Danh sách Seed Demo (`002_seed_dichvu_can_lam_sang.sql`)

| Mã (`maCode`) | Tên dịch vụ | Nhóm (`nhomDichVu`) | Quyền (`quyenThucHien`) | Giá (VND) | Trạng thái |
|---|---|---|---|---:|---|
| `XN_CTM` | Tổng phân tích tế bào máu ngoại vi | `xet-nghiem` | `cls.xet_nghiem.perform` | 90,000 | Hoạt động (`1`) |
| `XN_SHM_CO_BAN` | Sinh hóa máu cơ bản (Glucose, Ure, Creatinin, AST, ALT) | `xet-nghiem` | `cls.xet_nghiem.perform` | 180,000 | Hoạt động (`1`) |
| `XN_MO_MAU` | Bilan Lipid máu (Cholesterol, Triglycerid, HDL, LDL) | `xet-nghiem` | `cls.xet_nghiem.perform` | 150,000 | Hoạt động (`1`) |
| `XN_NUOC_TIEU` | Tổng phân tích nước tiểu 10 thông số | `xet-nghiem` | `cls.xet_nghiem.perform` | 70,000 | Hoạt động (`1`) |
| `XN_HBA1C` | Định lượng HbA1c | `xet-nghiem` | `cls.xet_nghiem.perform` | 160,000 | Hoạt động (`1`) |
| `XN_CRP` | Định lượng CRP (chỉ số viêm) | `xet-nghiem` | `cls.xet_nghiem.perform` | 120,000 | Hoạt động (`1`) |
| `XN_LEGACY_RAPID` | Test nhanh kháng nguyên (mẫu lưu trữ ngừng dùng) | `xet-nghiem` | `cls.xet_nghiem.perform` | 100,000 | Ngừng dùng (`0`) |
| `CDHA_XQ_NGUC` | Chụp X-quang ngực thẳng | `chan-doan-hinh-anh` | `cls.cdha.perform` | 150,000 | Hoạt động (`1`) |
| `CDHA_XQ_KHOP` | Chụp X-quang xương khớp (2 tư thế) | `chan-doan-hinh-anh` | `cls.cdha.perform` | 180,000 | Hoạt động (`1`) |
| `CDHA_SA_BUNG` | Siêu âm ổ bụng tổng quát | `chan-doan-hinh-anh` | `cls.cdha.perform` | 200,000 | Hoạt động (`1`) |
| `CDHA_SA_TUYEN_GIAP` | Siêu âm tuyến giáp | `chan-doan-hinh-anh` | `cls.cdha.perform` | 180,000 | Hoạt động (`1`) |
| `CDHA_SA_TIM` | Siêu âm tim Doppler màu | `chan-doan-hinh-anh` | `cls.cdha.perform` | 350,000 | Hoạt động (`1`) |
| `CDHA_ECG` | Điện tâm đồ (ECG 12 chuyển đạo) | `chan-doan-hinh-anh` | `cls.cdha.perform` | 100,000 | Hoạt động (`1`) |
| `NS_TMH` | Nội soi Tai - Mũi - Họng ống mềm | `noi-soi` | `cls.noi_soi.perform` | 250,000 | Hoạt động (`1`) |
| `NS_DA_DAY` | Nội soi thực quản - dạ dày - tá tràng | `noi-soi` | `cls.noi_soi.perform` | 650,000 | Hoạt động (`1`) |
| `NS_DA_DAY_HP` | Nội soi dạ dày kèm test nhanh H.pylori (CLO test) | `noi-soi` | `cls.noi_soi.perform` | 750,000 | Hoạt động (`1`) |
| `NS_DAI_TRANG` | Nội soi đại trực tràng | `noi-soi` | `cls.noi_soi.perform` | 850,000 | Hoạt động (`1`) |
