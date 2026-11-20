# DESIGN.md — Eden Health Clinical Design System

> **Mục đích:** Tài liệu chuẩn thiết kế giao diện cho hệ thống y tế **Eden Health** (Quản lý khám chữa bệnh & đặt lịch khám). Mọi trang, modal, component hoặc tính năng giao diện mới do lập trình viên hoặc AI Agent tạo ra **bắt buộc** tuân thủ tài liệu này để đảm bảo tính đồng bộ, độ tin cậy chuẩn y tế và khả năng tiếp cận cao.

---

## 1. Nguyên Tắc Thiết Kế Y Tế (Clinical Design Principles)

1. **Tin cậy & Trầm tĩnh (Calm Clinical Trust):** Giao diện y tế cần tạo cảm giác an tâm, sạch sẽ, rõ ràng và nghiêm túc. Ưu tiên nền trắng/xám lạnh sáng, khoảng thở hợp lý, không phô trương.
2. **Ưu tiên đọc hiểu & quét nhanh (Scannability First):** Bệnh nhân (kể cả người lớn tuổi) và nhân viên y tế (bác sĩ, lễ tân, quản trị viên) cần đọc được giờ khám, mã hồ sơ, trạng thái lịch, đơn thuốc và số thứ tự ngay lập tức.
3. **Màu sắc mang ngữ nghĩa, không trang trí thừa (Semantic Over Decorative):** Màu chỉ dùng để phân cấp hành động chính, trạng thái lâm sàng hoặc cảnh báo an toàn — không dùng màu sắc rực rỡ hay hiệu ứng cầu kỳ gây nhiễu.
4. **Nhất quán giữa các vai trò (Role Consistency):** Dù là trang công khai/bệnh nhân hay dashboard nội bộ (Admin, Bác sĩ, Nhân viên), tất cả đều dùng chung một hệ token màu y tế, kiểu chữ và ngôn ngữ component.

---

## 2. Quy Tắc Bắt Buộc — Cấm Gradient & Chống "AI Slop" (Strict Policy)

Đây là các quy tắc **cứng** khi tạo mới hoặc chỉnh sửa giao diện:

### BẮT BUỘC TRÁNH (DO NOT USE)
- **KHÔNG dùng `linear-gradient(...)`, `radial-gradient(...)`, `conic-gradient(...)`** trên các phần tử mới hoặc khi chỉnh sửa:
  - Nút bấm (buttons), header/banner hero, sidebar, modal header, table `<thead>`, badge/pill, avatar, thẻ thống kê, thanh tiến trình, chữ gradient (`-webkit-background-clip: text`).
- **KHÔNG dùng tông tím / xanh tím (Purple / Violet / Indigo):**
  - Cấm tuyệt đối các mã màu cũ như `#667eea`, `#764ba2`, `#8b5cf6`, `#6d28d9`, `#6c5ce7`, `#5b7fe8`, `rgba(102, 126, 234, ...)`, `rgba(118, 75, 162, ...)`.
  - Không dùng biến legacy `--primary-purple` hoặc `--secondary-purple` trong code mới (các biến này trong CSS dùng chung chỉ giữ lại cho tương thích ngược).
- **KHÔNG dùng hiệu ứng "AI slop":**
  - Không dùng glassmorphism mờ nặng chồng chéo, blob/hình cầu trôi nổi trang trí dày đặc, icon nảy/lắc liên tục (`pulse`/`float`) gây mất tập trung trong màn hình nghiệp vụ, hoặc đổ bóng màu neon rực.

### NÊN DÙNG THAY THẾ (DO USE)
- **Màu đơn sắc (Flat Solid Fill)** từ bộ token chuẩn (`var(--primary-blue)`, `var(--primary-blue-dark)`, `var(--secondary-green)`).
- **Nền phân tách nhẹ bằng sắc độ trung tính** (`#FFFFFF`, `#F7F9FC`, `#F5F7FA`, `#E3F2FD`) kết hợp **viền mỏng rõ nét** (`1px solid var(--border-color)`).
- **Đổ bóng trung tính, nhẹ** (`var(--shadow-sm)`, `var(--shadow-md)`) để tạo phân tầng thay vì đổi màu nền lòe loẹt.

