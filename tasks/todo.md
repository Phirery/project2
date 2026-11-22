# Task list: Cổng tác nghiệp nhân viên phòng khám

## Task 1: Chốt trạng thái và catalog dịch vụ cận lâm sàng

**Description:** Viết contract trạng thái cho hồ sơ, chỉ định, hàng đợi dịch vụ và đơn thuốc; seed danh mục xét nghiệm, X-quang/siêu âm, nội soi với nhóm dịch vụ và phí.

**Acceptance criteria:**
- [x] Có bảng transition hợp lệ và các điều kiện hoàn tất hồ sơ.
- [x] Mỗi dịch vụ có nhóm chuyên môn, giá, trạng thái hoạt động và quyền thực hiện.
- [x] Có seed đủ cho kịch bản demo 3 loại: xét nghiệm, X-quang, nội soi.

**Verification:**
- [ ] Review state-machine với người phụ trách nghiệp vụ.
- [x] Import seed trên database local thành công.

**Dependencies:** None

**Files likely touched:** `src/database/migrations/*`, `docs/clinical-workflow.md`

**Estimated scope:** Medium: 3-5 files

## Task 2: Migration dữ liệu cho quyền, chỉ định, kết quả và phát thuốc

**Description:** Bổ sung migration có thể chạy lặp an toàn cho nhóm quyền nhân viên, gán quyền, danh mục dịch vụ, chỉ định/chi tiết/kết quả/lịch sử/hàng đợi và phiếu phát thuốc. Thêm index, FK và audit fields.

**Acceptance criteria:**
- [x] Không đổi ENUM `nguoidung.vaiTro` để tạo từng loại chuyên môn; các chuyên môn là nhóm quyền của `nhanvien`.
- [x] Một chỉ định liên kết được hồ sơ, bệnh nhân, bác sĩ chỉ định, dịch vụ, kết quả và người xử lý.
- [x] Một lần phát thuốc lưu được chi tiết, số lượng thực phát, dược sĩ và thời điểm.

**Verification:**
- [x] Chạy migration mới trên DB sạch và DB mẫu không lỗi.
- [x] Kiểm tra EXPLAIN cho truy vấn hàng đợi theo trạng thái/ngày/nhóm dịch vụ.

**Dependencies:** Task 1

**Files likely touched:** `src/database/migrations/*`, `src/database/datlichkham_table.sql`, `src/database/datlichkham.sql`

**Estimated scope:** Medium: 3-5 files

## Task 3: RBAC cho nhân viên và menu theo quyền

**Description:** Tạo helper kiểm tra permission phía server, API lấy quyền hiện tại, seed các nhóm tiếp nhận, điều dưỡng, xét nghiệm, chẩn đoán hình ảnh, nội soi, dược sĩ, thu ngân và `nhanvien_tong_hop_demo`; render sidebar theo quyền.

**Acceptance criteria:**
- [ ] Mỗi endpoint mới trả 403 khi thiếu permission, kể cả khi người dùng tự mở URL/trực tiếp gọi API.
- [ ] Nhân viên tổng hợp demo truy cập được các màn tác nghiệp nhưng không được quản trị hệ thống.
- [ ] Menu chỉ hiển thị module được phép và có trạng thái active nhất quán.

**Verification:**
- [ ] Đăng nhập test bằng ít nhất dược sĩ, KTV xét nghiệm và nhân viên demo.
- [ ] Test thủ công các API bị từ chối/cấp phép.

**Dependencies:** Task 2

**Files likely touched:** `src/config/session.php`, `src/includes/*permission*.php`, `src/components/sidebar-nhanvien.html`, `src/assets/js/sidebar-nhanvien.js`, `src/api/staff/*`

**Estimated scope:** Large: 5-8 files; tách helper và sidebar thành hai PR nếu cần

## Checkpoint: Foundation

- [ ] Migration, seed và RBAC hoạt động trên local.
- [ ] Không có API mới chỉ dựa vào ẩn nút để bảo vệ quyền.

## Task 4: Trang điều dưỡng và sàng lọc trước khám

**Description:** Xây `dieu-duong.html` nhận bệnh nhân đã check-in, ghi dấu hiệu sinh tồn, dị ứng/cảnh báo, ghi chú sàng lọc và chuyển bệnh nhân tới hàng đợi bác sĩ.

**Acceptance criteria:**
- [ ] Chỉ hiển thị bệnh nhân đã check-in và chưa chuyển/hoàn tất.
- [ ] Dữ liệu sàng lọc hiển thị read-only cho bác sĩ trong hồ sơ.
- [ ] Có log người thao tác và thời điểm chuyển hàng đợi.

