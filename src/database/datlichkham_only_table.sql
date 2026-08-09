-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th8 08, 2026 lúc 08:01 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `datlichkham`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `bacsi`
--

CREATE TABLE `bacsi` (
  `nguoiDungId` int(11) NOT NULL,
  `maBacSi` varchar(20) NOT NULL,
  `tenBacSi` varchar(100) DEFAULT NULL,
  `maChuyenKhoa` varchar(10) DEFAULT NULL,
  `moTa` text DEFAULT NULL,
  `gioiTinh` enum('nam','nu') DEFAULT NULL,
  `namLamViec` smallint(5) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Cấu trúc bảng cho bảng `benhnhan`
--

CREATE TABLE `benhnhan` (
  `nguoiDungId` int(11) NOT NULL,
  `maBenhNhan` varchar(20) NOT NULL,
  `tenBenhNhan` varchar(100) DEFAULT NULL,
  `ngaySinh` date DEFAULT NULL,
  `gioiTinh` enum('nam','nu','khac') DEFAULT NULL,
  `soTheBHYT` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Bẫy `benhnhan`
--
DELIMITER $$
CREATE TRIGGER `validate_birthdate_before_insert` BEFORE INSERT ON `benhnhan` FOR EACH ROW BEGIN
    IF NEW.ngaySinh > CURDATE() THEN
        SET NEW.ngaySinh = CURDATE();
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `validate_birthdate_before_update` BEFORE UPDATE ON `benhnhan` FOR EACH ROW BEGIN
    IF NEW.ngaySinh > CURDATE() THEN
        SET NEW.ngaySinh = CURDATE();
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `calamviec`
--

CREATE TABLE `calamviec` (
  `maCa` int(11) NOT NULL,
  `tenCa` varchar(30) NOT NULL,
  `gioBatDau` time NOT NULL,
  `gioKetThuc` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `calamviec`
--

INSERT INTO `calamviec` (`maCa`, `tenCa`, `gioBatDau`, `gioKetThuc`) VALUES
(1, 'Ca sáng', '07:00:00', '11:00:00'),
(2, 'Ca chiều', '13:00:00', '17:00:00');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chitietdonthuoc`
--

CREATE TABLE `chitietdonthuoc` (
  `id` int(11) NOT NULL,
  `maDonThuoc` int(11) DEFAULT NULL,
  `maThuoc` int(11) DEFAULT NULL,
  `soLuong` int(11) DEFAULT NULL,
  `lieuDung` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Cấu trúc bảng cho bảng `chuyenkhoa`
--

CREATE TABLE `chuyenkhoa` (
  `maChuyenKhoa` varchar(10) NOT NULL,
  `tenChuyenKhoa` varchar(100) NOT NULL,
  `maKhoa` varchar(10) DEFAULT NULL,
  `moTa` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Cấu trúc bảng cho bảng `doimatkhau`
--

CREATE TABLE `doimatkhau` (
  `id` int(11) NOT NULL,
  `nguoiDungId` int(11) NOT NULL,
  `trangThai` enum('Chờ','Đã xử lý','Từ chối') DEFAULT 'Chờ',
  `thoiGianYeuCau` datetime DEFAULT current_timestamp(),
  `thoiGianXuLy` datetime DEFAULT NULL,
  `nguoiXuLy` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Bẫy `doimatkhau`
--
DELIMITER $$
CREATE TRIGGER `after_doimatkhau_insert` AFTER INSERT ON `doimatkhau` FOR EACH ROW BEGIN
    DECLARE userName VARCHAR(100);
    DECLARE userPhone VARCHAR(16);
    DECLARE userRole VARCHAR(20);
    
    SELECT tenDangNhap, soDienThoai, vaiTro
    INTO userName, userPhone, userRole
    FROM nguoidung
    WHERE id = NEW.nguoiDungId;
    
    INSERT INTO thongbaoadmin (
        maYeuCau, -- Đã đổi tên
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
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_doimatkhau_update` AFTER UPDATE ON `doimatkhau` FOR EACH ROW BEGIN
    IF NEW.trangThai != OLD.trangThai THEN
        UPDATE thongbaoadmin
        SET trangThai = NEW.trangThai,
            thoiGianXuLy = NEW.thoiGianXuLy
        WHERE maYeuCau = NEW.id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `donthuoc`
--

CREATE TABLE `donthuoc` (
  `maDonThuoc` int(11) NOT NULL,
  `maLichKham` int(11) DEFAULT NULL,
  `chuanDoan` text DEFAULT NULL,
  `loiDanBacSi` text DEFAULT NULL,
  `ngayKeDon` datetime DEFAULT current_timestamp(),
  `tongTienThuoc` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Cấu trúc bảng cho bảng `goikham`
--

CREATE TABLE `goikham` (
  `maGoi` int(11) NOT NULL,
  `tenGoi` varchar(100) NOT NULL,
  `moTa` text DEFAULT NULL,
  `thoiLuong` int(11) DEFAULT 40,
  `gia` decimal(10,2) NOT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `goikham`
--

INSERT INTO `goikham` (`maGoi`, `tenGoi`, `moTa`, `thoiLuong`, `gia`, `isActive`) VALUES
(1, 'Gói khám thường', 'Khám với bác sĩ tổng quát', 60, 150000.00, 1),
(2, 'Gói khám cao cấp', 'Khám với bác sĩ chuyên gia', 60, 250000.00, 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `hoadon`
--

CREATE TABLE `hoadon` (
  `maHoaDon` int(11) NOT NULL,
  `maLichKham` int(11) DEFAULT NULL,
  `soTien` decimal(10,2) DEFAULT NULL,
  `ngayTao` datetime DEFAULT current_timestamp(),
  `trangThai` enum('Chưa thanh toán','Đã thanh toán') DEFAULT 'Chưa thanh toán',
  `phuongThuc` enum('TienMat','VNPAY') DEFAULT NULL,
  `vnp_TransactionNo` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Cấu trúc bảng cho bảng `hosobenhan`
--

CREATE TABLE `hosobenhan` (
  `maHoSo` varchar(20) NOT NULL,
  `maBenhNhan` varchar(20) DEFAULT NULL,
  `maBacSi` varchar(20) DEFAULT NULL,
  `maLichKham` int(11) DEFAULT NULL,
  `chanDoan` text DEFAULT NULL,
  `dieuTri` text DEFAULT NULL,
  `trangThai` enum('Chưa hoàn thành','Đã hoàn thành') DEFAULT 'Chưa hoàn thành',
  `ngayTao` datetime DEFAULT current_timestamp(),
  `ngayHoanThanh` datetime DEFAULT NULL,
  `ghiChu` text DEFAULT NULL,
  `ngayKham` datetime DEFAULT NULL,
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `deletedAt` datetime DEFAULT NULL,
  `deletedBy` int(11) DEFAULT NULL,
  `deleteReason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Cấu trúc bảng cho bảng `khoa`
--

CREATE TABLE `khoa` (
  `maKhoa` varchar(10) NOT NULL,
  `tenKhoa` varchar(100) NOT NULL,
  `moTa` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Cấu trúc bảng cho bảng `lichkham`
--

CREATE TABLE `lichkham` (
  `maLichKham` int(11) NOT NULL,
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
  `thoiGianDoiLich` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Bẫy `lichkham`
--
DELIMITER $$
CREATE TRIGGER `after_lichkham_insert` AFTER INSERT ON `lichkham` FOR EACH ROW BEGIN
    DECLARE patientName VARCHAR(100);
    DECLARE appointmentDate VARCHAR(20);
    DECLARE shiftName VARCHAR(50);
    DECLARE noteText TEXT DEFAULT '';
    
    -- Lấy thông tin cơ bản
    SELECT tenBenhNhan INTO patientName FROM benhnhan WHERE maBenhNhan = NEW.maBenhNhan;
    SELECT tenCa INTO shiftName FROM calamviec WHERE maCa = NEW.maCa;
    SET appointmentDate = IFNULL(DATE_FORMAT(NEW.ngayKham, '%d/%m/%Y'), '(chưa có ngày)');
    
    -- Xử lý ghi chú: Nếu có ghi chú thì thêm vào nội dung
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
            noteText -- Thêm phần ghi chú vào đây
        ),
        NOW(),
        0
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_lichkham_update` AFTER UPDATE ON `lichkham` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `lienhe`
--

CREATE TABLE `lienhe` (
  `maLienHe` int(11) NOT NULL,
  `hoTen` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `soDienThoai` varchar(15) NOT NULL,
  `chuDe` varchar(100) NOT NULL,
  `noiDung` text NOT NULL,
  `trangThai` enum('Chưa xử lý','Đã xử lý') NOT NULL DEFAULT 'Chưa xử lý',
  `thoiGianGui` datetime NOT NULL DEFAULT current_timestamp(),
  `nguoiXuLy` int(11) DEFAULT NULL,
  `thoiGianXuLy` datetime DEFAULT NULL,
  `ghiChu` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Cấu trúc bảng cho bảng `mail_notification_log`
--

CREATE TABLE `mail_notification_log` (
  `id` int(11) NOT NULL,
  `event_code` varchar(64) NOT NULL,
  `event_key` varchar(191) NOT NULL,
  `recipient_email` varchar(150) NOT NULL,
  `status` enum('sent','failed','skipped') NOT NULL DEFAULT 'sent',
  `error_message` text DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `sent_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Cấu trúc bảng cho bảng `medicine_stock_log`
--

CREATE TABLE `medicine_stock_log` (
  `id` int(11) NOT NULL,
  `maThuoc` int(11) NOT NULL,
  `maLichKham` int(11) DEFAULT NULL,
  `maHoSo` varchar(30) DEFAULT NULL,
  `changeQty` int(11) NOT NULL,
  `balanceAfter` int(11) NOT NULL,
  `actionType` varchar(32) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Cấu trúc bảng cho bảng `ngaynghi`
--

CREATE TABLE `ngaynghi` (
  `maNghi` int(11) NOT NULL,
  `maBacSi` varchar(20) NOT NULL,
  `ngayNghi` date NOT NULL,
  `maCa` int(11) DEFAULT NULL,
  `lyDo` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Bẫy `ngaynghi`
--
DELIMITER $$
CREATE TRIGGER `after_ngaynghi_delete` AFTER DELETE ON `ngaynghi` FOR EACH ROW BEGIN
    DECLARE doctorName VARCHAR(100);
    DECLARE doctorUserId INT;
    DECLARE leaveDate VARCHAR(20);
    DECLARE caInfo VARCHAR(100);
    DECLARE otherShiftExists INT DEFAULT 0;
    
    SELECT tenBacSi, nguoiDungId INTO doctorName, doctorUserId
    FROM bacsi WHERE maBacSi = OLD.maBacSi;
    
    SET leaveDate = DATE_FORMAT(OLD.ngayNghi, '%d/%m/%Y');
    
    -- Kiểm tra xem còn ca nào khác trong ngày đó không
    SELECT COUNT(*) INTO otherShiftExists
    FROM ngaynghi
    WHERE maBacSi = OLD.maBacSi AND ngayNghi = OLD.ngayNghi;
    
    -- Nếu còn ca khác -> Tức là trước đó nghỉ cả ngày, giờ hủy 1 ca -> Cập nhật thông báo thành 1 ca
    -- Nếu không còn ca nào -> Hủy hết -> Gửi thông báo hủy
    
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
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_ngaynghi_insert` AFTER INSERT ON `ngaynghi` FOR EACH ROW BEGIN
    DECLARE doctorName VARCHAR(100);
    DECLARE doctorUserId INT;
    DECLARE existingNotifID INT;
    DECLARE finalReason TEXT;
    
    -- Lấy thông tin bác sĩ
    SELECT tenBacSi, nguoiDungId INTO doctorName, doctorUserId
    FROM bacsi 
    WHERE maBacSi = NEW.maBacSi;
    
    -- Xử lý lý do: Nếu lý do mới là '0' hoặc rỗng, cố gắng giữ lý do cũ (nếu có)
    SET finalReason = NEW.lyDo;
    
    -- KIỂM TRA: Đã có thông báo nào cho User này, Ngày này, Loại 'Nghỉ phép' chưa?
    SELECT maThongBao INTO existingNotifID
    FROM thongbaoadmin
    WHERE nguoiDungId = doctorUserId
      AND loai = 'Nghỉ phép'
      AND ngayLienQuan = NEW.ngayNghi
    LIMIT 1;
    
    IF existingNotifID IS NOT NULL THEN
        -- === TRƯỜNG HỢP 2: ĐÃ CÓ THÔNG BÁO (Tức là đây là insert thứ 2 cho cùng 1 ngày) ===
        -- Cập nhật thông báo cũ thành "Cả ngày"
        
        -- Logic fix lý do: Nếu lý do hiện tại là '0', thử lấy lý do từ dòng db kia
        IF finalReason = '0' OR finalReason = '' THEN
             SELECT lyDo INTO finalReason FROM ngaynghi 
             WHERE maBacSi = NEW.maBacSi AND ngayNghi = NEW.ngayNghi AND maCa != NEW.maCa LIMIT 1;
        END IF;

        UPDATE thongbaoadmin
        SET noiDung = CONCAT('Bác sĩ ', doctorName, ' xin nghỉ phép vào ngày ', DATE_FORMAT(NEW.ngayNghi, '%d/%m/%Y'), ' - Cả ngày. Lý do: ', finalReason),
            thoiGian = NOW(), -- Cập nhật lại thời gian mới nhất
            daXem = 0         -- Đẩy lên thành chưa xem
        WHERE maThongBao = existingNotifID;
        
    ELSE
        -- === TRƯỜNG HỢP 1: CHƯA CÓ THÔNG BÁO (Insert đầu tiên) ===
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
            NEW.ngayNghi -- Lưu ngày để check trùng
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `nguoidung`
--

CREATE TABLE `nguoidung` (
  `id` int(11) NOT NULL,
  `tenDangNhap` varchar(50) NOT NULL,
  `matKhau` varchar(255) NOT NULL,
  `soDienThoai` varchar(16) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `vaiTro` enum('benhnhan','bacsi','quantri') NOT NULL,
  `trangThai` enum('Hoạt Động','Khóa') NOT NULL DEFAULT 'Hoạt Động',
  `ngayCapNhatTaiKhoan` datetime DEFAULT NULL,
  `ngayCapNhatMatKhau` datetime DEFAULT NULL,
  `avatar` varchar(255) DEFAULT 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png',
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `deletedAt` datetime DEFAULT NULL,
  `deletedBy` int(11) DEFAULT NULL,
  `deleteReason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Cấu trúc bảng cho bảng `quantrivien`
--

CREATE TABLE `quantrivien` (
  `nguoiDungId` int(11) NOT NULL,
  `maQuanTriVien` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Cấu trúc bảng cho bảng `suatkham`
--

CREATE TABLE `suatkham` (
  `maSuat` int(11) NOT NULL,
  `maCa` int(11) NOT NULL,
  `gioBatDau` time NOT NULL,
  `gioKetThuc` time NOT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `effectiveFrom` date NOT NULL DEFAULT '1900-01-01',
  `effectiveTo` date DEFAULT NULL,
  `presetMinutes` int(11) NOT NULL DEFAULT 40
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `suatkham`
--

INSERT INTO `suatkham` (`maSuat`, `maCa`, `gioBatDau`, `gioKetThuc`, `isActive`, `effectiveFrom`, `effectiveTo`, `presetMinutes`) VALUES
(1, 1, '07:00:00', '07:40:00', 0, '1900-01-01', '2026-04-20', 40),
(2, 1, '07:40:00', '08:20:00', 0, '1900-01-01', '2026-04-20', 40),
(3, 1, '08:20:00', '09:00:00', 0, '1900-01-01', '2026-04-20', 40),
(4, 1, '09:00:00', '09:40:00', 0, '1900-01-01', '2026-04-20', 40),
(5, 1, '09:40:00', '10:20:00', 0, '1900-01-01', '2026-04-20', 40),
(6, 1, '10:20:00', '11:00:00', 0, '1900-01-01', '2026-04-20', 40),
(7, 2, '13:00:00', '13:40:00', 0, '1900-01-01', '2026-04-20', 40),
(8, 2, '13:40:00', '14:20:00', 0, '1900-01-01', '2026-04-20', 40),
(9, 2, '14:20:00', '15:00:00', 0, '1900-01-01', '2026-04-20', 40),
(10, 2, '15:00:00', '15:40:00', 0, '1900-01-01', '2026-04-20', 40),
(11, 2, '15:40:00', '16:20:00', 0, '1900-01-01', '2026-04-20', 40),
(12, 2, '16:20:00', '17:00:00', 0, '1900-01-01', '2026-04-20', 40),
(65, 1, '07:00:00', '08:00:00', 1, '2026-04-21', NULL, 60),
(66, 1, '08:00:00', '09:00:00', 1, '2026-04-21', NULL, 60),
(67, 1, '09:00:00', '10:00:00', 1, '2026-04-21', NULL, 60),
(68, 1, '10:00:00', '11:00:00', 1, '2026-04-21', NULL, 60),
(69, 2, '13:00:00', '14:00:00', 1, '2026-04-21', NULL, 60),
(70, 2, '14:00:00', '15:00:00', 1, '2026-04-21', NULL, 60),
(71, 2, '15:00:00', '16:00:00', 1, '2026-04-21', NULL, 60),
(72, 2, '16:00:00', '17:00:00', 1, '2026-04-21', NULL, 60);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `thongbaoadmin`
--

CREATE TABLE `thongbaoadmin` (
  `maThongBao` int(11) NOT NULL,
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
  `ngayLienQuan` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Cấu trúc bảng cho bảng `thongbaobenhnhan`
--

CREATE TABLE `thongbaobenhnhan` (
  `maThongBao` int(11) NOT NULL,
  `maBenhNhan` varchar(20) NOT NULL,
  `loai` enum('Hệ thống','Lịch khám','Mật khẩu','Khác') NOT NULL DEFAULT 'Hệ thống',
  `tieuDe` varchar(255) NOT NULL,
  `noiDung` text NOT NULL,
  `thoiGian` datetime DEFAULT current_timestamp(),
  `daXem` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Cấu trúc bảng cho bảng `thongbaolichkham`
--

CREATE TABLE `thongbaolichkham` (
  `maThongBao` int(11) NOT NULL,
  `maBacSi` varchar(20) NOT NULL,
  `maLichKham` int(11) DEFAULT NULL,
  `loai` enum('Đặt lịch','Hủy lịch','Hệ thống') NOT NULL,
  `tieuDe` varchar(255) NOT NULL,
  `noiDung` text NOT NULL,
  `thoiGian` datetime DEFAULT current_timestamp(),
  `daXem` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Cấu trúc bảng cho bảng `thuoc`
--

CREATE TABLE `thuoc` (
  `maThuoc` int(11) NOT NULL,
  `tenThuoc` varchar(100) NOT NULL,
  `donViTinh` varchar(20) DEFAULT NULL,
  `soLuongTon` int(11) DEFAULT 0,
  `giaTien` decimal(10,2) DEFAULT NULL,
  `cachDungMacDinh` text DEFAULT NULL,
  `loaiThuoc` varchar(50) DEFAULT NULL COMMENT 'Loại thuốc: kháng sinh, giảm đau, vitamin...',
  `nhaSanXuat` varchar(100) DEFAULT NULL COMMENT 'Nhà sản xuất / Nguồn gốc',
  `hanSuDung` date DEFAULT NULL COMMENT 'Hạn sử dụng mặc định',
  `nguongCanhBao` int(11) NOT NULL DEFAULT 10 COMMENT 'Ngưỡng cảnh báo tồn kho thấp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `bacsi`
--
ALTER TABLE `bacsi`
  ADD PRIMARY KEY (`maBacSi`),
  ADD UNIQUE KEY `nguoiDungId` (`nguoiDungId`),
  ADD KEY `maChuyenKhoa` (`maChuyenKhoa`),
  ADD KEY `idx_bacsi_ten_ma` (`tenBacSi`,`maBacSi`),
  ADD KEY `idx_bacsi_chuyenkhoa_ten_ma` (`maChuyenKhoa`,`tenBacSi`,`maBacSi`);

--
-- Chỉ mục cho bảng `benhnhan`
--
ALTER TABLE `benhnhan`
  ADD PRIMARY KEY (`maBenhNhan`),
  ADD UNIQUE KEY `nguoiDungId` (`nguoiDungId`);

--
-- Chỉ mục cho bảng `calamviec`
--
ALTER TABLE `calamviec`
  ADD PRIMARY KEY (`maCa`);

--
-- Chỉ mục cho bảng `chitietdonthuoc`
--
ALTER TABLE `chitietdonthuoc`
  ADD PRIMARY KEY (`id`),
  ADD KEY `maDonThuoc` (`maDonThuoc`),
  ADD KEY `maThuoc` (`maThuoc`);

--
-- Chỉ mục cho bảng `chuyenkhoa`
--
ALTER TABLE `chuyenkhoa`
  ADD PRIMARY KEY (`maChuyenKhoa`),
  ADD KEY `maKhoa` (`maKhoa`),
  ADD KEY `idx_chuyenkhoa_makhoa_mack` (`maKhoa`,`maChuyenKhoa`);

--
-- Chỉ mục cho bảng `doimatkhau`
--
ALTER TABLE `doimatkhau`
  ADD PRIMARY KEY (`id`),
  ADD KEY `nguoiDungId` (`nguoiDungId`),
  ADD KEY `nguoiXuLy` (`nguoiXuLy`);

--
-- Chỉ mục cho bảng `donthuoc`
--
ALTER TABLE `donthuoc`
  ADD PRIMARY KEY (`maDonThuoc`),
  ADD KEY `maLichKham` (`maLichKham`);

--
-- Chỉ mục cho bảng `goikham`
--
ALTER TABLE `goikham`
  ADD PRIMARY KEY (`maGoi`);

--
-- Chỉ mục cho bảng `hoadon`
--
ALTER TABLE `hoadon`
  ADD PRIMARY KEY (`maHoaDon`),
  ADD KEY `maLichKham` (`maLichKham`);

--
-- Chỉ mục cho bảng `hosobenhan`
--
ALTER TABLE `hosobenhan`
  ADD PRIMARY KEY (`maHoSo`),
  ADD KEY `maBenhNhan` (`maBenhNhan`),
  ADD KEY `maBacSi` (`maBacSi`),
  ADD KEY `maLichKham` (`maLichKham`),
  ADD KEY `idx_hosobenhan_isDeleted` (`isDeleted`);

--
-- Chỉ mục cho bảng `khoa`
--
ALTER TABLE `khoa`
  ADD PRIMARY KEY (`maKhoa`);

--
-- Chỉ mục cho bảng `lichkham`
--
ALTER TABLE `lichkham`
  ADD PRIMARY KEY (`maLichKham`),
  ADD KEY `maBacSi` (`maBacSi`),
  ADD KEY `maBenhNhan` (`maBenhNhan`),
  ADD KEY `maCa` (`maCa`),
  ADD KEY `maSuat` (`maSuat`),
  ADD KEY `maGoi` (`maGoi`);

--
-- Chỉ mục cho bảng `lienhe`
--
ALTER TABLE `lienhe`
  ADD PRIMARY KEY (`maLienHe`),
  ADD KEY `fk_lienhe_nguoixuly` (`nguoiXuLy`),
  ADD KEY `idx_trangThai` (`trangThai`),
  ADD KEY `idx_thoiGianGui` (`thoiGianGui`);

--
-- Chỉ mục cho bảng `mail_notification_log`
--
ALTER TABLE `mail_notification_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_mail_event_recipient` (`event_code`,`event_key`,`recipient_email`),
  ADD KEY `idx_mail_sent_at` (`sent_at`);

--
-- Chỉ mục cho bảng `medicine_stock_log`
--
ALTER TABLE `medicine_stock_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_medicine_stock_log_maThuoc` (`maThuoc`),
  ADD KEY `idx_medicine_stock_log_maLichKham` (`maLichKham`),
  ADD KEY `idx_medicine_stock_log_maHoSo` (`maHoSo`),
  ADD KEY `idx_medicine_stock_log_createdAt` (`createdAt`);

--
-- Chỉ mục cho bảng `ngaynghi`
--
ALTER TABLE `ngaynghi`
  ADD PRIMARY KEY (`maNghi`),
  ADD KEY `maBacSi` (`maBacSi`),
  ADD KEY `maCa` (`maCa`);

--
-- Chỉ mục cho bảng `nguoidung`
--
ALTER TABLE `nguoidung`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenDangNhap` (`tenDangNhap`),
  ADD UNIQUE KEY `soDienThoai` (`soDienThoai`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_nguoidung_isDeleted` (`isDeleted`);

--
-- Chỉ mục cho bảng `quantrivien`
--
ALTER TABLE `quantrivien`
  ADD PRIMARY KEY (`maQuanTriVien`),
  ADD UNIQUE KEY `nguoiDungId` (`nguoiDungId`);

--
-- Chỉ mục cho bảng `suatkham`
--
ALTER TABLE `suatkham`
  ADD PRIMARY KEY (`maSuat`),
  ADD UNIQUE KEY `uniq_suatkham_slot_version` (`maCa`,`gioBatDau`,`gioKetThuc`,`effectiveFrom`),
  ADD KEY `maCa` (`maCa`);

--
-- Chỉ mục cho bảng `thongbaoadmin`
--
ALTER TABLE `thongbaoadmin`
  ADD PRIMARY KEY (`maThongBao`),
  ADD KEY `fk_tba_nguoidung` (`nguoiDungId`),
  ADD KEY `fk_tba_ngaynghi` (`maNghi`),
  ADD KEY `fk_tba_yeucau` (`maYeuCau`);

--
-- Chỉ mục cho bảng `thongbaobenhnhan`
--
ALTER TABLE `thongbaobenhnhan`
  ADD PRIMARY KEY (`maThongBao`),
  ADD KEY `maBenhNhan` (`maBenhNhan`),
  ADD KEY `idx_daxem` (`daXem`),
  ADD KEY `idx_thoigian` (`thoiGian`);

--
-- Chỉ mục cho bảng `thongbaolichkham`
--
ALTER TABLE `thongbaolichkham`
  ADD PRIMARY KEY (`maThongBao`),
  ADD KEY `maBacSi` (`maBacSi`),
  ADD KEY `maLichKham` (`maLichKham`),
  ADD KEY `idx_daxem` (`daXem`),
  ADD KEY `idx_thoigian` (`thoiGian`);

--
-- Chỉ mục cho bảng `thuoc`
--
ALTER TABLE `thuoc`
  ADD PRIMARY KEY (`maThuoc`),
  ADD KEY `idx_tenThuoc` (`tenThuoc`),
  ADD KEY `idx_loaiThuoc` (`loaiThuoc`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `calamviec`
--
ALTER TABLE `calamviec`
  MODIFY `maCa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `chitietdonthuoc`
--
ALTER TABLE `chitietdonthuoc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `doimatkhau`
--
ALTER TABLE `doimatkhau`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT cho bảng `donthuoc`
--
ALTER TABLE `donthuoc`
  MODIFY `maDonThuoc` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `goikham`
--
ALTER TABLE `goikham`
  MODIFY `maGoi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `hoadon`
--
ALTER TABLE `hoadon`
  MODIFY `maHoaDon` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT cho bảng `lichkham`
--
ALTER TABLE `lichkham`
  MODIFY `maLichKham` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT cho bảng `lienhe`
--
ALTER TABLE `lienhe`
  MODIFY `maLienHe` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `mail_notification_log`
--
ALTER TABLE `mail_notification_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT cho bảng `medicine_stock_log`
--
ALTER TABLE `medicine_stock_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `ngaynghi`
--
ALTER TABLE `ngaynghi`
  MODIFY `maNghi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT cho bảng `nguoidung`
--
ALTER TABLE `nguoidung`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT cho bảng `suatkham`
--
ALTER TABLE `suatkham`
  MODIFY `maSuat` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT cho bảng `thongbaoadmin`
--
ALTER TABLE `thongbaoadmin`
  MODIFY `maThongBao` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT cho bảng `thongbaobenhnhan`
--
ALTER TABLE `thongbaobenhnhan`
  MODIFY `maThongBao` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT cho bảng `thongbaolichkham`
--
ALTER TABLE `thongbaolichkham`
  MODIFY `maThongBao` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=288;

--
-- AUTO_INCREMENT cho bảng `thuoc`
--
ALTER TABLE `thuoc`
  MODIFY `maThuoc` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `bacsi`
--
ALTER TABLE `bacsi`
  ADD CONSTRAINT `bacsi_ibfk_1` FOREIGN KEY (`nguoiDungId`) REFERENCES `nguoidung` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bacsi_ibfk_2` FOREIGN KEY (`maChuyenKhoa`) REFERENCES `chuyenkhoa` (`maChuyenKhoa`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `benhnhan`
--
ALTER TABLE `benhnhan`
  ADD CONSTRAINT `benhnhan_ibfk_1` FOREIGN KEY (`nguoiDungId`) REFERENCES `nguoidung` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `chitietdonthuoc`
--
ALTER TABLE `chitietdonthuoc`
  ADD CONSTRAINT `chitietdonthuoc_ibfk_1` FOREIGN KEY (`maDonThuoc`) REFERENCES `donthuoc` (`maDonThuoc`),
  ADD CONSTRAINT `chitietdonthuoc_ibfk_2` FOREIGN KEY (`maThuoc`) REFERENCES `thuoc` (`maThuoc`);

--
-- Các ràng buộc cho bảng `chuyenkhoa`
--
ALTER TABLE `chuyenkhoa`
  ADD CONSTRAINT `chuyenkhoa_ibfk_1` FOREIGN KEY (`maKhoa`) REFERENCES `khoa` (`maKhoa`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `doimatkhau`
--
ALTER TABLE `doimatkhau`
  ADD CONSTRAINT `doimatkhau_ibfk_1` FOREIGN KEY (`nguoiDungId`) REFERENCES `nguoidung` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `doimatkhau_ibfk_2` FOREIGN KEY (`nguoiXuLy`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `donthuoc`
--
ALTER TABLE `donthuoc`
  ADD CONSTRAINT `donthuoc_ibfk_1` FOREIGN KEY (`maLichKham`) REFERENCES `lichkham` (`maLichKham`);

--
-- Các ràng buộc cho bảng `hoadon`
--
ALTER TABLE `hoadon`
  ADD CONSTRAINT `hoadon_ibfk_1` FOREIGN KEY (`maLichKham`) REFERENCES `lichkham` (`maLichKham`);

--
-- Các ràng buộc cho bảng `hosobenhan`
--
ALTER TABLE `hosobenhan`
  ADD CONSTRAINT `hosobenhan_ibfk_1` FOREIGN KEY (`maBenhNhan`) REFERENCES `benhnhan` (`maBenhNhan`) ON DELETE CASCADE,
  ADD CONSTRAINT `hosobenhan_ibfk_2` FOREIGN KEY (`maBacSi`) REFERENCES `bacsi` (`maBacSi`) ON DELETE SET NULL,
  ADD CONSTRAINT `hosobenhan_ibfk_3` FOREIGN KEY (`maLichKham`) REFERENCES `lichkham` (`maLichKham`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `lichkham`
--
ALTER TABLE `lichkham`
  ADD CONSTRAINT `lichkham_ibfk_1` FOREIGN KEY (`maBacSi`) REFERENCES `bacsi` (`maBacSi`),
  ADD CONSTRAINT `lichkham_ibfk_2` FOREIGN KEY (`maBenhNhan`) REFERENCES `benhnhan` (`maBenhNhan`),
  ADD CONSTRAINT `lichkham_ibfk_3` FOREIGN KEY (`maCa`) REFERENCES `calamviec` (`maCa`),
  ADD CONSTRAINT `lichkham_ibfk_4` FOREIGN KEY (`maSuat`) REFERENCES `suatkham` (`maSuat`),
  ADD CONSTRAINT `lichkham_ibfk_5` FOREIGN KEY (`maGoi`) REFERENCES `goikham` (`maGoi`);

--
-- Các ràng buộc cho bảng `lienhe`
--
ALTER TABLE `lienhe`
  ADD CONSTRAINT `fk_lienhe_nguoixuly` FOREIGN KEY (`nguoiXuLy`) REFERENCES `nguoidung` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `ngaynghi`
--
ALTER TABLE `ngaynghi`
  ADD CONSTRAINT `ngaynghi_ibfk_1` FOREIGN KEY (`maBacSi`) REFERENCES `bacsi` (`maBacSi`),
  ADD CONSTRAINT `ngaynghi_ibfk_2` FOREIGN KEY (`maCa`) REFERENCES `calamviec` (`maCa`);

--
-- Các ràng buộc cho bảng `quantrivien`
--
ALTER TABLE `quantrivien`
  ADD CONSTRAINT `quantrivien_ibfk_1` FOREIGN KEY (`nguoiDungId`) REFERENCES `nguoidung` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `suatkham`
--
ALTER TABLE `suatkham`
  ADD CONSTRAINT `suatkham_ibfk_1` FOREIGN KEY (`maCa`) REFERENCES `calamviec` (`maCa`);

--
-- Các ràng buộc cho bảng `thongbaoadmin`
--
ALTER TABLE `thongbaoadmin`
  ADD CONSTRAINT `fk_tba_ngaynghi` FOREIGN KEY (`maNghi`) REFERENCES `ngaynghi` (`maNghi`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tba_nguoidung` FOREIGN KEY (`nguoiDungId`) REFERENCES `nguoidung` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tba_yeucau` FOREIGN KEY (`maYeuCau`) REFERENCES `doimatkhau` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `thongbaobenhnhan`
--
ALTER TABLE `thongbaobenhnhan`
  ADD CONSTRAINT `thongbaobenhnhan_ibfk_1` FOREIGN KEY (`maBenhNhan`) REFERENCES `benhnhan` (`maBenhNhan`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `thongbaolichkham`
--
ALTER TABLE `thongbaolichkham`
  ADD CONSTRAINT `thongbao_ibfk_1` FOREIGN KEY (`maBacSi`) REFERENCES `bacsi` (`maBacSi`) ON DELETE CASCADE,
  ADD CONSTRAINT `thongbao_ibfk_2` FOREIGN KEY (`maLichKham`) REFERENCES `lichkham` (`maLichKham`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