---

## 3. Hệ Thống Màu Sắc (Color Tokens)

Kế thừa trực tiếp từ `src/assets/css/common.css` và `src/assets/css/sidebar-*.css`:

### 3.1. Màu Thương Hiệu Y Tế (Brand & Clinical Core)
| Token CSS | Mã Hex | Vai trò & Cách dùng |
| :--- | :--- | :--- |
| `--primary-blue` | `#4A90E2` | Màu chủ đạo (Primary CTA, link active, icon nhấn, tab active, modal header, avatar mặc định, viền focus). |
| `--primary-blue-dark` | `#357ABD` | Trạng thái hover của nút/link chính, nền banner/hero lớn có chữ trắng (đảm bảo độ tương phản WCAG AA ~4.5:1), chữ trên nền xanh nhạt. |
| `--primary-blue-light` | `#E3F2FD` | Nền badge chuyên khoa, mục chưa đọc, trạng thái hover nhẹ, icon box nhạt. |
| `--secondary-green` | `#52c41a` | Điểm nhấn y tế tích cực (badge thông báo, bước đã hoàn thành, xác nhận thành công). |
| `--secondary-green-dark` | `#389e0d` | Trạng thái hover màu xanh lá, chữ đậm trên nền xanh lá nhạt. |
| `--secondary-green-light` | `#f6ffed` | Nền pill thông tin tích cực, banner xác nhận nhẹ. |

### 3.2. Màu Trung Tính & Bề Mặt (Neutrals & Surfaces)
| Token / Giá trị | Mã Hex | Vai trò & Cách dùng |
| :--- | :--- | :--- |
| Nền trang công khai | `#FFFFFF` | Nền chính cho các trang bệnh nhân/công khai (`common.css`). |
| Nền trang quản trị | `#F5F7FA` | Nền tổng thể của khu vực dashboard nội bộ để card trắng nổi bật rõ ràng. |
| `--light-bg` | `#F7F9FC` / `#F8F9FA` | Nền phụ cho section, header card, ô lọc, vùng chi tiết hồ sơ. |
| `--border-color` | `#E1E8ED` | Viền card, bảng, thanh top-bar, đường phân cách. |
| Viền input / bảng phụ | `#E5E7EB` / `#E8EEF5` | Viền nhẹ cho ô nhập liệu, hàng bảng, thanh tab. |
| `--text-primary` | `#2C3E50` (hoặc `#1E293B`) | Tiêu đề, nội dung chính, nhãn trường dữ liệu quan trọng. |
| `--text-secondary` | `#5A6C7D` (hoặc `#64748B`) | Mô tả, phụ đề, nhãn cột phụ, thông tin meta. |
| `--text-light` | `#8899A6` | Dấu thời gian, placeholder, nội dung trạng thái trống. |
| Footer tối | `#1E293B` | Nền chân trang công khai (đơn sắc, chữ `#FFFFFF` và `#94A3B8`). |

### 3.3. Màu Ngữ Nghĩa Lâm Sàng & Trạng Thái (Semantic Status)
Luôn dùng cặp **nền pastel nhẹ + chữ đậm tương phản cao** cho badge trạng thái:

| Ngữ nghĩa | Nền (Background) | Chữ / Icon (Text) | Ví dụ áp dụng |
| :--- | :--- | :--- | :--- |
| **Đã xác nhận / Đang khám** | `#CFE2FF` | `#084298` | Lịch hẹn đã xác nhận, bệnh nhân đang trong phòng khám |
| **Hoàn thành / Đã duyệt / Có BHYT** | `#D1FAE5` (hoặc `#D4EDDA`) | `#065F46` (hoặc `#155724`) | Đã khám xong, tài khoản hoạt động, xác thực BHYT |
| **Chờ khám / Chờ xử lý** | `#FFF3CD` | `#856404` | Đang chờ tiếp nhận, yêu cầu nghỉ phép/liên hệ chờ duyệt |
| **Đã hủy / Khóa / Cảnh báo nguy hiểm** | `#F8D7DA` | `#721C24` | Lịch đã hủy, tài khoản bị khóa, hết tồn kho thuốc |
| **Phân biệt metric phụ (Clinical Teal)** | `#CCFBF1` / `#0D9488` | `#0F766E` / `#FFFFFF` | Thẻ thống kê phụ khi cần phân biệt với xanh dương & xanh lá |
| **Cảnh báo tồn kho / Nhấn mạnh phụ** | `#FEF3C7` / `#D97706` | `#92400E` / `#FFFFFF` | Thuốc sắp hết, lưu ý quan trọng |