**Verification:**
- [ ] Kịch bản check-in → điều dưỡng → bác sĩ chạy xuyên suốt.
- [ ] Kiểm tra giao diện desktop/mobile và lỗi validation số đo.

**Dependencies:** Task 3

**Files likely touched:** `src/dieu-duong.html`, `src/assets/js/dieu-duong.js`, `src/api/staff/*`, `src/assets/css/*`, `src/components/sidebar-nhanvien.html`

**Estimated scope:** Large: 5-8 files

## Task 5: Bác sĩ tạo và quản lý chỉ định cận lâm sàng

**Description:** Mở rộng modal/screen chỉnh hồ sơ bệnh án để chọn nhiều dịch vụ, nhập ghi chú lâm sàng/độ ưu tiên và tạo hoặc hủy chỉ định; chuyển các chỉ định mới sang hàng đợi đúng bộ phận.

**Acceptance criteria:**
- [ ] Bác sĩ tạo được nhiều chỉ định trong một hồ sơ và thấy trạng thái từng chỉ định.
- [ ] Chỉ định bị hủy phải có lý do và không còn trong hàng đợi hoạt động.
- [ ] Không cho hoàn tất hồ sơ khi còn chỉ định bắt buộc chưa có kết quả duyệt.

**Verification:**
- [ ] Test luồng tạo, chỉnh trước thực hiện, hủy và chặn hoàn tất.
- [ ] Verify quyền: chỉ bác sĩ chỉ định hoặc permission được giao mới thay đổi được.

**Dependencies:** Tasks 2-3

**Files likely touched:** `src/ho-so-benh-an.html`, `src/api/doctor/create-record.php`, `src/api/doctor/update-record.php`, `src/api/doctor/*order*.php`, `src/includes/*`

**Estimated scope:** Large: 5-8 files

## Task 6: Màn hình cận lâm sàng dùng chung

**Description:** Xây `can-lam-sang.html` dùng query/module theo nhóm dịch vụ; KTV nhận bệnh nhân, chuyển đang thực hiện, nhập kết quả có cấu trúc/nhận xét, đính kèm ảnh/link khi phù hợp và trả kết quả để duyệt.

**Acceptance criteria:**
- [ ] KTV chỉ thấy chỉ định thuộc nhóm được cấp quyền.
- [ ] Không thể trả kết quả cho chỉ định hủy hoặc của nhóm khác.
- [ ] Kết quả có lịch sử tạo/sửa và trạng thái chờ duyệt.

**Verification:**
- [ ] Test các ca xét nghiệm, X-quang và nội soi bằng cùng contract API.
- [ ] Kiểm tra upload URL/metadata bị validate và không vượt giới hạn.

**Dependencies:** Tasks 2-3, Task 5

**Files likely touched:** `src/can-lam-sang.html`, `src/assets/js/can-lam-sang.js`, `src/api/staff/*result*.php`, `src/api/staff/*queue*.php`, `src/assets/css/*`

**Estimated scope:** Large: 5-8 files; ưu tiên xét nghiệm trước rồi tái dùng cho các nhóm khác

## Task 7: Bác sĩ xem/duyệt kết quả và hoàn tất hồ sơ

**Description:** Hiển thị kết quả theo thời gian thực trong hồ sơ, hỗ trợ bác sĩ duyệt/ghi nhận, quay lại khám khi cần và chỉ hoàn tất khi điều kiện state machine đạt.

**Acceptance criteria:**
- [ ] Kết quả đã trả hiển thị cạnh chỉ định, có tác giả/thời điểm và tệp đính kèm.
- [ ] Kết quả chỉ xuất hiện ở API bệnh nhân sau khi được duyệt.
- [ ] Hoàn tất hồ sơ cập nhật trạng thái liên quan một lần, có transaction và không phá luồng đơn thuốc.

**Verification:**
- [ ] Kịch bản: chỉ định → trả kết quả → duyệt → hoàn tất.
- [ ] Kịch bản đua 2 request hoàn tất chỉ tạo một thay đổi trạng thái.

**Dependencies:** Tasks 5-6

**Files likely touched:** `src/ho-so-benh-an.html`, `src/api/doctor/get-record-detail.php`, `src/api/doctor/update-record.php`, `src/api/patient/get-medical-records.php`, `src/includes/*`

