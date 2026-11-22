-- Migration 004: Seed nhóm quyền nhân viên, gán quyền và tài khoản demo tác nghiệp (Task 3)
-- Idempotent: an toàn khi chạy lặp nhiều lần trên cả DB sạch và DB mẫu.
-- Mật khẩu demo cho các tài khoản nhân viên seed mới: 123456

SET NAMES utf8mb4;
SET time_zone = "+07:00";

-- =====================================================================
-- 1. DANH MỤC QUYỀN TÁC NGHIỆP NHÂN VIÊN (KHÔNG BAO GỒM QUYỀN QUẢN TRỊ)
-- =====================================================================

INSERT INTO `nhanvien_quyen` (`maQuyen`, `tenQuyen`, `nhomChucNang`, `moTa`, `isActive`) VALUES
('staff.dashboard.view', 'Xem trang tổng quan nhân viên', 'chung', 'Truy cập dashboard cổng tác nghiệp và thống kê theo phân quyền cá nhân', 1),
('reception.checkin', 'Tiếp nhận & check-in bệnh nhân', 'tiep_nhan', 'Tìm kiếm/tạo bệnh nhân, tạo lịch trực tiếp và check-in vào hàng đợi khám', 1),
('nursing.screen', 'Điều dưỡng sàng lọc trước khám', 'dieu_duong', 'Tiếp nhận bệnh nhân đã check-in, nhập sinh hiệu, dị ứng và chuyển hàng đợi bác sĩ', 1),
('cls.xet_nghiem.perform', 'Thực hiện & trả kết quả xét nghiệm', 'can_lam_sang', 'Nhận mẫu, cập nhật hàng đợi và nhập/trả kết quả nhóm xét nghiệm', 1),
('cls.cdha.perform', 'Thực hiện & trả kết quả chẩn đoán hình ảnh', 'can_lam_sang', 'Thực hiện X-quang, siêu âm, ECG, đính kèm ảnh và trả kết quả CĐHA', 1),
('cls.noi_soi.perform', 'Thực hiện & trả kết quả nội soi', 'can_lam_sang', 'Checklist chuẩn bị, thực hiện thủ thuật và nhập/trả kết quả nội soi', 1),
('pharmacy.dispense', 'Phát thuốc tại quầy dược', 'duoc', 'Tiếp nhận đơn chờ phát, kiểm tra tồn kho, phát thuốc đủ/một phần và trừ tồn nguyên tử', 1),
('billing.collect', 'Thu ngân & hóa đơn tổng hợp', 'thu_ngan', 'Tổng hợp phí khám, dịch vụ CLS, thuốc đã phát và ghi nhận thanh toán/hóa đơn', 1)
ON DUPLICATE KEY UPDATE
  `tenQuyen` = VALUES(`tenQuyen`),
  `nhomChucNang` = VALUES(`nhomChucNang`),
  `moTa` = VALUES(`moTa`),
  `isActive` = VALUES(`isActive`);

-- =====================================================================
-- 2. CÁC NHÓM QUYỀN NHÂN VIÊN (8 NHÓM)
-- =====================================================================

INSERT INTO `nhanvien_nhom_quyen` (`maNhomQuyen`, `tenNhomQuyen`, `moTa`, `isDemo`, `isActive`) VALUES
('tiep_nhan', 'Tiếp nhận / Lễ tân', 'Tiếp nhận bệnh nhân, đăng ký khám trực tiếp và check-in hàng đợi', 0, 1),
('dieu_duong', 'Điều dưỡng', 'Sàng lọc sinh hiệu, dị ứng và chuyển bệnh nhân sang hàng đợi bác sĩ', 0, 1),
('ktv_xet_nghiem', 'KTV Xét nghiệm', 'Thực hiện hàng đợi và trả kết quả nhóm xét nghiệm', 0, 1),
('ktv_cdha', 'KTV Chẩn đoán hình ảnh', 'Thực hiện hàng đợi và trả kết quả X-quang, siêu âm, ECG', 0, 1),
('ktv_noi_soi', 'KTV Nội soi / ĐD thủ thuật', 'Chuẩn bị, thực hiện và trả kết quả nhóm nội soi', 0, 1),
('duoc_si', 'Dược sĩ / Phát thuốc', 'Đối chiếu đơn thuốc, kiểm tra tồn kho và phát thuốc cho bệnh nhân', 0, 1),
('thu_ngan', 'Thu ngân', 'Tổng hợp chi phí khám, CLS, thuốc thực phát và xác nhận thanh toán', 0, 1),
('nhanvien_tong_hop_demo', 'Nhân viên tổng hợp (Demo)', 'Tài khoản demo có toàn bộ quyền tác nghiệp nhân viên (không bao gồm quyền quản trị hệ thống)', 1, 1)
ON DUPLICATE KEY UPDATE
  `tenNhomQuyen` = VALUES(`tenNhomQuyen`),
  `moTa` = VALUES(`moTa`),
  `isDemo` = VALUES(`isDemo`),
  `isActive` = VALUES(`isActive`);

