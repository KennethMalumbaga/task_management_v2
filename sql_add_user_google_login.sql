-- User Google Sign-In columns (MySQL/MariaDB)
-- Run this on existing databases before enabling Sign in with Google.

ALTER TABLE users
    ADD COLUMN google_sub varchar(255) NULL AFTER username,
    ADD COLUMN google_picture varchar(2048) NULL AFTER profile_image,
    ADD COLUMN google_email_verified tinyint(1) NOT NULL DEFAULT 0 AFTER google_picture;