**Estimated scope:** Medium: 3-5 files

## Checkpoint: Clinical loop

- [ ] Có dữ liệu demo end-to-end và mỗi bước hiển thị đúng ở đúng vai trò.
- [ ] Dữ liệu bệnh nhân không lộ kết quả chưa duyệt.

## Task 8: Tách kê đơn khỏi phát thuốc và làm trang dược sĩ

**Description:** Giữ bác sĩ tạo đơn, nhưng bỏ giảm tồn tại `update-record.php`; xây `phat-thuoc.html` để dược sĩ nhận đơn, kiểm tra tồn/liều, phát đủ hoặc một phần, in/xem hướng dẫn và giảm tồn nguyên tử tại lúc phát.

**Acceptance criteria:**
- [ ] Hoàn tất hồ sơ chỉ đưa đơn vào hàng đợi dược, không giảm `thuoc.soLuongTon`.
- [ ] Mỗi lần phát lưu số lượng thực phát và người phát; phát lại cùng request không trừ kho lần hai.
- [ ] Không phát vượt tồn; phát một phần giữ phần còn lại ở trạng thái chờ.

**Verification:**
- [ ] Test đơn đủ thuốc, thiếu tồn, phát một phần, hủy và retry request.
- [ ] Đối chiếu tổng phát với biến động tồn kho trong transaction.

**Dependencies:** Tasks 2-3, Task 7

**Files likely touched:** `src/phat-thuoc.html`, `src/assets/js/phat-thuoc.js`, `src/api/staff/*dispens*.php`, `src/api/doctor/update-record.php`, `src/includes/medicine-stock.php`, `src/api/patient/get-medical-records.php`

**Estimated scope:** Large: 5-8 files

## Task 9: Trang thu ngân và hóa đơn tổng hợp

**Description:** Bổ sung `thu-ngan.html` để tìm lượt khám, tổng hợp phí khám, dịch vụ cận lâm sàng và thuốc đã phát, sau đó ghi nhận thanh toán/in hóa đơn. Tái sử dụng VNPAY/hoá đơn hiện hữu nếu contract đáp ứng.

**Acceptance criteria:**
- [ ] Không tính thuốc chưa phát hoặc chỉ định đã hủy.
- [ ] Không thể thanh toán trùng một khoản mục.
- [ ] Hóa đơn thể hiện rõ từng nhóm chi phí và trạng thái thanh toán.

**Verification:**
- [ ] Test tiền mặt và dữ liệu VNPAY hiện có nếu áp dụng.
- [ ] Đối chiếu tổng hóa đơn với chi tiết dịch vụ/thuốc.

**Dependencies:** Tasks 2, 6, 8

**Files likely touched:** `src/thu-ngan.html`, `src/assets/js/thu-ngan.js`, `src/api/staff/*invoice*.php`, `src/api/payment/*`, `src/components/sidebar-nhanvien.html`

**Estimated scope:** Large: 5-8 files

## Task 10: Cổng bệnh nhân, thông báo, báo cáo và audit

**Description:** Bổ sung hiển thị chỉ định/kết quả đã duyệt, trạng thái phát thuốc và hóa đơn cho bệnh nhân; thêm thông báo theo mốc chính, báo cáo vận hành và màn audit cho quản trị.

**Acceptance criteria:**
- [ ] Bệnh nhân chỉ xem được dữ liệu của mình và kết quả đã duyệt.
- [ ] Có báo cáo tối thiểu theo ngày: số lượt CLS, thời gian chờ, số đơn phát, thuốc sắp hết.
- [ ] Mọi trạng thái quan trọng có thể truy tới người và thời điểm thao tác.

**Verification:**
- [ ] Test phân quyền API bệnh nhân bằng ID khác.
- [ ] Xuất báo cáo thử và đối chiếu với dữ liệu nghiệp vụ.

**Dependencies:** Tasks 6-9

**Files likely touched:** `src/ho-so-benh-an-ca-nhan.html`, `src/api/patient/*`, `src/thong-ke.html`, `src/api/admin/*`, `src/includes/mail-events.php`

**Estimated scope:** Large: 5-8 files; có thể chia patient notification và reporting thành hai task

## Checkpoint: Ready for demo

- [ ] Demo 5 tài khoản: tiếp nhận, bác sĩ, KTV xét nghiệm, dược sĩ, thu ngân/nhân viên tổng hợp.
- [ ] Không có lỗi cấp quyền, trừ tồn hoặc thanh toán trùng trong các kịch bản chính.