---

## 4. Kiểu Chữ & Phân Cấp Nội Dung (Typography)

- **Font chính:** `'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif`.
  - Mọi trang HTML mới phải nạp Google Font `Inter` (weights `400;500;600;700;800`) để hiển thị dấu tiếng Việt sắc nét và đồng nhất trên mọi hệ điều hành.
- **Thang kích thước chuẩn:**
  - **Hero / Page Title (Công khai):** `clamp(1.75rem, 3.2vw, 2.5rem)`, `font-weight: 800`, `line-height: 1.2`.
  - **Dashboard Page Title (`.section-title`):** `1.6rem – 1.8rem`, `font-weight: 700`, gạch chân `border-bottom: 3px solid var(--primary-blue)`.
  - **Section / Card Heading (`h3`–`h5`):** `1.1rem – 1.35rem`, `font-weight: 700`.
  - **Body Text:** `0.95rem – 1rem` (`15px–16px`), `line-height: 1.6 – 1.75` (ưu tiên đọc dễ chịu cho chẩn đoán, hướng dẫn uống thuốc, mô tả triệu chứng).
  - **Table Cell / Form Input:** `0.9rem`, `line-height: 1.5`.
  - **Table Header / Overline / Label:** `0.78rem – 0.85rem`, `font-weight: 600–700`, `text-transform: uppercase`, `letter-spacing: 0.4px`.
  - **Meta / Caption:** `0.78rem – 0.82rem`, màu `var(--text-secondary)` hoặc `var(--text-light)`.
- **Số liệu lâm sàng & tài chính:** Luôn dùng `font-variant-numeric: tabular-nums;` cho số thứ tự (STT), khung giờ khám, chi phí khám, số lượng thuốc và chỉ số thống kê để các cột số thẳng hàng.

---

## 5. Kiến Trúc Bố Cục & Khung Theo Vai Trò (Layout & Role Shells)

Hệ thống chia thành **2 nhóm layout chính**, tái sử dụng sẵn CSS & HTML components của dự án:

### 5.1. Trang Công Khai & Bệnh Nhân
- **Stylesheet bắt buộc:** `assets/css/common.css` + Bootstrap `5.3.8`.
- **Thành phần tái sử dụng:**
  - `components/header.html` (Navbar sticky `min-height: 58px`, menu chính, thông báo trượt từ phải, user avatar).
  - `components/footer.html` (Footer nền `#1E293B`).
  - `components/chat-support.html` (Nút hỗ trợ góc dưới phải).
- **Container nội dung:** `max-width: 1200px` (trang chi tiết, form đặt lịch `1100px`) hoặc `1400px` (danh sách bác sĩ/chuyên khoa/lịch khám).
- **Header đầu trang (Page Banner):** Nền đơn sắc `var(--primary-blue-dark)` (`#357ABD`) hoặc nền sáng `var(--light-bg)` với viền dưới `1px solid var(--border-color)`. Không dùng gradient.

### 5.2. Cổng Nội Bộ (Admin, Bác Sĩ, Nhân Viên Tiếp Nhận)
- **Stylesheet theo vai trò:**
  - Quản trị viên: `assets/css/sidebar-admin.css` + `assets/css/admin-summary.css` + `components/sidebar-admin.html`
  - Bác sĩ: `assets/css/sidebar-doctor.css` + `components/sidebar-doctor.html`
  - Nhân viên: `assets/css/sidebar-nhanvien.css` + `assets/css/admin-summary.css` + `components/sidebar-nhanvien.html`
