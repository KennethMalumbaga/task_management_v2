-- Workspace theme mode column (MySQL/MariaDB)
-- Run this if you already added theme_primary/theme_secondary/theme_accent before dark mode support.

ALTER TABLE organizations
    ADD COLUMN theme_mode varchar(20) NOT NULL DEFAULT 'light';
