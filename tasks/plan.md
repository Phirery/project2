# Implementation Plan: Bác sĩ xếp lịch theo tháng

## Overview
Chúng ta sẽ chuyển `#requestLeaveModal` trong [`src/lich-kham.html`](/C:/xampp/htdocs/DO_AN/src/lich-kham.html) từ luồng "xin nghỉ phép" sang luồng "xếp lịch". Thay vì giả định bác sĩ luôn có ca mỗi ngày rồi mới xin nghỉ, hệ thống sẽ mặc định bác sĩ làm việc từ Thứ 2 đến Thứ 7 và nghỉ Chủ nhật, sau đó cho phép bác sĩ mở modal để xem lịch của tháng hiện tại và tắt/bỏ chọn từng ngày hoặc từng ca để đánh dấu không làm việc.

## Architecture Decisions
- Giữ `ngaynghi` làm bảng ngoại lệ cho các ngày/ca không làm việc để tránh thay đổi lớn ở các luồng đặt lịch hiện có.
- Đưa quy tắc mặc định "Thứ 2 - Thứ 7 làm việc, Chủ nhật nghỉ" vào tầng kiểm tra khả dụng của bác sĩ, không phụ thuộc vào việc bác sĩ đã xếp lịch hay chưa.
- Tách rõ hai thao tác: đọc lịch tháng hiện tại và lưu các thay đổi lịch tháng, thay vì tiếp tục dùng một form "xin nghỉ" đơn lẻ theo ngày.
- Ưu tiên thay đổi theo kiểu "override" cho từng ngày/ca trong tháng, vì cách này khớp với cách các endpoint đặt lịch hiện tại đang đọc ngoại lệ.
- Khi bác sĩ bỏ chọn một ngày/ca đã có bệnh nhân đặt, backend vẫn nên trả preview các lịch bị ảnh hưởng trước khi commit, để giữ an toàn như luồng nghỉ phép hiện tại.

## Task List

### Phase 1: Chuẩn hóa luồng và quy tắc lịch
- [ ] Task 1: Rà lại luồng hiện tại của `lich-kham.html`, `dat-lich.html`, và các endpoint `get-available-*`, `get-doctor-schedule`, `request-leave`.
- [ ] Task 2: Chốt mô hình dữ liệu và payload cho "xếp lịch tháng" theo kiểu ngoại lệ ngày/ca.
- [ ] Task 3: Xác định rõ cách biểu diễn mặc định tuần làm việc và Chủ nhật nghỉ trong backend.

### Checkpoint: Foundation
- [ ] Luồng hiện tại và luồng mới không mâu thuẫn về mặt dữ liệu.
- [ ] Có một quy ước duy nhất cho trạng thái: mặc định làm việc, ngoại lệ là không làm việc.

### Phase 2: Backend cho lịch tháng
- [ ] Task 4: Tạo endpoint đọc lịch tháng của bác sĩ, trả về trạng thái từng ngày và từng ca trong tháng.
- [ ] Task 5: Tạo endpoint lưu lịch tháng theo batch, hỗ trợ chọn nhiều ngày/ca và bỏ chọn để xóa ngoại lệ.
- [ ] Task 5: Tạo endpoint lưu lịch tháng theo batch, có preview các lịch bị ảnh hưởng trước khi chốt nếu bác sĩ tắt một ca/ngày đã có lịch.
- [ ] Task 6: Cập nhật các endpoint kiểm tra khả dụng cho bệnh nhân để hiểu quy tắc Chủ nhật nghỉ và các ngoại lệ tháng.

### Checkpoint: Core Features
- [ ] Nếu bác sĩ chưa xếp lịch, hệ thống vẫn coi Thứ 2 - Thứ 7 là làm việc.
- [ ] Nếu bác sĩ bỏ chọn ngày/ca nào, các màn hình đặt lịch của bệnh nhân sẽ không còn hiện slot đó.

### Phase 3: Frontend modal xếp lịch
- [ ] Task 7: Đổi nội dung modal `requestLeaveModal` thành giao diện "Xếp lịch" với lịch tháng, chọn/bỏ chọn ngày và ca.
- [ ] Task 8: Thêm trạng thái tải, trạng thái không có dữ liệu, và tóm tắt số ngày/ca đang được mở hoặc tắt.
- [ ] Task 9: Cập nhật copy text, nút bấm, và tên hàm JS cho đúng ngữ nghĩa mới, nhưng giữ cấu trúc trang càng giống hiện tại càng tốt.

### Checkpoint: Complete
- [ ] Bác sĩ có thể mở modal, xem lịch tháng, bỏ chọn ca/ngày, và lưu thành công.
- [ ] Trang đặt lịch bệnh nhân phản ánh đúng lịch mới.
- [ ] Không phá các luồng đặt lịch, đổi lịch, hoặc xem lịch khám cá nhân hiện có.

## Risks and Mitigations
| Risk | Impact | Mitigation |
|------|--------|------------|
| Giữ `ngaynghi` nhưng đổi ngữ nghĩa làm code khó hiểu | Medium | Đặt tên endpoint/UI rõ là "xếp lịch", thêm helper/hàm mô tả đúng vai trò của bảng này |
| Quy tắc Chủ nhật nghỉ bị bỏ sót ở một endpoint nào đó | High | Rà các endpoint `get-available-doctors`, `get-available-shifts`, `get-available-slots`, và màn chi tiết bác sĩ trước khi chốt |
| Modal tháng quá nặng nếu render toàn bộ ô theo ngày x ca | Medium | Chỉ render đúng tháng hiện tại, lazy load theo tháng, và dùng batch save |
| Bác sĩ muốn đổi lại trạng thái cũ nhưng chỉ có thao tác thêm mới | Medium | Thiết kế lưu theo kiểu upsert/xóa ngoại lệ để có thể toggle 2 chiều |

## Open Questions
- Có muốn cho phép cấu hình lại ngày nghỉ mặc định ngoài Chủ nhật trong tương lai không, hay cứ cố định là Chủ nhật?
- Khi bác sĩ xếp lịch, hệ thống có cần hỗ trợ ghi chú theo ngày/ca không, hay chỉ cần bật/tắt ca?
- Có muốn giữ endpoint `request-leave.php` cho tương thích ngược, hay đổi hẳn sang endpoint mới để tránh lẫn nghĩa?
- Có cần đồng bộ luôn màn `chi-tiet-bac-si.html` để hiển thị lịch tháng theo quy tắc mới không?