- **Cấu trúc chuẩn:**
  - Sidebar cố định bên trái `width: 260px`, nền đơn sắc `var(--primary-blue-dark)` hoặc `var(--primary-blue)` (khi tạo mới không dùng gradient).
  - Top bar sticky nền `#FFFFFF`, viền dưới `1px solid var(--border-color)`, padding `15px 30px`.
  - `.content-area` có `padding: 30px` (mobile `20px 15px`).
  - Thứ tự nội dung chuẩn trên màn hình quản lý:
    1. Tiêu đề trang (`.section-title`) hoặc thanh tổng quan nhanh (`.summary-bar` với `.summary-chip`).
    2. Khung tìm kiếm / bộ lọc (`.filter-section` / `.search-section`).
    3. Khung bảng dữ liệu hoặc tab nghiệp vụ (`.table-section` / `.section-card`).

### 5.3. Khoảng Cách, Bo Góc & Đổ Bóng
- **Hệ khoảng cách (Spacing):** Bội số của `4px` (`8px`, `12px`, `16px`, `20px`, `24px`, `30px`).
- **Bo góc (Border Radius):**
  - Input, nút chuẩn, nút thao tác bảng: `6px – 10px`.
  - Card, panel, filter box, modal: `12px – 16px`.
  - Chip lọc, badge trạng thái, pill: `999px` (hoặc `20px`).
  - Avatar, số thứ tự tròn (`.stt-badge`): `50%`.
- **Đổ bóng (Elevation):**
  - `--shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);` (card tĩnh, nút hover nhẹ)
  - `--shadow-md: 0 4px 16px rgba(0, 0, 0, 0.10);` (card nổi, filter box)
  - `--shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.12);` (dropdown, modal, chat panel)
- **Breakpoint responsive:** `992px` (tablet/navbar), `768px` (sidebar trượt mobile, bảng co gọn), `576px` (điện thoại nhỏ).

---

## 6. Quy Chuẩn Component (Component Specifications)

### 6.1. Nút Bấm (Buttons)
- **Nút chính (Primary):** Nền `var(--primary-blue)`, chữ `#FFFFFF`, `border: none`, `border-radius: 8px` (hoặc `25px` trên nút thêm mới trong bảng admin cũ để khớp ngữ cảnh), `font-weight: 600`. Hover: nền `var(--primary-blue-dark)`, nâng nhẹ tối đa `-1px` đến `-2px`.
- **Nút phụ (Secondary / Outline):** Nền `#FFFFFF`, viền `1.5px solid var(--primary-blue)`, chữ `var(--primary-blue)`. Hover: nền `var(--primary-blue-light)` hoặc `var(--primary-blue)` chữ trắng.
- **Nút trung tính (Cancel / Back):** Nền `#F1F5F9`, chữ `var(--text-primary)`, viền `1px solid var(--border-color)`. Hover: `#E2E8F0`.
- **Nút hành động trong bảng (`.btn-action`):**
  - Xem (`.btn-view`): nền `#E3F2FD`, chữ `#1976D2` → hover nền `#1976D2`, chữ `#FFFFFF`.
  - Sửa (`.btn-edit`): nền `#FFF3CD`, chữ `#856404` → hover nền `#D97706`, chữ `#FFFFFF`.
  - Xóa / Khóa (`.btn-delete` / `.btn-lock`): nền `#F8D7DA`, chữ `#721C24` → hover nền `#DC3545`, chữ `#FFFFFF`.
  - Xác nhận / Mở khóa / Xuất file: nền `var(--secondary-green)` hoặc `#D4EDDA` chữ `#155724`.

### 6.2. Card & Container
- Nền `#FFFFFF`, `border: 1px solid var(--border-color)`, `border-radius: 12px` đến `16px`, `box-shadow: var(--shadow-sm)`.
- **Card header (nếu có):** Nền `var(--light-bg)` (`#F7F9FC`), `border-bottom: 1px solid var(--border-color)`, padding `16px 24px`. Không dùng nền gradient.

### 6.3. Thanh Tổng Quan & Bộ Lọc (`admin-summary.css`)
- Dùng `.summary-bar` và `.summary-chip` (có `.is-clickable` khi lọc nhanh được, `.is-active` khi đang chọn).
- Mọi ô tìm kiếm/chọn lọc nên bọc trong `.filter-control-wrap` với nút xóa nhanh `.filter-clear-btn` (như `check-in.html` và các trang quản lý).