-- =====================================================================
-- 3. ÁNH XẠ NHÓM QUYỀN -> PERMISSION
-- =====================================================================

INSERT IGNORE INTO `nhanvien_nhom_quyen_chitiet` (`maNhomQuyen`, `maQuyen`) VALUES
-- Tiếp nhận
('tiep_nhan', 'staff.dashboard.view'),
('tiep_nhan', 'reception.checkin'),
-- Điều dưỡng
('dieu_duong', 'staff.dashboard.view'),
('dieu_duong', 'nursing.screen'),
-- KTV Xét nghiệm
('ktv_xet_nghiem', 'staff.dashboard.view'),
('ktv_xet_nghiem', 'cls.xet_nghiem.perform'),
-- KTV CĐHA
('ktv_cdha', 'staff.dashboard.view'),
('ktv_cdha', 'cls.cdha.perform'),
-- KTV Nội soi
('ktv_noi_soi', 'staff.dashboard.view'),
('ktv_noi_soi', 'cls.noi_soi.perform'),
-- Dược sĩ
('duoc_si', 'staff.dashboard.view'),
('duoc_si', 'pharmacy.dispense'),
-- Thu ngân
('thu_ngan', 'staff.dashboard.view'),
('thu_ngan', 'billing.collect'),
-- Nhân viên tổng hợp demo (toàn bộ quyền tác nghiệp nhân viên, không có quyền quản trị)
('nhanvien_tong_hop_demo', 'staff.dashboard.view'),
('nhanvien_tong_hop_demo', 'reception.checkin'),
('nhanvien_tong_hop_demo', 'nursing.screen'),
('nhanvien_tong_hop_demo', 'cls.xet_nghiem.perform'),
('nhanvien_tong_hop_demo', 'cls.cdha.perform'),
('nhanvien_tong_hop_demo', 'cls.noi_soi.perform'),
('nhanvien_tong_hop_demo', 'pharmacy.dispense'),
('nhanvien_tong_hop_demo', 'billing.collect');

-- =====================================================================
-- 4. GÁN NHÓM QUYỀN CHO NHÂN VIÊN TIẾP NHẬN HIỆN CÓ
-- =====================================================================

INSERT IGNORE INTO `nhanvien_gan_nhom_quyen` (`maNhanVien`, `maNhomQuyen`, `isActive`)
SELECT `maNhanVien`, 'tiep_nhan', 1
FROM `nhanvien`
WHERE `loaiNhanVien` = 'tiepnhan';

-- =====================================================================
-- 5. SEED TÀI KHOẢN DEMO CHO TỪNG CHUYÊN MÔN NHÂN VIÊN (MẬT KHẨU: 123456)
--    Giữ nguyên nguoidung.vaiTro = 'nhanvien'
-- =====================================================================

INSERT IGNORE INTO `nguoidung` (`tenDangNhap`, `matKhau`, `soDienThoai`, `email`, `vaiTro`, `trangThai`, `taiKhoanTamThoi`, `isDeleted`) VALUES
('dieuduong1', '$2y$10$Q4C1EVAzKIs48840fj.w6.3GocHtpJ9YFgw400bfIUjj1FO/VYn46', '0901000101', 'dieuduong1@edenhealth.local', 'nhanvien', 'Hoạt Động', 0, 0),
('xetnghiem1', '$2y$10$Q4C1EVAzKIs48840fj.w6.3GocHtpJ9YFgw400bfIUjj1FO/VYn46', '0901000102', 'xetnghiem1@edenhealth.local', 'nhanvien', 'Hoạt Động', 0, 0),
('cdha1', '$2y$10$Q4C1EVAzKIs48840fj.w6.3GocHtpJ9YFgw400bfIUjj1FO/VYn46', '0901000103', 'cdha1@edenhealth.local', 'nhanvien', 'Hoạt Động', 0, 0),
('noisoi1', '$2y$10$Q4C1EVAzKIs48840fj.w6.3GocHtpJ9YFgw400bfIUjj1FO/VYn46', '0901000104', 'noisoi1@edenhealth.local', 'nhanvien', 'Hoạt Động', 0, 0),
('duocsi1', '$2y$10$Q4C1EVAzKIs48840fj.w6.3GocHtpJ9YFgw400bfIUjj1FO/VYn46', '0901000105', 'duocsi1@edenhealth.local', 'nhanvien', 'Hoạt Động', 0, 0),
('thungan1', '$2y$10$Q4C1EVAzKIs48840fj.w6.3GocHtpJ9YFgw400bfIUjj1FO/VYn46', '0901000106', 'thungan1@edenhealth.local', 'nhanvien', 'Hoạt Động', 0, 0),
('nhanvien_demo', '$2y$10$Q4C1EVAzKIs48840fj.w6.3GocHtpJ9YFgw400bfIUjj1FO/VYn46', '0901000107', 'nhanvien_demo@edenhealth.local', 'nhanvien', 'Hoạt Động', 0, 0);

