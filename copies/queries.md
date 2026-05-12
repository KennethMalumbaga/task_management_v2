# Query Management Notes

Date: 2026-05-01

## Goal

Reduce dashboard lag by lowering the number of database queries made during one page load, especially on `index.php`.

The main strategy is:

- Load only preview-sized data on the dashboard.
- Batch repeated per-card queries into one query.
- Replace per-user leaderboard loops with grouped aggregate queries.
- Cache repeated schema checks inside the same request.
- Add indexes for the columns used by dashboard filters and joins.

## What Was Changed

## Hosting Follow-Up

You mentioned hosting still lags even with no tasks and only 26 users. That points away from task-card queries and toward page-wide work that still runs even when there are no tasks:

- Sidebar unread message counts.
- Notification counts.
- Active user attendance checks.
- Subscription reminder checks.
- Screenshot-retention cleanup checks.
- Missing indexes on user/chat/attendance helper tables.

Additional changes were made for that case:

- Added more hosting-focused indexes to `run_migration_dashboard_performance_indexes.php`.
- Throttled subscription reminder dispatch in `inc/new_sidebar.php` so it only checks once per hour per admin session/workspace.
- Moved screenshot-retention throttling earlier in `inc/workspace_screenshot_retention.php` so normal page loads avoid the heavier cleanup path.

## Network Polling Follow-Up

The hosting Network tab showed constant XHR requests:

- `check_attendance.php`
- `notification_preview.php`
- `presence_heartbeat.php`

That means the page kept asking the server for updates every few seconds. Even when each request is small, many open dashboards can create lag because PHP/MySQL is repeatedly booting, checking sessions, and running queries.

Changes made:

- `check_attendance.php` now supports `?light=1` for cheap active-attendance checks.
- Shared sidebar attendance polling now uses `check_attendance.php?light=1`.
- Sidebar attendance polling changed from every `3s` to every `30s`.
- Notification preview polling changed from every `5s` to every `60s`.
- Notification preview now fetches only 8 rows from SQL instead of loading all and slicing.
- Presence heartbeat changed from every `25s` to every `60s`.
- Admin active-user refresh changed from every `5s` to every `30s`.
- Notification and active-user polling pause when the browser tab is hidden.

Files updated for this polling fix:

- `check_attendance.php`
- `app/ajax/notification_preview.php`
- `inc/new_sidebar.php`
- `index.php`

Expected result:

- The Network tab should no longer fill with requests every few seconds.
- Hosting CPU/PHP worker usage should drop.
- The dashboard should still update, just less aggressively.

## LCP / 3-Second Dashboard Load Follow-Up

The Performance tab screenshot showed `Largest Contentful Paint (LCP): 3.17s`. That is a page-render/load metric, not the same thing as a 3-second polling timer.

More changes were made for an empty-dashboard workspace:

- If there are zero tasks, `index.php` now skips user rating stats and collaborative score queries for the current employee.
- If there are zero tasks, `index.php` now skips top employee and top group leaderboard aggregate queries.
- Notification preview no longer fires immediately on page load because the server already rendered the initial notification HTML. It waits 15 seconds, then refreshes every 60 seconds.

Files updated for this follow-up:

- `index.php`
- `inc/new_sidebar.php`

Important hosting note:

If hosting still shows old 3-second Network polling after upload, it usually means one of these is true:

- `inc/new_sidebar.php` on hosting was not updated.
- Browser cache is still using old HTML/JS.
- Cloudflare or hosting cache is serving the old page.
- Another old copied deployment folder is what the domain is actually using.

After uploading, hard refresh with `Ctrl + F5`, clear hosting/CDN cache if any, then check the Network tab again.

New indexes added locally:

- `chats(receiver_id, opened, organization_id, chat_id)`
- `chats(sender_id, receiver_id, organization_id, chat_id)`
- `attendance_pauses(attendance_id, organization_id, resumed_at)`
- `users(organization_id, role, id)`
- `organization_members(organization_id, role, user_id)`
- `subscriptions(organization_id)`

On hosting, run the updated migration again:

```bash
php run_migration_dashboard_performance_indexes.php
```

If hosting does not allow CLI PHP, open the migration file through the browser once, then remove or protect it after it reports the indexes.

### 1. Dashboard Task Cards Now Use Batched Data

File: `index.php`

Before:

- Each visible task card called `get_subtasks_by_task($pdo, $task['id'])`.
- Each visible task card called `get_task_assignees($pdo, $task['id'])`.
- That means 8 admin task cards could create 16 extra queries just for card status and assignees.

Now:

- Dashboard collects all visible task IDs once.
- It calls one batched assignee query.
- It calls one batched subtask-status query.
- Rendering reuses maps:
  - `$recentTaskAssigneesMap`
  - `$recentTaskStartedSubtaskMap`

This turns repeated task-card lookups from `N + N` queries into `2` queries.

### 2. Added Batched Task Assignee Fetching

File: `app/model/Task.php`

Added:

```php
get_task_assignees_map($pdo, array $task_ids)
```

This returns assignees grouped by `task_id`, so the dashboard can fetch all leaders and members for visible tasks in one database round trip.

