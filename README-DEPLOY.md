# Task Management System - Setup and Deployment Guide

This guide is written for beginners. Follow it step by step.

## 1. Understand the important files

- `.env.example` = safe template. This file can be pushed to GitHub.
- `.env.local` = your real secrets (database password, mail password). Do not push this file.
- `.gitignore` already excludes `.env.local`, `uploads/`, `screenshots/`, and backups.

## 2. What you need before starting

- PHP 8.1 or higher
- PostgreSQL database
- Web server (Apache in XAMPP is fine)
- SMTP account for emails (for example Gmail App Password)

## 3. Local setup (Windows + XAMPP)

1. Put the project in:
   `C:\xampp\htdocs\task_management_v2`
2. Start Apache.
3. Create your local env file:
   - Copy `.env.example` to `.env.local`
4. Edit `.env.local` and set real values:
   - `APP_ENV=development`
   - `APP_URL=http://localhost/task_management_v2`
   - `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, `PGPASSWORD`
   - `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`
5. Create database (if needed) and import schema/data:
   - `createdb -U your_db_user your_db_name`
   - `psql -U your_db_user -d your_db_name -f task_management_db.sql`
6. Open the app:
   - `http://localhost/task_management_v2/login.php`

## 4. Production deployment checklist

1. Set production env values on your server/platform:
   - `APP_ENV=production`
   - `APP_URL=https://your-domain.com`
   - real DB and mail credentials
2. Keep maintenance scripts disabled:
   - `ALLOW_MAINTENANCE_SCRIPTS=0`
3. Use HTTPS.
4. Use a strong database password and strong mail app password.
5. Confirm `.env.local` is not in git.
6. Confirm backups/dumps with private data are not in git.

## 5. Maintenance scripts (important)

Maintenance and debug scripts are now protected.

- They are allowed when:
  - run from CLI, or
  - run on localhost in non-production, or
  - `ALLOW_MAINTENANCE_SCRIPTS=1`
- In production, keep `ALLOW_MAINTENANCE_SCRIPTS=0`.

Run maintenance scripts from CLI (safer):

- `php run_migration_task_assignees.php`
- `php run_migration_group_task_link.php`
- `php run_cleanup_orphan_task_chats.php`
- `php run_cleanup_legacy_duplicate_group_chats.php`

Warning:

- `reset_database.php` deletes data and recreates admin credentials.
- Do not run `reset_database.php` in production.

## 6. Email behavior

Signup and password reset now use `APP_URL` from env.

- Local links: `http://localhost/task_management_v2/...`
- Production links: `https://your-domain.com/...`

If email fails, check:

1. `MAIL_USERNAME` and `MAIL_PASSWORD`
2. SMTP host/port settings
3. Sender permissions of your mail provider

## 7. Security basics you should do now

1. Rotate (change) your SMTP app password if it was ever exposed.
2. Never commit `.env.local`.
3. Never commit database dumps with real user data.
4. Keep `APP_ENV=production` in live environments.
5. Keep `ALLOW_MAINTENANCE_SCRIPTS=0` in live environments.

## 8. Quick go-live test

1. Login works.
2. Create task works.
3. Upload file works.
4. Signup email works.
5. Reset password email works.
6. Screenshots/uploads are being saved correctly.

If all 6 pass, your deployment is in good shape.

