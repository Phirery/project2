-- Migration 003: Phân quyền nhân viên (RBAC), sàng lọc điều dưỡng, chỉ định/kết quả/hàng đợi CLS và phát thuốc (Task 2)
-- Idempotent: có thể chạy lặp an toàn trên DB sạch lẫn DB đã có dữ liệu mẫu.
-- Lưu ý: KHÔNG thay đổi ENUM `nguoidung.vaiTro`; chuyên môn nhân viên quản lý qua bảng nhóm quyền.

SET NAMES utf8mb4;
SET time_zone = "+07:00";

-- =====================================================================
-- 1. NHÓM QUYỀN & PHÂN QUYỀN NHÂN VIÊN (RBAC)
-- =====================================================================

CREATE TABLE IF NOT EXISTS `nhanvien_nhom_quyen` (
  `maNhomQuyen` varchar(50) NOT NULL,
  `tenNhomQuyen` varchar(100) NOT NULL,
  `moTa` text DEFAULT NULL,
  `isDemo` tinyint(1) NOT NULL DEFAULT 0,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`maNhomQuyen`),
  KEY `idx_nvnq_active` (`isActive`, `isDemo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `nhanvien_quyen` (
  `maQuyen` varchar(64) NOT NULL,
  `tenQuyen` varchar(120) NOT NULL,
  `nhomChucNang` varchar(50) NOT NULL,
  `moTa` text DEFAULT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`maQuyen`),
  KEY `idx_nvq_nhom_active` (`nhomChucNang`, `isActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `nhanvien_nhom_quyen_chitiet` (
  `maNhomQuyen` varchar(50) NOT NULL,
  `maQuyen` varchar(64) NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`maNhomQuyen`, `maQuyen`),
  KEY `idx_nvnqct_quyen` (`maQuyen`),
  CONSTRAINT `fk_nvnqct_nhom` FOREIGN KEY (`maNhomQuyen`) REFERENCES `nhanvien_nhom_quyen` (`maNhomQuyen`) ON DELETE CASCADE,
  CONSTRAINT `fk_nvnqct_quyen` FOREIGN KEY (`maQuyen`) REFERENCES `nhanvien_quyen` (`maQuyen`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `nhanvien_gan_nhom_quyen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `maNhanVien` varchar(20) NOT NULL,
  `maNhomQuyen` varchar(50) NOT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `assignedBy` int(11) DEFAULT NULL,
  `assignedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nv_nhom_quyen` (`maNhanVien`, `maNhomQuyen`),
  KEY `idx_nvgnq_nhom_active` (`maNhomQuyen`, `isActive`),
  KEY `idx_nvgnq_nv_active` (`maNhanVien`, `isActive`),
  KEY `fk_nvgnq_assigned_by` (`assignedBy`),
  CONSTRAINT `fk_nvgnq_nhanvien` FOREIGN KEY (`maNhanVien`) REFERENCES `nhanvien` (`maNhanVien`) ON DELETE CASCADE,
  CONSTRAINT `fk_nvgnq_nhom` FOREIGN KEY (`maNhomQuyen`) REFERENCES `nhanvien_nhom_quyen` (`maNhomQuyen`) ON DELETE CASCADE,
  CONSTRAINT `fk_nvgnq_assigned_by` FOREIGN KEY (`assignedBy`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================================
-- 2. SÀNG LỌC ĐIỀU DƯỠNG TRƯỚC KHÁM
-- =====================================================================

CREATE TABLE IF NOT EXISTS `sang_loc_dieu_duong` (
  `maSangLoc` int(11) NOT NULL AUTO_INCREMENT,
  `maHangDoi` int(11) NOT NULL,
  `maLichKham` int(11) NOT NULL,
  `maBenhNhan` varchar(20) NOT NULL,
  `maNhanVienSangLoc` varchar(20) NOT NULL,
  `mach` smallint(5) UNSIGNED DEFAULT NULL COMMENT 'Nhịp tim (lần/phút)',
  `huyetApTamThu` smallint(5) UNSIGNED DEFAULT NULL COMMENT 'mmHg',
  `huyetApTamTruong` smallint(5) UNSIGNED DEFAULT NULL COMMENT 'mmHg',
  `nhietDo` decimal(4,1) DEFAULT NULL COMMENT 'Độ C',
  `spo2` tinyint(3) UNSIGNED DEFAULT NULL COMMENT 'Phần trăm SpO2',
  `nhipTho` tinyint(3) UNSIGNED DEFAULT NULL COMMENT 'Nhịp thở (lần/phút)',
  `canNang` decimal(5,2) DEFAULT NULL COMMENT 'kg',
  `chieuCao` decimal(5,2) DEFAULT NULL COMMENT 'cm',
  `diUng` text DEFAULT NULL,
  `canhBaoLamSang` text DEFAULT NULL,
  `ghiChu` text DEFAULT NULL,
  `trangThai` enum('Chờ sàng lọc','Đã sàng lọc','Đã chuyển bác sĩ') NOT NULL DEFAULT 'Đã sàng lọc',
  `thoiGianSangLoc` datetime NOT NULL DEFAULT current_timestamp(),
  `thoiGianChuyenBacSi` datetime DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`maSangLoc`),
  UNIQUE KEY `uq_sldd_hangdoi` (`maHangDoi`),
  KEY `idx_sldd_lichkham` (`maLichKham`),
  KEY `idx_sldd_benhnhan_time` (`maBenhNhan`, `thoiGianSangLoc`),
  KEY `idx_sldd_nv` (`maNhanVienSangLoc`, `thoiGianSangLoc`),
  CONSTRAINT `fk_sldd_hangdoi` FOREIGN KEY (`maHangDoi`) REFERENCES `hangdoikham` (`maHangDoi`) ON DELETE CASCADE,
  CONSTRAINT `fk_sldd_lichkham` FOREIGN KEY (`maLichKham`) REFERENCES `lichkham` (`maLichKham`) ON DELETE CASCADE,
  CONSTRAINT `fk_sldd_benhnhan` FOREIGN KEY (`maBenhNhan`) REFERENCES `benhnhan` (`maBenhNhan`) ON DELETE CASCADE,
  CONSTRAINT `fk_sldd_nhanvien` FOREIGN KEY (`maNhanVienSangLoc`) REFERENCES `nhanvien` (`maNhanVien`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================================
-- 3. CHỈ ĐỊNH, CHI TIẾT, HÀNG ĐỢI, KẾT QUẢ & LỊCH SỬ CẬN LÂM SÀNG
-- =====================================================================

CREATE TABLE IF NOT EXISTS `chi_dinh_can_lam_sang` (
  `maChiDinh` int(11) NOT NULL AUTO_INCREMENT,
  `maPhieuChiDinh` varchar(32) NOT NULL,
  `maHoSo` varchar(20) NOT NULL,
  `maLichKham` int(11) DEFAULT NULL,
  `maBenhNhan` varchar(20) NOT NULL,
  `maBacSiChiDinh` varchar(20) NOT NULL,
  `doUuTien` enum('Bình thường','Ưu tiên','Khẩn') NOT NULL DEFAULT 'Bình thường',
  `ghiChuLamSang` text DEFAULT NULL,
  `trangThai` enum('Mới tạo','Đang chờ','Đang thực hiện','Có kết quả','Đã duyệt','Hủy') NOT NULL DEFAULT 'Mới tạo',
  `lyDoHuy` text DEFAULT NULL,
  `cancelledBy` int(11) DEFAULT NULL,
  `cancelledAt` datetime DEFAULT NULL,
  `createdBy` int(11) DEFAULT NULL,
  `updatedBy` int(11) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`maChiDinh`),
  UNIQUE KEY `uq_cdcls_maPhieu` (`maPhieuChiDinh`),
  KEY `idx_cdcls_hoso_status` (`maHoSo`, `trangThai`),
  KEY `idx_cdcls_lichkham` (`maLichKham`),
  KEY `idx_cdcls_benhnhan_created` (`maBenhNhan`, `createdAt`),
  KEY `idx_cdcls_bacsi_created` (`maBacSiChiDinh`, `createdAt`),
  KEY `fk_cdcls_cancelled_by` (`cancelledBy`),
  KEY `fk_cdcls_created_by` (`createdBy`),
  KEY `fk_cdcls_updated_by` (`updatedBy`),
  CONSTRAINT `fk_cdcls_hoso` FOREIGN KEY (`maHoSo`) REFERENCES `hosobenhan` (`maHoSo`) ON DELETE CASCADE,
  CONSTRAINT `fk_cdcls_lichkham` FOREIGN KEY (`maLichKham`) REFERENCES `lichkham` (`maLichKham`) ON DELETE SET NULL,
  CONSTRAINT `fk_cdcls_benhnhan` FOREIGN KEY (`maBenhNhan`) REFERENCES `benhnhan` (`maBenhNhan`) ON DELETE CASCADE,
  CONSTRAINT `fk_cdcls_bacsi` FOREIGN KEY (`maBacSiChiDinh`) REFERENCES `bacsi` (`maBacSi`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cdcls_cancelled_by` FOREIGN KEY (`cancelledBy`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cdcls_created_by` FOREIGN KEY (`createdBy`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cdcls_updated_by` FOREIGN KEY (`updatedBy`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `chi_dinh_chi_tiet` (
  `maChiTietChiDinh` int(11) NOT NULL AUTO_INCREMENT,
  `maChiDinh` int(11) NOT NULL,
  `maHoSo` varchar(20) NOT NULL,
  `maDichVu` int(11) NOT NULL,
  `nhomDichVu` enum('xet-nghiem','chan-doan-hinh-anh','noi-soi') NOT NULL,
  `donGia` decimal(12,2) NOT NULL DEFAULT 0.00,
  `batBuoc` tinyint(1) NOT NULL DEFAULT 1,
  `doUuTien` enum('Bình thường','Ưu tiên','Khẩn') NOT NULL DEFAULT 'Bình thường',
  `ghiChu` text DEFAULT NULL,
  `trangThai` enum('Mới tạo','Đang chờ','Đang thực hiện','Có kết quả','Đã duyệt','Hủy') NOT NULL DEFAULT 'Mới tạo',
  `maNhanVienXuLy` varchar(20) DEFAULT NULL,
  `maBacSiDuyet` varchar(20) DEFAULT NULL,
  `thoiGianDuyet` datetime DEFAULT NULL,
  `lyDoHuy` text DEFAULT NULL,
  `cancelledBy` int(11) DEFAULT NULL,
  `cancelledAt` datetime DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`maChiTietChiDinh`),
  KEY `idx_cdct_chidinh_status` (`maChiDinh`, `trangThai`),
  KEY `idx_cdct_hoso_status_req` (`maHoSo`, `trangThai`, `batBuoc`),
  KEY `idx_cdct_nhom_status` (`nhomDichVu`, `trangThai`),
  KEY `idx_cdct_dichvu` (`maDichVu`),
  KEY `idx_cdct_nv_xuly` (`maNhanVienXuLy`),
  KEY `idx_cdct_bs_duyet` (`maBacSiDuyet`),
  KEY `fk_cdct_cancelled_by` (`cancelledBy`),
  CONSTRAINT `fk_cdct_chidinh` FOREIGN KEY (`maChiDinh`) REFERENCES `chi_dinh_can_lam_sang` (`maChiDinh`) ON DELETE CASCADE,
  CONSTRAINT `fk_cdct_hoso` FOREIGN KEY (`maHoSo`) REFERENCES `hosobenhan` (`maHoSo`) ON DELETE CASCADE,
  CONSTRAINT `fk_cdct_dichvu` FOREIGN KEY (`maDichVu`) REFERENCES `dichvu_can_lam_sang` (`maDichVu`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cdct_nv_xuly` FOREIGN KEY (`maNhanVienXuLy`) REFERENCES `nhanvien` (`maNhanVien`) ON DELETE SET NULL,
  CONSTRAINT `fk_cdct_bs_duyet` FOREIGN KEY (`maBacSiDuyet`) REFERENCES `bacsi` (`maBacSi`) ON DELETE SET NULL,
  CONSTRAINT `fk_cdct_cancelled_by` FOREIGN KEY (`cancelledBy`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `hangdoi_dichvu` (
  `maHangDoiDichVu` int(11) NOT NULL AUTO_INCREMENT,
  `maChiTietChiDinh` int(11) NOT NULL,
  `maChiDinh` int(11) NOT NULL,
  `maHoSo` varchar(20) NOT NULL,
  `maBenhNhan` varchar(20) NOT NULL,
  `maDichVu` int(11) NOT NULL,
  `nhomDichVu` enum('xet-nghiem','chan-doan-hinh-anh','noi-soi') NOT NULL,
  `ngay` date NOT NULL,
  `soThuTu` int(11) NOT NULL,
  `doUuTien` enum('Bình thường','Ưu tiên','Khẩn') NOT NULL DEFAULT 'Bình thường',
  `trangThai` enum('Đang chờ','Đang thực hiện','Hoàn thành','Bỏ lỡ','Hủy') NOT NULL DEFAULT 'Đang chờ',
  `maNhanVienTiepNhan` varchar(20) DEFAULT NULL,
  `maNhanVienThucHien` varchar(20) DEFAULT NULL,
  `thoiGianVaoHangDoi` datetime NOT NULL DEFAULT current_timestamp(),
  `thoiGianGoi` datetime DEFAULT NULL,
  `thoiGianBatDau` datetime DEFAULT NULL,
  `thoiGianHoanThanh` datetime DEFAULT NULL,
  `ghiChu` text DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`maHangDoiDichVu`),
  UNIQUE KEY `uq_hddv_chitiet` (`maChiTietChiDinh`),
  UNIQUE KEY `uq_hddv_nhom_ngay_stt` (`nhomDichVu`, `ngay`, `soThuTu`),
  KEY `idx_hddv_trangthai_ngay_nhom` (`trangThai`, `ngay`, `nhomDichVu`, `soThuTu`),
  KEY `idx_hddv_nhom_ngay_trangthai` (`nhomDichVu`, `ngay`, `trangThai`, `doUuTien`, `soThuTu`),
  KEY `idx_hddv_benhnhan_ngay` (`maBenhNhan`, `ngay`),
  KEY `idx_hddv_hoso` (`maHoSo`),
  KEY `fk_hddv_chidinh` (`maChiDinh`),
  KEY `fk_hddv_dichvu` (`maDichVu`),
  KEY `fk_hddv_nv_tiepnhan` (`maNhanVienTiepNhan`),
  KEY `fk_hddv_nv_thuchien` (`maNhanVienThucHien`),
  CONSTRAINT `fk_hddv_chitiet` FOREIGN KEY (`maChiTietChiDinh`) REFERENCES `chi_dinh_chi_tiet` (`maChiTietChiDinh`) ON DELETE CASCADE,
  CONSTRAINT `fk_hddv_chidinh` FOREIGN KEY (`maChiDinh`) REFERENCES `chi_dinh_can_lam_sang` (`maChiDinh`) ON DELETE CASCADE,
  CONSTRAINT `fk_hddv_hoso` FOREIGN KEY (`maHoSo`) REFERENCES `hosobenhan` (`maHoSo`) ON DELETE CASCADE,
  CONSTRAINT `fk_hddv_benhnhan` FOREIGN KEY (`maBenhNhan`) REFERENCES `benhnhan` (`maBenhNhan`) ON DELETE CASCADE,
  CONSTRAINT `fk_hddv_dichvu` FOREIGN KEY (`maDichVu`) REFERENCES `dichvu_can_lam_sang` (`maDichVu`) ON DELETE RESTRICT,
  CONSTRAINT `fk_hddv_nv_tiepnhan` FOREIGN KEY (`maNhanVienTiepNhan`) REFERENCES `nhanvien` (`maNhanVien`) ON DELETE SET NULL,
  CONSTRAINT `fk_hddv_nv_thuchien` FOREIGN KEY (`maNhanVienThucHien`) REFERENCES `nhanvien` (`maNhanVien`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ket_qua_can_lam_sang` (
  `maKetQua` int(11) NOT NULL AUTO_INCREMENT,
  `maChiTietChiDinh` int(11) NOT NULL,
  `maChiDinh` int(11) NOT NULL,
  `maHoSo` varchar(20) NOT NULL,
  `maBenhNhan` varchar(20) NOT NULL,
  `maDichVu` int(11) NOT NULL,
  `trangThai` enum('Nháp','Chờ duyệt','Đã duyệt','Yêu cầu làm lại') NOT NULL DEFAULT 'Nháp',
  `chiSoKetQua` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (`chiSoKetQua` IS NULL OR json_valid(`chiSoKetQua`)),
  `ketLuan` text DEFAULT NULL,
  `nhanXet` text DEFAULT NULL,
  `deNghi` text DEFAULT NULL,
  `tepDinhKem` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (`tepDinhKem` IS NULL OR json_valid(`tepDinhKem`)),
  `maNhanVienThucHien` varchar(20) NOT NULL,
  `thoiGianTraKetQua` datetime DEFAULT NULL,
  `maBacSiDuyet` varchar(20) DEFAULT NULL,
  `thoiGianDuyet` datetime DEFAULT NULL,
  `lyDoYeuCauLamLai` text DEFAULT NULL,
  `phienBan` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`maKetQua`),
  UNIQUE KEY `uq_kqcls_chitiet` (`maChiTietChiDinh`),
  KEY `idx_kqcls_hoso_status` (`maHoSo`, `trangThai`),
  KEY `idx_kqcls_benhnhan_status` (`maBenhNhan`, `trangThai`),
  KEY `idx_kqcls_nv_time` (`maNhanVienThucHien`, `thoiGianTraKetQua`),
  KEY `fk_kqcls_chidinh` (`maChiDinh`),
  KEY `fk_kqcls_dichvu` (`maDichVu`),
  KEY `fk_kqcls_bs_duyet` (`maBacSiDuyet`),
  CONSTRAINT `fk_kqcls_chitiet` FOREIGN KEY (`maChiTietChiDinh`) REFERENCES `chi_dinh_chi_tiet` (`maChiTietChiDinh`) ON DELETE CASCADE,
  CONSTRAINT `fk_kqcls_chidinh` FOREIGN KEY (`maChiDinh`) REFERENCES `chi_dinh_can_lam_sang` (`maChiDinh`) ON DELETE CASCADE,
  CONSTRAINT `fk_kqcls_hoso` FOREIGN KEY (`maHoSo`) REFERENCES `hosobenhan` (`maHoSo`) ON DELETE CASCADE,
  CONSTRAINT `fk_kqcls_benhnhan` FOREIGN KEY (`maBenhNhan`) REFERENCES `benhnhan` (`maBenhNhan`) ON DELETE CASCADE,
  CONSTRAINT `fk_kqcls_dichvu` FOREIGN KEY (`maDichVu`) REFERENCES `dichvu_can_lam_sang` (`maDichVu`) ON DELETE RESTRICT,
  CONSTRAINT `fk_kqcls_nv_thuchien` FOREIGN KEY (`maNhanVienThucHien`) REFERENCES `nhanvien` (`maNhanVien`) ON DELETE RESTRICT,
  CONSTRAINT `fk_kqcls_bs_duyet` FOREIGN KEY (`maBacSiDuyet`) REFERENCES `bacsi` (`maBacSi`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ket_qua_lich_su` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `maKetQua` int(11) DEFAULT NULL,
  `maChiTietChiDinh` int(11) NOT NULL,
  `phienBan` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `hanhDong` varchar(50) NOT NULL,
  `trangThaiCu` varchar(50) DEFAULT NULL,
  `trangThaiMoi` varchar(50) NOT NULL,
  `duLieuSnapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (`duLieuSnapshot` IS NULL OR json_valid(`duLieuSnapshot`)),
  `ghiChu` text DEFAULT NULL,
  `actorUserId` int(11) DEFAULT NULL,
  `actorMaNhanVien` varchar(20) DEFAULT NULL,
  `actorMaBacSi` varchar(20) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kqls_chitiet_time` (`maChiTietChiDinh`, `createdAt`),
  KEY `idx_kqls_ketqua_ver` (`maKetQua`, `phienBan`),
  KEY `idx_kqls_actor_user` (`actorUserId`),
  CONSTRAINT `fk_kqls_ketqua` FOREIGN KEY (`maKetQua`) REFERENCES `ket_qua_can_lam_sang` (`maKetQua`) ON DELETE SET NULL,
  CONSTRAINT `fk_kqls_chitiet` FOREIGN KEY (`maChiTietChiDinh`) REFERENCES `chi_dinh_chi_tiet` (`maChiTietChiDinh`) ON DELETE CASCADE,
  CONSTRAINT `fk_kqls_actor_user` FOREIGN KEY (`actorUserId`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================================
-- 4. PHIẾU PHÁT THUỐC & CHI TIẾT PHÁT THUỐC
-- =====================================================================

CREATE TABLE IF NOT EXISTS `phieu_phat_thuoc` (
  `maPhieuPhat` int(11) NOT NULL AUTO_INCREMENT,
  `maPhieuCode` varchar(32) NOT NULL,
  `maDonThuoc` int(11) NOT NULL,
  `maLichKham` int(11) DEFAULT NULL,
  `maBenhNhan` varchar(20) NOT NULL,
  `maDuocSi` varchar(20) NOT NULL,
  `nguoiPhatUserId` int(11) DEFAULT NULL,
  `clientRequestId` varchar(64) DEFAULT NULL,
  `loaiPhat` enum('Đủ','Một phần') NOT NULL DEFAULT 'Đủ',
  `trangThai` enum('Hoàn thành','Hủy') NOT NULL DEFAULT 'Hoàn thành',
  `tongTienPhat` decimal(12,2) NOT NULL DEFAULT 0.00,
  `huongDanSuDung` text DEFAULT NULL,
  `ghiChu` text DEFAULT NULL,
  `thoiGianPhat` datetime NOT NULL DEFAULT current_timestamp(),
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`maPhieuPhat`),
  UNIQUE KEY `uq_ppt_maPhieuCode` (`maPhieuCode`),
  UNIQUE KEY `uq_ppt_clientRequestId` (`clientRequestId`),
  KEY `idx_ppt_donthuoc_time` (`maDonThuoc`, `thoiGianPhat`),
  KEY `idx_ppt_duocsi_time` (`maDuocSi`, `thoiGianPhat`),
  KEY `idx_ppt_benhnhan_time` (`maBenhNhan`, `thoiGianPhat`),
  KEY `fk_ppt_lichkham` (`maLichKham`),
  KEY `fk_ppt_user` (`nguoiPhatUserId`),
  CONSTRAINT `fk_ppt_donthuoc` FOREIGN KEY (`maDonThuoc`) REFERENCES `donthuoc` (`maDonThuoc`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ppt_lichkham` FOREIGN KEY (`maLichKham`) REFERENCES `lichkham` (`maLichKham`) ON DELETE SET NULL,
  CONSTRAINT `fk_ppt_benhnhan` FOREIGN KEY (`maBenhNhan`) REFERENCES `benhnhan` (`maBenhNhan`) ON DELETE CASCADE,
  CONSTRAINT `fk_ppt_duocsi` FOREIGN KEY (`maDuocSi`) REFERENCES `nhanvien` (`maNhanVien`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ppt_user` FOREIGN KEY (`nguoiPhatUserId`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `phieu_phat_thuoc_chitiet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `maPhieuPhat` int(11) NOT NULL,
  `chiTietDonThuocId` int(11) NOT NULL,
  `maThuoc` int(11) NOT NULL,
  `soLuongKe` int(11) NOT NULL,
  `soLuongThucPhat` int(11) NOT NULL,
  `donGia` decimal(10,2) NOT NULL DEFAULT 0.00,
  `thanhTien` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tonTruocPhat` int(11) NOT NULL,
  `tonSauPhat` int(11) NOT NULL,
  `ghiChu` text DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pptct_phieu_dong` (`maPhieuPhat`, `chiTietDonThuocId`),
  KEY `idx_pptct_thuoc` (`maThuoc`),
  KEY `idx_pptct_ctdt` (`chiTietDonThuocId`),
  CONSTRAINT `fk_pptct_phieuphat` FOREIGN KEY (`maPhieuPhat`) REFERENCES `phieu_phat_thuoc` (`maPhieuPhat`) ON DELETE CASCADE,
  CONSTRAINT `fk_pptct_ctdt` FOREIGN KEY (`chiTietDonThuocId`) REFERENCES `chitietdonthuoc` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pptct_thuoc` FOREIGN KEY (`maThuoc`) REFERENCES `thuoc` (`maThuoc`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================================
-- 5. MỞ RỘNG AN TOÀN CÁC BẢNG HIỆN HỮU (hosobenhan, donthuoc, chitietdonthuoc)
--    Dùng INFORMATION_SCHEMA + PREPARE/EXECUTE (không dùng stored procedure)
--    để tương thích mọi môi trường MariaDB/MySQL và chạy lặp an toàn.
-- =====================================================================

-- 5.1. hosobenhan: trangThaiQuyTrinh, updatedAt, updatedBy + index + FK
SET @has_hsba_qt := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hosobenhan' AND COLUMN_NAME = 'trangThaiQuyTrinh'
);
SET @sql := IF(
  @has_hsba_qt = 0,
  "ALTER TABLE `hosobenhan` ADD COLUMN `trangThaiQuyTrinh` enum('Đang khám','Chờ thực hiện CLS','Chờ kết quả','Chờ bác sĩ kết luận','Hoàn tất','Hủy') NOT NULL DEFAULT 'Đang khám' AFTER `trangThai`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @has_hsba_qt = 0,
  "UPDATE `hosobenhan` SET `trangThaiQuyTrinh` = 'Hoàn tất' WHERE `trangThai` = 'Đã hoàn thành'",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_hsba_ua := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hosobenhan' AND COLUMN_NAME = 'updatedAt'
);
SET @sql := IF(
  @has_hsba_ua = 0,
  "ALTER TABLE `hosobenhan` ADD COLUMN `updatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `ngayHoanThanh`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_hsba_ub := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hosobenhan' AND COLUMN_NAME = 'updatedBy'
);
SET @sql := IF(
  @has_hsba_ub = 0,
  "ALTER TABLE `hosobenhan` ADD COLUMN `updatedBy` int(11) DEFAULT NULL AFTER `updatedAt`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hosobenhan' AND INDEX_NAME = 'idx_hsba_bacsi_quytrinh'
);
SET @sql := IF(
  @has_idx = 0,
  "ALTER TABLE `hosobenhan` ADD KEY `idx_hsba_bacsi_quytrinh` (`maBacSi`, `trangThaiQuyTrinh`, `isDeleted`)",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hosobenhan' AND INDEX_NAME = 'fk_hsba_updated_by'
);
SET @sql := IF(
  @has_idx = 0,
  "ALTER TABLE `hosobenhan` ADD KEY `fk_hsba_updated_by` (`updatedBy`)",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'hosobenhan' AND CONSTRAINT_NAME = 'fk_hsba_updated_by' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(
  @has_fk = 0,
  "ALTER TABLE `hosobenhan` ADD CONSTRAINT `fk_hsba_updated_by` FOREIGN KEY (`updatedBy`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5.2. donthuoc: trangThaiPhat, lyDoHuy, cancelledBy, cancelledAt, updatedAt + index + FK
SET @has_dt_ttp := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donthuoc' AND COLUMN_NAME = 'trangThaiPhat'
);
SET @sql := IF(
  @has_dt_ttp = 0,
  "ALTER TABLE `donthuoc` ADD COLUMN `trangThaiPhat` enum('Đã kê','Chờ phát','Đã phát một phần','Đã phát đủ','Hủy') NOT NULL DEFAULT 'Đã kê' AFTER `tongTienThuoc`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_dt_ldh := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donthuoc' AND COLUMN_NAME = 'lyDoHuy'
);
SET @sql := IF(
  @has_dt_ldh = 0,
  "ALTER TABLE `donthuoc` ADD COLUMN `lyDoHuy` text DEFAULT NULL AFTER `trangThaiPhat`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_dt_cb := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donthuoc' AND COLUMN_NAME = 'cancelledBy'
);
SET @sql := IF(
  @has_dt_cb = 0,
  "ALTER TABLE `donthuoc` ADD COLUMN `cancelledBy` int(11) DEFAULT NULL AFTER `lyDoHuy`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_dt_ca := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donthuoc' AND COLUMN_NAME = 'cancelledAt'
);
SET @sql := IF(
  @has_dt_ca = 0,
  "ALTER TABLE `donthuoc` ADD COLUMN `cancelledAt` datetime DEFAULT NULL AFTER `cancelledBy`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_dt_ua := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donthuoc' AND COLUMN_NAME = 'updatedAt'
);
SET @sql := IF(
  @has_dt_ua = 0,
  "ALTER TABLE `donthuoc` ADD COLUMN `updatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `cancelledAt`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donthuoc' AND INDEX_NAME = 'idx_donthuoc_trangthaiphat'
);
SET @sql := IF(
  @has_idx = 0,
  "ALTER TABLE `donthuoc` ADD KEY `idx_donthuoc_trangthaiphat` (`trangThaiPhat`, `ngayKeDon`)",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donthuoc' AND INDEX_NAME = 'fk_donthuoc_cancelled_by'
);
SET @sql := IF(
  @has_idx = 0,
  "ALTER TABLE `donthuoc` ADD KEY `fk_donthuoc_cancelled_by` (`cancelledBy`)",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'donthuoc' AND CONSTRAINT_NAME = 'fk_donthuoc_cancelled_by' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(
  @has_fk = 0,
  "ALTER TABLE `donthuoc` ADD CONSTRAINT `fk_donthuoc_cancelled_by` FOREIGN KEY (`cancelledBy`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5.3. chitietdonthuoc: soLuongDaPhat
SET @has_ctdt_sdp := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chitietdonthuoc' AND COLUMN_NAME = 'soLuongDaPhat'
);
SET @sql := IF(
  @has_ctdt_sdp = 0,
  "ALTER TABLE `chitietdonthuoc` ADD COLUMN `soLuongDaPhat` int(11) NOT NULL DEFAULT 0 AFTER `soLuong`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill 1 lần duy nhất cho các đơn thuốc cũ đã trừ kho trước Task 8
SET @sql := IF(
  @has_dt_ttp = 0,
  "UPDATE `donthuoc` dt JOIN `hosobenhan` hs ON hs.`maLichKham` = dt.`maLichKham` AND hs.`isDeleted` = 0 SET dt.`trangThaiPhat` = 'Đã phát đủ' WHERE hs.`trangThai` = 'Đã hoàn thành'",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @has_ctdt_sdp = 0,
  "UPDATE `chitietdonthuoc` ct JOIN `donthuoc` dt ON dt.`maDonThuoc` = ct.`maDonThuoc` SET ct.`soLuongDaPhat` = COALESCE(ct.`soLuong`, 0) WHERE dt.`trangThaiPhat` = 'Đã phát đủ'",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
