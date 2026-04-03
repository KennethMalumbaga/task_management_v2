-- Workspace screenshot interval settings (MySQL/MariaDB)
-- Run once to enable per-workspace random screenshot timing controls.

ALTER TABLE organizations
    ADD COLUMN screenshot_interval_min_minutes int NOT NULL DEFAULT 20,
    ADD COLUMN screenshot_interval_max_minutes int NOT NULL DEFAULT 30;
