# DO_AN - Eden Health

Hệ thống đặt lịch khám và quản lý khám chữa bệnh theo vai trò (bệnh nhân, bác sĩ, quản trị). Dự án gồm giao diện HTML/CSS/JS và backend PHP + MySQL, chạy tốt trên XAMPP.

## Tính năng chính
- Đăng ký/đăng nhập, quên mật khẩu, OTP.
- Đặt lịch khám, xem lịch hẹn và hồ sơ bệnh án.
- Bảng điều khiển cho quản trị/bác sĩ/bệnh nhân.
- Quản lý bác sĩ, bệnh nhân, khoa/phòng, lịch khám.
- Thống kê, báo cáo, xuất dữ liệu (CSV/Excel/PDF).
- Thông báo và liên hệ.
- Mail giao dịch cho các sự kiện chính: đăng ký, OTP, đặt/hủy/nhắc lịch, xử lý liên hệ, thanh toán, hoàn tất hồ sơ khám.

## Công nghệ
- PHP (API)
- MySQL
- HTML/CSS/JavaScript thuần
- PHPMailer (gửi email)

## Cấu trúc thư mục
- `src/index.html`: trang chủ.
- `src/assets/`: CSS/JS dùng chung.
- `src/api/`: API theo vai trò (`auth`, `admin`, `doctor`, `patient`, `user`).
- `src/config/`: cấu hình DB, mail, CORS, session.
- `src/database/db.sql`: cấu trúc & dữ liệu mẫu database.
- `src/PHPMailer/`: thư viện gửi mail.

## Cài đặt & chạy (XAMPP)
1. Mở XAMPP, bật `Apache` và `MySQL`.
2. Import database:
   - Tạo database `datlichkham`.
   - Import file `src/database/db.sql`.
3. Kiểm tra cấu hình DB tại `src/config/dp.php`.
4. Cấu hình mail tại `src/config/mail.php`.
5. Truy cập:
   - Trang chủ: `http://localhost/DO_AN/src/index.html`
   - Các trang khác: mở trực tiếp các file HTML trong `src/`.

## Cấu hình quan trọng
- `src/config/dp.php`: thông tin kết nối MySQL.
- `src/config/mail.php`: tài khoản gửi mail (PHPMailer).
- `src/config/mail-notifications.php`: bật/tắt từng loại mail nghiệp vụ.
- `src/config/cors.php`: CORS cho API.
- `src/config/session.php`: cấu hình session.

## Mail Reminder Cron
- Script: `src/cron/send-appointment-reminders.php`
- Mục đích: gửi mail nhắc lịch trước 24h và 2h.
- Chạy thử thủ công:
  - `php src/cron/send-appointment-reminders.php`
- Trước khi chạy production, import migration:
  - `src/database/migrations/20260223_mail_notification_log.sql`

## Ghi chú
- Nên thay đổi thông tin mail và DB theo môi trường của bạn.
- Không commit thông tin nhạy cảm (email, password) lên repo công khai.
