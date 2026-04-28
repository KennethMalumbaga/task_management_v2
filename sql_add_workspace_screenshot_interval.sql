-- Adds per-workspace screen capture timing settings.
-- Import this once in Hostinger/phpMyAdmin for the app database.

SET @min_column_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'organizations'
    AND COLUMN_NAME = 'screenshot_interval_min_minutes'
);

SET @sql := IF(
  @min_column_exists = 0,
  'ALTER TABLE `organizations` ADD COLUMN `screenshot_interval_min_minutes` INT NOT NULL DEFAULT 20',
  'SELECT ''Column screenshot_interval_min_minutes already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @max_column_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'organizations'
    AND COLUMN_NAME = 'screenshot_interval_max_minutes'
);

SET @sql := IF(
  @max_column_exists = 0,
  'ALTER TABLE `organizations` ADD COLUMN `screenshot_interval_max_minutes` INT NOT NULL DEFAULT 30',
  'SELECT ''Column screenshot_interval_max_minutes already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
