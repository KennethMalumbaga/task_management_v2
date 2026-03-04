# TaskFlow Update Log - 2026-03-05

## Summary
This update focused on idle detection reliability, cross-tab behavior, capture-before-logout handling, and session stability during auto-logout flows.

## Completed Changes

### 1) Employee-only shared idle handling across pages
- Added a shared idle modal/logic for employee users through `inc/new_sidebar.php`.
- Idle checks now work on pages that include the new sidebar, not only dashboard-specific activity.
- Admin users are excluded from shared idle flow.

### 2) Idle warning countdown and thresholds
- Updated warning countdown to **60 seconds**.
- Updated no-attendance local inactivity threshold to **60 seconds**.
- Updated capture input-idle request threshold to **60 seconds** (extension-enforced minimum still respected).
- Files:
  - `inc/new_sidebar.php`
  - `index.php`
  - `capture.html`

### 3) Capture-before-idle-logout support
- Dashboard legacy flow already had pre-logout capture request; timeout window was increased for reliability.
- Shared idle flow now also requests a final capture before forcing logout.
- Implemented localStorage request/ack handshake between app page and `capture.html`:
  - Request key: `taskflow_capture_before_idle_logout_req`
  - Ack key: `taskflow_capture_before_idle_logout_ack`
- Files:
  - `inc/new_sidebar.php`
  - `index.php`
  - `capture.html`

### 4) Heartbeat + input-idle status pipeline
- Capture window now publishes heartbeat and input state continuously.
- Added employee heartbeat endpoint and attendance polling response fields.
- Added/used `attendance.last_heartbeat_at` support with migration script.
- Files:
  - `capture.html`
  - `attendance_heartbeat.php` (new)
  - `check_attendance.php`
  - `time_in.php`
  - `run_migration_attendance_heartbeat.php` (new)
  - `task_management_db.sql`
  - `task_management_db_mysql.sql`

### 5) Chrome extension idle integration hardening
- Added `idle` permission and `GET_SYSTEM_IDLE_STATE` message flow.
- Enforced Chrome idle API minimum threshold handling.
- Added safer content-script messaging to avoid hard failures when extension context is reloaded/invalidated.
- Files:
  - `extension/manifest.json`
  - `extension/background.js`
  - `extension/content.js`

### 6) Session-lock/race stability improvements
- Reduced session lock contention by calling `session_write_close()` early in high-frequency endpoints.
- Updated logout flow to release session lock early and continue clock-out DB update safely.
- Added guarded retry in `login.php` session start for transient lock contention.
- Files:
  - `login.php`
  - `logout.php`
  - `save_screenshot.php`
  - `check_attendance.php`
  - `time_in.php`
  - `time_out.php`
  - `attendance_heartbeat.php`

## Current Idle Behavior (as configured)
- Warning countdown: **60s**
- No-attendance local inactivity threshold: **60s**
- Input-idle threshold request from capture: **60s**

## Notes
- Chrome extension changes require reloading the unpacked extension in `chrome://extensions`.
- A hard refresh/new tab session is recommended after JS changes.
- `landing.php` and `screenshot_debug.log` had existing local modifications and were not reverted.
