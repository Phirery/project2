# Implementation Plan: Hoàn thiện cổng tác nghiệp nhân viên phòng khám

## Overview

Mở rộng luồng hiện có từ tiếp nhận/check-in đến kết thúc điều trị thành một luồng có thể theo dõi: điều dưỡng sàng lọc (nếu áp dụng) → bác sĩ khám và ra chỉ định → bộ phận cận lâm sàng thực hiện/trả kết quả → bác sĩ kết luận, kê đơn → dược sĩ phát thuốc → thu ngân hoàn tất thanh toán. Kế hoạch ưu tiên một mô hình chỉ định và hàng đợi dùng chung cho xét nghiệm, chẩn đoán hình ảnh và nội soi, tránh tạo các luồng dữ liệu tách rời.

## Current-state findings

- `check-in.html` đã tiếp nhận bệnh nhân, tạo lịch trực tiếp và check-in vào `hangdoikham` của bác sĩ.
- `ho-so-benh-an.html` cho bác sĩ tạo hồ sơ, chẩn đoán, điều trị và kê đơn. Khi hoàn thành, `update-record.php` đang đồng thời hoàn thành lịch hẹn **và giảm tồn kho thuốc**.
- CSDL hiện chỉ có bốn giá trị vai trò đăng nhập: `benhnhan`, `bacsi`, `quantri`, `nhanvien`. Bảng `nhanvien.loaiNhanVien` có sẵn nhưng hiện chưa được dùng để cấp quyền API.
- Chưa có thực thể chỉ định, kết quả, hàng đợi cận lâm sàng, trạng thái phát thuốc hay nhật ký phát thuốc. Vì vậy không nên chỉ thêm trang giao diện trước.

## Architecture decisions

- Giữ `nguoidung.vaiTro = nhanvien` cho các nhân viên chuyên môn; phân quyền chi tiết bằng nhóm quyền/permission, không mở rộng ENUM thành `xetnghiem`, `xquang`, `noisoi`, `duocsi`... Điều này giảm thay đổi dây chuyền trong đăng nhập, session và 116+ API đang gọi `require_role`.
- Tạo nhóm quyền `nhanvien_tong_hop_demo` có toàn bộ quyền **tác nghiệp nhân viên** để demo; không mặc định cấp quyền quản trị tài khoản, cấu hình, hay sửa kho thuốc. API vẫn phải kiểm tra quyền, không chỉ ẩn/hiện menu.
- Dùng `dichvu_can_lam_sang` + `chi_dinh_can_lam_sang`/chi tiết chỉ định + `ket_qua_can_lam_sang` cho xét nghiệm, X-quang/siêu âm và nội soi. Mỗi dịch vụ có `nhomDichVu` và quyền thực hiện tương ứng.
- Dùng hàng đợi dịch vụ tổng quát, thay vì tái dùng `hangdoikham` vốn gắn chặt với bác sĩ và lịch hẹn. Mọi thay đổi trạng thái phải được lưu lịch sử/audit.
- Thuốc chỉ giảm tồn khi dược sĩ xác nhận phát; bác sĩ chỉ tạo đơn. Cần transaction và khoá dòng tồn kho để không phát trùng hoặc âm tồn.
- Kết quả chỉ hiển thị cho bệnh nhân sau khi bác sĩ duyệt/khóa lâm sàng; nhân viên cận lâm sàng chỉ sửa kết quả của chính bộ phận mình trước bước duyệt.

## Target workflow

```text
Check-in / lễ tân
        ↓
Điều dưỡng: dấu hiệu sinh tồn, sàng lọc (tuỳ phòng khám)
        ↓
Bác sĩ: hồ sơ nháp → chỉ định CLS và/hoặc đơn thuốc
        ├──────────────→ Hàng đợi CLS → thực hiện → kết quả → bác sĩ xem lại
        ↓
Bác sĩ kết luận, ký hoàn tất hồ sơ
        ├──────────────→ Dược sĩ: xác thực đơn → phát thuốc → trừ tồn
        └──────────────→ Thu ngân: thu dịch vụ/thuốc → hóa đơn
        ↓
Bệnh nhân xem hồ sơ, đơn và kết quả đã được duyệt
```

### State rules to agree before coding

- Hồ sơ: `Đang khám` → `Chờ thực hiện CLS` → `Chờ kết quả` → `Chờ bác sĩ kết luận` → `Hoàn tất` (hoặc `Hủy`). Không cho hoàn tất nếu còn chỉ định bắt buộc chưa có kết quả được duyệt.
- Chỉ định: `Mới tạo` → `Đang chờ` → `Đang thực hiện` → `Có kết quả` → `Đã duyệt` / `Hủy`.
- Đơn thuốc: `Đã kê` → `Chờ phát` → `Đã phát một phần` / `Đã phát đủ` / `Hủy`. Không trừ kho ở trạng thái `Đã kê`.
- Mỗi nghiệp vụ cần người thao tác, thời gian, lý do hủy/sửa và chống thao tác lặp (idempotency).

## Roles and pages

