# TaskFlow Update Log - 2026-02-21

## Summary
This update focused on notification behavior, mobile UI improvements, and rating logic consistency (especially for leader scoring and task-level display).

## Completed Changes

### 1) Profile dropdown text overflow fix
- Long profile names/emails now stay inside the dropdown container.
- File:
  - `css/dashboard.css`

### 2) Notification click target (employee flow)
- Clicking task notifications for employees now opens task modal flow in `my_task.php` instead of going to task details page directly.
- File:
  - `app/notification-read.php`

### 3) Subtask notifications cleanup
- No notification when leader assigns subtask to self.
- "Subtask submitted by User X" changed to use submitter full name.
- Avoid notifying leader about own submission.
- Files:
  - `app/add-subtask.php`
  - `app/update-subtask-submission.php`

### 4) Rating-related notification wording and self-notify prevention
- Subtask accepted with score now notifies as `Subtask Rated`.
- Task accepted with score now notifies as `Task Rated`.
- No self-notification when leader accepts own subtask.
- Files:
  - `app/review-subtask.php`
  - `app/admin-review-task.php`

### 5) Mobile topbar UI (notifications + profile)
- Added mobile notification dropdown.
- Added mobile profile dropdown.
- Kept/returned Messages icon in mobile top actions beside bell/profile.
- Files:
  - `inc/new_sidebar.php`
  - `css/dashboard.css`

### 6) Relative notification time ("x mins/hours/days ago")
- Notification displays now use relative time instead of raw date.
- Added DB-reference-now handling to avoid timezone mismatch showing false `just now`.
- Applied to desktop dropdown, mobile dropdown, notifications page, and legacy notification feed.
- Files:
  - `app/helpers/notification.php`
  - `inc/new_sidebar.php`
  - `notifications.php`
  - `app/notification.php`

### 7) Notification timestamp accuracy support
- Added `notified_at` timestamp support in notification model inserts/order.
- Added migration script and executed it.
- Files:
  - `app/model/Notification.php`
  - `run_migration_notifications_notified_at.php`

#### Migration run result
- Added `notifications.notified_at`
- Backfilled existing rows
- Added index: `idx_notifications_recipient_notified_at`

### 8) Leader rating logic changed to true 50/50
- Replaced smoothing-based leader peer logic with direct 50/50 blend:
  - 50% admin leader rating
  - 50% member average rating
- Removed smoothed/raw mismatch in leader feedback display.
- Files:
  - `app/model/Subtask.php`
  - `app/model/LeaderFeedback.php`
  - `tasks.php`

### 9) Task modal ratings are now task-specific
- In task modal (admin tasks page), displayed leader/member rate and collab rate are now based on that specific task (not profile overall).
- File:
  - `tasks.php`

## Validation
- PHP syntax checks passed for all modified PHP files after each update.

## Notes
- Older notifications without real historical time precision are handled with safe fallback.
- Leaderboard values still represent overall logic where applicable; task modal now shows per-task values.
