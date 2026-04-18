SET @db = DATABASE();

SET @stmt = (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = @db
              AND table_name = 'goikham'
              AND column_name = 'isActive'
        ),
        'SELECT 1',
        'ALTER TABLE goikham ADD COLUMN isActive TINYINT(1) NOT NULL DEFAULT 1 AFTER gia'
    )
);
PREPARE migration_stmt FROM @stmt;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @stmt = (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = @db
              AND table_name = 'suatkham'
              AND column_name = 'isActive'
        ),
        'SELECT 1',
        'ALTER TABLE suatkham ADD COLUMN isActive TINYINT(1) NOT NULL DEFAULT 1 AFTER gioKetThuc'
    )
);
PREPARE migration_stmt FROM @stmt;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @stmt = (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = @db
              AND table_name = 'suatkham'
              AND column_name = 'effectiveFrom'
        ),
        'SELECT 1',
        'ALTER TABLE suatkham ADD COLUMN effectiveFrom DATE NOT NULL DEFAULT ''1900-01-01'' AFTER isActive'
    )
);
PREPARE migration_stmt FROM @stmt;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @stmt = (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = @db
              AND table_name = 'suatkham'
              AND column_name = 'effectiveTo'
        ),
        'SELECT 1',
        'ALTER TABLE suatkham ADD COLUMN effectiveTo DATE DEFAULT NULL AFTER effectiveFrom'
    )
);
PREPARE migration_stmt FROM @stmt;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @stmt = (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = @db
              AND table_name = 'suatkham'
              AND column_name = 'presetMinutes'
        ),
        'SELECT 1',
        'ALTER TABLE suatkham ADD COLUMN presetMinutes INT(11) NOT NULL DEFAULT 40 AFTER effectiveTo'
    )
);
PREPARE migration_stmt FROM @stmt;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

UPDATE goikham SET isActive = 1 WHERE isActive IS NULL;
UPDATE suatkham SET isActive = 1 WHERE isActive IS NULL;
UPDATE suatkham SET effectiveFrom = '1900-01-01' WHERE effectiveFrom IS NULL OR effectiveFrom = '0000-00-00';
UPDATE suatkham SET effectiveTo = NULL WHERE effectiveTo = '0000-00-00';
UPDATE suatkham SET presetMinutes = 40 WHERE presetMinutes IS NULL OR presetMinutes <= 0;

ALTER TABLE thongbaolichkham
  MODIFY COLUMN loai enum('Đặt lịch','Hủy lịch','Cập nhật lịch biểu') NOT NULL;