Also improved `column_exists()` with:

- Per-request static cache.
- `table_schema = DATABASE()` filter.

This avoids repeated `information_schema` checks from becoming hidden dashboard overhead.

### 3. Added Batched Started-Subtask Status Fetching

File: `app/model/Subtask.php`

Added:

```php
get_task_started_subtask_map($pdo, array $task_ids)
```

This tells the dashboard which visible tasks have subtasks in active/submitted/completed states without loading every subtask row for every task.

The dashboard only needs a yes/no answer for status display, so this query uses `COUNT(*)` grouped by `task_id`.

### 4. Reworked Top Users Leaderboard Aggregation

File: `app/model/user.php`

Before:

- `get_top_rated_users()` fetched all employees.
- Then it called rating/collaboration functions once per employee.
- Some collaboration logic also queried per leader task.

Now:

- It still fetches employees once.
- It fetches task ratings grouped by user.
- It fetches leader admin ratings grouped by user.
- It fetches peer feedback grouped by leader.
- It fetches member subtask scores grouped by member.
- PHP combines the maps and sorts the final preview.

This changes leaderboard work from many per-user queries into a small fixed set of aggregate queries.

### 5. Sidebar Notifications Are Limited

Files:

- `app/model/Notification.php`
- `inc/new_sidebar.php`

The sidebar notification preview now requests only the latest 8 notifications instead of loading all notifications and slicing in PHP.

### 6. Group Unread Count Was Aggregated

File: `app/model/GroupMessage.php`

The group unread count was rewritten from a per-group loop into one aggregate query. This is better for users who belong to many groups.

### 7. Tenant Schema Checks Are Cached

File: `inc/tenant.php`

`tenant_column_exists()` and `tenant_table_exists()` now cache results for the current PHP request. This matters because many models call these helpers repeatedly.

### 8. Dashboard Performance Indexes Were Added

File:

- `run_migration_dashboard_performance_indexes.php`

This migration adds indexes for dashboard-heavy queries, including:

- `attendance(user_id, att_date, time_out)`
- `notifications(recipient, is_read, date, id)`
- `task_assignees(user_id, role, task_id)`
- `task_assignees(task_id, user_id, role)`
- `group_members(user_id, group_id)`
- `group_message_reads(user_id, group_id, last_message_id)`
- `group_messages(group_id, id)`
- `leader_feedback(leader_id, task_id, rating)`
- `screenshots(user_id, attendance_id, taken_at)`
- `bulletin_posts(organization_id, id)`

The migration was run locally and reported all indexes as added.

## Files Updated For Query/Performance Work

- `index.php`
- `app/model/Task.php`
- `app/model/Subtask.php`
- `app/model/user.php`
- `app/model/GroupMessage.php`
- `app/model/Notification.php`
- `inc/new_sidebar.php`
- `inc/tenant.php`
- `inc/loading_screen.php`
- `inc/workspace_screenshot_retention.php`
- `run_migration_dashboard_performance_indexes.php`
- `copies/queries.md`

## Other Modified Files Currently Present

These files are also modified in the working tree, but they were already present as unrelated local changes or are outside the query-performance change set:

- `app/google-login.php`
- `app/login.php`
- `app/verify-login-code.php`
- `capture.html`
- `copies/mobile-desktop-clockin-update/css/dashboard-page.css`
- `copies/mobile-desktop-clockin-update/index.php`
- `css/dashboard-page.css`
- `inc/loading_screen.php`
- `login.php`
- `tmp/google_identity_certs_cache.json`
- `tmp/screenshot_retention_self_cleanup_org_1.json`

## Verification Done

PHP lint passed for:

- `index.php`
- `app/model/Task.php`
- `app/model/Subtask.php`
- `app/model/user.php`

Earlier lint also passed for:

- `inc/tenant.php`
- `app/model/Notification.php`
- `app/model/GroupMessage.php`
- `run_migration_dashboard_performance_indexes.php`

Latest follow-up lint also passed for:

- `inc/workspace_screenshot_retention.php`
- `inc/new_sidebar.php`
- `run_migration_dashboard_performance_indexes.php`

## Practical Rules For Keeping Queries Fast

Use these rules when adding new dashboard widgets:

- Do not load full tables for previews. Add `LIMIT`.
- Do not query inside loops if the loop can have multiple records.
- Use grouped queries like `GROUP BY user_id`, `GROUP BY task_id`, or `COUNT(*)`.
- Add indexes for columns used in `WHERE`, `JOIN`, and `ORDER BY`.
- For page chrome like sidebars, fetch only preview rows.
- For expensive panels, load them later with AJAX instead of blocking the first dashboard paint.

## Login 11 Second LCP Follow-Up

The latest Chrome Performance screenshot showed:

- LCP: `11.10 s`
- LCP element: `img.tm-loading-logo`

That means Chrome was measuring the loading overlay logo as the largest visible content, not the dashboard cards. The dashboard may still have query work, but this specific 11 second reading was mainly caused by the loading screen staying visible too long after Google login.

File updated:

- `inc/loading_screen.php`

What changed:

