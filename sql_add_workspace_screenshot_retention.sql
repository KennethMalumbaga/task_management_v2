-- Workspace screenshot retention setting (MySQL/MariaDB)
-- Run once to enable per-workspace screenshot retention controls.

ALTER TABLE organizations
    ADD COLUMN screenshot_retention_days int NOT NULL DEFAULT 7;
