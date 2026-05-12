# Hosting Performance Snapshot - 2026-05-01

This folder contains the files updated for the Hostinger/dashboard performance work.

## Included Files

- `index.php`
- `check_attendance.php`
- `run_migration_dashboard_performance_indexes.php`
- `inc/loading_screen.php`
- `inc/new_sidebar.php`
- `inc/tenant.php`
- `inc/workspace_screenshot_retention.php`
- `app/ajax/notification_preview.php`
- `app/ajax/bulletin_posts.php`
- `app/model/GroupMessage.php`
- `app/model/Notification.php`
- `app/model/Subtask.php`
- `app/model/Task.php`
- `app/model/user.php`
- `copies/queries.md`

## Main Purpose

- Reduce first dashboard render work.
- Defer heavier widgets after first paint.
- Lower polling pressure on shared hosting.
- Add/prepare database indexes for dashboard-heavy queries.
- Improve page switching smoothness with safe internal prefetching.
- Document the work in `queries.md`.