| Vai trò / nhóm quyền | Trang chính | Phạm vi tối thiểu |
|---|---|---|
| Lễ tân / tiếp nhận | `check-in.html` (đang có) | tìm/tạo bệnh nhân, lịch trực tiếp, check-in, điều phối hàng đợi |
| Điều dưỡng | `dieu-duong.html` | hàng đợi đã check-in, dấu hiệu sinh tồn, dị ứng, sàng lọc, chuyển bác sĩ |
| Bác sĩ | `ho-so-benh-an.html` (mở rộng) | khám, chỉ định, xem/duyệt kết quả, kê đơn, kết luận |
| KTV xét nghiệm | `can-lam-sang.html?nhom=xet-nghiem` | nhận mẫu, thực hiện, nhập/trả kết quả xét nghiệm |
| KTV chẩn đoán hình ảnh | `can-lam-sang.html?nhom=chan-doan-hinh-anh` | X-quang/siêu âm, tải ảnh hoặc link ảnh, nhập kết luận sơ bộ |
| KTV nội soi / điều dưỡng thủ thuật | `can-lam-sang.html?nhom=noi-soi` | checklist chuẩn bị, thực hiện, biên bản và ảnh/kết quả |
| Dược sĩ / phát thuốc | `phat-thuoc.html` | hàng đợi đơn chờ phát, đối chiếu, phát từng phần, hướng dẫn dùng thuốc, trừ tồn |
| Thu ngân (nên có) | `thu-ngan.html` | tổng hợp phí khám/dịch vụ/thuốc, thanh toán, in/xem hóa đơn |
| Nhân viên tổng hợp demo | `dashboard-nhanvien.html` hoặc menu điều hướng chung | toàn quyền tác nghiệp của các vai trò nhân viên ở trên, có banner “Chế độ demo”; không phải quyền quản trị |

## Task List

### Phase 1: Contract, migration, and authorization

- [x] Task 1: Chốt state machine và danh mục dịch vụ cận lâm sàng.
- [x] Task 2: Bổ sung migration cho quyền nhân viên, chỉ định/kết quả, hàng đợi dịch vụ và phát thuốc.
- [x] Task 3: Xây helper phân quyền nhân viên và điều hướng/sidebar theo quyền.

### Checkpoint: Foundation

- [x] Migration chạy được trên DB trống và DB đang có dữ liệu mẫu.
- [x] Tài khoản của mỗi vai trò chỉ gọi được API được cấp quyền.

### Phase 2: Complete clinical loop

- [x] Task 4: Thêm sàng lọc điều dưỡng và chuyển hàng đợi bác sĩ.
- [x] Task 5: Bổ sung tạo/chỉnh/hủy chỉ định vào hồ sơ bệnh án.
- [x] Task 6: Làm màn hình cận lâm sàng và trả kết quả.
- [x] Task 7: Cho bác sĩ xem, duyệt kết quả và hoàn tất hồ sơ theo điều kiện.

### Checkpoint: Clinical loop

- [x] Một bệnh nhân có thể đi từ check-in đến kết quả đã duyệt, rồi quay lại bác sĩ trong một luồng liên tục.
- [x] Không thể truy cập chéo dữ liệu hoặc hoàn thành hồ sơ khi còn chỉ định bắt buộc chưa xử lý.

### Phase 3: Prescription, dispensing, and payment

- [x] Task 8: Tách kê đơn khỏi giảm tồn kho và xây trang phát thuốc.
- [x] Task 9: Hoàn thiện thu ngân/hóa đơn cho phí dịch vụ và thuốc.
- [ ] Task 10: Mở rộng hồ sơ bệnh nhân, thông báo và báo cáo/audit.

### Checkpoint: Ready for demo

- [ ] Chạy được kịch bản demo đa vai trò từ check-in đến nhận thuốc/thanh toán.
- [ ] Tồn kho, hóa đơn và lịch sử thao tác khớp sau các ca phát một phần, hủy và thử lại request.

## Risks and mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Trừ tồn khi bác sĩ hoàn thành hồ sơ | Sai tồn kho khi chưa phát thuốc | Chuyển `applyPrescriptionStockDelta` sang nghiệp vụ phát thuốc có transaction/row lock |
| Mở rộng ENUM vai trò cho từng chuyên môn | Sửa diện rộng login, session, API | Giữ `nhanvien`; dùng permission/nhóm quyền và `loaiNhanVien` làm nhãn nghiệp vụ |
| Dùng một hàng đợi cho nhiều bộ phận | Không biết bệnh nhân đang ở đâu | `hangdoi_dichvu` gắn từng chi tiết chỉ định, có trạng thái và lịch sử riêng |
| Kết quả chưa được bác sĩ xem đã lộ cho bệnh nhân | Rủi ro nghiệp vụ | Có bước duyệt/khóa kết quả và lọc API bệnh nhân theo trạng thái đã duyệt |
| Kết quả ảnh/nội soi lớn | Chậm và thiếu bảo mật | Lưu metadata + URL Cloudinary/private asset; phân quyền truy cập và giới hạn định dạng/dung lượng |

## Open questions for product review

- Thu ngân thu trước khi thực hiện, sau khi thực hiện, hay cho phép cả hai? Kế hoạch mặc định thu ở cuối để demo đơn giản.
- Phòng khám có cần đặt lịch riêng cho nội soi/chẩn đoán hình ảnh hay chỉ điều phối nội bộ trong ngày?
- Ai được duyệt kết quả: chỉ bác sĩ chỉ định hay bác sĩ/kỹ thuật viên phụ trách theo từng loại dịch vụ?
- Có cần quản lý mẫu xét nghiệm theo mã vạch trong phạm vi đồ án không? Có thể để Phase 2.5 nếu thầy/cô đánh giá cao quy trình lab.
