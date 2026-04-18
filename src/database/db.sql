CREATE TABLE bacsi (
  nguoiDungId int(11) NOT NULL,
  maBacSi varchar(20) NOT NULL,
  tenBacSi varchar(100) DEFAULT NULL,
  maChuyenKhoa varchar(10) DEFAULT NULL,
  moTa text DEFAULT NULL,
  gioiTinh enum('nam','nu') DEFAULT NULL,
  namLamViec smallint(5) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO bacsi (nguoiDungId, maBacSi, tenBacSi, maChuyenKhoa, moTa, gioiTinh, namLamViec) VALUES
(2, 'bs1', 'Trần Văn Bảo', 'EYE102501', '', 'nam', 2021),
(16, 'BS20251121009', 'Lê Thanh Bình', 'PED102501', 'Bác sĩ chuyên khoa II Nhi khoa. 10 năm công tác tại Bệnh viện Nhi Đồng 1. Rất mát tay và yêu trẻ, chuyên điều trị các bệnh hô hấp ở trẻ nhỏ.', 'nam', 2015),
(17, 'BS20251121010', 'Nguyễn Thị Kim Anh', 'PED102502', 'Tốt nghiệp loại Giỏi Đại học Y Dược. Chuyên gia về chăm sóc và nuôi dưỡng trẻ sơ sinh, đặc biệt là trẻ sinh non nhẹ cân.', 'nu', 2013),
(18, 'BS20251121011', 'Trần Quốc Đạt', 'OBG102501', 'Trưởng khoa Sản bệnh viện Đa khoa Khu vực. Hơn 15 năm kinh nghiệm đỡ sinh và phẫu thuật sản khoa (mổ lấy thai) phức tạp.', 'nam', 2018),
(19, 'BS20251121012', 'Phạm Thị Thanh Thủy', 'OBG102502', 'Chuyên gia tư vấn sức khỏe sinh sản và điều trị các bệnh lý phụ khoa. Tốt nghiệp Thạc sĩ y học tại Pháp. Nhẹ nhàng, tâm lý.', 'nu', 2011),
(20, 'BS20251121013', 'Hoàng Văn Sơn', 'DER102501', 'Chuyên điều trị các bệnh lý về da mãn tính: Vảy nến, Viêm da cơ địa, Nấm da. 7 năm kinh nghiệm tại Bệnh viện Da Liễu.', 'nam', 2018),
(21, 'BS20251121014', 'Vương Thị Mai', 'DER102502', 'Bác sĩ trẻ tài năng, chuyên sâu về Laser thẩm mỹ, trị sẹo rỗ và trẻ hóa da. Chứng chỉ hành nghề thẩm mỹ quốc tế.', 'nu', 2005),
(22, 'BS20251121015', 'Đặng Minh Hiếu', 'ENT102502', 'Kinh nghiệm dày dặn trong phẫu thuật nội soi mũi xoang và điều trị viêm xoang mãn tính.', 'nam', 2008),
(23, 'BS20251121016', 'Bùi Thị Lan', 'ENT102501', 'Chuyên khám và đo thính lực, điều trị viêm tai giữa ở trẻ em và người lớn. Nhẹ nhàng, cẩn thận.', 'nu', 2011),
(24, 'BS20251121017', 'Phan Thành Long', 'DEN102503', 'Chuyên gia cấy ghép Implant và phẫu thuật chấn thương hàm mặt. Tu nghiệp 2 năm tại Hàn Quốc.', 'nam', 2011),
(25, 'BS20251121018', 'Lý Thị Bích Ngọc', 'DEN102502', 'Chuyên sâu về chỉnh nha (niềng răng) thẩm mỹ invisalign. Đã thực hiện thành công hơn 500 ca chỉnh nha phức tạp.', 'nu', 2010),
(26, 'BS20251121019', 'Nguyễn Quang Dũng', 'CAR102502', 'Phó giáo sư, Tiến sĩ y học. Chuyên gia hàng đầu về đặt stent và chụp mạch vành. 25 năm kinh nghiệm.', 'nam', 2019),
(27, 'BS20251121020', 'Trần Thị Hương Giang', 'CAR102501', 'Chuyên điều trị tăng huyết áp, suy tim và rối loạn nhịp tim bằng thuốc. Theo dõi sát sao sức khỏe bệnh nhân cao tuổi.', 'nu', 2019),
(28, 'BS20251121021', 'Vũ Minh Đức', 'INT102503', 'Chuyên điều trị đau đầu, mất ngủ, rối loạn tiền đình và phục hồi sau đột quỵ.', 'nam', 2011),
(29, 'BS20251121022', 'Nguyễn Hoàng Anh', 'INT102503', 'Bác sĩ trẻ, cập nhật các phác đồ điều trị mới về bệnh Parkinson và Alzheimer.', 'nam', 2015),
(30, 'BS20251121023', 'Lê Trung Kiên', 'INT102502', 'Chuyên gia nội soi tiêu hóa (dạ dày, đại tràng) không đau. Tầm soát ung thư đường tiêu hóa sớm.', 'nam', 2013),
(31, 'BS20251121024', 'Trần Thị Kim Huệ', 'INT102502', 'Điều trị viêm loét dạ dày do vi khuẩn HP, bệnh lý gan mật và tư vấn dinh dưỡng tiêu hóa.', 'nu', 2020),
(32, 'BS20251121025', 'Phạm Thanh Hải', 'SUR102502', 'Chuyên gia phẫu thuật thay khớp gối, khớp háng và nội soi khớp vai. Đã thực hiện hơn 1000 ca mổ thành công.', 'nam', 2020),
(33, 'BS20251121026', 'Nguyễn Thị Thu Phương', 'SUR102502', 'Chuyên điều trị bảo tồn (không phẫu thuật) các chấn thương thể thao, thoái hóa khớp và loãng xương.', 'nu', 2019),
(34, 'BS20251121027', 'Trần Trung Nghĩa', 'EYE102502', 'Chuyên điều trị các bệnh lý đáy mắt, võng mạc tiểu đường và thoái hóa điểm vàng. 8 năm kinh nghiệm tại Viện Mắt.', 'nam', 2016),
(35, 'BS20251121028', 'Đỗ Thị Bích', 'EYE102502', 'Điều trị viêm kết mạc, khô mắt và các bệnh lý bề mặt nhãn cầu. Tận tâm, nhẹ nhàng với người cao tuổi.', 'nu', 2020),
(36, 'BS20251121029', 'Nguyễn Hoàng Sơn', 'EYE102503', 'Bàn tay vàng trong phẫu thuật Phaco (đục thủy tinh thể). Đã thực hiện thành công hơn 5000 ca mổ mắt.', 'nam', 2000),
(37, 'BS20251121030', 'Lê Thị Mai Hương', 'EYE102503', 'Chuyên phẫu thuật mộng thịt, quặm mi và thẩm mỹ mắt. Được đào tạo chuyên sâu về tạo hình nhãn khoa.', 'nu', 2014),
(38, 'BS20251121031', 'Phạm Văn Quang', 'INT102501', 'Chuyên gia điều trị Hen suyễn và Phổi tắc nghẽn mạn tính (COPD). Có nhiều công trình nghiên cứu về chức năng hô hấp.', 'nam', 2014),
(39, 'BS20251121032', 'Nguyễn Thị Lan Phương', 'INT102501', 'Chuyên khám và điều trị viêm phổi, viêm phế quản. Tư vấn cai thuốc lá và phục hồi chức năng hô hấp hậu Covid.', 'nu', 2014),
(40, 'BS20251121033', 'Hoàng Văn Khải', 'INT102504', 'Chuyên gia điều trị Đái tháo đường và các bệnh lý Tuyến giáp. Giúp bệnh nhân kiểm soát đường huyết ổn định lâu dài.', 'nam', 2016),
(41, 'BS20251121034', 'Vũ Thị Tú Oanh', 'INT102504', 'Tư vấn dinh dưỡng và điều trị rối loạn chuyển hóa, béo phì, mỡ máu cao. Tốt nghiệp Đại học Y Hà Nội.', 'nu', 2010),
(42, 'BS20251121035', 'Đặng Văn Hùng', 'INT102505', 'Chuyên điều trị hội chứng thận hư, suy thận mạn. Tư vấn các phương pháp lọc máu và bảo tồn chức năng thận.', 'nam', 2017),
(43, 'BS20251121036', 'Lý Thị Minh', 'INT102505', 'Điều trị viêm đường tiết niệu, sỏi thận nhỏ bằng phương pháp nội khoa. Nhẹ nhàng, chu đáo.', 'nu', 2009),
(44, 'BS20251121037', 'Nguyễn Văn Phúc', 'SUR102503', 'Phẫu thuật viên thần kinh sọ não hàng đầu. Chuyên mổ u não, chấn thương sọ não và phẫu thuật cột sống ít xâm lấn.', 'nam', 2019),
(45, 'BS20251121038', 'Trần Thị Yến', 'SUR102503', 'Chuyên gia về phẫu thuật điều trị thoát vị đĩa đệm và đau thần kinh tọa. Áp dụng công nghệ vi phẫu hiện đại.', 'nu', 2009),
(46, 'BS20251121039', 'Bùi Văn Toàn', 'SUR102504', 'Chuyên tán sỏi thận qua da và nội soi ngược dòng. Điều trị phì đại tuyến tiền liệt bằng Laser.', 'nam', 2007),
(47, 'BS20251121040', 'Phạm Thị Hương', 'SUR102504', 'Bác sĩ nữ hiếm hoi trong ngành ngoại tiết niệu. Chuyên điều trị són tiểu, bàng quang tăng hoạt ở phụ nữ.', 'nu', 2014),
(48, 'BS20251121041', 'Lê Minh Vương', 'SUR102505', 'Bàn tay vàng phẫu thuật tim hở và bắc cầu động mạch vành. 15 năm tu nghiệp tại Đức và Pháp.', 'nam', 2013),
(49, 'BS20251121042', 'Nguyễn Thị Kiều Trinh', 'SUR102505', 'Chuyên phẫu thuật nội soi lồng ngực điều trị tăng tiết mồ hôi tay và các bệnh lý phổi, màng phổi.', 'nu', 2016),
(50, 'BS20251121043', 'Đỗ Vũ Hoàng', 'PED102503', 'Chuyên gia tầm soát bệnh tim bẩm sinh ở trẻ em. Từng tu nghiệp 3 năm tại Singapore về can thiệp tim mạch nhi.', 'nam', 2022),
(51, 'BS20251121044', 'Lưu Thị Minh', 'PED102503', 'Nguyên trưởng khoa Nhi Bệnh viện Tim. Hơn 30 năm kinh nghiệm theo dõi và điều trị nội khoa tim mạch cho trẻ sinh non.', 'nu', 2012),
(52, 'BS20251121045', 'Mạc Văn Khoa', 'INT102506', 'Bác sĩ nội trú tốt nghiệp loại xuất sắc. Năng động, cập nhật nhanh các phác đồ điều trị mới về bệnh lý nhiễm trùng và miễn dịch.', 'nam', 2022),
(53, 'BS20251121046', 'Tống Thị Kim', 'INT102506', 'Chuyên khám sức khỏe tổng quát và tư vấn tầm soát ung thư sớm. Có kinh nghiệm dày dặn trong điều trị các bệnh lão khoa.', 'nu', 2008),
(54, 'BS20251121047', 'Nguyễn Bá Duy', 'ENT102503', 'Chuyên phẫu thuật cắt Amidan bằng công nghệ Plasma. Điều trị hiệu quả các bệnh lý khàn tiếng, hạt xơ dây thanh.', 'nam', 2019),
(55, 'BS20251121048', 'Hồ Tiên', 'ENT102503', 'Chuyên gia đầu ngành về bệnh lý ung thư vòm họng và thanh quản. Giảng viên kiêm nhiệm Đại học Y Dược.', 'nu', 2019),
(56, 'BS20251121049', 'Trịnh Quốc Thái', 'DEN102501', 'Bác sĩ trẻ nhiệt huyết, tốt nghiệp Thủ khoa Răng Hàm Mặt. Ứng dụng công nghệ kỹ thuật số trong hàn răng và điều trị tủy.', 'nam', 2024),
(57, 'BS20251121050', 'Bùi Thị Xuân', 'DEN102501', 'Hơn 20 năm kinh nghiệm trong nha khoa gia đình. Nhẹ nhàng, tâm lý, chuyên điều trị sâu răng cho trẻ em không gây đau.', 'nu', 2010);

CREATE TABLE benhnhan (
  nguoiDungId int(11) NOT NULL,
  maBenhNhan varchar(20) NOT NULL,
  tenBenhNhan varchar(100) DEFAULT NULL,
  ngaySinh date DEFAULT NULL,
  gioiTinh enum('nam','nu','khac') DEFAULT NULL,
  soTheBHYT varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO benhnhan (nguoiDungId, maBenhNhan, tenBenhNhan, ngaySinh, gioiTinh, soTheBHYT) VALUES
(1, 'bn1', 'Nguyễn Văn Anh', '2000-01-01', 'nam', ''),
(8, 'BN202511082304701', 'Dương Thanh Hóa', '2005-10-09', 'nam', ''),
(11, 'BN202511101515250', 'Đinh Quốc Thịnh', '1999-11-09', 'khac', 'BH189318214111'),
(15, 'BN2025111712142915', 'Trần Văn Hoàng', '2000-12-31', 'nam', NULL),
(58, 'BN2025112200000058', 'Võ Quốc Thái', '2000-11-21', 'nam', NULL),
(59, 'BN2025112200000059', 'Lê Minh Tuyền', '2005-11-21', 'nam', NULL),
(61, 'BN2026030400000061', 'Nguyễn Đạt', '2005-10-09', 'nam', NULL);
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

CREATE TABLE calamviec (
  maCa int(11) NOT NULL,
  tenCa varchar(30) NOT NULL,
  gioBatDau time NOT NULL,
  gioKetThuc time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO calamviec (maCa, tenCa, gioBatDau, gioKetThuc) VALUES
(1, 'Ca sáng', '07:00:00', '11:00:00'),
(2, 'Ca chiều', '13:00:00', '17:00:00');

CREATE TABLE chitietdonthuoc (
  id int(11) NOT NULL,
  maDonThuoc int(11) DEFAULT NULL,
  maThuoc int(11) DEFAULT NULL,
  soLuong int(11) DEFAULT NULL,
  lieuDung text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE chuyenkhoa (
  maChuyenKhoa varchar(10) NOT NULL,
  tenChuyenKhoa varchar(100) NOT NULL,
  maKhoa varchar(10) DEFAULT NULL,
  moTa text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO chuyenkhoa (maChuyenKhoa, tenChuyenKhoa, maKhoa, moTa) VALUES
('CAR102501', 'Tim Mạch Nội Khoa', 'CAR1025', 'Chẩn đoán và điều trị các bệnh tim bằng thuốc, như tăng huyết áp, rối loạn nhịp tim, suy tim. Khoa chú trọng điều trị lâu dài và phòng ngừa tái phát.'),
('CAR102502', 'Tim Mạch Can Thiệp', 'CAR1025', 'Thực hiện các thủ thuật như nong mạch, đặt stent, chụp mạch vành. Khoa phối hợp chặt chẽ với nội tim mạch để quản lý bệnh nhân sau can thiệp.'),
('DEN102501', 'Nha Khoa Tổng Quát', 'DEN1025', 'Khám, trám, nhổ răng và điều trị sâu răng. Khoa tiếp nhận hầu hết các ca bệnh răng miệng thông thường.'),
('DEN102502', 'Chỉnh Nha', 'DEN1025', 'Điều chỉnh vị trí răng, khớp cắn bằng khí cụ hoặc niềng răng. Khoa giúp cải thiện cả chức năng nhai và thẩm mỹ.'),
('DEN102503', 'Phẫu Thuật Hàm Mặt', 'DEN1025', 'Thực hiện các ca phẫu thuật chấn thương, dị tật và thẩm mỹ vùng mặt. Khoa kết hợp với chuyên khoa tai – mũi – họng khi cần thiết.'),
('DER102501', 'Da Liễu Tổng Quát', 'DER1025', 'Khám và điều trị các bệnh ngoài da như viêm da, mụn, nấm và dị ứng. Khoa áp dụng phác đồ hiện đại để cải thiện tình trạng da.'),
('DER102502', 'Da Liễu Thẩm Mỹ', 'DER1025', 'Cung cấp các dịch vụ chăm sóc và điều trị thẩm mỹ da như laser, trị sẹo, giảm nám. Khoa đảm bảo an toàn và hiệu quả cho từng loại da.'),
('ENT102501', 'Tai Học', 'ENT1025', 'Chẩn đoán và điều trị các bệnh về tai như viêm tai, thủng màng nhĩ, giảm thính lực. Khoa cung cấp dịch vụ đo thính lực và phục hồi thính giác.'),
('ENT102502', 'Mũi Xoang', 'ENT1025', 'Điều trị viêm mũi dị ứng, viêm xoang và polyp mũi. Khoa sử dụng nội soi để chẩn đoán và phẫu thuật chính xác.'),
('ENT102503', 'Họng – Thanh Quản', 'ENT1025', 'Khám và điều trị các bệnh về họng, thanh quản như khàn tiếng, viêm amidan. Khoa cũng thực hiện các phẫu thuật nhỏ như cắt amidan hoặc polyp dây thanh.'),
('EYE102501', 'Khúc Xạ', 'EYE1025', 'Chẩn đoán và điều trị các tật khúc xạ như cận, viễn, loạn thị. Khoa cũng tư vấn sử dụng kính và phẫu thuật laser điều chỉnh thị lực.'),
('EYE102502', 'Mắt Nội Khoa', 'EYE1025', 'Điều trị các bệnh lý như viêm kết mạc, tăng nhãn áp, thoái hóa điểm vàng. Khoa chú trọng phát hiện sớm để ngăn ngừa mất thị lực.'),
('EYE102503', 'Mắt Phẫu Thuật', 'EYE1025', 'Thực hiện các ca phẫu thuật mắt như đục thủy tinh thể, mổ lé và ghép giác mạc. Các bác sĩ được đào tạo chuyên sâu về vi phẫu.'),
('INT102501', 'Nội Hô Hấp', 'INT1025', 'Chuyên khoa Nội hô hấp điều trị các bệnh về đường hô hấp như viêm phổi, hen suyễn, COPD. Khoa cung cấp các dịch vụ chẩn đoán chuyên sâu như đo chức năng hô hấp và nội soi phế quản.'),
('INT102502', 'Nội Tiêu Hóa', 'INT1025', 'Chuyên khoa Nội tiêu hóa tập trung chẩn đoán và điều trị bệnh dạ dày, gan, mật, ruột. Các kỹ thuật nội soi và xét nghiệm sinh hóa thường được sử dụng.'),
('INT102503', 'Nội Thần Kinh', 'INT1025', 'Chuyên khoa Nội thần kinh điều trị các bệnh về não và hệ thần kinh như đột quỵ, động kinh, Parkinson. Khoa phối hợp với chẩn đoán hình ảnh để theo dõi tiến triển bệnh.'),
('INT102504', 'Nội Tiết', 'INT1025', 'Chuyên khoa Nội tiết chuyên điều trị các rối loạn hormone như tiểu đường, cường giáp, béo phì. Khoa thường theo dõi bệnh nhân lâu dài để kiểm soát bệnh mạn tính.'),
('INT102505', 'Nội Thận – Tiết Niệu', 'INT1025', 'Chuyên khoa này điều trị các bệnh lý về thận và đường tiết niệu không cần phẫu thuật. Bao gồm viêm cầu thận, suy thận, sỏi thận giai đoạn nhẹ.'),
('INT102506', 'Nội Tổng Hợp', 'INT1025', 'Chuyên khoa Nội tổng hợp tiếp nhận và điều trị các bệnh thông thường và chưa xác định rõ chuyên khoa. Đây là nơi bệnh nhân được thăm khám tổng quát và phân tuyến điều trị.'),
('OBG102501', 'Sản Khoa', 'OBG1025', 'Theo dõi thai kỳ, đỡ sinh, mổ lấy thai và chăm sóc hậu sản. Khoa đảm bảo an toàn cho cả mẹ và bé trong suốt quá trình mang thai và sinh nở.'),
('OBG102502', 'Phụ Khoa', 'OBG1025', 'Điều trị các bệnh lý phụ khoa như viêm nhiễm, u xơ, rối loạn kinh nguyệt. Ngoài ra còn tư vấn sức khỏe sinh sản cho phụ nữ.'),
('PED102501', 'Nhi Tổng Quát', 'PED1025', 'Khám và điều trị các bệnh thường gặp ở trẻ em như sốt, ho, tiêu chảy, viêm phổi. Khoa đảm bảo theo dõi và tư vấn dinh dưỡng cho trẻ.'),
('PED102502', 'Nhi Sơ Sinh', 'PED1025', 'Chăm sóc và điều trị các bệnh lý ở trẻ sơ sinh, đặc biệt là trẻ sinh non. Khoa có trang thiết bị hỗ trợ hô hấp và nuôi dưỡng đặc biệt.'),
('PED102503', 'Nhi Tim Mạch', 'PED1025', 'Chẩn đoán và điều trị bệnh tim bẩm sinh ở trẻ em. Khoa phối hợp với khoa tim mạch để theo dõi và can thiệp sớm.'),
('SUR102501', 'Ngoại Tổng Hợp', 'SUR1025', 'Chuyên khoa Ngoại tổng hợp thực hiện các phẫu thuật phổ biến như ruột thừa, thoát vị, sỏi mật. Đây là chuyên khoa đa dạng, tiếp nhận nhiều loại bệnh lý khác nhau.'),
('SUR102502', 'Ngoại Chấn Thương Chỉnh Hình', 'SUR1025', 'Điều trị gãy xương, trật khớp và các chấn thương cơ xương khớp. Khoa thường phối hợp với vật lý trị liệu để phục hồi vận động.'),
('SUR102503', 'Ngoại Thần Kinh', 'SUR1025', 'Thực hiện phẫu thuật liên quan đến não, cột sống và tủy sống. Khoa xử lý các ca chấn thương sọ não, u não và thoát vị đĩa đệm.'),
('SUR102504', 'Ngoại Tiết Niệu', 'SUR1025', 'Điều trị bằng phẫu thuật cho các bệnh lý thận, bàng quang và tuyến tiền liệt. Khoa ứng dụng công nghệ nội soi và laser trong điều trị.'),
('SUR102505', 'Ngoại Lồng Ngực – Tim Mạch', 'SUR1025', 'Chuyên về phẫu thuật tim, phổi và mạch máu lớn. Các ca mổ đòi hỏi kỹ thuật cao được thực hiện tại đây.');

CREATE TABLE doimatkhau (
  id int(11) NOT NULL,
  nguoiDungId int(11) NOT NULL,
  trangThai enum('Chờ','Đã xử lý','Từ chối') DEFAULT 'Chờ',
  thoiGianYeuCau datetime DEFAULT current_timestamp(),
  thoiGianXuLy datetime DEFAULT NULL,
  nguoiXuLy int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO doimatkhau (id, nguoiDungId, trangThai, thoiGianYeuCau, thoiGianXuLy, nguoiXuLy) VALUES
(6, 58, 'Đã xử lý', '2025-11-24 22:12:44', '2025-11-24 22:13:00', 3),
(7, 2, 'Đã xử lý', '2025-11-24 22:14:15', '2025-11-24 22:14:26', 3),
(8, 58, 'Từ chối', '2025-11-24 22:17:16', '2025-11-24 22:18:00', 3),
(9, 58, 'Từ chối', '2025-11-24 22:20:20', '2025-11-24 22:20:35', 3),
(12, 29, 'Đã xử lý', '2026-03-04 23:25:19', '2026-03-04 23:25:55', 3);
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

CREATE TABLE donthuoc (
  maDonThuoc int(11) NOT NULL,
  maLichKham int(11) DEFAULT NULL,
  chuanDoan text DEFAULT NULL,
  loiDanBacSi text DEFAULT NULL,
  ngayKeDon datetime DEFAULT current_timestamp(),
  tongTienThuoc decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE goikham (
  maGoi int(11) NOT NULL,
  tenGoi varchar(100) NOT NULL,
  moTa text DEFAULT NULL,
  thoiLuong int(11) DEFAULT 40,
  gia decimal(10,2) NOT NULL,
  isActive tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO goikham (maGoi, tenGoi, moTa, thoiLuong, gia, isActive) VALUES
(1, 'Gói khám thường', 'Khám với bác sĩ tổng quát', 40, 150000.00, 1),
(2, 'Gói khám cao cấp', 'Khám với bác sĩ chuyên gia', 40, 250000.00, 1);

CREATE TABLE hoadon (
  maHoaDon int(11) NOT NULL,
  maLichKham int(11) DEFAULT NULL,
  soTien decimal(10,2) DEFAULT NULL,
  ngayTao datetime DEFAULT current_timestamp(),
  trangThai enum('Chưa thanh toán','Đã thanh toán') DEFAULT 'Chưa thanh toán',
  phuongThuc enum('TienMat','VNPAY') DEFAULT NULL,
  vnp_TransactionNo varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO hoadon (maHoaDon, maLichKham, soTien, ngayTao, trangThai, phuongThuc, vnp_TransactionNo) VALUES
(1, 77, 150000.00, '2026-02-23 22:55:04', 'Chưa thanh toán', NULL, NULL),
(2, 78, 250000.00, '2026-02-23 22:55:43', 'Chưa thanh toán', NULL, NULL);

CREATE TABLE hosobenhan (
  maHoSo varchar(20) NOT NULL,
  maBenhNhan varchar(20) DEFAULT NULL,
  maBacSi varchar(20) DEFAULT NULL,
  maLichKham int(11) DEFAULT NULL,
  chanDoan text DEFAULT NULL,
  dieuTri text DEFAULT NULL,
  trangThai enum('Chưa hoàn thành','Đã hoàn thành') DEFAULT 'Chưa hoàn thành',
  ngayTao datetime DEFAULT current_timestamp(),
  ngayHoanThanh datetime DEFAULT NULL,
  ghiChu text DEFAULT NULL,
  ngayKham datetime DEFAULT NULL,
  isDeleted tinyint(1) NOT NULL DEFAULT 0,
  deletedAt datetime DEFAULT NULL,
  deletedBy int(11) DEFAULT NULL,
  deleteReason text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO hosobenhan (maHoSo, maBenhNhan, maBacSi, maLichKham, chanDoan, dieuTri, trangThai, ngayTao, ngayHoanThanh, ghiChu, ngayKham, isDeleted, deletedAt, deletedBy, deleteReason) VALUES
('HS20251118184754834', 'BN2025111712142915', 'bs1', 26, 'Xong', 'Xong', 'Đã hoàn thành', '2025-11-18 18:47:54', '2025-11-18 19:33:24', '', NULL, 0, NULL, NULL, NULL),
('HS20251119083129501', 'BN2025111712142915', 'bs1', 30, '1', '1', 'Đã hoàn thành', '2025-11-19 08:31:29', '2025-11-19 08:31:52', '1', '2025-11-19 00:00:00', 0, NULL, NULL, NULL),
('HS20251119083135625', 'bn1', 'bs1', 29, '2', '2', 'Đã hoàn thành', '2025-11-19 08:31:35', '2025-11-19 08:31:55', '2', '2025-11-19 00:00:00', 0, NULL, NULL, NULL),
('HS20251119083141153', 'BN202511082304701', 'bs1', 28, '3', '3', 'Đã hoàn thành', '2025-11-19 08:31:41', '2025-11-19 08:31:57', '3', '2025-11-19 00:00:00', 0, NULL, NULL, NULL),
('HS20251119083148530', 'BN202511101515250', 'bs1', 27, '4', '4', 'Đã hoàn thành', '2025-11-19 08:31:48', '2025-11-19 08:31:59', '4', '2025-11-19 00:00:00', 0, NULL, NULL, NULL),
('HS20251123220207601', 'BN2025112200000058', 'bs1', 46, '1', '1', 'Đã hoàn thành', '2025-11-23 22:02:07', '2025-11-23 22:02:18', '1', '2025-11-23 00:00:00', 0, NULL, NULL, NULL),
('HS20251123220214894', 'BN2025112200000058', 'bs1', 45, '2', '2', 'Đã hoàn thành', '2025-11-23 22:02:14', '2025-11-23 22:02:21', '2', '2025-11-23 00:00:00', 0, NULL, NULL, NULL),
('HS20251130125617643', 'bn1', 'BS20251121022', 54, 'text', 'text', 'Đã hoàn thành', '2025-11-30 12:56:17', '2025-11-30 12:56:22', '', '2025-11-30 00:00:00', 0, NULL, NULL, NULL),
('HS20251130203924290', 'bn1', 'bs1', 62, 'Đau mắt, mờ mắt', 'Hạn chế tiếp xúc với ánh sáng xanh', 'Đã hoàn thành', '2025-11-30 20:39:24', '2025-11-30 23:19:48', '', '2025-12-01 00:00:00', 0, NULL, NULL, NULL),
('HS20251130204147751', 'bn1', 'bs1', 64, 'Mỏi mắt', 'Eyemiru 40 EX 15ml', 'Đã hoàn thành', '2025-11-30 20:41:47', '2025-11-30 23:19:52', 'ko', '2025-11-30 00:00:00', 0, NULL, NULL, NULL),
('HS20251130204237979', 'BN2025112200000058', 'bs1', 65, 'Mờ mắt', 'Eyemiru 40EX 15ml', 'Đã hoàn thành', '2025-11-30 20:42:37', '2025-11-30 23:19:50', '', '2025-11-30 00:00:00', 0, NULL, NULL, NULL),
('HS20251202131115238', 'BN2025111712142915', 'bs1', 73, '1', '1', 'Đã hoàn thành', '2025-12-02 13:11:15', '2025-12-04 20:09:27', '', '2025-12-05 00:00:00', 0, NULL, NULL, NULL),
('HS20251202131123736', 'BN2025112200000059', 'bs1', 71, '2', '2', 'Đã hoàn thành', '2025-12-02 13:11:23', '2025-12-04 20:14:59', '', '2025-12-04 00:00:00', 0, NULL, NULL, NULL),
('HS20251202131128242', 'BN202511101515250', 'bs1', 72, '3', '3', 'Đã hoàn thành', '2025-12-02 13:11:28', '2025-12-04 20:14:38', '', '2025-12-04 00:00:00', 0, NULL, NULL, NULL),
('HS20251204200920792', 'bn1', 'bs1', 70, '1', '1', 'Đã hoàn thành', '2025-12-04 20:09:20', '2025-12-04 20:15:02', '', '2025-12-04 00:00:00', 0, NULL, NULL, NULL);

CREATE TABLE khoa (
  maKhoa varchar(10) NOT NULL,
  tenKhoa varchar(100) NOT NULL,
  moTa text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO khoa (maKhoa, tenKhoa, moTa) VALUES
('CAR1025', 'Khoa Tim mạch', 'Khoa Tim mạch tập trung khám và điều trị các bệnh về tim và mạch máu. Đây là khoa chuyên sâu trong chẩn đoán, theo dõi và phục hồi chức năng tim mạch.'),
('DEN1025', 'Khoa Răng – Hàm – Mặt', 'Khoa này chuyên điều trị các bệnh lý răng miệng, hàm và vùng mặt. Ngoài điều trị, khoa còn thực hiện phẫu thuật thẩm mỹ và chỉnh hình hàm mặt.'),
('DER1025', 'Khoa Da liễu', 'Khoa Da liễu chuyên khám và điều trị các bệnh ngoài da, tóc, móng. Ngoài điều trị bệnh, khoa còn cung cấp dịch vụ thẩm mỹ da như laser, trị sẹo và nám.'),
('ENT1025', 'Khoa Tai – Mũi – Họng', 'Khoa Tai - Mũi - Họng chẩn đoán và điều trị các bệnh lý về tai, mũi, họng và thanh quản. Khoa cũng đảm nhận các ca phẫu thuật nhỏ trong khu vực đầu – cổ.'),
('EYE1025', 'Khoa Mắt', 'Khoa Mắt chịu trách nhiệm khám và điều trị các bệnh về mắt và thị lực. Ngoài ra còn thực hiện các phẫu thuật khúc xạ, đục thủy tinh thể và các tật bẩm sinh.'),
('INT1025', 'Khoa Nội', 'Khoa Nội chuyên khám và điều trị các bệnh lý bên trong cơ thể mà không cần phẫu thuật. Đây là nơi tập trung nhiều chuyên khoa như hô hấp, tiêu hóa, thần kinh và nội tổng hợp.'),
('OBG1025', 'Khoa Sản', 'Khoa Sản theo dõi, chăm sóc và điều trị cho phụ nữ trong thời kỳ mang thai và sau sinh. Ngoài ra, khoa còn hỗ trợ điều trị các bệnh lý phụ khoa thường gặp.'),
('PED1025', 'Khoa Nhi', 'Khoa Nhi chịu trách nhiệm chăm sóc sức khỏe cho trẻ sơ sinh và trẻ em. Khoa chuyên điều trị các bệnh thường gặp ở trẻ nhỏ, từ hô hấp đến tim mạch bẩm sinh.'),
('SUR1025', 'Khoa Ngoại', 'Khoa Ngoại đảm nhiệm các ca phẫu thuật và điều trị bằng can thiệp ngoại khoa. Tại đây tiếp nhận các trường hợp cần mổ từ đơn giản đến phức tạp.');

CREATE TABLE lichkham (
  maLichKham int(11) NOT NULL,
  maBacSi varchar(20) NOT NULL,
  maBenhNhan varchar(20) NOT NULL,
  ngayKham date NOT NULL,
  maCa int(11) NOT NULL,
  maSuat int(11) NOT NULL,
  maGoi int(11) DEFAULT NULL,
  trangThai enum('Chờ','Đã đặt','Hoàn thành','Hủy') DEFAULT 'Đã đặt',
  ghiChu text DEFAULT NULL,
  nguoiHuy enum('benhnhan','bacsi','quantri','hethong') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO lichkham (maLichKham, maBacSi, maBenhNhan, ngayKham, maCa, maSuat, maGoi, trangThai, ghiChu, nguoiHuy) VALUES
(8, 'bs1', 'bn1', '2025-11-12', 1, 1, 1, 'Hủy', NULL, NULL),
(26, 'bs1', 'BN2025111712142915', '2025-11-18', 1, 2, 2, 'Hoàn thành', NULL, NULL),
(27, 'bs1', 'BN202511101515250', '2025-11-19', 1, 1, 1, 'Hoàn thành', NULL, NULL),
(28, 'bs1', 'BN202511082304701', '2025-11-19', 1, 6, 1, 'Hoàn thành', NULL, NULL),
(29, 'bs1', 'bn1', '2025-11-19', 2, 7, 2, 'Hoàn thành', NULL, NULL),
(30, 'bs1', 'BN2025111712142915', '2025-11-19', 2, 12, 2, 'Hoàn thành', NULL, NULL),
(35, 'BS20251121022', 'bn1', '2025-11-22', 1, 1, 1, 'Hủy', '1\n[Lý do hủy]: 1', NULL),
(36, 'BS20251121022', 'bn1', '2025-11-22', 1, 2, 1, 'Hủy', '1\n[Lý do hủy]: a', NULL),
(38, 'BS20251121027', 'bn1', '2025-11-24', 1, 1, 1, 'Hủy', 'Không\n[Lý do hủy]: x', NULL),
(39, 'BS20251121027', 'BN2025112200000058', '2025-11-24', 1, 2, 2, 'Hủy', '1\n[Lý do hủy]: Thử', NULL),
(40, 'BS20251121028', 'BN2025112200000058', '2025-11-29', 1, 1, 2, 'Hủy', '\n[Lý do hủy]: Hủy', NULL),
(41, 'BS20251121027', 'BN2025112200000058', '2025-11-24', 1, 2, 2, 'Hủy', '1\n[Lý do hủy]: 1', NULL),
(42, 'BS20251121027', 'BN2025112200000058', '2025-11-24', 1, 2, 2, 'Hủy', '2\n[Lý do hủy]: 2', NULL),
(43, 'BS20251121027', 'BN2025112200000058', '2025-11-23', 1, 4, 2, 'Hủy', '\n[Lý do hủy]: a', NULL),
(44, 'BS20251121028', 'BN2025112200000058', '2025-11-23', 2, 12, 2, 'Hủy', '\n[Lý do hủy]: a', NULL),
(45, 'bs1', 'BN2025112200000058', '2025-11-23', 1, 6, 2, 'Hoàn thành', '', NULL),
(46, 'bs1', 'BN2025112200000058', '2025-11-23', 2, 12, 2, 'Hoàn thành', '', NULL),
(47, 'bs1', 'bn1', '2025-12-01', 1, 3, 2, 'Hủy', '22121\n[Lý do hủy]: 2', NULL),
(48, 'BS20251121022', 'bn1', '2025-12-01', 1, 2, 1, 'Hủy', '1\n[Lý do hủy]: 2', NULL),
(49, 'BS20251121022', 'bn1', '2025-12-01', 1, 2, 1, 'Hủy', 'ds\n[Lý do hủy]: ds', NULL),
(50, 'BS20251121022', 'bn1', '2025-12-02', 1, 2, 2, 'Hủy', 'f\n[Lý do hủy]: f1', NULL),
(51, 'BS20251121022', 'bn1', '2025-12-01', 2, 11, 1, 'Hủy', '1\n[Lý do hủy]: 1', NULL),
(52, 'BS20251121022', 'bn1', '2025-12-03', 1, 3, 2, 'Hủy', '', NULL),
(53, 'BS20251121022', 'bn1', '2025-12-01', 1, 4, 2, 'Hủy', '', NULL),
(54, 'BS20251121022', 'bn1', '2025-11-30', 1, 3, 2, 'Hoàn thành', '', NULL),
(55, 'BS20251121022', 'bn1', '2025-12-06', 1, 3, 2, 'Hủy', '', NULL),
(56, 'BS20251121022', 'bn1', '2025-12-01', 1, 6, 1, 'Hủy', '\n[Lý do hủy]: a', NULL),
(57, 'BS20251121022', 'bn1', '2025-12-01', 1, 3, 2, 'Hủy', '', NULL),
(58, 'BS20251121022', 'bn1', '2025-12-01', 1, 6, 2, 'Hủy', '\n[Lý do hủy]: a', 'benhnhan'),
(59, 'BS20251121022', 'bn1', '2025-12-01', 1, 4, 1, 'Hủy', '1', 'bacsi'),
(60, 'BS20251121022', 'bn1', '2025-12-03', 1, 3, 1, 'Hủy', '12121\n[Lý do hủy]: adsdada', 'benhnhan'),
(61, 'BS20251121022', 'bn1', '2025-12-02', 1, 6, 2, 'Hủy', 'test\n[Lý do hủy]: bận việc', 'benhnhan'),
(62, 'bs1', 'bn1', '2025-12-01', 1, 4, 1, 'Hủy', '\n[Lý do hủy]: 1', 'benhnhan'),
(63, 'BS20251121028', 'bn1', '2025-12-05', 1, 4, 2, 'Hủy', '\n[Lý do hủy]: 1', 'benhnhan'),
(64, 'bs1', 'bn1', '2025-11-30', 1, 1, 2, 'Hoàn thành', '', NULL),
(65, 'bs1', 'BN2025112200000058', '2025-11-30', 2, 8, 2, 'Hoàn thành', '', NULL),
(66, 'bs1', 'bn1', '2025-12-03', 1, 1, 1, 'Hoàn thành', 'Bệnh nhân tái khám, sức khỏe tốt.', NULL),
(67, 'bs1', 'BN2025112200000058', '2025-12-03', 1, 2, 2, 'Đã đặt', 'Đau đầu nhẹ, cần kiểm tra huyết áp.', NULL),
(68, 'bs1', 'BN2025111712142915', '2025-12-03', 1, 3, 1, 'Hủy', 'Bận việc đột xuất\n[Lý do hủy]: Bệnh nhân yêu cầu hủy', NULL),
(69, 'bs1', 'BN202511082304701', '2025-12-03', 2, 7, 2, 'Đã đặt', NULL, NULL),
(70, 'bs1', 'bn1', '2025-12-04', 1, 1, 1, 'Hủy', 'Khám tổng quát\n[Lý do hủy]: 1', 'benhnhan'),
(71, 'bs1', 'BN2025112200000059', '2025-12-04', 1, 2, 1, 'Đã đặt', NULL, NULL),
(72, 'bs1', 'BN202511101515250', '2025-12-04', 1, 3, 2, 'Đã đặt', 'Có triệu chứng sốt nhẹ', NULL),
(73, 'bs1', 'BN2025111712142915', '2025-12-05', 2, 10, 2, 'Đã đặt', 'Tái khám theo hẹn', NULL),
(74, 'bs1', 'bn1', '2025-12-02', 2, 11, 2, 'Hủy', 'không\n[Lý do hủy]: 1', 'benhnhan'),
(75, 'BS20251121022', 'bn1', '2025-12-05', 1, 1, 1, 'Hủy', '\n[Lý do hủy]: 1', 'benhnhan'),
(76, 'BS20251121027', 'BN2025112200000058', '2025-12-05', 1, 1, 1, 'Đã đặt', '', NULL),
(77, 'BS20251121022', 'bn1', '2026-02-24', 1, 3, 1, 'Hủy', 'Không!', 'bacsi'),
(78, 'BS20251121022', 'bn1', '2026-02-24', 1, 4, 2, 'Hủy', '', 'bacsi');
DELIMITER $$
CREATE TRIGGER `after_lichkham_insert` AFTER INSERT ON `lichkham` FOR EACH ROW BEGIN
    DECLARE patientName VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE appointmentDate VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE shiftName VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE noteText TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '';
    DECLARE messageText TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '';
    
    -- Lấy thông tin cơ bản, luôn trả về 1 dòng để tránh NULL ngoài ý muốn
    SELECT COALESCE(
        CONVERT(MAX(tenBenhNhan) USING utf8mb4) COLLATE utf8mb4_unicode_ci,
        _utf8mb4'Không rõ bệnh nhân' COLLATE utf8mb4_unicode_ci
    )
    INTO patientName
    FROM benhnhan
    WHERE maBenhNhan = NEW.maBenhNhan;

    SELECT COALESCE(
        CONVERT(MAX(tenCa) USING utf8mb4) COLLATE utf8mb4_unicode_ci,
        _utf8mb4'Không rõ ca' COLLATE utf8mb4_unicode_ci
    )
    INTO shiftName
    FROM calamviec
    WHERE maCa = NEW.maCa;

    SET appointmentDate = COALESCE(
        CONVERT(DATE_FORMAT(NEW.ngayKham, '%d/%m/%Y') USING utf8mb4) COLLATE utf8mb4_unicode_ci,
        _utf8mb4'(chưa có ngày)' COLLATE utf8mb4_unicode_ci
    );
    
    -- Xử lý ghi chú: Nếu có ghi chú thì thêm vào nội dung
    IF NEW.ghiChu IS NOT NULL AND NEW.ghiChu != '' THEN
        SET noteText = CONCAT(
            _utf8mb4'. Ghi chú: ' COLLATE utf8mb4_unicode_ci,
            CONVERT(NEW.ghiChu USING utf8mb4) COLLATE utf8mb4_unicode_ci
        );
    END IF;

    -- Nối chuỗi theo từng bước để tương thích tốt hơn trên host
    SET messageText = CONCAT(
        _utf8mb4'Bệnh nhân ' COLLATE utf8mb4_unicode_ci,
        patientName
    );
    SET messageText = CONCAT(
        messageText,
        _utf8mb4' đã đặt lịch khám vào ngày ' COLLATE utf8mb4_unicode_ci
    );
    SET messageText = CONCAT(messageText, appointmentDate);
    SET messageText = CONCAT(messageText, _utf8mb4' - ' COLLATE utf8mb4_unicode_ci);
    SET messageText = CONCAT(messageText, shiftName);
    SET messageText = CONCAT(messageText, noteText);
    
    INSERT INTO thongbaolichkham (maBacSi, maLichKham, loai, tieuDe, noiDung, thoiGian, daXem)
    VALUES (
        NEW.maBacSi,
        NEW.maLichKham,
        'Đặt lịch',
        'Lịch khám mới',
        messageText,
        NOW(),
        0
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_lichkham_update` AFTER UPDATE ON `lichkham` FOR EACH ROW BEGIN
    DECLARE patientName VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE doctorName VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE appointmentDate VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE shiftName VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE slotTime VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE cancelSource VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE cancelActor VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE reason TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '';

    IF NEW.trangThai = 'Hủy' AND OLD.trangThai != 'Hủy' THEN
        SELECT CONVERT(tenBenhNhan USING utf8mb4) COLLATE utf8mb4_unicode_ci
        INTO patientName FROM benhnhan WHERE maBenhNhan = NEW.maBenhNhan;
        SELECT CONVERT(tenBacSi USING utf8mb4) COLLATE utf8mb4_unicode_ci
        INTO doctorName FROM bacsi WHERE maBacSi = NEW.maBacSi;
        SELECT CONVERT(tenCa USING utf8mb4) COLLATE utf8mb4_unicode_ci
        INTO shiftName FROM calamviec WHERE maCa = NEW.maCa;
        SELECT CONCAT(
            CONVERT(SUBSTRING(gioBatDau, 1, 5) USING utf8mb4) COLLATE utf8mb4_unicode_ci,
            _utf8mb4' - ' COLLATE utf8mb4_unicode_ci,
            CONVERT(SUBSTRING(gioKetThuc, 1, 5) USING utf8mb4) COLLATE utf8mb4_unicode_ci
        )
        INTO slotTime FROM suatkham WHERE maSuat = NEW.maSuat;
        SET appointmentDate = CONVERT(DATE_FORMAT(NEW.ngayKham, '%d/%m/%Y') USING utf8mb4) COLLATE utf8mb4_unicode_ci;

        IF NEW.ghiChu LIKE '%[Lý do hủy]:%' THEN
            SET reason = CONVERT(SUBSTRING_INDEX(NEW.ghiChu, '[Lý do hủy]: ', -1) USING utf8mb4) COLLATE utf8mb4_unicode_ci;
        ELSE
            SET reason = _utf8mb4'Không có lý do cụ thể' COLLATE utf8mb4_unicode_ci;
        END IF;

        SET cancelActor = LOWER(TRIM(COALESCE(CONVERT(NEW.nguoiHuy USING utf8mb4) COLLATE utf8mb4_unicode_ci, '')));

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
                        _utf8mb4'Bệnh nhân ' COLLATE utf8mb4_unicode_ci, patientName,
                        _utf8mb4' đã hủy lịch khám ngày ' COLLATE utf8mb4_unicode_ci, appointmentDate,
                        _utf8mb4' - ' COLLATE utf8mb4_unicode_ci, shiftName,
                        _utf8mb4'. Lý do: ' COLLATE utf8mb4_unicode_ci, reason
                    ),
                    NOW(),
                    0
                );
            END IF;
        ELSEIF cancelActor IN ('bacsi', 'quantri', 'hethong') THEN
            SET cancelSource = CASE
                WHEN cancelActor = 'bacsi' THEN _utf8mb4'Bác sĩ' COLLATE utf8mb4_unicode_ci
                WHEN cancelActor = 'quantri' THEN _utf8mb4'Quản trị viên' COLLATE utf8mb4_unicode_ci
                WHEN cancelActor = 'hethong' THEN _utf8mb4'Hệ thống' COLLATE utf8mb4_unicode_ci
                ELSE _utf8mb4'Bệnh viện' COLLATE utf8mb4_unicode_ci
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
                        _utf8mb4'Lịch khám ngày ' COLLATE utf8mb4_unicode_ci, appointmentDate,
                        _utf8mb4' đã bị hủy bởi ' COLLATE utf8mb4_unicode_ci, cancelSource,
                        _utf8mb4'. Lý do: ' COLLATE utf8mb4_unicode_ci, reason,
                        _utf8mb4'. Vui lòng đặt lịch mới.' COLLATE utf8mb4_unicode_ci
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

DELIMITER $$
CREATE EVENT `auto_cancel_expired_appointments`
ON SCHEDULE EVERY 1 DAY
STARTS TIMESTAMP(CURRENT_DATE, '17:05:00')
DO
BEGIN
    UPDATE lichkham
    SET
        trangThai = 'Hủy',
        nguoiHuy = 'hethong',
        ghiChu = CASE
            WHEN ghiChu IS NULL OR TRIM(ghiChu) = '' THEN
                '[Lý do hủy]: Quá thời gian khám trong ngày'
            WHEN ghiChu LIKE '%[Lý do hủy]:%' THEN
                ghiChu
            ELSE
                CONCAT(ghiChu, '\n[Lý do hủy]: Quá thời gian khám trong ngày')
        END
    WHERE
        ngayKham <= CURDATE()
        AND trangThai NOT IN ('Hoàn thành', 'Hủy');
END
$$
DELIMITER ;

CREATE TABLE lienhe (
  maLienHe int(11) NOT NULL,
  hoTen varchar(100) NOT NULL,
  email varchar(100) NOT NULL,
  soDienThoai varchar(15) NOT NULL,
  chuDe varchar(100) NOT NULL,
  noiDung text NOT NULL,
  trangThai enum('Chưa xử lý','Đã xử lý') NOT NULL DEFAULT 'Chưa xử lý',
  thoiGianGui datetime NOT NULL DEFAULT current_timestamp(),
  nguoiXuLy int(11) DEFAULT NULL,
  thoiGianXuLy datetime DEFAULT NULL,
  ghiChu text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO lienhe (maLienHe, hoTen, email, soDienThoai, chuDe, noiDung, trangThai, thoiGianGui, nguoiXuLy, thoiGianXuLy, ghiChu) VALUES
(1, 'Test', 'test@gmail.com', '0123456789', 'Khác', 'test', 'Đã xử lý', '2025-11-23 19:21:51', 3, '2025-11-26 09:50:39', NULL),
(2, 'testtwo', 'two@gmail.vn', '0987654321', 'Khác', 'a', 'Đã xử lý', '2025-11-23 19:42:29', 3, '2025-11-26 09:50:35', NULL),
(3, 'hovaten', 'example@gamil.com', '0123456789', 'Khác', 'test n', 'Đã xử lý', '2025-11-24 17:10:15', 3, '2025-11-26 09:50:31', NULL),
(4, 'Nguyễn Đạt', 'dat123456789fa+lienhe1@gmail.com', '0123123123', 'Khác', 'Hi', 'Đã xử lý', '2026-03-04 23:58:56', 3, '2026-03-05 00:01:02', 'Gửi lại lời chào cho khách hàng');

CREATE TABLE mail_notification_log (
  id int(11) NOT NULL,
  event_code varchar(64) NOT NULL,
  event_key varchar(191) NOT NULL,
  recipient_email varchar(150) NOT NULL,
  status enum('sent','failed','skipped') NOT NULL DEFAULT 'sent',
  error_message text DEFAULT NULL,
  payload longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(payload)),
  sent_at datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO mail_notification_log (id, event_code, event_key, recipient_email, status, error_message, payload, sent_at) VALUES
(1, 'appointment_cancelled_patient', '61:cancel:2025-12-02:4caa2073cbd316a4c37de3eacd5ddb36', 'dat123456789fa@gmail.com', 'sent', NULL, '{\"subject\":\"Thong bao huy lich kham #61\"}', '2026-02-23 22:47:46'),
(2, 'appointment_booked_patient', '77:booked:2026-02-24:3', 'dat123456789fa@gmail.com', 'sent', NULL, '{\"subject\":\"Xac nhan dat lich kham #77\"}', '2026-02-23 22:55:08'),
(3, 'appointment_booked_patient', '78:booked:2026-02-24:4', 'dat123456789fa@gmail.com', 'sent', NULL, '{\"subject\":\"Xac nhan dat lich kham #78\"}', '2026-02-23 22:55:48'),
(4, 'appointment_cancelled_patient', '74:cancel:2025-12-02:67a00db4b3fa1c075abba10fd1f0c708', 'dat123456789fa@gmail.com', 'sent', NULL, '{\"subject\":\"Thong bao huy lich kham #74\"}', '2026-02-23 22:56:13'),
(5, 'appointment_cancelled_doctor', '74:cancel:2025-12-02:67a00db4b3fa1c075abba10fd1f0c708', 'nguoidung2@gmail.com', 'sent', NULL, '{\"subject\":\"Thong bao huy lich kham benh nhan #74\"}', '2026-02-23 22:56:18'),
(6, 'appointment_cancelled_patient', '70:cancel:2025-12-04:67a00db4b3fa1c075abba10fd1f0c708', 'dat123456789fa@gmail.com', 'sent', NULL, '{\"subject\":\"Thong bao huy lich kham #70\"}', '2026-02-23 22:56:31'),
(7, 'appointment_cancelled_doctor', '70:cancel:2025-12-04:67a00db4b3fa1c075abba10fd1f0c708', 'nguoidung2@gmail.com', 'sent', NULL, '{\"subject\":\"Thong bao huy lich kham benh nhan #70\"}', '2026-02-23 22:56:35'),
(8, 'appointment_cancelled_patient', '75:cancel:2025-12-05:67a00db4b3fa1c075abba10fd1f0c708', 'dat123456789fa@gmail.com', 'sent', NULL, '{\"subject\":\"Thong bao huy lich kham #75\"}', '2026-02-23 22:56:52'),
(9, 'appointment_cancelled_patient', '63:cancel:2025-12-05:67a00db4b3fa1c075abba10fd1f0c708', 'dat123456789fa@gmail.com', 'sent', NULL, '{\"subject\":\"Thong bao huy lich kham #63\"}', '2026-02-23 22:57:12'),
(10, 'appointment_cancelled_patient', '77:cancel:2026-02-24:e85cb88eb47badfeb0819053c9d72568', 'dat123456789fa@gmail.com', 'sent', NULL, '{\"subject\":\"Thong bao huy lich kham #77\"}', '2026-02-23 22:57:57'),
(11, 'appointment_cancelled_patient', '78:cancel:2026-02-24:e85cb88eb47badfeb0819053c9d72568', 'dat123456789fa@gmail.com', 'sent', NULL, '{\"subject\":\"Thong bao huy lich kham #78\"}', '2026-02-25 23:19:33'),
(12, 'auth_forgot_otp', 'forgot_otp:1:974521', 'dat123456789fa@gmail.com', 'sent', NULL, '{\"subject\":\"Mã OTP đặt lại mật khẩu - Eden Health\"}', '2026-03-02 21:34:32'),
(13, 'auth_password_changed', 'password_changed:1:1772462149', 'dat123456789fa@gmail.com', 'sent', NULL, '{\"subject\":\"Mật khẩu đã được thay đổi - Eden Health\"}', '2026-03-02 21:35:50'),
(14, 'auth_register_otp', 'register_otp:00db042cd3d0326c4c3b15dad3724611662887761c7dd45740a8b68bba171351:1772614186', 'dat123456789fa+web2@gmail.com', 'sent', NULL, '{\"subject\":\"Mã OTP xác thực đăng ký tài khoản - Eden Health\"}', '2026-03-04 15:49:49'),
(15, 'auth_register_success', 'register:61', 'dat123456789fa+web2@gmail.com', 'sent', NULL, '{\"subject\":\"Chào mừng đến với Eden Health!\"}', '2026-03-04 15:50:07'),
(16, 'contact_received', '4:contact:received', 'dat123456789fa+lienhe1@gmail.com', 'sent', NULL, '{\"subject\":\"Da tiep nhan lien he #4\"}', '2026-03-04 23:58:57'),
(17, 'contact_processed', '4:contact:processed:2026-03-05 00:01:02', 'dat123456789fa+lienhe1@gmail.com', 'sent', NULL, '{\"subject\":\"Lien he #4 da duoc xu ly\"}', '2026-03-05 00:01:03'),
(18, 'account_locked', '58:account_status:locked:20260309220912', 'dat123456789fa+benhnhan4@gmail.com', 'sent', NULL, '{\"subject\":\"Thong bao khoa tai khoan\"}', '2026-03-09 22:09:13'),
(19, 'account_unlocked', '58:account_status:active:20260309220945', 'dat123456789fa+benhnhan4@gmail.com', 'sent', NULL, '{\"subject\":\"Thong bao mo khoa tai khoan\"}', '2026-03-09 22:09:46');

CREATE TABLE ngaynghi (
  maNghi int(11) NOT NULL,
  maBacSi varchar(20) NOT NULL,
  ngayNghi date NOT NULL,
  maCa int(11) DEFAULT NULL,
  lyDo text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO ngaynghi (maNghi, maBacSi, ngayNghi, maCa, lyDo) VALUES
(7, 'bs1', '2025-11-27', 1, '0'),
(8, 'bs1', '2025-11-27', 2, 'Thử'),
(9, 'bs1', '2025-12-03', 1, 'Thích');
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

CREATE TABLE nguoidung (
  id int(11) NOT NULL,
  tenDangNhap varchar(50) NOT NULL,
  matKhau varchar(255) NOT NULL,
  soDienThoai varchar(16) DEFAULT NULL,
  email varchar(100) DEFAULT NULL,
  vaiTro enum('benhnhan','bacsi','quantri') NOT NULL,
  trangThai enum('Hoạt Động','Khóa') NOT NULL DEFAULT 'Hoạt Động',
  ngayCapNhatTaiKhoan datetime DEFAULT NULL,
  ngayCapNhatMatKhau datetime DEFAULT NULL,
  avatar varchar(255) DEFAULT 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png',
  isDeleted tinyint(1) NOT NULL DEFAULT 0,
  deletedAt datetime DEFAULT NULL,
  deletedBy int(11) DEFAULT NULL,
  deleteReason text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO nguoidung (id, tenDangNhap, matKhau, soDienThoai, email, vaiTro, trangThai, ngayCapNhatTaiKhoan, ngayCapNhatMatKhau, avatar, isDeleted, deletedAt, deletedBy, deleteReason) VALUES
(1, 'nguoidung1', '$2y$10$Y8HGcx2vQmfKgEb3Kp.Xfu41/ZmWMs9y78oKa5OhnSfuOjk/yC3hy', '0987654322', 'dat123456789fa@gmail.com', 'benhnhan', 'Hoạt Động', '2025-11-21 15:25:43', '2026-03-02 21:35:49', 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(2, 'nguoidung2', '$2y$10$7G5Z78wzljUpOrRM0rS3zeDIGO3PNEao6/RbvbqvbYwRdqQtZ1kYq', '0987654323', 'dat123456789fa+nguoidung2@gmail.com', 'bacsi', 'Hoạt Động', '2026-02-24 00:46:34', '2025-12-02 12:15:59', 'https://res.cloudinary.com/dlnevod7e/image/upload/v1773160452/eden_health/avatars/doctors/doctor_admin_20260310233402_6441.jpg', 0, NULL, NULL, NULL),
(3, 'nguoidung3', '$2y$10$/0FrHldUcP41w29..ISGO.rhD3NHA.YBmYWzOoxe9jnZBnpm97v1G', '0987654321', 'nguoidung3@gmail.com', 'quantri', 'Hoạt Động', NULL, '2025-12-02 12:16:42', 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(8, 'ABCD', 'passwork', '0936846244', 'abcd@gmail.com', 'benhnhan', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(11, '0000000000', 'passwork', '0000000000', NULL, 'benhnhan', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(15, 'tranvanh2000', '$2y$10$7867Ekei9uerWlzDqGdIfef6p2glRKnjxuBwLV4rD8ZdtBCyvnPYO', '0345678921', NULL, 'benhnhan', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(16, 'lethanhbinh', '$2y$10$63zjkIUyLfKGw1XzgYGhFOfIYD4gFpmYr6qLSX88UvXLrpeY1iFoy', '0912001001', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(17, 'nguyenthikimanh', 'nguyenthikimanh1999', '0912001002', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(18, 'tranquocdat', 'tranquocdat1990', '0912002001', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(19, 'phamthithanhthuy', 'phamthithanhthuy2001', '0912002002', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(20, 'hoangvanson', 'hoangvanson1996', '0912003001', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(21, 'vuongthimai', 'vuongthimai2002', '0912003002', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(22, 'dangminhhieu', 'dangminhhieu1994', '0912004001', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(23, 'buithilan', 'buithilan2000', '0912004002', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(24, 'phanthanhlong', 'phanthanhlong1991', '0912005001', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(25, 'lythibichngoc', 'lythibichngoc2003', '0912005002', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(26, 'nguyenquangdung', 'nguyenquangdung1988', '0912006001', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(27, 'tranthihuonggiang', 'tranthihuonggiang1998', '0912006002', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(28, 'vuminhduc', 'vuminhduc1992', '0912007001', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(29, 'nguyenhoanganh', '$2y$10$QtY6Ya/D64sH0XWBYPypFOsyax8PeHsxFPxZvNW5D1skGpEA.3Mvq', '0912007002', 'dat123456789+bacsi1@gmail.com', 'bacsi', 'Hoạt Động', '2026-03-06 10:10:17', NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1772766617/Images/bacsi_29.png', 0, NULL, NULL, NULL),
(30, 'letrungkien', 'letrungkien1995', '0912008001', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(31, 'tranthikimhue', 'tranthikimhue1997', '0912008002', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(32, 'phamthanhhai', 'phamthanhhai1990', '0912009001', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(33, 'nguyenthithuphuong', 'nguyenthithuphuong2000', '0912009002', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(34, 'trantrungnghia', 'trantrungnghia1994', '0922001001', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(35, 'dothibich', 'dothibich2001', '0922001002', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(36, 'nguyenhoangson', 'nguyenhoangson1990', '0922002001', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(37, 'lethimaihuong', 'lethimaihuong1999', '0922002002', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(38, 'phamvanquang', 'phamvanquang1992', '0922003001', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(39, 'nguyenthilanphuong', 'nguyenthilanphuong2000', '0922003002', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(40, 'hoangvankhai', 'hoangvankhai1995', '0922004001', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(41, 'vuthituoanh', 'vuthituoanh1998', '0922004002', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(42, 'dangvanhung', 'dangvanhung1991', '0922005001', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(43, 'lythiminh', 'lythiminh2003', '0922005002', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(44, 'nguyenvanphuc', 'nguyenvanphuc1989', '0922006001', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(45, 'tranthiyen', 'tranthiyen2002', '0922006002', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(46, 'buivantoan', 'buivantoan1996', '0922007001', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(47, 'phamthihuong', 'phamthihuong2000', '0922007002', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(48, 'leminhvuong', 'leminhvuong1987', '0922008001', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(49, 'nguyenthikieutrinh', 'nguyenthikieutrinh1999', '0922008002', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(50, 'dovuhoang', 'dovuhoang2011', '0933001001', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(51, 'luuthiminh', 'luuthiminh1993', '0933001002', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(52, 'macvankhoa', 'macvankhoa2022', '0933002001', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(53, 'tongthikim', 'tongthikim2008', '0933002002', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(54, 'nguyenbaduy', 'nguyenbaduy2019', '0933003001', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(55, 'hotien', 'hotien1991', '0933003002', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(56, 'trinhquocthai', 'trinhquocthai2024', '0933004001', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(57, 'buithixuan', 'buithixuan2004', '0933004002', NULL, 'bacsi', 'Hoạt Động', NULL, NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(58, 'test1', '$2y$10$QRCJtDF7COPA8NViycczmOk.0srx9hql/gbe97mzpr5CQxI2q9/Di', '0111111111', 'dat123456789fa+benhnhan4@gmail.com', 'benhnhan', 'Hoạt Động', '2025-11-22 23:31:28', NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(59, 'test2', 'Eden24112025', '0222222222', 'dat123456789fa+benhnhan5@gmail.com', 'benhnhan', 'Hoạt Động', '2025-11-22 23:33:27', NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL),
(61, 'benhnhan3', '$2y$10$OCV02D1gSyYuyT1ufFWgdOaDEOOUEFGhHrXPNcjOabtdykjrTibpm', '0123123123', 'dat123456789fa+web2@gmail.com', 'benhnhan', 'Hoạt Động', '2026-03-04 15:50:05', NULL, 'https://res.cloudinary.com/dlnevod7e/image/upload/v1769960987/samples/paper.png', 0, NULL, NULL, NULL);

CREATE TABLE quantrivien (
  nguoiDungId int(11) NOT NULL,
  maQuanTriVien varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO quantrivien (nguoiDungId, maQuanTriVien) VALUES
(3, 'admin1');

CREATE TABLE suatkham (
  maSuat int(11) NOT NULL,
  maCa int(11) NOT NULL,
  gioBatDau time NOT NULL,
  gioKetThuc time NOT NULL,
  isActive tinyint(1) NOT NULL DEFAULT 1,
  effectiveFrom date NOT NULL DEFAULT '1900-01-01',
  effectiveTo date DEFAULT NULL,
  presetMinutes int(11) NOT NULL DEFAULT 40
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO suatkham (maSuat, maCa, gioBatDau, gioKetThuc, isActive, effectiveFrom, effectiveTo, presetMinutes) VALUES
(1, 1, '07:00:00', '07:40:00', 1, '1900-01-01', NULL, 40),
(2, 1, '07:40:00', '08:20:00', 1, '1900-01-01', NULL, 40),
(3, 1, '08:20:00', '09:00:00', 1, '1900-01-01', NULL, 40),
(4, 1, '09:00:00', '09:40:00', 1, '1900-01-01', NULL, 40),
(5, 1, '09:40:00', '10:20:00', 1, '1900-01-01', NULL, 40),
(6, 1, '10:20:00', '11:00:00', 1, '1900-01-01', NULL, 40),
(7, 2, '13:00:00', '13:40:00', 1, '1900-01-01', NULL, 40),
(8, 2, '13:40:00', '14:20:00', 1, '1900-01-01', NULL, 40),
(9, 2, '14:20:00', '15:00:00', 1, '1900-01-01', NULL, 40),
(10, 2, '15:00:00', '15:40:00', 1, '1900-01-01', NULL, 40),
(11, 2, '15:40:00', '16:20:00', 1, '1900-01-01', NULL, 40),
(12, 2, '16:20:00', '17:00:00', 1, '1900-01-01', NULL, 40);

CREATE TABLE thongbaoadmin (
  maThongBao int(11) NOT NULL,
  nguoiDungId int(11) NOT NULL,
  maNghi int(11) DEFAULT NULL,
  maYeuCau int(11) DEFAULT NULL,
  soDienThoai varchar(16) DEFAULT NULL,
  loai enum('Nghỉ phép','Hủy nghỉ','Cấp lại mật khẩu') NOT NULL DEFAULT 'Nghỉ phép',
  tieuDe varchar(255) NOT NULL,
  noiDung text NOT NULL,
  thoiGian datetime DEFAULT current_timestamp(),
  daXem tinyint(1) DEFAULT 0,
  trangThai enum('Chờ','Đã xử lý','Từ chối') DEFAULT 'Chờ',
  thoiGianXuLy datetime DEFAULT NULL,
  ngayLienQuan date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO thongbaoadmin (maThongBao, nguoiDungId, maNghi, maYeuCau, soDienThoai, loai, tieuDe, noiDung, thoiGian, daXem, trangThai, thoiGianXuLy, ngayLienQuan) VALUES
(1, 58, NULL, 6, '0111111111', 'Cấp lại mật khẩu', 'Yêu cầu cấp lại mật khẩu', 'Người dùng test1 (benhnhan) yêu cầu cấp lại mật khẩu', '2025-11-24 22:12:44', 1, 'Đã xử lý', '2025-11-24 22:13:00', NULL),
(3, 2, 7, NULL, NULL, 'Nghỉ phép', 'Đơn xin nghỉ phép', 'Bác sĩ Trần Văn BBD xin nghỉ phép vào ngày 27/11/2025 - Cả ngày. Lý do: Thử', '2025-11-24 22:15:13', 1, 'Chờ', NULL, '2025-11-27'),
(4, 58, NULL, 8, '0111111111', 'Cấp lại mật khẩu', 'Yêu cầu cấp lại mật khẩu', 'Người dùng test1 (benhnhan) yêu cầu cấp lại mật khẩu', '2025-11-24 22:17:16', 1, 'Từ chối', '2025-11-24 22:18:00', NULL),
(5, 58, NULL, 9, '0111111111', 'Cấp lại mật khẩu', 'Yêu cầu cấp lại mật khẩu', 'Người dùng test1 (benhnhan) yêu cầu cấp lại mật khẩu', '2025-11-24 22:20:20', 1, 'Từ chối', '2025-11-24 22:20:35', NULL),
(6, 2, 9, NULL, NULL, 'Nghỉ phép', 'Đơn xin nghỉ phép', 'Bác sĩ Trần Văn Bảo xin nghỉ phép vào ngày 03/12/2025 - Ca sáng. Lý do: Thích', '2025-12-02 00:00:58', 1, 'Chờ', NULL, '2025-12-03'),
(9, 29, NULL, 12, '0912007002', 'Cấp lại mật khẩu', 'Yêu cầu cấp lại mật khẩu', 'Người dùng nguyenhoanganh (bacsi) yêu cầu cấp lại mật khẩu', '2026-03-04 23:25:19', 1, 'Đã xử lý', '2026-03-04 23:25:55', NULL);

CREATE TABLE thongbaobenhnhan (
  maThongBao int(11) NOT NULL,
  maBenhNhan varchar(20) NOT NULL,
  loai enum('Hệ thống','Lịch khám','Mật khẩu','Khác') NOT NULL DEFAULT 'Hệ thống',
  tieuDe varchar(255) NOT NULL,
  noiDung text NOT NULL,
  thoiGian datetime DEFAULT current_timestamp(),
  daXem tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO thongbaobenhnhan (maThongBao, maBenhNhan, loai, tieuDe, noiDung, thoiGian, daXem) VALUES
(1, 'BN2025112200000058', 'Mật khẩu', 'Cấp lại mật khẩu', 'Mật khẩu mới của bạn là: Eden24112025. Vui lòng đổi mật khẩu sau khi đăng nhập.', '2025-11-24 22:13:00', 1),
(2, 'BN2025112200000058', 'Hệ thống', 'Yêu cầu bị từ chối', 'Yêu cầu cấp lại mật khẩu của bạn đã bị từ chối. Vui lòng liên hệ quản trị viên để biết thêm chi tiết.', '2025-11-24 22:18:00', 1),
(3, 'BN2025112200000058', 'Hệ thống', 'Yêu cầu bị từ chối', 'Yêu cầu cấp lại mật khẩu của bạn đã bị từ chối. Vui lòng liên hệ quản trị viên để biết thêm chi tiết.', '2025-11-24 22:20:35', 1),
(4, 'bn1', 'Lịch khám', 'Lịch khám đã bị hủy', 'Lịch khám của bạn với bác sĩ Nguyễn Hoàng Anh vào ngày 00/00/0000 - Ca sáng (07:00 - 07:40) đã bị hủy.', '2025-11-30 10:12:27', 1),
(5, 'bn1', 'Lịch khám', 'Lịch khám đã bị hủy', 'Lịch khám của bạn với bác sĩ Nguyễn Hoàng Anh vào ngày 22/11/2025 - Ca sáng (07:00 - 07:40) đã bị hủy.', '2025-11-30 10:38:05', 1),
(6, 'bn1', 'Lịch khám', 'Lịch khám đã bị hủy', 'Lịch khám của bạn với bác sĩ Trần Văn BBD vào ngày 01/12/2025 - Ca sáng (08:20 - 09:00) đã bị hủy.', '2025-11-30 10:38:29', 1),
(7, 'bn1', 'Lịch khám', 'Lịch khám đã bị hủy', 'Lịch khám của bạn với bác sĩ Nguyễn Hoàng Anh vào ngày 01/12/2025 - Ca sáng (07:40 - 08:20) đã bị hủy.', '2025-11-30 10:39:37', 1),
(8, 'bn1', 'Lịch khám', 'Lịch khám bị hủy', 'Lịch khám ngày 01/12/2025 với bác sĩ Nguyễn Hoàng Anh đã bị hủy bởi Bác sĩ. Vui lòng kiểm tra lại hoặc đặt lịch mới.', '2025-11-30 12:43:47', 1),
(9, 'bn1', 'Lịch khám', 'Lịch khám bị hủy', 'Lịch khám ngày 24/02/2026 đã bị hủy bởi Bác sĩ. Lý do: Không có lý do cụ thể. Vui lòng đặt lịch mới.', '2026-02-23 22:57:53', 1),
(10, 'bn1', 'Lịch khám', 'Lịch khám bị hủy', 'Lịch khám ngày 24/02/2026 đã bị hủy bởi Bác sĩ. Lý do: Không có lý do cụ thể. Vui lòng đặt lịch mới.', '2026-02-25 23:19:29', 1);

CREATE TABLE thongbaolichkham (
  maThongBao int(11) NOT NULL,
  maBacSi varchar(20) NOT NULL,
  maLichKham int(11) DEFAULT NULL,
  loai enum('Đặt lịch','Hủy lịch','Cập nhật lịch biểu') NOT NULL,
  tieuDe varchar(255) NOT NULL,
  noiDung text NOT NULL,
  thoiGian datetime DEFAULT current_timestamp(),
  daXem tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO thongbaolichkham (maThongBao, maBacSi, maLichKham, loai, tieuDe, noiDung, thoiGian, daXem) VALUES
(1, 'bs1', 26, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Trần Văn H đã đặt lịch khám vào ngày 18/11/2025 - Ca sáng', '2025-11-18 03:44:15', 1),
(2, 'bs1', 27, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân AAAAAAAA đã đặt lịch khám vào ngày 19/11/2025 - Ca sáng', '2025-11-18 18:48:42', 1),
(3, 'bs1', 28, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân ABCs đã đặt lịch khám vào ngày 19/11/2025 - Ca sáng', '2025-11-18 18:48:59', 1),
(4, 'bs1', 29, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn A đã đặt lịch khám vào ngày 19/11/2025 - Ca chiều', '2025-11-18 18:49:11', 1),
(5, 'bs1', 30, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Trần Văn H đã đặt lịch khám vào ngày 19/11/2025 - Ca chiều', '2025-11-18 18:49:24', 1),
(6, 'bs1', NULL, 'Đặt lịch', 'Cấp lại mật khẩu', 'Mật khẩu mới của bạn là: Eden19112025. Vui lòng đổi mật khẩu sau khi đăng nhập.', '2025-11-19 11:49:57', 1),
(7, 'bs1', NULL, 'Đặt lịch', 'Cấp lại mật khẩu', 'Mật khẩu mới của bạn là: Eden21112025. Vui lòng đổi mật khẩu sau khi đăng nhập.', '2025-11-21 00:26:19', 1),
(8, 'BS20251121031', NULL, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn A đã đặt lịch khám vào ngày 00/00/0000 - Ca sáng', '2025-11-21 13:12:04', 0),
(15, 'BS20251121027', 38, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 24/11/2025 - Ca sáng', '2025-11-23 15:07:43', 0),
(16, 'BS20251121027', 39, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Test đã đặt lịch khám vào ngày 24/11/2025 - Ca sáng', '2025-11-23 15:14:29', 0),
(17, 'BS20251121028', 40, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Test đã đặt lịch khám vào ngày 29/11/2025 - Ca sáng', '2025-11-23 15:32:44', 0),
(18, 'BS20251121027', 39, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Test đã hủy lịch khám vào ngày 24/11/2025 - Ca sáng - 07:40 - 08:20', '2025-11-23 16:09:29', 0),
(19, 'BS20251121027', 39, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Test đã hủy lịch khám vào ngày 24/11/2025 - Ca sáng - 07:40 - 08:20. Lý do: Thử', '2025-11-23 16:09:29', 0),
(20, 'BS20251121028', 40, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Test đã hủy lịch khám vào ngày 29/11/2025 - Ca sáng - 07:00 - 07:40', '2025-11-23 16:09:38', 0),
(21, 'BS20251121028', 40, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Test đã hủy lịch khám vào ngày 29/11/2025 - Ca sáng - 07:00 - 07:40. Lý do: Hủy', '2025-11-23 16:09:38', 0),
(22, 'BS20251121027', 41, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Test đã đặt lịch khám vào ngày 24/11/2025 - Ca sáng', '2025-11-23 16:11:19', 0),
(23, 'BS20251121027', 41, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Test đã hủy lịch khám vào ngày 24/11/2025 - Ca sáng - 07:40 - 08:20', '2025-11-23 16:11:38', 0),
(24, 'BS20251121027', 41, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Test đã hủy lịch khám vào ngày 24/11/2025 - Ca sáng - 07:40 - 08:20. Lý do: 1', '2025-11-23 16:11:38', 0),
(25, 'BS20251121027', 42, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Test đã đặt lịch khám vào ngày 24/11/2025 - Ca sáng', '2025-11-23 16:11:59', 0),
(26, 'BS20251121027', 42, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Test đã hủy lịch khám vào ngày 24/11/2025 - Ca sáng - 07:40 - 08:20', '2025-11-23 16:26:45', 0),
(27, 'BS20251121027', 42, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Test đã hủy lịch khám vào ngày 24/11/2025 - Ca sáng - 07:40 - 08:20. Lý do: 2', '2025-11-23 16:26:45', 0),
(28, 'BS20251121027', 43, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Test đã đặt lịch khám vào ngày 23/11/2025 - Ca sáng', '2025-11-23 21:54:48', 0),
(29, 'BS20251121028', 44, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Test đã đặt lịch khám vào ngày 23/11/2025 - Ca chiều', '2025-11-23 21:55:14', 0),
(30, 'bs1', 45, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Test đã đặt lịch khám vào ngày 23/11/2025 - Ca sáng', '2025-11-23 22:00:28', 1),
(31, 'BS20251121027', 43, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Test đã hủy lịch khám vào ngày 23/11/2025 - Ca sáng - 09:00 - 09:40', '2025-11-23 22:00:53', 0),
(32, 'BS20251121027', 43, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Test đã hủy lịch khám vào ngày 23/11/2025 - Ca sáng - 09:00 - 09:40. Lý do: a', '2025-11-23 22:00:53', 0),
(33, 'BS20251121028', 44, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Test đã hủy lịch khám vào ngày 23/11/2025 - Ca chiều - 16:20 - 17:00', '2025-11-23 22:00:59', 0),
(34, 'BS20251121028', 44, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Test đã hủy lịch khám vào ngày 23/11/2025 - Ca chiều - 16:20 - 17:00. Lý do: a', '2025-11-23 22:00:59', 0),
(35, 'bs1', 46, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Test đã đặt lịch khám vào ngày 23/11/2025 - Ca chiều', '2025-11-23 22:01:29', 1),
(36, 'bs1', NULL, 'Đặt lịch', 'Cấp lại mật khẩu', 'Mật khẩu mới của bạn là: Eden24112025. Vui lòng đổi mật khẩu sau khi đăng nhập.', '2025-11-24 22:14:26', 1),
(39, 'bs1', 47, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 01/12/2025 - Ca sáng', '2025-11-30 10:32:47', 1),
(43, 'bs1', 47, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Nguyễn Văn Anh đã hủy lịch khám vào ngày 01/12/2025 - Ca sáng - 08:20 - 09:00', '2025-11-30 10:38:29', 1),
(44, 'bs1', 47, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Nguyễn Văn Anh đã hủy lịch khám vào ngày 01/12/2025 - Ca sáng - 08:20 - 09:00. Lý do: 2', '2025-11-30 10:38:29', 1),
(49, 'BS20251121027', 38, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Nguyễn Văn Anh đã hủy lịch khám vào ngày 24/11/2025 - Ca sáng - 07:00 - 07:40', '2025-11-30 10:56:52', 0),
(50, 'BS20251121027', 38, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Nguyễn Văn Anh đã hủy lịch khám vào ngày 24/11/2025 - Ca sáng - 07:00 - 07:40. Lý do: x', '2025-11-30 10:56:52', 0),
(56, 'BS20251121022', 50, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 02/12/2025 - Ca sáng', '2025-11-30 11:32:48', 1),
(57, 'BS20251121022', 51, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 01/12/2025 - Ca chiều', '2025-11-30 11:34:32', 1),
(58, 'BS20251121022', 51, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Nguyễn Văn Anh đã hủy lịch khám vào ngày 01/12/2025 - Ca chiều - 15:40 - 16:20. Lý do: 1', '2025-11-30 11:34:50', 1),
(59, 'BS20251121022', 52, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 03/12/2025 - Ca sáng', '2025-11-30 11:35:48', 1),
(60, 'BS20251121022', 52, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Nguyễn Văn Anh đã hủy lịch khám vào ngày 03/12/2025 - Ca sáng - 08:20 - 09:00', '2025-11-30 11:36:37', 1),
(61, 'BS20251121022', 53, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 01/12/2025 - Ca sáng', '2025-11-30 11:42:44', 1),
(62, 'BS20251121022', 53, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Nguyễn Văn Anh đã hủy lịch khám vào ngày 01/12/2025 - Ca sáng - 09:00 - 09:40', '2025-11-30 11:43:06', 1),
(63, 'BS20251121022', 54, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 30/11/2025 - Ca sáng', '2025-11-30 11:45:53', 1),
(64, 'BS20251121022', 55, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 06/12/2025 - Ca sáng', '2025-11-30 11:46:41', 1),
(65, 'BS20251121022', 55, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Nguyễn Văn Anh đã hủy lịch khám vào ngày 06/12/2025 - Ca sáng - 08:20 - 09:00', '2025-11-30 11:47:03', 1),
(66, 'BS20251121022', 56, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 01/12/2025 - Ca sáng', '2025-11-30 11:49:56', 1),
(67, 'BS20251121022', 56, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Nguyễn Văn Anh đã hủy lịch khám vào ngày 01/12/2025 - Ca sáng - 10:20 - 11:00', '2025-11-30 11:50:07', 1),
(68, 'BS20251121022', 57, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 01/12/2025 - Ca sáng', '2025-11-30 11:52:44', 1),
(69, 'BS20251121022', 57, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Nguyễn Văn Anh đã hủy lịch khám vào ngày 01/12/2025 - Ca sáng - 08:20 - 09:00', '2025-11-30 11:53:00', 1),
(70, 'BS20251121022', 58, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 01/12/2025 - Ca sáng', '2025-11-30 12:05:35', 1),
(71, 'BS20251121022', 58, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Nguyễn Văn Anh đã hủy lịch khám ngày 01/12/2025 - Ca sáng (10:20 - 11:00).', '2025-11-30 12:42:44', 1),
(72, 'BS20251121022', 59, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 01/12/2025 - Ca sáng', '2025-11-30 12:43:31', 1),
(73, 'BS20251121022', 60, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 03/12/2025 - Ca sáng', '2025-11-30 12:51:36', 1),
(74, 'BS20251121022', 60, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Nguyễn Văn Anh đã hủy lịch khám ngày 03/12/2025 - Ca sáng. Lý do: adsdada', '2025-11-30 12:51:45', 1),
(75, 'BS20251121022', 61, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 02/12/2025 - Ca sáng. Ghi chú: test', '2025-11-30 13:13:30', 1),
(76, 'bs1', 62, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 01/12/2025 - Ca sáng', '2025-11-30 20:04:31', 1),
(77, 'BS20251121028', 63, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 05/12/2025 - Ca sáng', '2025-11-30 20:25:10', 0),
(78, 'bs1', 64, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 30/11/2025 - Ca sáng', '2025-11-30 20:29:10', 1),
(79, 'bs1', 65, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Võ Quốc Thái đã đặt lịch khám vào ngày 30/11/2025 - Ca chiều', '2025-11-30 20:32:21', 1),
(80, 'bs1', 66, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 03/12/2025 - Ca sáng. Ghi chú: Bệnh nhân tái khám, sức khỏe tốt.', '2025-12-02 12:57:19', 1),
(81, 'bs1', 67, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Võ Quốc Thái đã đặt lịch khám vào ngày 03/12/2025 - Ca sáng. Ghi chú: Đau đầu nhẹ, cần kiểm tra huyết áp.', '2025-12-02 12:57:19', 1),
(82, 'bs1', 68, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Trần Văn Hoàng đã đặt lịch khám vào ngày 03/12/2025 - Ca sáng. Ghi chú: Bận việc đột xuất\n[Lý do hủy]: Bệnh nhân yêu cầu hủy', '2025-12-02 12:57:19', 1),
(83, 'bs1', 69, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Dương Thanh Hóa đã đặt lịch khám vào ngày 03/12/2025 - Ca chiều', '2025-12-02 12:57:19', 1),
(84, 'bs1', 70, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 04/12/2025 - Ca sáng. Ghi chú: Khám tổng quát', '2025-12-02 12:57:19', 1),
(85, 'bs1', 71, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Lê Minh Tuyền đã đặt lịch khám vào ngày 04/12/2025 - Ca sáng', '2025-12-02 12:57:19', 1),
(86, 'bs1', 72, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Đinh Quốc Thịnh đã đặt lịch khám vào ngày 04/12/2025 - Ca sáng. Ghi chú: Có triệu chứng sốt nhẹ', '2025-12-02 12:57:19', 1),
(87, 'bs1', 73, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Trần Văn Hoàng đã đặt lịch khám vào ngày 05/12/2025 - Ca chiều. Ghi chú: Tái khám theo hẹn', '2025-12-02 12:57:19', 1),
(88, 'bs1', 74, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 02/12/2025 - Ca chiều. Ghi chú: không', '2025-12-02 12:59:21', 1),
(89, 'BS20251121022', 75, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 05/12/2025 - Ca sáng', '2025-12-04 20:07:55', 1),
(90, 'BS20251121027', 76, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Võ Quốc Thái đã đặt lịch khám vào ngày 05/12/2025 - Ca sáng', '2025-12-04 20:22:28', 0),
(91, 'bs1', 62, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Nguyễn Văn Anh đã hủy lịch khám ngày 01/12/2025 - Ca sáng. Lý do: 1', '2025-12-04 20:25:47', 1),
(92, 'BS20251121022', 61, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Nguyễn Văn Anh đã hủy lịch khám ngày 02/12/2025 - Ca sáng. Lý do: bận việc', '2026-02-23 22:47:41', 1),
(93, 'BS20251121022', 77, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 24/02/2026 - Ca sáng. Ghi chú: Không!', '2026-02-23 22:55:04', 1),
(94, 'BS20251121022', 78, 'Đặt lịch', 'Lịch khám mới', 'Bệnh nhân Nguyễn Văn Anh đã đặt lịch khám vào ngày 24/02/2026 - Ca sáng', '2026-02-23 22:55:43', 1),
(95, 'bs1', 74, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Nguyễn Văn Anh đã hủy lịch khám ngày 02/12/2025 - Ca chiều. Lý do: 1', '2026-02-23 22:56:10', 1),
(96, 'bs1', 70, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Nguyễn Văn Anh đã hủy lịch khám ngày 04/12/2025 - Ca sáng. Lý do: 1', '2026-02-23 22:56:27', 1),
(97, 'BS20251121022', 75, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Nguyễn Văn Anh đã hủy lịch khám ngày 05/12/2025 - Ca sáng. Lý do: 1', '2026-02-23 22:56:48', 1),
(98, 'BS20251121028', 63, 'Hủy lịch', 'Lịch khám đã hủy', 'Bệnh nhân Nguyễn Văn Anh đã hủy lịch khám ngày 05/12/2025 - Ca sáng. Lý do: 1', '2026-02-23 22:57:08', 0),
(99, 'BS20251121022', NULL, 'Đặt lịch', 'Cấp lại mật khẩu', 'Mật khẩu mới của bạn là: Eden04032026. Vui lòng đổi mật khẩu sau khi đăng nhập.', '2026-03-04 23:25:55', 1);

CREATE TABLE thuoc (
  maThuoc int(11) NOT NULL,
  tenThuoc varchar(100) NOT NULL,
  donViTinh varchar(20) DEFAULT NULL,
  soLuongTon int(11) DEFAULT 0,
  giaTien decimal(10,2) DEFAULT NULL,
  cachDungMacDinh text DEFAULT NULL,
  loaiThuoc varchar(50) DEFAULT NULL COMMENT 'Loại thuốc: kháng sinh, giảm đau, vitamin...',
  nhaSanXuat varchar(100) DEFAULT NULL COMMENT 'Nhà sản xuất / Nguồn gốc',
  hanSuDung date DEFAULT NULL COMMENT 'Hạn sử dụng mặc định',
  nguongCanhBao int(11) NOT NULL DEFAULT 10 COMMENT 'Ngưỡng cảnh báo tồn kho thấp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO thuoc (maThuoc, tenThuoc, donViTinh, soLuongTon, giaTien, cachDungMacDinh, loaiThuoc, nhaSanXuat, hanSuDung, nguongCanhBao) VALUES
(1, 'Paracetamol 500mg', 'Viên', 500, 1500.00, 'Uống 1-2 viên/lần, 4-6 giờ/lần, tối đa 8 viên/ngày', 'Giảm đau / Hạ sốt', 'Hậu Giang Pharma', '2027-06-30', 50),
(2, 'Paracetamol 650mg (Efferalgan)', 'Viên', 200, 4500.00, 'Uống 1 viên/lần, 4-6 giờ/lần. Uống ngay sau ăn', 'Giảm đau / Hạ sốt', 'UPSA - Pháp', '2026-12-31', 20),
(3, 'Ibuprofen 400mg', 'Viên', 300, 3200.00, 'Uống 1 viên/lần sau ăn, 3 lần/ngày. Không dùng khi đói', 'Giảm đau / Hạ sốt', 'DHG Pharma', '2027-03-31', 30),
(4, 'Diclofenac 50mg', 'Viên', 150, 2800.00, 'Uống 1 viên/lần, 2-3 lần/ngày sau bữa ăn', 'Giảm đau / Hạ sốt', 'Pymepharco', '2026-09-30', 20),
(5, 'Meloxicam 7.5mg', 'Viên', 120, 4200.00, 'Uống 1 viên/ngày trong bữa ăn. Không dùng quá 15mg/ngày', 'Giảm đau / Hạ sốt', 'Boehringer Ingelheim', '2027-01-31', 15),
(6, 'Celecoxib 200mg', 'Viên', 100, 8500.00, 'Uống 1-2 viên/ngày. Uống cùng bữa ăn', 'Giảm đau / Hạ sốt', 'Pfizer', '2026-11-30', 10),
(7, 'Amoxicillin 500mg', 'Viên', 400, 4500.00, 'Uống 1 viên x 3 lần/ngày, cách đều 8 giờ. Uống hết liều', 'Kháng sinh', 'Stada', '2026-08-31', 40),
(8, 'Amoxicillin + Clavulanic 625mg', 'Viên', 200, 12000.00, 'Uống 1 viên x 2 lần/ngày trong bữa ăn', 'Kháng sinh', 'GlaxoSmithKline', '2026-10-31', 20),
(9, 'Azithromycin 500mg', 'Viên', 180, 9500.00, 'Uống 1 viên/ngày x 3 ngày liên tục, xa bữa ăn 1 giờ', 'Kháng sinh', 'Pfizer', '2027-02-28', 15),
(10, 'Ciprofloxacin 500mg', 'Viên', 200, 5500.00, 'Uống 1 viên x 2 lần/ngày. Uống nhiều nước, xa antacid', 'Kháng sinh', 'Bayer', '2026-07-31', 20),
(11, 'Doxycycline 100mg', 'Viên', 150, 4800.00, 'Uống 1 viên x 2 lần/ngày sau bữa ăn. Uống đủ nước', 'Kháng sinh', 'Pymepharco', '2026-12-31', 15),
(12, 'Cefuroxime 500mg', 'Viên', 120, 18000.00, 'Uống 1 viên x 2 lần/ngày sau bữa ăn', 'Kháng sinh', 'GlaxoSmithKline', '2026-09-30', 10),
(13, 'Metronidazole 500mg', 'Viên', 250, 2500.00, 'Uống 1 viên x 3 lần/ngày trong bữa ăn. Không uống rượu', 'Kháng sinh', 'Hậu Giang Pharma', '2027-04-30', 25),
(14, 'Clarithromycin 250mg', 'Viên', 100, 12500.00, 'Uống 1 viên x 2 lần/ngày, cách đều 12 giờ', 'Kháng sinh', 'Abbott', '2026-11-30', 10),
(15, 'Trimethoprim + SMX 480mg', 'Viên', 200, 3500.00, 'Uống 2 viên x 2 lần/ngày. Uống nhiều nước', 'Kháng sinh', 'DHG Pharma', '2026-08-31', 20),
(16, 'Vitamin C 1000mg (Sủi)', 'Viên', 300, 3500.00, 'Hòa tan 1 viên trong 200ml nước, uống 1 lần/ngày sau ăn', 'Vitamin & Khoáng chất', 'Meyer-BPC', '2027-06-30', 30),
(17, 'Vitamin B Complex', 'Viên', 400, 1800.00, 'Uống 1-2 viên/ngày sau bữa ăn', 'Vitamin & Khoáng chất', 'DHG Pharma', '2027-05-31', 40),
(18, 'Vitamin D3 2000IU', 'Viên', 200, 5500.00, 'Uống 1 viên/ngày trong bữa ăn có chất béo', 'Vitamin & Khoáng chất', 'Ostelin', '2027-08-31', 20),
(19, 'Canxi + D3 (Calcium Sandoz)', 'Viên', 250, 4200.00, 'Nhai hoặc ngậm 1-2 viên/ngày. Không uống cùng sắt', 'Vitamin & Khoáng chất', 'Novartis', '2027-07-31', 25),
(20, 'Sắt Fumarate 200mg', 'Viên', 200, 3800.00, 'Uống 1 viên/ngày xa bữa ăn. Có thể uống cùng Vitamin C', 'Vitamin & Khoáng chất', 'Pymepharco', '2026-12-31', 20),
(21, 'Magie B6', 'Viên', 300, 4500.00, 'Uống 2-6 viên/ngày chia nhiều lần trong bữa ăn', 'Vitamin & Khoáng chất', 'Sanofi', '2027-03-31', 30),
(22, 'Kẽm Gluconate 70mg', 'Viên', 150, 3200.00, 'Uống 1 viên/ngày sau bữa ăn', 'Vitamin & Khoáng chất', 'DHG Pharma', '2027-02-28', 15),
(23, 'Omega-3 1000mg', 'Viên', 200, 8500.00, 'Uống 1-2 viên/ngày trong bữa ăn', 'Vitamin & Khoáng chất', 'Nature Made', '2027-09-30', 20),
(24, 'Omeprazole 20mg', 'Viên', 300, 3500.00, 'Uống 1 viên trước bữa ăn sáng 30 phút', 'Tiêu hóa', 'Stada', '2026-10-31', 30),
(25, 'Pantoprazole 40mg', 'Viên', 200, 7500.00, 'Uống 1 viên/ngày trước bữa ăn sáng 30-60 phút', 'Tiêu hóa', 'Pymepharco', '2026-12-31', 20),
(26, 'Domperidone 10mg', 'Viên', 300, 2200.00, 'Uống 1 viên x 3 lần/ngày trước bữa ăn 15-30 phút', 'Tiêu hóa', 'Janssen', '2026-09-30', 30),
(27, 'Metoclopramide 10mg', 'Viên', 200, 1800.00, 'Uống 1 viên x 3 lần/ngày trước bữa ăn 30 phút', 'Tiêu hóa', 'DHG Pharma', '2026-08-31', 20),
(28, 'Smecta 3g', 'Gói', 200, 8500.00, 'Pha 1 gói với 50ml nước, uống 3 lần/ngày xa bữa ăn', 'Tiêu hóa', 'Ipsen', '2027-01-31', 20),
(29, 'Loperamide 2mg', 'Viên', 200, 3200.00, 'Uống 2 viên lần đầu, sau đó 1 viên sau mỗi lần tiêu chảy', 'Tiêu hóa', 'Janssen', '2026-11-30', 20),
(30, 'Men vi sinh Enterogermina', 'Ống', 150, 15000.00, 'Uống 1-2 ống/ngày trong hoặc sau bữa ăn', 'Tiêu hóa', 'Sanofi', '2026-07-31', 8),
(31, 'Simethicone 40mg', 'Viên', 300, 2500.00, 'Nhai 2-4 viên sau bữa ăn và trước khi ngủ', 'Tiêu hóa', 'Pymepharco', '2027-02-28', 30),
(32, 'Amlodipine 5mg', 'Viên', 200, 2800.00, 'Uống 1 viên/ngày, cùng giờ mỗi ngày, có thể cùng bữa ăn', 'Tim mạch', 'Pfizer', '2027-04-30', 20),
(33, 'Losartan 50mg', 'Viên', 180, 4500.00, 'Uống 1 viên/ngày, cùng giờ', 'Tim mạch', 'MSD', '2027-03-31', 15),
(34, 'Atenolol 50mg', 'Viên', 150, 2200.00, 'Uống 1 viên/ngày vào buổi sáng', 'Tim mạch', 'AstraZeneca', '2026-10-31', 15),
(35, 'Bisoprolol 5mg', 'Viên', 150, 5500.00, 'Uống 1 viên/ngày trong bữa sáng', 'Tim mạch', 'Merck', '2026-11-30', 15),
(36, 'Aspirin 81mg (Cardio)', 'Viên', 300, 3200.00, 'Uống 1 viên/ngày sau ăn tối', 'Tim mạch', 'Bayer', '2027-06-30', 30),
(37, 'Atorvastatin 10mg', 'Viên', 200, 8500.00, 'Uống 1 viên/ngày, có thể bất kỳ lúc nào', 'Tim mạch', 'Pfizer', '2027-05-31', 20),
(38, 'Cetirizine 10mg', 'Viên', 300, 2800.00, 'Uống 1 viên/ngày vào buổi tối', 'Hô hấp', 'UCB', '2027-04-30', 30),
(39, 'Loratadine 10mg', 'Viên', 300, 2200.00, 'Uống 1 viên/ngày vào buổi sáng', 'Hô hấp', 'Pymepharco', '2027-03-31', 30),
(40, 'Ambroxol 30mg', 'Viên', 250, 2500.00, 'Uống 1 viên x 3 lần/ngày sau bữa ăn', 'Hô hấp', 'Boehringer Ingelheim', '2026-11-30', 25),
(41, 'Acetylcysteine 600mg (Sủi)', 'Viên', 180, 9500.00, 'Hòa tan 1 viên trong 200ml nước, uống 1 lần/ngày', 'Hô hấp', 'Zambon', '2026-12-31', 15),
(42, 'Bromhexine 8mg', 'Viên', 200, 1800.00, 'Uống 1 viên x 3 lần/ngày sau bữa ăn', 'Hô hấp', 'Hậu Giang Pharma', '2026-10-31', 20),
(43, 'Montelukast 10mg', 'Viên', 120, 12000.00, 'Uống 1 viên/ngày vào buổi tối', 'Hô hấp', 'MSD', '2027-02-28', 10),
(44, 'Fexofenadine 120mg', 'Viên', 150, 8500.00, 'Uống 1 viên x 2 lần/ngày trước bữa ăn 1 giờ', 'Hô hấp', 'Sanofi', '2027-01-31', 15),
(45, 'Clotrimazole cream 1%', 'Tuýp', 100, 25000.00, 'Thoa một lớp mỏng lên vùng da tổn thương 2-3 lần/ngày', 'Da liễu', 'Bayer', '2026-08-31', 10),
(46, 'Hydrocortisone cream 1%', 'Tuýp', 80, 28000.00, 'Thoa nhẹ lên vùng da viêm 1-2 lần/ngày. Không dùng quá 7 ngày', 'Da liễu', 'DHG Pharma', '2026-12-31', 8),
(47, 'Acyclovir 400mg', 'Viên', 100, 8500.00, 'Uống 1 viên x 3-5 lần/ngày theo chỉ định bác sĩ', 'Da liễu', 'GlaxoSmithKline', '2026-11-30', 10),
(48, 'Betamethasone 0.1% cream', 'Tuýp', 80, 35000.00, 'Thoa 1-2 lần/ngày lên vùng da. Không thoa mặt, vùng kín', 'Da liễu', 'GlaxoSmithKline', '2027-03-31', 8),
(49, 'Piracetam 800mg', 'Viên', 200, 5500.00, 'Uống 2-4 viên x 3 lần/ngày sau bữa ăn', 'Thần kinh', 'UCB', '2026-10-31', 20),
(50, 'Cinnarizine 25mg', 'Viên', 300, 2200.00, 'Uống 1 viên x 3 lần/ngày sau bữa ăn', 'Thần kinh', 'Janssen', '2026-12-31', 30),
(51, 'Betahistine 16mg', 'Viên', 200, 6500.00, 'Uống 1 viên x 3 lần/ngày trong bữa ăn', 'Thần kinh', 'Abbott', '2027-01-31', 20),
(52, 'Flunarizine 5mg', 'Viên', 150, 4500.00, 'Uống 1-2 viên/ngày vào buổi tối', 'Thần kinh', 'Janssen', '2026-11-30', 15),
(53, 'Metformin 500mg', 'Viên', 400, 1800.00, 'Uống 1-2 viên x 2-3 lần/ngày trong bữa ăn', 'Nội tiết', 'Merck', '2027-06-30', 40),
(54, 'Metformin 1000mg', 'Viên', 250, 3200.00, 'Uống 1 viên x 2 lần/ngày trong bữa ăn', 'Nội tiết', 'Merck', '2027-05-31', 25),
(55, 'Glipizide 5mg', 'Viên', 200, 3500.00, 'Uống 1 viên x 1-2 lần/ngày trước bữa ăn 30 phút', 'Nội tiết', 'Pfizer', '2026-10-31', 20),
(56, 'Levothyroxine 50mcg', 'Viên', 150, 8500.00, 'Uống 1 viên/ngày lúc đói, trước ăn sáng 30 phút', 'Nội tiết', 'Merck', '2026-12-31', 15),
(57, 'Glibenclamide 5mg', 'Viên', 200, 2800.00, 'Uống 1-3 viên/ngày trước bữa ăn sáng', 'Nội tiết', 'Hậu Giang Pharma', '2026-09-30', 20),
(58, 'Prednisolone 5mg', 'Viên', 300, 1500.00, 'Uống theo chỉ định bác sĩ. Uống sau ăn sáng', 'Kháng viêm', 'DHG Pharma', '2026-12-31', 30),
(59, 'Methylprednisolone 16mg', 'Viên', 150, 9500.00, 'Uống theo chỉ định bác sĩ sau bữa ăn sáng', 'Kháng viêm', 'Pfizer', '2026-10-31', 15),
(60, 'Dexamethasone 0.5mg', 'Viên', 200, 1200.00, 'Uống theo chỉ định bác sĩ', 'Kháng viêm', 'Pymepharco', '2026-08-31', 20),
(61, 'Colchicine 0.6mg', 'Viên', 80, 12000.00, 'Uống theo chỉ định bác sĩ. Không vượt quá 1.2mg/lần', 'Kháng viêm', 'Takeda', '2026-11-30', 8),
(62, 'Tobramycin nhỏ mắt 0.3%', 'Lọ', 100, 35000.00, 'Nhỏ 1-2 giọt vào mắt x 4 lần/ngày', 'Mắt', 'Alcon', '2026-07-31', 10),
(63, 'Nước muối sinh lý 0.9%', 'Lọ', 500, 5000.00, 'Rửa mắt, mũi hoặc vệ sinh vết thương theo nhu cầu', 'Mắt', 'Hậu Giang Pharma', '2027-12-31', 50),
(64, 'Xylometazoline xịt mũi 0.1%', 'Lọ', 150, 28000.00, 'Xịt 2-3 lần vào mỗi bên mũi x 2-3 lần/ngày. Không dùng >7 ngày', 'Tai mũi họng', 'GlaxoSmithKline', '2026-09-30', 15),
(65, 'Strepsils (Viêm họng)', 'Viên', 200, 8500.00, 'Ngậm 1 viên mỗi 2-3 giờ, tối đa 8 viên/ngày', 'Tai mũi họng', 'Reckitt', '2027-02-28', 20),
(66, 'Oresol (ORS)', 'Gói', 300, 3500.00, 'Pha 1 gói với 200ml nước sôi để nguội, uống sau mỗi lần tiêu chảy', 'Điện giải', 'DHG Pharma', '2027-08-31', 30),
(67, 'Hydrite (Bù điện giải)', 'Gói', 200, 4500.00, 'Pha 1 gói với 200ml nước sôi để nguội, uống từng ngụm nhỏ', 'Điện giải', 'Stada', '2027-06-30', 20),
(68, 'Povidone Iodine 10% (Betadine)', 'Chai', 200, 45000.00, 'Bôi trực tiếp lên vết thương đã rửa sạch, 1-2 lần/ngày', 'Sát khuẩn', 'Mundipharma', '2027-06-30', 20),
(69, 'Cồn Ethanol 70°', 'Chai', 300, 18000.00, 'Sát trùng da, dụng cụ trước và sau thủ thuật', 'Sát khuẩn', 'DHG Pharma', '2027-12-31', 30),
(70, 'Hydrogen Peroxide 3%', 'Chai', 150, 12000.00, 'Sử dụng để rửa vết thương, pha loãng 1:1 với nước', 'Sát khuẩn', 'Hậu Giang Pharma', '2027-10-31', 15),
(71, 'Furosemide 40mg', 'Viên', 150, 2200.00, 'Uống 1 viên/ngày vào buổi sáng. Uống sau ăn', 'Lợi tiểu', 'Sanofi', '2026-10-31', 15),
(72, 'Allopurinol 300mg', 'Viên', 150, 3200.00, 'Uống 1 viên/ngày sau bữa ăn. Uống nhiều nước', 'Gout', 'Pymepharco', '2027-03-31', 15),
(73, 'Clopidogrel 75mg', 'Viên', 120, 15000.00, 'Uống 1 viên/ngày sau bữa ăn', 'Chống đông', 'Sanofi', '2027-01-31', 10),
(74, 'Warfarin 2mg', 'Viên', 80, 8500.00, 'Uống theo chỉ định bác sĩ, cùng giờ mỗi ngày', 'Chống đông', 'Orion', '2026-12-31', 10),
(75, 'Epinephrine 1mg/mL (tiêm)', 'Ống', 30, 45000.00, 'Chỉ dùng trong cấp cứu phản vệ, theo chỉ định bác sĩ', 'Cấp cứu', 'Aguettant', '2026-08-31', 3),
(76, 'Atropine 0.5mg (tiêm)', 'Ống', 30, 38000.00, 'Dùng theo chỉ định bác sĩ, tiêm tĩnh mạch hoặc bắp', 'Cấp cứu', 'DHG Pharma', '2026-07-31', 3),
(77, 'Paracetamol 500mg', 'Viên', 100, 2500.00, 'Uống sau ăn 30 phút', 'Giảm đau / Hạ sốt', 'Hậu Giang', '2026-12-31', 10),
(78, 'Amoxicillin 500mg', 'Viên', 50, 8000.00, 'Uống 3 lần/ngày', 'Kháng sinh', 'Stada', '2026-09-01', 5);


ALTER TABLE bacsi
  ADD PRIMARY KEY (maBacSi),
  ADD UNIQUE KEY nguoiDungId (nguoiDungId),
  ADD KEY maChuyenKhoa (maChuyenKhoa),
  ADD KEY idx_bacsi_ten_ma (tenBacSi,maBacSi),
  ADD KEY idx_bacsi_chuyenkhoa_ten_ma (maChuyenKhoa,tenBacSi,maBacSi);

ALTER TABLE benhnhan
  ADD PRIMARY KEY (maBenhNhan),
  ADD UNIQUE KEY nguoiDungId (nguoiDungId);

ALTER TABLE calamviec
  ADD PRIMARY KEY (maCa);

ALTER TABLE chitietdonthuoc
  ADD PRIMARY KEY (id),
  ADD KEY maDonThuoc (maDonThuoc),
  ADD KEY maThuoc (maThuoc);

ALTER TABLE chuyenkhoa
  ADD PRIMARY KEY (maChuyenKhoa),
  ADD KEY maKhoa (maKhoa),
  ADD KEY idx_chuyenkhoa_makhoa_mack (maKhoa,maChuyenKhoa);

ALTER TABLE doimatkhau
  ADD PRIMARY KEY (id),
  ADD KEY nguoiDungId (nguoiDungId),
  ADD KEY nguoiXuLy (nguoiXuLy);

ALTER TABLE donthuoc
  ADD PRIMARY KEY (maDonThuoc),
  ADD KEY maLichKham (maLichKham);

ALTER TABLE goikham
  ADD PRIMARY KEY (maGoi);

ALTER TABLE hoadon
  ADD PRIMARY KEY (maHoaDon),
  ADD KEY maLichKham (maLichKham);

ALTER TABLE hosobenhan
  ADD PRIMARY KEY (maHoSo),
  ADD KEY maBenhNhan (maBenhNhan),
  ADD KEY maBacSi (maBacSi),
  ADD KEY maLichKham (maLichKham),
  ADD KEY idx_hosobenhan_isDeleted (isDeleted);

ALTER TABLE khoa
  ADD PRIMARY KEY (maKhoa);

ALTER TABLE lichkham
  ADD PRIMARY KEY (maLichKham),
  ADD KEY maBacSi (maBacSi),
  ADD KEY maBenhNhan (maBenhNhan),
  ADD KEY maCa (maCa),
  ADD KEY maSuat (maSuat),
  ADD KEY maGoi (maGoi);

ALTER TABLE lienhe
  ADD PRIMARY KEY (maLienHe),
  ADD KEY fk_lienhe_nguoixuly (nguoiXuLy),
  ADD KEY idx_trangThai (trangThai),
  ADD KEY idx_thoiGianGui (thoiGianGui);

ALTER TABLE mail_notification_log
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY uq_mail_event_recipient (event_code,event_key,recipient_email),
  ADD KEY idx_mail_sent_at (sent_at);

ALTER TABLE ngaynghi
  ADD PRIMARY KEY (maNghi),
  ADD KEY maBacSi (maBacSi),
  ADD KEY maCa (maCa);

ALTER TABLE nguoidung
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY tenDangNhap (tenDangNhap),
  ADD UNIQUE KEY soDienThoai (soDienThoai),
  ADD UNIQUE KEY email (email),
  ADD KEY idx_nguoidung_isDeleted (isDeleted);

ALTER TABLE quantrivien
  ADD PRIMARY KEY (maQuanTriVien),
  ADD UNIQUE KEY nguoiDungId (nguoiDungId);

ALTER TABLE suatkham
  ADD PRIMARY KEY (maSuat),
  ADD KEY maCa (maCa);

ALTER TABLE thongbaoadmin
  ADD PRIMARY KEY (maThongBao),
  ADD KEY fk_tba_nguoidung (nguoiDungId),
  ADD KEY fk_tba_ngaynghi (maNghi),
  ADD KEY fk_tba_yeucau (maYeuCau);

ALTER TABLE thongbaobenhnhan
  ADD PRIMARY KEY (maThongBao),
  ADD KEY maBenhNhan (maBenhNhan),
  ADD KEY idx_daxem (daXem),
  ADD KEY idx_thoigian (thoiGian);

ALTER TABLE thongbaolichkham
  ADD PRIMARY KEY (maThongBao),
  ADD KEY maBacSi (maBacSi),
  ADD KEY maLichKham (maLichKham),
  ADD KEY idx_daxem (daXem),
  ADD KEY idx_thoigian (thoiGian);

ALTER TABLE thuoc
  ADD PRIMARY KEY (maThuoc),
  ADD KEY idx_tenThuoc (tenThuoc),
  ADD KEY idx_loaiThuoc (loaiThuoc);


ALTER TABLE calamviec
  MODIFY maCa int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE chitietdonthuoc
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE doimatkhau
  MODIFY id int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

ALTER TABLE donthuoc
  MODIFY maDonThuoc int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE goikham
  MODIFY maGoi int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE hoadon
  MODIFY maHoaDon int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE lichkham
  MODIFY maLichKham int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

ALTER TABLE lienhe
  MODIFY maLienHe int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE mail_notification_log
  MODIFY id int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

ALTER TABLE ngaynghi
  MODIFY maNghi int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

ALTER TABLE nguoidung
  MODIFY id int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

ALTER TABLE suatkham
  MODIFY maSuat int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

ALTER TABLE thongbaoadmin
  MODIFY maThongBao int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

ALTER TABLE thongbaobenhnhan
  MODIFY maThongBao int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

ALTER TABLE thongbaolichkham
  MODIFY maThongBao int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

ALTER TABLE thuoc
  MODIFY maThuoc int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;


ALTER TABLE bacsi
  ADD CONSTRAINT bacsi_ibfk_1 FOREIGN KEY (nguoiDungId) REFERENCES nguoidung (id) ON DELETE CASCADE,
  ADD CONSTRAINT bacsi_ibfk_2 FOREIGN KEY (maChuyenKhoa) REFERENCES chuyenkhoa (maChuyenKhoa) ON DELETE SET NULL;

ALTER TABLE benhnhan
  ADD CONSTRAINT benhnhan_ibfk_1 FOREIGN KEY (nguoiDungId) REFERENCES nguoidung (id) ON DELETE CASCADE;

ALTER TABLE chitietdonthuoc
  ADD CONSTRAINT chitietdonthuoc_ibfk_1 FOREIGN KEY (maDonThuoc) REFERENCES donthuoc (maDonThuoc),
  ADD CONSTRAINT chitietdonthuoc_ibfk_2 FOREIGN KEY (maThuoc) REFERENCES thuoc (maThuoc);

ALTER TABLE chuyenkhoa
  ADD CONSTRAINT chuyenkhoa_ibfk_1 FOREIGN KEY (maKhoa) REFERENCES khoa (maKhoa) ON DELETE CASCADE;

ALTER TABLE doimatkhau
  ADD CONSTRAINT doimatkhau_ibfk_1 FOREIGN KEY (nguoiDungId) REFERENCES nguoidung (id) ON DELETE CASCADE,
  ADD CONSTRAINT doimatkhau_ibfk_2 FOREIGN KEY (nguoiXuLy) REFERENCES nguoidung (id) ON DELETE SET NULL;

ALTER TABLE donthuoc
  ADD CONSTRAINT donthuoc_ibfk_1 FOREIGN KEY (maLichKham) REFERENCES lichkham (maLichKham);

ALTER TABLE hoadon
  ADD CONSTRAINT hoadon_ibfk_1 FOREIGN KEY (maLichKham) REFERENCES lichkham (maLichKham);

ALTER TABLE hosobenhan
  ADD CONSTRAINT hosobenhan_ibfk_1 FOREIGN KEY (maBenhNhan) REFERENCES benhnhan (maBenhNhan) ON DELETE CASCADE,
  ADD CONSTRAINT hosobenhan_ibfk_2 FOREIGN KEY (maBacSi) REFERENCES bacsi (maBacSi) ON DELETE SET NULL,
  ADD CONSTRAINT hosobenhan_ibfk_3 FOREIGN KEY (maLichKham) REFERENCES lichkham (maLichKham) ON DELETE SET NULL;

ALTER TABLE lichkham
  ADD CONSTRAINT lichkham_ibfk_1 FOREIGN KEY (maBacSi) REFERENCES bacsi (maBacSi),
  ADD CONSTRAINT lichkham_ibfk_2 FOREIGN KEY (maBenhNhan) REFERENCES benhnhan (maBenhNhan),
  ADD CONSTRAINT lichkham_ibfk_3 FOREIGN KEY (maCa) REFERENCES calamviec (maCa),
  ADD CONSTRAINT lichkham_ibfk_4 FOREIGN KEY (maSuat) REFERENCES suatkham (maSuat),
  ADD CONSTRAINT lichkham_ibfk_5 FOREIGN KEY (maGoi) REFERENCES goikham (maGoi);

ALTER TABLE lienhe
  ADD CONSTRAINT fk_lienhe_nguoixuly FOREIGN KEY (nguoiXuLy) REFERENCES nguoidung (id) ON DELETE SET NULL;

ALTER TABLE ngaynghi
  ADD CONSTRAINT ngaynghi_ibfk_1 FOREIGN KEY (maBacSi) REFERENCES bacsi (maBacSi),
  ADD CONSTRAINT ngaynghi_ibfk_2 FOREIGN KEY (maCa) REFERENCES calamviec (maCa);

ALTER TABLE quantrivien
  ADD CONSTRAINT quantrivien_ibfk_1 FOREIGN KEY (nguoiDungId) REFERENCES nguoidung (id) ON DELETE CASCADE;

ALTER TABLE suatkham
  ADD CONSTRAINT suatkham_ibfk_1 FOREIGN KEY (maCa) REFERENCES calamviec (maCa);

ALTER TABLE thongbaoadmin
  ADD CONSTRAINT fk_tba_ngaynghi FOREIGN KEY (maNghi) REFERENCES ngaynghi (maNghi) ON DELETE SET NULL,
  ADD CONSTRAINT fk_tba_nguoidung FOREIGN KEY (nguoiDungId) REFERENCES nguoidung (id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_tba_yeucau FOREIGN KEY (maYeuCau) REFERENCES doimatkhau (id) ON DELETE SET NULL;

ALTER TABLE thongbaobenhnhan
  ADD CONSTRAINT thongbaobenhnhan_ibfk_1 FOREIGN KEY (maBenhNhan) REFERENCES benhnhan (maBenhNhan) ON DELETE CASCADE;

ALTER TABLE thongbaolichkham
  ADD CONSTRAINT thongbao_ibfk_1 FOREIGN KEY (maBacSi) REFERENCES bacsi (maBacSi) ON DELETE CASCADE,
  ADD CONSTRAINT thongbao_ibfk_2 FOREIGN KEY (maLichKham) REFERENCES lichkham (maLichKham) ON DELETE SET NULL;
