-- Migration 001: Catalog dịch vụ cận lâm sàng (Task 1)
-- Idempotent: có thể chạy lặp an toàn trên DB sạch hoặc DB đang có dữ liệu.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `dichvu_can_lam_sang` (
  `maDichVu` int(11) NOT NULL AUTO_INCREMENT,
  `maCode` varchar(32) NOT NULL COMMENT 'Mã nghiệp vụ ổn định để seed và tra cứu',
  `tenDichVu` varchar(150) NOT NULL,
  `nhomDichVu` enum('xet-nghiem','chan-doan-hinh-anh','noi-soi') NOT NULL,
  `tenNhom` varchar(100) NOT NULL,
  `quyenThucHien` varchar(64) NOT NULL COMMENT 'Permission nhân viên cần có để thực hiện dịch vụ',
  `donViTinh` varchar(30) NOT NULL DEFAULT 'Lần',
  `gia` decimal(12,2) NOT NULL DEFAULT 0.00,
  `thoiGianDuKienPhut` smallint(5) UNSIGNED NOT NULL DEFAULT 15,
  `chuanBiTruocKhiLam` text DEFAULT NULL,
  `mauKetQuaMacDinh` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (`mauKetQuaMacDinh` IS NULL OR json_valid(`mauKetQuaMacDinh`)),
  `moTa` text DEFAULT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`maDichVu`),
  UNIQUE KEY `uq_dvcls_maCode` (`maCode`),
  KEY `idx_dvcls_nhom_active` (`nhomDichVu`, `isActive`),
  KEY `idx_dvcls_quyen_active` (`quyenThucHien`, `isActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