### 6.4. Bảng Dữ Liệu (Tables)
- **Bảng nghiệp vụ nội bộ (`.custom-table`):**
  - `<thead> th`: Nền phẳng `#F1F5F9` (hoặc `#F8F9FA`), chữ `#475569`, `font-size: 0.82rem – 0.85rem`, `font-weight: 600`, `text-transform: uppercase`, `border-bottom: 2px solid var(--border-color)`.
  - `<tbody> td`: Padding `14px 15px`, `vertical-align: middle`, `border-bottom: 1px solid #EDF2F7`.
  - Hover hàng: nền `#F8FAFC`.
- **Bảng cho bệnh nhân (lịch khám, hồ sơ bệnh án):**
  - `<thead>`: Nền đơn sắc `var(--primary-blue)` chữ `#FFFFFF` (hoặc nền phẳng `#F1F5F9` chữ `#1E293B`), tuyệt đối không gradient.
- Luôn bọc bảng trong `.table-responsive` để cuộn ngang mượt trên màn hình nhỏ.

### 6.5. Biểu Mẫu & Nhập Liệu (Forms)
- Chiều cao tối thiểu `40px – 42px`, `border: 1.5px solid #DDE4EE`, `border-radius: 8px`, padding `10px 14px`.
- Trạng thái `:focus`: `border-color: var(--primary-blue); box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.15); outline: none;`.
- Nhãn (`.form-label`): luôn nằm trên ô nhập, `font-weight: 600`, màu `var(--text-primary)`, đánh dấu trường bắt buộc bằng `<span class="text-danger">*</span>`.
- Thông báo lỗi validation rõ ràng bằng tiếng Việt ngay dưới ô nhập, không chỉ đổi viền đỏ không lời giải thích.

### 6.6. Modal, Tabs & Phân Trang
- **Modal:** `.modal-content` bo góc `14px–16px`, `.modal-header` nền đơn sắc `var(--primary-blue)` chữ trắng (`.btn-close` đảo màu trắng) hoặc nền sáng có đường viền dưới rõ ràng.
- **Tabs (`.custom-tabs`):** Đường viền dưới `2px solid #E8EEF5`, tab active có chữ `var(--primary-blue)` và `border-bottom: 3px solid var(--primary-blue)`, nền trong suốt.
- **Pagination:** Nút trang active dùng nền đơn sắc `var(--primary-blue)`, chữ `#FFFFFF`, viền `var(--primary-blue)`.

### 6.7. Trạng Thái Tải, Trống & Lỗi (Loading / Empty / Error)
- **Loading:** Spinner Bootstrap màu `var(--primary-blue)` kèm chữ mô tả ngắn (ví dụ: *"Đang tải danh sách lịch khám..."*).
- **Empty State:** Icon trung tính (`#CBD5E1`), tiêu đề rõ ràng, mô tả ngắn và nút hành động tiếp theo (nếu có).
- **Error State:** Khung cảnh báo rõ nguyên nhân + nút *"Thử lại"*.

---

## 7. Chuẩn Trải Nghiệm Y Tế & Khả Năng Tiếp Cận (Healthcare UX & Accessibility)

1. **Độ tương phản (WCAG 2.1 AA):**
   - Chữ thường: tỷ lệ tương phản tối thiểu **4.5:1**.
   - Chữ lớn (>= 24px hoặc >= 19px bold) và thành phần UI: tối thiểu **3:1**.
   - Khi đặt chữ trắng lên mảng màu xanh lớn (banner/hero/header), ưu tiên `--primary-blue-dark` (`#357ABD`) để đảm bảo độ đọc rõ.
2. **Không dựa hoàn toàn vào màu sắc:** Mọi trạng thái y tế (Chờ khám, Đang khám, Hoàn thành, Đã hủy, Hết thuốc) phải đi kèm **nhãn văn bản rõ ràng** và/hoặc **icon phân biệt** (hỗ trợ người mù màu).
3. **An toàn thao tác (Clinical Safety):**
   - Các thao tác khó hoàn tác (hủy lịch khám, xóa bác sĩ/thuốc, khóa tài khoản, hoàn tất kê đơn trừ kho) phải có **hộp thoại xác nhận rõ ràng** hiển thị tóm tắt đối tượng bị tác động.
