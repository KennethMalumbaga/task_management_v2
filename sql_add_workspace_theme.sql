-- Workspace theme palette columns (MySQL/MariaDB)
-- Run once to enable per-workspace UI color customization.

ALTER TABLE organizations
    ADD COLUMN theme_primary varchar(20) DEFAULT NULL,
    ADD COLUMN theme_secondary varchar(20) DEFAULT NULL,
    ADD COLUMN theme_accent varchar(20) DEFAULT NULL;
