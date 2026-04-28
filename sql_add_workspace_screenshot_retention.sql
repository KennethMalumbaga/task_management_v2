-- Adds per-workspace screen capture retention settings.
-- Import this once in Hostinger/phpMyAdmin for the app database.

SET @column_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'organizations'
    AND COLUMN_NAME = 'screenshot_retention_days'
);

SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE `organizations` ADD COLUMN `screenshot_retention_days` INT NOT NULL DEFAULT 7',
  'SELECT ''Column screenshot_retention_days already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
