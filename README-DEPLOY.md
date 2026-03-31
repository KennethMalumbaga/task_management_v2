# Task Management System - Setup and Deployment Guide

This guide is written for beginners. Follow it step by step.

## 0. SaaS docs (read this first if you are enabling workspaces)

- Quick setup: `README-SAAS-QUICKSTART.md`
- Full beginner guide: `README-SAAS.md`
- Support operations cheatsheet: `README-SUPPORT-CHEATSHEET.md`
- Billing/settings admin page: `workspace-billing.php`
- Seat update endpoint (used by billing page): `app/update-workspace-seat-limit.php`

## 1. Understand the important files

- `.env.example` = safe template. This file can be pushed to GitHub.
- `.env.local` = your real secrets (database password, mail password). Do not push this file.
- `.gitignore` already excludes `.env.local`, `uploads/`, `screenshots/`, and backups.

## 2. What you need before starting

- PHP 8.1 or higher
- MySQL/MariaDB database
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
   - `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
   - `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`
   - if using Google Sign-In, set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`
5. Create database (if needed) and import schema/data:
   - `mysql -u your_db_user -p -e "CREATE DATABASE IF NOT EXISTS your_db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"`
   - `mysql -u your_db_user -p your_db_name < task_management_db_mysql.sql`
6. (SaaS foundation) run the multi-tenant migration:
   - `mysql -u your_db_user -p your_db_name < sql_add_multi_tenancy_foundation.sql`
7. (SaaS invite flow) run workspace invites migration:
   - `mysql -u your_db_user -p your_db_name < sql_create_workspace_invites.sql`
   - or: `php run_migration_workspace_invites.php`
8. Open the app:
   - `http://localhost/task_management_v2/login.php`

## 4. Production deployment checklist

1. Set production env values on your server/platform:
   - `APP_ENV=production`
   - `APP_URL=https://your-domain.com`
   - real DB and mail credentials
   - if using Google Sign-In on Railway, set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`
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

## 6.1 Google Sign-In on Local and Railway

Google Sign-In now uses the callback-based Google Identity flow for login, signup, and invite acceptance, so it does not depend on a fixed redirect URI per environment.

- Local env example:
  - `APP_URL=http://localhost/task_management_v2`
- Railway env example:
  - `APP_URL=https://your-app.up.railway.app`
- In Google Cloud Console, add every host you use under Authorized JavaScript origins:
  - `http://localhost`
  - `https://your-app.up.railway.app`

If Google login works locally but fails on Railway, or the other way around, the usual cause is that one of those origins is missing from the Google OAuth client.

## 7. Security basics you should do now

1. Rotate (change) your SMTP app password if it was ever exposed.
2. Never commit `.env.local`.
3. Never commit database dumps with real user data.
4. Keep `APP_ENV=production` in live environments.
5. Keep `ALLOW_MAINTENANCE_SCRIPTS=0` in live environments.
6. Keep users on normal in-app UI actions (forms and AJAX endpoints now rely on session CSRF tokens).
7. CSRF is now enabled for major write flows (tasks, groups, subtasks, profile/user updates, auth/reset, admin clock-out, chat AJAX, attendance/capture AJAX, and notification-read action).

## 8. Quick go-live test

1. Login works.
2. Create task works.
3. Upload file works.
4. Signup email works.
5. Reset password email works.
6. Screenshots/uploads are being saved correctly.
7. Invite flow works (send invite, accept invite, revoke invite).
8. Billing seat update form works.
9. Task/group/subtask write actions still work from normal UI (create/review/delete/submit/resubmit/rate).
10. Profile and user role update forms still work.
11. Admin clock-out button works from `user.php`.
12. Submitting old/stale forms shows safe `Invalid or expired request` and does not perform the action.
13. Messages still send/load in `messages.php` (direct and group chat).
14. Employee time-in/time-out still works from dashboard, and capture uploads still work.
15. Clicking a notification marks it read and redirects correctly.
16. Bulk employee invite upload works from `invite-user.php` (xlsx/csv/pdf).
17. One-time generated join link works and is consumed after first successful signup.

If all checks pass, your deployment is in good shape.
