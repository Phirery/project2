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

UPDATE goikham SET isActive = 1 WHERE isActive IS NULL;
UPDATE suatkham SET isActive = 1 WHERE isActive IS NULL;