- The login loading overlay now hides once the DOM is interactive, instead of mainly waiting for the full `window.load` event.
- Minimum loader visibility was reduced from `500ms` to `350ms`.
- A hard maximum loader visibility cap of `1200ms` was added.
- The old fallback waited up to `6000ms`, which could make hosting feel much slower when external assets or background requests delayed the load event.

Expected effect:

- Login should no longer show the loading logo as the LCP element for many seconds.
- The dashboard content should become visible sooner after Google login.
- If the page still feels slow after this, the next check should be the actual document request timing in Network, especially TTFB for `index.php`.

## Local 0.43 Second LCP vs Hosting Goal

The local screenshot showed:

- LCP: `0.43 s`
- LCP element: `h1.dash-content-topbar-title`

This is the healthy version of the page. Chrome is measuring the real dashboard title, not the loading overlay. That means the browser can paint the page shell almost immediately on local.

Why local is much faster:

- PHP and MySQL are on the same machine.
- There is almost no network latency.
- Static files load from disk instead of the internet.
- The database is not sharing resources with other hosting accounts.
- Browser cache and local server cache are usually warm.

Why hosting can still be slower:

- Shared hosting has slower CPU and disk I/O.
- Database calls have more latency.
- The first `index.php` document may wait on PHP sessions, Google login redirects, subscription checks, notification checks, sidebar counts, or dashboard queries.
- If indexes are not added on the hosting database, the same optimized code can still run slowly.
- If PHP OPcache is disabled, every request recompiles PHP files.

Target for hosting:

- Realistic good target: `1.0s` to `2.0s` LCP.
- Excellent target on stronger hosting/VPS: under `1.0s`.
- Matching local `0.43s` exactly is unlikely on shared hosting, but the page can get much closer if TTFB is low and the loader is not counted as LCP.

Hosting checklist:

- Upload the latest code changes.
- Run `run_migration_dashboard_performance_indexes.php` once on the hosting database.
- Check Network timing for the main `index.php` request.
- If `index.php` TTFB is high, the bottleneck is server/PHP/database.
- If TTFB is low but LCP is high, the bottleneck is frontend rendering, loader, CSS, images, or JavaScript.
- Enable PHP OPcache in hosting if available.
- Keep polling intervals slow enough so 26 online users do not continuously hit PHP endpoints every few seconds.

## Hosting Smoothness Follow-Up

Another hosting-focused change was added to reduce the first `index.php` server work.

File updated:

- `index.php`

What changed:

- The admin dashboard no longer fetches the full active-user list during the first PHP render.
- The Active Users panel now shows a small loading state first.
- The existing `app/ajax/active_users.php` endpoint loads the active-user list after the browser has had a chance to paint the dashboard.
- The first active-user refresh is scheduled with `requestIdleCallback` when available, with a timeout fallback.

Why this helps:

- `get_active_users_with_pause_state()` can be heavier on hosting because it checks active attendance, pause state, profile data, and related rows.
- Moving it out of the first document response lowers the chance that `index.php` TTFB delays LCP.
- Admins still get the same active-user panel, but it loads just after the page shell appears.

Next best candidates:

- Move leaderboard data to AJAX if production still has high `index.php` TTFB.
- Move bulletin posts to AJAX if bulletin visibility queries become expensive.
- Add a small timing log around dashboard data sections to find the exact slowest PHP block on hosting.

## Bulletin Board Deferred Loading

Files updated:

- `index.php`
- `app/ajax/bulletin_posts.php`
- `copies/queries.md`

What changed:

- `index.php` no longer queries bulletin posts before sending the dashboard HTML.
- The Bulletin Board shows a small loading state first.
- `app/ajax/bulletin_posts.php` fetches the posts after the page shell can paint.
- Posting and deleting bulletin posts still update the same `bulletinPosts` array on the page.

Why this helps hosting:

- Bulletin visibility can involve audience checks for everyone, groups, and task assignments.
- Moving that query after first paint reduces the amount of PHP/MySQL work blocking the first dashboard response.
- This should help `index.php` TTFB and LCP on Hostinger, especially on shared resources.

Tradeoff:

- Bulletin posts may appear a fraction of a second after the dashboard shell.
- The page should feel smoother because the main content can paint first.

## Page Switching Smoothness

File updated:

- `inc/loading_screen.php`

What changed:

- Internal sidebar/topbar navigation links now trigger the transition loading screen.
- Same-origin navigation links are prefetched when the user hovers, focuses, or touches them.
- Prefetch only applies to normal internal navigation pages, not downloads/assets, external links, notification action links, or logout.
- Logout links are excluded so the logout confirmation modal still behaves normally.

Why this helps:

- Prefetch can warm the next page before the click, making page switching feel faster when the browser and hosting allow it.
- The transition loader gives immediate visual feedback after clicking a page, so hosting delay feels less like the system froze.
- The destination page still loads normally, so page permissions and server logic are not bypassed.

Notes:

- Prefetch is a hint, not a guarantee. Browsers may skip it on slow networks or low resources.
- This improves perceived smoothness. If Hostinger `index.php` or another page has high TTFB, server/database optimization is still needed.