4. **Cảnh báo miễn trừ trách nhiệm AI (Medical AI Disclaimer):**
   - Mọi màn hình có tính năng AI gợi ý chuyên khoa / chatbot triệu chứng phải hiển thị rõ ghi chú: *Kết quả gợi ý từ AI chỉ mang tính tham khảo hỗ trợ định hướng chuyên khoa, không thay thế chẩn đoán của bác sĩ.*
5. **Khả năng đọc hồ sơ & in ấn:**
   - Hồ sơ bệnh án, đơn thuốc, mã QR check-in phải có bố cục nhãn–giá trị rõ ràng, không cắt chữ quan trọng (`word-break: break-word`), khoảng cách giữa các thuốc/liều dùng tách bạch để tránh nhầm lẫn.
6. **Điều hướng bàn phím & chạm:**
   - Vùng chạm nút/chip tối thiểu `38px–44px` trên mobile.
   - Luôn giữ `:focus-visible` rõ ràng (`outline: 2px solid var(--primary-blue); outline-offset: 2px;`).
   - Mọi nút chỉ có icon phải có `aria-label` tiếng Việt mô tả đúng chức năng.

---

## 8. Ngăn Xếp Kỹ Thuật Frontend (Frontend Tech Stack)

- **Kiến trúc:** Pure HTML5 + CSS3 + Vanilla JavaScript (không dùng bundler/React/Tailwind).
- **CSS Framework:** Bootstrap `5.3.8` (`https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css`).
- **Icons:** Font Awesome `6.7.2` (`https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css`) và RemixIcon `4.3.0` khi cần.
- **Typography:** Google Fonts `Inter`.
- **Thư viện chuyên dụng sẵn dùng trong dự án:** `Chart.js` (biểu đồ thống kê), `Flatpickr` (chọn ngày), `SheetJS/xlsx` (xuất/nhập Excel), `html5-qrcode` (quét QR check-in).
- **Quy ước CSS:**
  - Tận dụng biến CSS từ `common.css` hoặc `sidebar-*.css`, **không định nghĩa lại `:root` ghi đè màu chủ đạo** trong từng file HTML riêng lẻ.
  - Không đặt style inline rườm rà khi có thể viết class rõ ràng trong `<style>` hoặc stylesheet chung.

---

## 9. Danh Sách Kiểm Tra Cho AI Agent Trước Khi Hoàn Thành UI

Trước khi bàn giao bất kỳ trang hoặc thay đổi giao diện nào, agent phải tự kiểm tra:

- [ ] **Không có gradient:** Đã kiểm tra không có `linear-gradient`, `radial-gradient`, `conic-gradient` trong code vừa viết.
- [ ] **Không có màu tím/xanh tím:** Không có `#667eea`, `#764ba2`, `#8b5cf6`, `#6c5ce7`, `102, 126, 234`, `--primary-purple`.
- [ ] **Đúng token màu Eden Health:** Sử dụng `--primary-blue`, `--primary-blue-dark`, `--primary-blue-light`, `--secondary-green`, `--text-primary`, `--border-color`.
- [ ] **Đúng shell vai trò:** Nạp đúng `common.css` hoặc `sidebar-{admin|doctor|nhanvien}.css` và component tương ứng.
- [ ] **Đủ 4 trạng thái UI:** Đã xử lý đầy đủ loading, empty, error và dữ liệu thực.
- [ ] **Tương phản & dễ đọc:** Chữ tiếng Việt rõ ràng, số liệu thẳng hàng (`tabular-nums`), badge trạng thái có chữ + tương phản cao.
- [ ] **Responsive:** Hiển thị tốt ở desktop (`1280px+`), tablet (`768px`) và mobile (`375px`), bảng có `.table-responsive`.
- [ ] **Accessibility:** Có `focus-visible`, `aria-label` cho nút icon, xác nhận cho thao tác hủy/xóa/khóa.
