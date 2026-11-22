-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: datlichkham
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `bacsi`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bacsi` (
  `nguoiDungId` int(11) NOT NULL,
  `maBacSi` varchar(20) NOT NULL,
  `tenBacSi` varchar(100) DEFAULT NULL,
  `maChuyenKhoa` varchar(10) DEFAULT NULL,
  `moTa` text DEFAULT NULL,
  `gioiTinh` enum('nam','nu') DEFAULT NULL,
  `namLamViec` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`maBacSi`),
  UNIQUE KEY `nguoiDungId` (`nguoiDungId`),
  KEY `maChuyenKhoa` (`maChuyenKhoa`),
  KEY `idx_bacsi_ten_ma` (`tenBacSi`,`maBacSi`),
  KEY `idx_bacsi_chuyenkhoa_ten_ma` (`maChuyenKhoa`,`tenBacSi`,`maBacSi`),
  CONSTRAINT `bacsi_ibfk_1` FOREIGN KEY (`nguoiDungId`) REFERENCES `nguoidung` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bacsi_ibfk_2` FOREIGN KEY (`maChuyenKhoa`) REFERENCES `chuyenkhoa` (`maChuyenKhoa`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `benhnhan`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `benhnhan` (
  `nguoiDungId` int(11) NOT NULL,
  `maBenhNhan` varchar(20) NOT NULL,
  `tenBenhNhan` varchar(100) DEFAULT NULL,
  `ngaySinh` date DEFAULT NULL,
  `gioiTinh` enum('nam','nu','khac') DEFAULT NULL,
  `soTheBHYT` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`maBenhNhan`),
  UNIQUE KEY `nguoiDungId` (`nguoiDungId`),
  CONSTRAINT `benhnhan_ibfk_1` FOREIGN KEY (`nguoiDungId`) REFERENCES `nguoidung` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `validate_birthdate_before_insert` BEFORE INSERT ON `benhnhan` FOR EACH ROW BEGIN
    IF NEW.ngaySinh > CURDATE() THEN
        SET NEW.ngaySinh = CURDATE();
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `validate_birthdate_before_update` BEFORE UPDATE ON `benhnhan` FOR EACH ROW BEGIN
    IF NEW.ngaySinh > CURDATE() THEN
        SET NEW.ngaySinh = CURDATE();
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `calamviec`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calamviec` (
  `maCa` int(11) NOT NULL AUTO_INCREMENT,
  `tenCa` varchar(30) NOT NULL,
  `gioBatDau` time NOT NULL,
  `gioKetThuc` time NOT NULL,
  PRIMARY KEY (`maCa`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chi_dinh_can_lam_sang`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chi_dinh_can_lam_sang` (
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
  KEY `idx_cdcls_hoso_status` (`maHoSo`,`trangThai`),
  KEY `idx_cdcls_lichkham` (`maLichKham`),
  KEY `idx_cdcls_benhnhan_created` (`maBenhNhan`,`createdAt`),
  KEY `idx_cdcls_bacsi_created` (`maBacSiChiDinh`,`createdAt`),
  KEY `fk_cdcls_cancelled_by` (`cancelledBy`),
  KEY `fk_cdcls_created_by` (`createdBy`),
  KEY `fk_cdcls_updated_by` (`updatedBy`),
  CONSTRAINT `fk_cdcls_bacsi` FOREIGN KEY (`maBacSiChiDinh`) REFERENCES `bacsi` (`maBacSi`),
  CONSTRAINT `fk_cdcls_benhnhan` FOREIGN KEY (`maBenhNhan`) REFERENCES `benhnhan` (`maBenhNhan`) ON DELETE CASCADE,
  CONSTRAINT `fk_cdcls_cancelled_by` FOREIGN KEY (`cancelledBy`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cdcls_created_by` FOREIGN KEY (`createdBy`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cdcls_hoso` FOREIGN KEY (`maHoSo`) REFERENCES `hosobenhan` (`maHoSo`) ON DELETE CASCADE,
  CONSTRAINT `fk_cdcls_lichkham` FOREIGN KEY (`maLichKham`) REFERENCES `lichkham` (`maLichKham`) ON DELETE SET NULL,
  CONSTRAINT `fk_cdcls_updated_by` FOREIGN KEY (`updatedBy`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chi_dinh_chi_tiet`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chi_dinh_chi_tiet` (
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
  KEY `idx_cdct_chidinh_status` (`maChiDinh`,`trangThai`),
  KEY `idx_cdct_hoso_status_req` (`maHoSo`,`trangThai`,`batBuoc`),
  KEY `idx_cdct_nhom_status` (`nhomDichVu`,`trangThai`),
  KEY `idx_cdct_dichvu` (`maDichVu`),
  KEY `idx_cdct_nv_xuly` (`maNhanVienXuLy`),
  KEY `idx_cdct_bs_duyet` (`maBacSiDuyet`),
  KEY `fk_cdct_cancelled_by` (`cancelledBy`),
  CONSTRAINT `fk_cdct_bs_duyet` FOREIGN KEY (`maBacSiDuyet`) REFERENCES `bacsi` (`maBacSi`) ON DELETE SET NULL,
  CONSTRAINT `fk_cdct_cancelled_by` FOREIGN KEY (`cancelledBy`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cdct_chidinh` FOREIGN KEY (`maChiDinh`) REFERENCES `chi_dinh_can_lam_sang` (`maChiDinh`) ON DELETE CASCADE,
  CONSTRAINT `fk_cdct_dichvu` FOREIGN KEY (`maDichVu`) REFERENCES `dichvu_can_lam_sang` (`maDichVu`),
  CONSTRAINT `fk_cdct_hoso` FOREIGN KEY (`maHoSo`) REFERENCES `hosobenhan` (`maHoSo`) ON DELETE CASCADE,
  CONSTRAINT `fk_cdct_nv_xuly` FOREIGN KEY (`maNhanVienXuLy`) REFERENCES `nhanvien` (`maNhanVien`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chitietdonthuoc`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chitietdonthuoc` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `maDonThuoc` int(11) DEFAULT NULL,
  `maThuoc` int(11) DEFAULT NULL,
  `soLuong` int(11) DEFAULT NULL,
  `soLuongDaPhat` int(11) NOT NULL DEFAULT 0,
  `lieuDung` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `maDonThuoc` (`maDonThuoc`),
  KEY `maThuoc` (`maThuoc`),
  CONSTRAINT `chitietdonthuoc_ibfk_1` FOREIGN KEY (`maDonThuoc`) REFERENCES `donthuoc` (`maDonThuoc`),
  CONSTRAINT `chitietdonthuoc_ibfk_2` FOREIGN KEY (`maThuoc`) REFERENCES `thuoc` (`maThuoc`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chuyenkhoa`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chuyenkhoa` (
  `maChuyenKhoa` varchar(10) NOT NULL,
  `tenChuyenKhoa` varchar(100) NOT NULL,
  `maKhoa` varchar(10) DEFAULT NULL,
  `moTa` text DEFAULT NULL,
  PRIMARY KEY (`maChuyenKhoa`),
  KEY `maKhoa` (`maKhoa`),
  KEY `idx_chuyenkhoa_makhoa_mack` (`maKhoa`,`maChuyenKhoa`),
  CONSTRAINT `chuyenkhoa_ibfk_1` FOREIGN KEY (`maKhoa`) REFERENCES `khoa` (`maKhoa`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dichvu_can_lam_sang`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dichvu_can_lam_sang` (
  `maDichVu` int(11) NOT NULL AUTO_INCREMENT,
  `maCode` varchar(32) NOT NULL COMMENT 'Mã nghiệp vụ ổn định để seed và tra cứu',
  `tenDichVu` varchar(150) NOT NULL,
  `nhomDichVu` enum('xet-nghiem','chan-doan-hinh-anh','noi-soi') NOT NULL,
  `tenNhom` varchar(100) NOT NULL,
  `quyenThucHien` varchar(64) NOT NULL COMMENT 'Permission nhân viên cần có để thực hiện dịch vụ',
  `donViTinh` varchar(30) NOT NULL DEFAULT 'Lần',
  `gia` decimal(12,2) NOT NULL DEFAULT 0.00,
  `thoiGianDuKienPhut` smallint(5) unsigned NOT NULL DEFAULT 15,
  `chuanBiTruocKhiLam` text DEFAULT NULL,
  `mauKetQuaMacDinh` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (`mauKetQuaMacDinh` is null or json_valid(`mauKetQuaMacDinh`)),
  `moTa` text DEFAULT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`maDichVu`),
  UNIQUE KEY `uq_dvcls_maCode` (`maCode`),
  KEY `idx_dvcls_nhom_active` (`nhomDichVu`,`isActive`),
  KEY `idx_dvcls_quyen_active` (`quyenThucHien`,`isActive`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `doimatkhau`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `doimatkhau` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nguoiDungId` int(11) NOT NULL,
  `trangThai` enum('Chờ','Đã xử lý','Từ chối') DEFAULT 'Chờ',
  `thoiGianYeuCau` datetime DEFAULT current_timestamp(),
  `thoiGianXuLy` datetime DEFAULT NULL,
  `nguoiXuLy` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nguoiDungId` (`nguoiDungId`),
  KEY `nguoiXuLy` (`nguoiXuLy`),
  CONSTRAINT `doimatkhau_ibfk_1` FOREIGN KEY (`nguoiDungId`) REFERENCES `nguoidung` (`id`) ON DELETE CASCADE,
  CONSTRAINT `doimatkhau_ibfk_2` FOREIGN KEY (`nguoiXuLy`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `after_doimatkhau_insert` AFTER INSERT ON `doimatkhau` FOR EACH ROW BEGIN
    DECLARE userName VARCHAR(100);
    DECLARE userPhone VARCHAR(16);
    DECLARE userRole VARCHAR(20);
    
    SELECT tenDangNhap, soDienThoai, vaiTro
    INTO userName, userPhone, userRole
    FROM nguoidung
    WHERE id = NEW.nguoiDungId;
    
    INSERT INTO thongbaoadmin (
        maYeuCau, 
        nguoiDungId, 
        soDienThoai,
        loai, 
        tieuDe, 
        noiDung, 
        thoiGian, 
        daXem,
        trangThai
    )
    VALUES (
        NEW.id,
        NEW.nguoiDungId, 
        userPhone,
        'Cấp lại mật khẩu',
        'Yêu cầu cấp lại mật khẩu',
        CONCAT('Người dùng ', userName, ' (', userRole, ') yêu cầu cấp lại mật khẩu'),
        NOW(),
        0,
        'Chờ'
    );
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `after_doimatkhau_update` AFTER UPDATE ON `doimatkhau` FOR EACH ROW BEGIN
    IF NEW.trangThai != OLD.trangThai THEN
        UPDATE thongbaoadmin
        SET trangThai = NEW.trangThai,
            thoiGianXuLy = NEW.thoiGianXuLy
        WHERE maYeuCau = NEW.id;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `donthuoc`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `donthuoc` (
  `maDonThuoc` int(11) NOT NULL AUTO_INCREMENT,
  `maLichKham` int(11) DEFAULT NULL,
  `chuanDoan` text DEFAULT NULL,
  `loiDanBacSi` text DEFAULT NULL,
  `ngayKeDon` datetime DEFAULT current_timestamp(),
  `tongTienThuoc` decimal(10,2) DEFAULT NULL,
  `trangThaiPhat` enum('Đã kê','Chờ phát','Đã phát một phần','Đã phát đủ','Hủy') NOT NULL DEFAULT 'Đã kê',
  `lyDoHuy` text DEFAULT NULL,
  `cancelledBy` int(11) DEFAULT NULL,
  `cancelledAt` datetime DEFAULT NULL,
  `updatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`maDonThuoc`),
  KEY `maLichKham` (`maLichKham`),
  KEY `idx_donthuoc_trangthaiphat` (`trangThaiPhat`,`ngayKeDon`),
  KEY `fk_donthuoc_cancelled_by` (`cancelledBy`),
  CONSTRAINT `donthuoc_ibfk_1` FOREIGN KEY (`maLichKham`) REFERENCES `lichkham` (`maLichKham`),
  CONSTRAINT `fk_donthuoc_cancelled_by` FOREIGN KEY (`cancelledBy`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `goikham`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `goikham` (
  `maGoi` int(11) NOT NULL AUTO_INCREMENT,
  `tenGoi` varchar(100) NOT NULL,
  `moTa` text DEFAULT NULL,
  `thoiLuong` int(11) DEFAULT 40,
  `gia` decimal(10,2) NOT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`maGoi`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hangdoi_dichvu`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hangdoi_dichvu` (
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
  UNIQUE KEY `uq_hddv_nhom_ngay_stt` (`nhomDichVu`,`ngay`,`soThuTu`),
  KEY `idx_hddv_trangthai_ngay_nhom` (`trangThai`,`ngay`,`nhomDichVu`,`soThuTu`),
  KEY `idx_hddv_nhom_ngay_trangthai` (`nhomDichVu`,`ngay`,`trangThai`,`doUuTien`,`soThuTu`),
  KEY `idx_hddv_benhnhan_ngay` (`maBenhNhan`,`ngay`),
  KEY `idx_hddv_hoso` (`maHoSo`),
  KEY `fk_hddv_chidinh` (`maChiDinh`),
  KEY `fk_hddv_dichvu` (`maDichVu`),
  KEY `fk_hddv_nv_tiepnhan` (`maNhanVienTiepNhan`),
  KEY `fk_hddv_nv_thuchien` (`maNhanVienThucHien`),
  CONSTRAINT `fk_hddv_benhnhan` FOREIGN KEY (`maBenhNhan`) REFERENCES `benhnhan` (`maBenhNhan`) ON DELETE CASCADE,
  CONSTRAINT `fk_hddv_chidinh` FOREIGN KEY (`maChiDinh`) REFERENCES `chi_dinh_can_lam_sang` (`maChiDinh`) ON DELETE CASCADE,
  CONSTRAINT `fk_hddv_chitiet` FOREIGN KEY (`maChiTietChiDinh`) REFERENCES `chi_dinh_chi_tiet` (`maChiTietChiDinh`) ON DELETE CASCADE,
  CONSTRAINT `fk_hddv_dichvu` FOREIGN KEY (`maDichVu`) REFERENCES `dichvu_can_lam_sang` (`maDichVu`),
  CONSTRAINT `fk_hddv_hoso` FOREIGN KEY (`maHoSo`) REFERENCES `hosobenhan` (`maHoSo`) ON DELETE CASCADE,
  CONSTRAINT `fk_hddv_nv_thuchien` FOREIGN KEY (`maNhanVienThucHien`) REFERENCES `nhanvien` (`maNhanVien`) ON DELETE SET NULL,
  CONSTRAINT `fk_hddv_nv_tiepnhan` FOREIGN KEY (`maNhanVienTiepNhan`) REFERENCES `nhanvien` (`maNhanVien`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hangdoikham`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hangdoikham` (
  `maHangDoi` int(11) NOT NULL AUTO_INCREMENT,
  `maLichKham` int(11) NOT NULL,
  `maBacSi` varchar(20) NOT NULL,
  `ngay` date NOT NULL,
  `soThuTu` int(11) NOT NULL,
  `thoiGianCheckIn` datetime NOT NULL DEFAULT current_timestamp(),
  `trangThai` enum('Đang chờ','Đang khám','Hoàn thành','Bỏ lỡ','Hủy') NOT NULL DEFAULT 'Đang chờ',
  `nguon` enum('online','truc_tiep') NOT NULL DEFAULT 'truc_tiep',
  `maNhanVienCheckIn` varchar(20) DEFAULT NULL,
  `thoiGianGoiKham` datetime DEFAULT NULL,
  `thoiGianHoanThanh` datetime DEFAULT NULL,
  `ghiChu` text DEFAULT NULL,
  PRIMARY KEY (`maHangDoi`),
  UNIQUE KEY `uniq_hangdoi_lichkham` (`maLichKham`),
  UNIQUE KEY `uniq_hangdoi_bacsi_ngay_stt` (`maBacSi`,`ngay`,`soThuTu`),
  KEY `maNhanVienCheckIn` (`maNhanVienCheckIn`),
  KEY `idx_hangdoi_bacsi_ngay_trangthai` (`maBacSi`,`ngay`,`trangThai`),
  CONSTRAINT `hangdoikham_ibfk_1` FOREIGN KEY (`maLichKham`) REFERENCES `lichkham` (`maLichKham`) ON DELETE CASCADE,
  CONSTRAINT `hangdoikham_ibfk_2` FOREIGN KEY (`maBacSi`) REFERENCES `bacsi` (`maBacSi`) ON DELETE CASCADE,
  CONSTRAINT `hangdoikham_ibfk_3` FOREIGN KEY (`maNhanVienCheckIn`) REFERENCES `nhanvien` (`maNhanVien`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hoadon`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hoadon` (
  `maHoaDon` int(11) NOT NULL AUTO_INCREMENT,
  `maLichKham` int(11) DEFAULT NULL,
  `soTien` decimal(10,2) DEFAULT NULL,
  `ngayTao` datetime DEFAULT current_timestamp(),
  `trangThai` enum('Chưa thanh toán','Đã thanh toán') DEFAULT 'Chưa thanh toán',
  `phuongThuc` enum('TienMat','VNPAY') DEFAULT NULL,
  `vnp_TransactionNo` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`maHoaDon`),
  KEY `maLichKham` (`maLichKham`),
  CONSTRAINT `hoadon_ibfk_1` FOREIGN KEY (`maLichKham`) REFERENCES `lichkham` (`maLichKham`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hosobenhan`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hosobenhan` (
  `maHoSo` varchar(20) NOT NULL,
  `maBenhNhan` varchar(20) DEFAULT NULL,
  `maBacSi` varchar(20) DEFAULT NULL,
  `maLichKham` int(11) DEFAULT NULL,
  `chanDoan` text DEFAULT NULL,
  `dieuTri` text DEFAULT NULL,
  `trangThai` enum('Chưa hoàn thành','Đã hoàn thành') DEFAULT 'Chưa hoàn thành',
  `trangThaiQuyTrinh` enum('Đang khám','Chờ thực hiện CLS','Chờ kết quả','Chờ bác sĩ kết luận','Hoàn tất','Hủy') NOT NULL DEFAULT 'Đang khám',
  `ngayTao` datetime DEFAULT current_timestamp(),
  `ngayHoanThanh` datetime DEFAULT NULL,
  `updatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updatedBy` int(11) DEFAULT NULL,
  `ghiChu` text DEFAULT NULL,
  `ngayKham` datetime DEFAULT NULL,
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `deletedAt` datetime DEFAULT NULL,
  `deletedBy` int(11) DEFAULT NULL,
  `deleteReason` text DEFAULT NULL,
  PRIMARY KEY (`maHoSo`),
  KEY `maBenhNhan` (`maBenhNhan`),
  KEY `maBacSi` (`maBacSi`),
  KEY `maLichKham` (`maLichKham`),
  KEY `idx_hosobenhan_isDeleted` (`isDeleted`),
  KEY `idx_hsba_bacsi_quytrinh` (`maBacSi`,`trangThaiQuyTrinh`,`isDeleted`),
  KEY `fk_hsba_updated_by` (`updatedBy`),
  CONSTRAINT `fk_hsba_updated_by` FOREIGN KEY (`updatedBy`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hosobenhan_ibfk_1` FOREIGN KEY (`maBenhNhan`) REFERENCES `benhnhan` (`maBenhNhan`) ON DELETE CASCADE,
  CONSTRAINT `hosobenhan_ibfk_2` FOREIGN KEY (`maBacSi`) REFERENCES `bacsi` (`maBacSi`) ON DELETE SET NULL,
  CONSTRAINT `hosobenhan_ibfk_3` FOREIGN KEY (`maLichKham`) REFERENCES `lichkham` (`maLichKham`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ket_qua_can_lam_sang`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ket_qua_can_lam_sang` (
  `maKetQua` int(11) NOT NULL AUTO_INCREMENT,
  `maChiTietChiDinh` int(11) NOT NULL,
  `maChiDinh` int(11) NOT NULL,
  `maHoSo` varchar(20) NOT NULL,
  `maBenhNhan` varchar(20) NOT NULL,
  `maDichVu` int(11) NOT NULL,
  `trangThai` enum('Nháp','Chờ duyệt','Đã duyệt','Yêu cầu làm lại') NOT NULL DEFAULT 'Nháp',
  `chiSoKetQua` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (`chiSoKetQua` is null or json_valid(`chiSoKetQua`)),
  `ketLuan` text DEFAULT NULL,
  `nhanXet` text DEFAULT NULL,
  `deNghi` text DEFAULT NULL,
  `tepDinhKem` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (`tepDinhKem` is null or json_valid(`tepDinhKem`)),
  `maNhanVienThucHien` varchar(20) NOT NULL,
  `thoiGianTraKetQua` datetime DEFAULT NULL,
  `maBacSiDuyet` varchar(20) DEFAULT NULL,
  `thoiGianDuyet` datetime DEFAULT NULL,
  `lyDoYeuCauLamLai` text DEFAULT NULL,
  `phienBan` smallint(5) unsigned NOT NULL DEFAULT 1,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`maKetQua`),
  UNIQUE KEY `uq_kqcls_chitiet` (`maChiTietChiDinh`),
  KEY `idx_kqcls_hoso_status` (`maHoSo`,`trangThai`),
  KEY `idx_kqcls_benhnhan_status` (`maBenhNhan`,`trangThai`),
  KEY `idx_kqcls_nv_time` (`maNhanVienThucHien`,`thoiGianTraKetQua`),
  KEY `fk_kqcls_chidinh` (`maChiDinh`),
  KEY `fk_kqcls_dichvu` (`maDichVu`),
  KEY `fk_kqcls_bs_duyet` (`maBacSiDuyet`),
  CONSTRAINT `fk_kqcls_benhnhan` FOREIGN KEY (`maBenhNhan`) REFERENCES `benhnhan` (`maBenhNhan`) ON DELETE CASCADE,
  CONSTRAINT `fk_kqcls_bs_duyet` FOREIGN KEY (`maBacSiDuyet`) REFERENCES `bacsi` (`maBacSi`) ON DELETE SET NULL,
  CONSTRAINT `fk_kqcls_chidinh` FOREIGN KEY (`maChiDinh`) REFERENCES `chi_dinh_can_lam_sang` (`maChiDinh`) ON DELETE CASCADE,
  CONSTRAINT `fk_kqcls_chitiet` FOREIGN KEY (`maChiTietChiDinh`) REFERENCES `chi_dinh_chi_tiet` (`maChiTietChiDinh`) ON DELETE CASCADE,
  CONSTRAINT `fk_kqcls_dichvu` FOREIGN KEY (`maDichVu`) REFERENCES `dichvu_can_lam_sang` (`maDichVu`),
  CONSTRAINT `fk_kqcls_hoso` FOREIGN KEY (`maHoSo`) REFERENCES `hosobenhan` (`maHoSo`) ON DELETE CASCADE,
  CONSTRAINT `fk_kqcls_nv_thuchien` FOREIGN KEY (`maNhanVienThucHien`) REFERENCES `nhanvien` (`maNhanVien`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ket_qua_lich_su`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ket_qua_lich_su` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `maKetQua` int(11) DEFAULT NULL,
  `maChiTietChiDinh` int(11) NOT NULL,
  `phienBan` smallint(5) unsigned NOT NULL DEFAULT 1,
  `hanhDong` varchar(50) NOT NULL,
  `trangThaiCu` varchar(50) DEFAULT NULL,
  `trangThaiMoi` varchar(50) NOT NULL,
  `duLieuSnapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (`duLieuSnapshot` is null or json_valid(`duLieuSnapshot`)),
  `ghiChu` text DEFAULT NULL,
  `actorUserId` int(11) DEFAULT NULL,
  `actorMaNhanVien` varchar(20) DEFAULT NULL,
  `actorMaBacSi` varchar(20) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kqls_chitiet_time` (`maChiTietChiDinh`,`createdAt`),
  KEY `idx_kqls_ketqua_ver` (`maKetQua`,`phienBan`),
  KEY `idx_kqls_actor_user` (`actorUserId`),
  CONSTRAINT `fk_kqls_actor_user` FOREIGN KEY (`actorUserId`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_kqls_chitiet` FOREIGN KEY (`maChiTietChiDinh`) REFERENCES `chi_dinh_chi_tiet` (`maChiTietChiDinh`) ON DELETE CASCADE,
  CONSTRAINT `fk_kqls_ketqua` FOREIGN KEY (`maKetQua`) REFERENCES `ket_qua_can_lam_sang` (`maKetQua`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `khoa`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `khoa` (
  `maKhoa` varchar(10) NOT NULL,
  `tenKhoa` varchar(100) NOT NULL,
  `moTa` text DEFAULT NULL,
  PRIMARY KEY (`maKhoa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lichkham`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lichkham` (
  `maLichKham` int(11) NOT NULL AUTO_INCREMENT,
  `maBacSi` varchar(20) NOT NULL,
  `maBenhNhan` varchar(20) NOT NULL,
  `ngayKham` date NOT NULL,
  `maCa` int(11) NOT NULL,
  `maSuat` int(11) NOT NULL,
  `maGoi` int(11) DEFAULT NULL,
  `trangThai` enum('Chờ','Đã đặt','Hoàn thành','Hủy') DEFAULT 'Đã đặt',
  `ghiChu` text DEFAULT NULL,
  `nguoiHuy` enum('benhnhan','bacsi','quantri','hethong') DEFAULT NULL,
  `soLanDoiLich` tinyint(1) NOT NULL DEFAULT 0,
  `thoiGianDoiLich` datetime DEFAULT NULL,
  `nguon` enum('online','truc_tiep') NOT NULL DEFAULT 'online',
  `maNhanVienTao` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`maLichKham`),
  KEY `maBacSi` (`maBacSi`),
  KEY `maBenhNhan` (`maBenhNhan`),
  KEY `maCa` (`maCa`),
  KEY `maSuat` (`maSuat`),
  KEY `maGoi` (`maGoi`),
  KEY `maNhanVienTao` (`maNhanVienTao`),
  CONSTRAINT `lichkham_ibfk_1` FOREIGN KEY (`maBacSi`) REFERENCES `bacsi` (`maBacSi`),
  CONSTRAINT `lichkham_ibfk_2` FOREIGN KEY (`maBenhNhan`) REFERENCES `benhnhan` (`maBenhNhan`),
  CONSTRAINT `lichkham_ibfk_3` FOREIGN KEY (`maCa`) REFERENCES `calamviec` (`maCa`),
  CONSTRAINT `lichkham_ibfk_4` FOREIGN KEY (`maSuat`) REFERENCES `suatkham` (`maSuat`),
  CONSTRAINT `lichkham_ibfk_5` FOREIGN KEY (`maGoi`) REFERENCES `goikham` (`maGoi`),
  CONSTRAINT `lichkham_ibfk_6` FOREIGN KEY (`maNhanVienTao`) REFERENCES `nhanvien` (`maNhanVien`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=110 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `after_lichkham_insert` AFTER INSERT ON `lichkham` FOR EACH ROW BEGIN
    DECLARE patientName VARCHAR(100);
    DECLARE appointmentDate VARCHAR(20);
    DECLARE shiftName VARCHAR(50);
    DECLARE noteText TEXT DEFAULT '';
    
    
    SELECT tenBenhNhan INTO patientName FROM benhnhan WHERE maBenhNhan = NEW.maBenhNhan;
    SELECT tenCa INTO shiftName FROM calamviec WHERE maCa = NEW.maCa;
    SET appointmentDate = IFNULL(DATE_FORMAT(NEW.ngayKham, '%d/%m/%Y'), '(chưa có ngày)');
    
    
    IF NEW.ghiChu IS NOT NULL AND NEW.ghiChu != '' THEN
        SET noteText = CONCAT('. Ghi chú: ', NEW.ghiChu);
    END IF;
    
    INSERT INTO thongbaolichkham (maBacSi, maLichKham, loai, tieuDe, noiDung, thoiGian, daXem)
    VALUES (
        NEW.maBacSi,
        NEW.maLichKham,
        'Đặt lịch',
        'Lịch khám mới',
        CONCAT(
            'Bệnh nhân ', patientName, 
            ' đã đặt lịch khám vào ngày ', appointmentDate, 
            ' - ', shiftName,
            noteText 
        ),
        NOW(),
        0
    );
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `after_lichkham_update` AFTER UPDATE ON `lichkham` FOR EACH ROW BEGIN
    DECLARE patientName VARCHAR(100);
    DECLARE doctorName VARCHAR(100);
    DECLARE appointmentDate VARCHAR(20);
    DECLARE shiftName VARCHAR(50);
    DECLARE slotTime VARCHAR(50);
    DECLARE cancelSource VARCHAR(50);
    DECLARE cancelActor VARCHAR(20);
    DECLARE reason TEXT DEFAULT '';

    IF NEW.trangThai = 'Hủy' AND OLD.trangThai != 'Hủy' THEN
        SELECT tenBenhNhan INTO patientName FROM benhnhan WHERE maBenhNhan = NEW.maBenhNhan;
        SELECT tenBacSi INTO doctorName FROM bacsi WHERE maBacSi = NEW.maBacSi;
        SELECT tenCa INTO shiftName FROM calamviec WHERE maCa = NEW.maCa;
        SELECT CONCAT(SUBSTRING(gioBatDau, 1, 5), ' - ', SUBSTRING(gioKetThuc, 1, 5))
        INTO slotTime FROM suatkham WHERE maSuat = NEW.maSuat;
        SET appointmentDate = DATE_FORMAT(NEW.ngayKham, '%d/%m/%Y');

        IF NEW.ghiChu LIKE '%[Lý do hủy]:%' THEN
            SET reason = SUBSTRING_INDEX(NEW.ghiChu, '[Lý do hủy]: ', -1);
        ELSE
            SET reason = 'Không có lý do cụ thể';
        END IF;

        SET cancelActor = LOWER(TRIM(COALESCE(NEW.nguoiHuy, '')));

        IF cancelActor = 'benhnhan' THEN
            IF NOT EXISTS (
                SELECT 1
                FROM thongbaolichkham
                WHERE maLichKham = NEW.maLichKham
                  AND loai = 'Hủy lịch'
                  AND thoiGian >= DATE_SUB(NOW(), INTERVAL 5 SECOND)
            ) THEN
                INSERT INTO thongbaolichkham (
                    maBacSi, maLichKham, loai, tieuDe, noiDung, thoiGian, daXem
                )
                VALUES (
                    NEW.maBacSi,
                    NEW.maLichKham,
                    'Hủy lịch',
                    'Lịch khám đã hủy',
                    CONCAT(
                        'Bệnh nhân ', patientName,
                        ' đã hủy lịch khám ngày ', appointmentDate, ' - ', shiftName,
                        '. Lý do: ', reason
                    ),
                    NOW(),
                    0
                );
            END IF;
        ELSEIF cancelActor IN ('bacsi', 'quantri', 'hethong') THEN
            SET cancelSource = CASE
                WHEN cancelActor = 'bacsi' THEN 'Bác sĩ'
                WHEN cancelActor = 'quantri' THEN 'Quản trị viên'
                WHEN cancelActor = 'hethong' THEN 'Hệ thống'
                ELSE 'Bệnh viện'
            END;

            IF NOT EXISTS (
                SELECT 1
                FROM thongbaobenhnhan
                WHERE maBenhNhan = NEW.maBenhNhan
                  AND loai = 'Lịch khám'
                  AND tieuDe = 'Lịch khám bị hủy'
                  AND thoiGian >= DATE_SUB(NOW(), INTERVAL 5 SECOND)
            ) THEN
                INSERT INTO thongbaobenhnhan (
                    maBenhNhan, loai, tieuDe, noiDung, thoiGian, daXem
                )
                VALUES (
                    NEW.maBenhNhan,
                    'Lịch khám',
                    'Lịch khám bị hủy',
                    CONCAT(
                        'Lịch khám ngày ', appointmentDate,
                        ' đã bị hủy bởi ', cancelSource,
                        '. Lý do: ', reason,
                        '. Vui lòng đặt lịch mới.'
                    ),
                    NOW(),
                    0
                );
            END IF;
        END IF;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `lienhe`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lienhe` (
  `maLienHe` int(11) NOT NULL AUTO_INCREMENT,
  `hoTen` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `soDienThoai` varchar(15) NOT NULL,
  `chuDe` varchar(100) NOT NULL,
  `noiDung` text NOT NULL,
  `trangThai` enum('Chưa xử lý','Đã xử lý') NOT NULL DEFAULT 'Chưa xử lý',
  `thoiGianGui` datetime NOT NULL DEFAULT current_timestamp(),
  `nguoiXuLy` int(11) DEFAULT NULL,
  `thoiGianXuLy` datetime DEFAULT NULL,
  `ghiChu` text DEFAULT NULL,
  PRIMARY KEY (`maLienHe`),
  KEY `fk_lienhe_nguoixuly` (`nguoiXuLy`),
  KEY `idx_trangThai` (`trangThai`),
  KEY `idx_thoiGianGui` (`thoiGianGui`),
  CONSTRAINT `fk_lienhe_nguoixuly` FOREIGN KEY (`nguoiXuLy`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mail_notification_log`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mail_notification_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_code` varchar(64) NOT NULL,
  `event_key` varchar(191) NOT NULL,
  `recipient_email` varchar(150) NOT NULL,
  `status` enum('sent','failed','skipped') NOT NULL DEFAULT 'sent',
  `error_message` text DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mail_event_recipient` (`event_code`,`event_key`,`recipient_email`),
  KEY `idx_mail_sent_at` (`sent_at`)
) ENGINE=InnoDB AUTO_INCREMENT=146 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `medicine_stock_log`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medicine_stock_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `maThuoc` int(11) NOT NULL,
  `maLichKham` int(11) DEFAULT NULL,
  `maHoSo` varchar(30) DEFAULT NULL,
  `changeQty` int(11) NOT NULL,
  `balanceAfter` int(11) NOT NULL,
  `actionType` varchar(32) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_medicine_stock_log_maThuoc` (`maThuoc`),
  KEY `idx_medicine_stock_log_maLichKham` (`maLichKham`),
  KEY `idx_medicine_stock_log_maHoSo` (`maHoSo`),
  KEY `idx_medicine_stock_log_createdAt` (`createdAt`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ngaynghi`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ngaynghi` (
  `maNghi` int(11) NOT NULL AUTO_INCREMENT,
  `maBacSi` varchar(20) NOT NULL,
  `ngayNghi` date NOT NULL,
  `maCa` int(11) DEFAULT NULL,
  `lyDo` text DEFAULT NULL,
  PRIMARY KEY (`maNghi`),
  KEY `maBacSi` (`maBacSi`),
  KEY `maCa` (`maCa`),
  CONSTRAINT `ngaynghi_ibfk_1` FOREIGN KEY (`maBacSi`) REFERENCES `bacsi` (`maBacSi`),
  CONSTRAINT `ngaynghi_ibfk_2` FOREIGN KEY (`maCa`) REFERENCES `calamviec` (`maCa`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `after_ngaynghi_insert` AFTER INSERT ON `ngaynghi` FOR EACH ROW BEGIN
    DECLARE doctorName VARCHAR(100);
    DECLARE doctorUserId INT;
    DECLARE existingNotifID INT;
    DECLARE finalReason TEXT;
    
    
    SELECT tenBacSi, nguoiDungId INTO doctorName, doctorUserId
    FROM bacsi 
    WHERE maBacSi = NEW.maBacSi;
    
    
    SET finalReason = NEW.lyDo;
    
    
    SELECT maThongBao INTO existingNotifID
    FROM thongbaoadmin
    WHERE nguoiDungId = doctorUserId
      AND loai = 'Nghỉ phép'
      AND ngayLienQuan = NEW.ngayNghi
    LIMIT 1;
    
    IF existingNotifID IS NOT NULL THEN
        
        
        
        
        IF finalReason = '0' OR finalReason = '' THEN
             SELECT lyDo INTO finalReason FROM ngaynghi 
             WHERE maBacSi = NEW.maBacSi AND ngayNghi = NEW.ngayNghi AND maCa != NEW.maCa LIMIT 1;
        END IF;

        UPDATE thongbaoadmin
        SET noiDung = CONCAT('Bác sĩ ', doctorName, ' xin nghỉ phép vào ngày ', DATE_FORMAT(NEW.ngayNghi, '%d/%m/%Y'), ' - Cả ngày. Lý do: ', finalReason),
            thoiGian = NOW(), 
            daXem = 0         
        WHERE maThongBao = existingNotifID;
        
    ELSE
        
        INSERT INTO thongbaoadmin (
            maNghi, nguoiDungId, loai, tieuDe, noiDung, thoiGian, daXem, ngayLienQuan
        )
        VALUES (
            NEW.maNghi,
            doctorUserId,
            'Nghỉ phép',
            'Đơn xin nghỉ phép',
            CONCAT('Bác sĩ ', doctorName, ' xin nghỉ phép vào ngày ', DATE_FORMAT(NEW.ngayNghi, '%d/%m/%Y'), 
                   IF(NEW.maCa = 1, ' - Ca sáng', ' - Ca chiều'), 
                   '. Lý do: ', finalReason),
            NOW(),
            0,
            NEW.ngayNghi 
        );
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `after_ngaynghi_delete` AFTER DELETE ON `ngaynghi` FOR EACH ROW BEGIN
    DECLARE doctorName VARCHAR(100);
    DECLARE doctorUserId INT;
    DECLARE leaveDate VARCHAR(20);
    DECLARE caInfo VARCHAR(100);
    DECLARE otherShiftExists INT DEFAULT 0;
    
    SELECT tenBacSi, nguoiDungId INTO doctorName, doctorUserId
    FROM bacsi WHERE maBacSi = OLD.maBacSi;
    
    SET leaveDate = DATE_FORMAT(OLD.ngayNghi, '%d/%m/%Y');
    
    
    SELECT COUNT(*) INTO otherShiftExists
    FROM ngaynghi
    WHERE maBacSi = OLD.maBacSi AND ngayNghi = OLD.ngayNghi;
    
    
    
    
    INSERT INTO thongbaoadmin (maNghi, nguoiDungId, loai, tieuDe, noiDung, thoiGian, daXem)
    VALUES (
        NULL,
        doctorUserId,
        'Hủy nghỉ',
        'Hủy đơn nghỉ phép',
        CONCAT('Bác sĩ ', doctorName, ' đã hủy đơn nghỉ phép ngày ', leaveDate, 
               IF(OLD.maCa = 1, ' - Ca sáng', ' - Ca chiều')),
        NOW(),
        0
    );
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `nguoidung`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nguoidung` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenDangNhap` varchar(50) NOT NULL,
  `matKhau` varchar(255) NOT NULL,
  `soDienThoai` varchar(16) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `vaiTro` enum('benhnhan','bacsi','quantri','nhanvien') NOT NULL,
  `trangThai` enum('Hoạt Động','Khóa') NOT NULL DEFAULT 'Hoạt Động',
  `taiKhoanTamThoi` tinyint(1) NOT NULL DEFAULT 0,
  `ngayCapNhatTaiKhoan` datetime DEFAULT NULL,
  `ngayCapNhatMatKhau` datetime DEFAULT NULL,
  `avatar` varchar(255) DEFAULT 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png',
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `deletedAt` datetime DEFAULT NULL,
  `deletedBy` int(11) DEFAULT NULL,
  `deleteReason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenDangNhap` (`tenDangNhap`),
  UNIQUE KEY `soDienThoai` (`soDienThoai`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_nguoidung_isDeleted` (`isDeleted`)
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nhanvien`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nhanvien` (
  `nguoiDungId` int(11) NOT NULL,
  `maNhanVien` varchar(20) NOT NULL,
  `tenNhanVien` varchar(100) DEFAULT NULL,
  `loaiNhanVien` varchar(50) DEFAULT NULL,
  `gioiTinh` enum('nam','nu') DEFAULT NULL,
  `ngayVaoLam` date DEFAULT NULL,
  PRIMARY KEY (`maNhanVien`),
  UNIQUE KEY `nguoiDungId` (`nguoiDungId`),
  CONSTRAINT `nhanvien_ibfk_1` FOREIGN KEY (`nguoiDungId`) REFERENCES `nguoidung` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nhanvien_gan_nhom_quyen`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nhanvien_gan_nhom_quyen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `maNhanVien` varchar(20) NOT NULL,
  `maNhomQuyen` varchar(50) NOT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `assignedBy` int(11) DEFAULT NULL,
  `assignedAt` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nv_nhom_quyen` (`maNhanVien`,`maNhomQuyen`),
  KEY `idx_nvgnq_nhom_active` (`maNhomQuyen`,`isActive`),
  KEY `idx_nvgnq_nv_active` (`maNhanVien`,`isActive`),
  KEY `fk_nvgnq_assigned_by` (`assignedBy`),
  CONSTRAINT `fk_nvgnq_assigned_by` FOREIGN KEY (`assignedBy`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_nvgnq_nhanvien` FOREIGN KEY (`maNhanVien`) REFERENCES `nhanvien` (`maNhanVien`) ON DELETE CASCADE,
  CONSTRAINT `fk_nvgnq_nhom` FOREIGN KEY (`maNhomQuyen`) REFERENCES `nhanvien_nhom_quyen` (`maNhomQuyen`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nhanvien_nhom_quyen`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nhanvien_nhom_quyen` (
  `maNhomQuyen` varchar(50) NOT NULL,
  `tenNhomQuyen` varchar(100) NOT NULL,
  `moTa` text DEFAULT NULL,
  `isDemo` tinyint(1) NOT NULL DEFAULT 0,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  `updatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`maNhomQuyen`),
  KEY `idx_nvnq_active` (`isActive`,`isDemo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nhanvien_nhom_quyen_chitiet`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nhanvien_nhom_quyen_chitiet` (
  `maNhomQuyen` varchar(50) NOT NULL,
  `maQuyen` varchar(64) NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`maNhomQuyen`,`maQuyen`),
  KEY `idx_nvnqct_quyen` (`maQuyen`),
  CONSTRAINT `fk_nvnqct_nhom` FOREIGN KEY (`maNhomQuyen`) REFERENCES `nhanvien_nhom_quyen` (`maNhomQuyen`) ON DELETE CASCADE,
  CONSTRAINT `fk_nvnqct_quyen` FOREIGN KEY (`maQuyen`) REFERENCES `nhanvien_quyen` (`maQuyen`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nhanvien_quyen`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nhanvien_quyen` (
  `maQuyen` varchar(64) NOT NULL,
  `tenQuyen` varchar(120) NOT NULL,
  `nhomChucNang` varchar(50) NOT NULL,
  `moTa` text DEFAULT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`maQuyen`),
  KEY `idx_nvq_nhom_active` (`nhomChucNang`,`isActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `phieu_phat_thuoc`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `phieu_phat_thuoc` (
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
  KEY `idx_ppt_donthuoc_time` (`maDonThuoc`,`thoiGianPhat`),
  KEY `idx_ppt_duocsi_time` (`maDuocSi`,`thoiGianPhat`),
  KEY `idx_ppt_benhnhan_time` (`maBenhNhan`,`thoiGianPhat`),
  KEY `fk_ppt_lichkham` (`maLichKham`),
  KEY `fk_ppt_user` (`nguoiPhatUserId`),
  CONSTRAINT `fk_ppt_benhnhan` FOREIGN KEY (`maBenhNhan`) REFERENCES `benhnhan` (`maBenhNhan`) ON DELETE CASCADE,
  CONSTRAINT `fk_ppt_donthuoc` FOREIGN KEY (`maDonThuoc`) REFERENCES `donthuoc` (`maDonThuoc`),
  CONSTRAINT `fk_ppt_duocsi` FOREIGN KEY (`maDuocSi`) REFERENCES `nhanvien` (`maNhanVien`),
  CONSTRAINT `fk_ppt_lichkham` FOREIGN KEY (`maLichKham`) REFERENCES `lichkham` (`maLichKham`) ON DELETE SET NULL,
  CONSTRAINT `fk_ppt_user` FOREIGN KEY (`nguoiPhatUserId`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `phieu_phat_thuoc_chitiet`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `phieu_phat_thuoc_chitiet` (
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
  UNIQUE KEY `uq_pptct_phieu_dong` (`maPhieuPhat`,`chiTietDonThuocId`),
  KEY `idx_pptct_thuoc` (`maThuoc`),
  KEY `idx_pptct_ctdt` (`chiTietDonThuocId`),
  CONSTRAINT `fk_pptct_ctdt` FOREIGN KEY (`chiTietDonThuocId`) REFERENCES `chitietdonthuoc` (`id`),
  CONSTRAINT `fk_pptct_phieuphat` FOREIGN KEY (`maPhieuPhat`) REFERENCES `phieu_phat_thuoc` (`maPhieuPhat`) ON DELETE CASCADE,
  CONSTRAINT `fk_pptct_thuoc` FOREIGN KEY (`maThuoc`) REFERENCES `thuoc` (`maThuoc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `quantrivien`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quantrivien` (
  `nguoiDungId` int(11) NOT NULL,
  `maQuanTriVien` varchar(20) NOT NULL,
  PRIMARY KEY (`maQuanTriVien`),
  UNIQUE KEY `nguoiDungId` (`nguoiDungId`),
  CONSTRAINT `quantrivien_ibfk_1` FOREIGN KEY (`nguoiDungId`) REFERENCES `nguoidung` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sang_loc_dieu_duong`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sang_loc_dieu_duong` (
  `maSangLoc` int(11) NOT NULL AUTO_INCREMENT,
  `maHangDoi` int(11) NOT NULL,
  `maLichKham` int(11) NOT NULL,
  `maBenhNhan` varchar(20) NOT NULL,
  `maNhanVienSangLoc` varchar(20) NOT NULL,
  `mach` smallint(5) unsigned DEFAULT NULL COMMENT 'Nhịp tim (lần/phút)',
  `huyetApTamThu` smallint(5) unsigned DEFAULT NULL COMMENT 'mmHg',
  `huyetApTamTruong` smallint(5) unsigned DEFAULT NULL COMMENT 'mmHg',
  `nhietDo` decimal(4,1) DEFAULT NULL COMMENT 'Độ C',
  `spo2` tinyint(3) unsigned DEFAULT NULL COMMENT 'Phần trăm SpO2',
  `nhipTho` tinyint(3) unsigned DEFAULT NULL COMMENT 'Nhịp thở (lần/phút)',
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
  KEY `idx_sldd_benhnhan_time` (`maBenhNhan`,`thoiGianSangLoc`),
  KEY `idx_sldd_nv` (`maNhanVienSangLoc`,`thoiGianSangLoc`),
  CONSTRAINT `fk_sldd_benhnhan` FOREIGN KEY (`maBenhNhan`) REFERENCES `benhnhan` (`maBenhNhan`) ON DELETE CASCADE,
  CONSTRAINT `fk_sldd_hangdoi` FOREIGN KEY (`maHangDoi`) REFERENCES `hangdoikham` (`maHangDoi`) ON DELETE CASCADE,
  CONSTRAINT `fk_sldd_lichkham` FOREIGN KEY (`maLichKham`) REFERENCES `lichkham` (`maLichKham`) ON DELETE CASCADE,
  CONSTRAINT `fk_sldd_nhanvien` FOREIGN KEY (`maNhanVienSangLoc`) REFERENCES `nhanvien` (`maNhanVien`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suatkham`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suatkham` (
  `maSuat` int(11) NOT NULL AUTO_INCREMENT,
  `maCa` int(11) NOT NULL,
  `gioBatDau` time NOT NULL,
  `gioKetThuc` time NOT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `effectiveFrom` date NOT NULL DEFAULT '1900-01-01',
  `effectiveTo` date DEFAULT NULL,
  `presetMinutes` int(11) NOT NULL DEFAULT 40,
  PRIMARY KEY (`maSuat`),
  UNIQUE KEY `uniq_suatkham_slot_version` (`maCa`,`gioBatDau`,`gioKetThuc`,`effectiveFrom`),
  KEY `maCa` (`maCa`),
  CONSTRAINT `suatkham_ibfk_1` FOREIGN KEY (`maCa`) REFERENCES `calamviec` (`maCa`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `thongbaoadmin`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `thongbaoadmin` (
  `maThongBao` int(11) NOT NULL AUTO_INCREMENT,
  `nguoiDungId` int(11) NOT NULL,
  `maNghi` int(11) DEFAULT NULL,
  `maYeuCau` int(11) DEFAULT NULL,
  `soDienThoai` varchar(16) DEFAULT NULL,
  `loai` enum('Nghỉ phép','Hủy nghỉ','Cấp lại mật khẩu') NOT NULL DEFAULT 'Nghỉ phép',
  `tieuDe` varchar(255) NOT NULL,
  `noiDung` text NOT NULL,
  `thoiGian` datetime DEFAULT current_timestamp(),
  `daXem` tinyint(1) DEFAULT 0,
  `trangThai` enum('Chờ','Đã xử lý','Từ chối') DEFAULT 'Chờ',
  `thoiGianXuLy` datetime DEFAULT NULL,
  `ngayLienQuan` date DEFAULT NULL,
  PRIMARY KEY (`maThongBao`),
  KEY `fk_tba_nguoidung` (`nguoiDungId`),
  KEY `fk_tba_ngaynghi` (`maNghi`),
  KEY `fk_tba_yeucau` (`maYeuCau`),
  CONSTRAINT `fk_tba_ngaynghi` FOREIGN KEY (`maNghi`) REFERENCES `ngaynghi` (`maNghi`) ON DELETE SET NULL,
  CONSTRAINT `fk_tba_nguoidung` FOREIGN KEY (`nguoiDungId`) REFERENCES `nguoidung` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tba_yeucau` FOREIGN KEY (`maYeuCau`) REFERENCES `doimatkhau` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `thongbaobenhnhan`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `thongbaobenhnhan` (
  `maThongBao` int(11) NOT NULL AUTO_INCREMENT,
  `maBenhNhan` varchar(20) NOT NULL,
  `loai` enum('Hệ thống','Lịch khám','Mật khẩu','Khác') NOT NULL DEFAULT 'Hệ thống',
  `tieuDe` varchar(255) NOT NULL,
  `noiDung` text NOT NULL,
  `thoiGian` datetime DEFAULT current_timestamp(),
  `daXem` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`maThongBao`),
  KEY `maBenhNhan` (`maBenhNhan`),
  KEY `idx_daxem` (`daXem`),
  KEY `idx_thoigian` (`thoiGian`),
  CONSTRAINT `thongbaobenhnhan_ibfk_1` FOREIGN KEY (`maBenhNhan`) REFERENCES `benhnhan` (`maBenhNhan`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `thongbaolichkham`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `thongbaolichkham` (
  `maThongBao` int(11) NOT NULL AUTO_INCREMENT,
  `maBacSi` varchar(20) NOT NULL,
  `maLichKham` int(11) DEFAULT NULL,
  `loai` enum('Đặt lịch','Hủy lịch','Hệ thống') NOT NULL DEFAULT 'Đặt lịch',
  `tieuDe` varchar(255) NOT NULL,
  `noiDung` text NOT NULL,
  `thoiGian` datetime DEFAULT current_timestamp(),
  `daXem` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`maThongBao`),
  KEY `maBacSi` (`maBacSi`),
  KEY `maLichKham` (`maLichKham`),
  KEY `idx_daxem` (`daXem`),
  KEY `idx_thoigian` (`thoiGian`),
  CONSTRAINT `thongbao_ibfk_1` FOREIGN KEY (`maBacSi`) REFERENCES `bacsi` (`maBacSi`) ON DELETE CASCADE,
  CONSTRAINT `thongbao_ibfk_2` FOREIGN KEY (`maLichKham`) REFERENCES `lichkham` (`maLichKham`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=573 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `thuoc`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `thuoc` (
  `maThuoc` int(11) NOT NULL AUTO_INCREMENT,
  `tenThuoc` varchar(100) NOT NULL,
  `donViTinh` varchar(20) DEFAULT NULL,
  `soLuongTon` int(11) DEFAULT 0,
  `giaTien` decimal(10,2) DEFAULT NULL,
  `cachDungMacDinh` text DEFAULT NULL,
  `loaiThuoc` varchar(50) DEFAULT NULL COMMENT 'Loại thuốc: kháng sinh, giảm đau, vitamin...',
  `nhaSanXuat` varchar(100) DEFAULT NULL COMMENT 'Nhà sản xuất / Nguồn gốc',
  `hanSuDung` date DEFAULT NULL COMMENT 'Hạn sử dụng mặc định',
  `nguongCanhBao` int(11) NOT NULL DEFAULT 10 COMMENT 'Ngưỡng cảnh báo tồn kho thấp',
  PRIMARY KEY (`maThuoc`),
  KEY `idx_tenThuoc` (`tenThuoc`),
  KEY `idx_loaiThuoc` (`loaiThuoc`)
) ENGINE=InnoDB AUTO_INCREMENT=307 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-22 15:09:49
