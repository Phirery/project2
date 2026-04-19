SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

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
(41, 1, '07:00:00', '08:00:00', 1, '2026-04-21', NULL, 60),
(42, 1, '08:00:00', '09:00:00', 1, '2026-04-21', NULL, 60),
(43, 1, '09:00:00', '10:00:00', 1, '2026-04-21', NULL, 60),
(44, 1, '10:00:00', '11:00:00', 1, '2026-04-21', NULL, 60),
(45, 2, '13:00:00', '14:00:00', 1, '2026-04-21', NULL, 60),
(46, 2, '14:00:00', '15:00:00', 1, '2026-04-21', NULL, 60),
(47, 2, '15:00:00', '16:00:00', 1, '2026-04-21', NULL, 60),
(48, 2, '16:00:00', '17:00:00', 1, '2026-04-21', NULL, 60);


ALTER TABLE suatkham
  ADD PRIMARY KEY (maSuat),
  ADD KEY maCa (maCa),
  ADD UNIQUE KEY uniq_suatkham_slot_version (maCa, gioBatDau, gioKetThuc, effectiveFrom);


ALTER TABLE suatkham
  MODIFY maSuat int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;


ALTER TABLE suatkham
  ADD CONSTRAINT suatkham_ibfk_1 FOREIGN KEY (maCa) REFERENCES calamviec (maCa);