INSERT IGNORE INTO `nhanvien` (`nguoiDungId`, `maNhanVien`, `tenNhanVien`, `loaiNhanVien`, `gioiTinh`, `ngayVaoLam`)
SELECT nd.`id`, 'NV_DD_001', 'Trần Thị Điều Dưỡng', 'dieuduong', 'nu', '2026-08-15'
FROM `nguoidung` nd WHERE nd.`tenDangNhap` = 'dieuduong1';

INSERT IGNORE INTO `nhanvien` (`nguoiDungId`, `maNhanVien`, `tenNhanVien`, `loaiNhanVien`, `gioiTinh`, `ngayVaoLam`)
SELECT nd.`id`, 'NV_XN_001', 'Lê Văn Xét Nghiệm', 'xetnghiem', 'nam', '2026-08-15'
FROM `nguoidung` nd WHERE nd.`tenDangNhap` = 'xetnghiem1';

INSERT IGNORE INTO `nhanvien` (`nguoiDungId`, `maNhanVien`, `tenNhanVien`, `loaiNhanVien`, `gioiTinh`, `ngayVaoLam`)
SELECT nd.`id`, 'NV_CDHA_001', 'Phạm Minh Hình Ảnh', 'cdha', 'nam', '2026-08-15'
FROM `nguoidung` nd WHERE nd.`tenDangNhap` = 'cdha1';

INSERT IGNORE INTO `nhanvien` (`nguoiDungId`, `maNhanVien`, `tenNhanVien`, `loaiNhanVien`, `gioiTinh`, `ngayVaoLam`)
SELECT nd.`id`, 'NV_NS_001', 'Hoàng Thị Nội Soi', 'noisoi', 'nu', '2026-08-15'
FROM `nguoidung` nd WHERE nd.`tenDangNhap` = 'noisoi1';

INSERT IGNORE INTO `nhanvien` (`nguoiDungId`, `maNhanVien`, `tenNhanVien`, `loaiNhanVien`, `gioiTinh`, `ngayVaoLam`)
SELECT nd.`id`, 'NV_DS_001', 'Nguyễn Thị Dược Sĩ', 'duocsi', 'nu', '2026-08-15'
FROM `nguoidung` nd WHERE nd.`tenDangNhap` = 'duocsi1';

INSERT IGNORE INTO `nhanvien` (`nguoiDungId`, `maNhanVien`, `tenNhanVien`, `loaiNhanVien`, `gioiTinh`, `ngayVaoLam`)
SELECT nd.`id`, 'NV_TN_001', 'Võ Văn Thu Ngân', 'thungan', 'nam', '2026-08-15'
FROM `nguoidung` nd WHERE nd.`tenDangNhap` = 'thungan1';

INSERT IGNORE INTO `nhanvien` (`nguoiDungId`, `maNhanVien`, `tenNhanVien`, `loaiNhanVien`, `gioiTinh`, `ngayVaoLam`)
SELECT nd.`id`, 'NV_DEMO_001', 'Nhân Viên Tổng Hợp Demo', 'tonghop_demo', 'nam', '2026-08-15'
FROM `nguoidung` nd WHERE nd.`tenDangNhap` = 'nhanvien_demo';

INSERT IGNORE INTO `nhanvien_gan_nhom_quyen` (`maNhanVien`, `maNhomQuyen`, `isActive`) VALUES
('NV_DD_001', 'dieu_duong', 1),
('NV_XN_001', 'ktv_xet_nghiem', 1),
('NV_CDHA_001', 'ktv_cdha', 1),
('NV_NS_001', 'ktv_noi_soi', 1),
('NV_DS_001', 'duoc_si', 1),
('NV_TN_001', 'thu_ngan', 1),
('NV_DEMO_001', 'nhanvien_tong_hop_demo', 1);
