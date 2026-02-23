DROP TRIGGER IF EXISTS after_lichkham_update;

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
END$$
DELIMITER ;
