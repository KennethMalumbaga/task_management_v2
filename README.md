# Task Management System Documentation

Updated: 2026-05-12

This is the consolidated documentation for the Task Management system. Older project-owned Markdown files were merged into this file so the repository has one current source of truth. Third-party vendor documentation under `lib/PHPMailer/` is intentionally kept separate because it belongs to the bundled mail library.

## 1. System Overview

The application is a PHP and MySQL task management, workforce monitoring, messaging, billing, payroll, and reporting system for workspace-based teams. It supports a SaaS-style tenant model where each organization has its own users, tasks, attendance records, screenshots, messages, reports, billing state, and workspace settings.

The system is built around two user-facing roles:

- Admins manage the workspace, users, groups, billing, tasks, timelines, attendance, reports, payroll, payslips, screenshots, announcements, meetings, and formal email.
- Employees work on assigned tasks and subtasks, participate in chat, clock in and out, submit outputs, view their reports, and view their payslips.

There is also a special super admin path used by the username `admin` for maintenance and cross-workspace operations.

## 2. Technology Stack

- Backend: procedural PHP with shared helper/model files.
- Database: MySQL or MariaDB through PDO.
- Web server: Apache, commonly through XAMPP locally or the provided Docker image.
- Frontend: server-rendered PHP, Bootstrap-style markup, custom CSS, JavaScript, jQuery AJAX, FullCalendar, and timeline-specific JavaScript.
- Email: bundled PHPMailer under `lib/PHPMailer`.
- Authentication integrations: Google Identity for sign-in/signup/invite flows.
- Google Workspace integrations: Google Docs, Sheets, Slides, Calendar, Meet, and Gmail.
- Monitoring extension: Chrome Manifest V3 extension under `extension/`.
- Deployment helpers: `Dockerfile`, `.dockerignore`, `docker/apache-vhost.conf`, `docker/php.ini`, `docker/entrypoint.sh`, and `.user.ini`.

Important environment files:

- `.env.example`: sample configuration for database, app URL, mail, Google OAuth, PayMongo, and maintenance flags.
- `.env.local`: local override file loaded before `.env`.
- `.env`: normal deployment configuration.

## 3. Main Directory Map

- `app/`: request handlers, AJAX endpoints, helpers, and models.
- `app/model/`: database-facing model functions for users, tasks, subtasks, groups, messages, reports, payroll, calendar meetings, notifications, timeline, and related features.
- `app/helpers/`: shared service helpers for input handling, password rules, Google services, PayMongo, login verification, Google Docs subtasks, Gmail, Google Calendar, and signup policy.
- `app/ajax/`: chat, notification, presence, typing, Gmail, and live UI AJAX endpoints.
- `inc/`: shared UI includes, sidebar, CSRF helpers, tenant helpers, attendance pause helpers, workspace theme helpers, screenshot retention and interval helpers.
- `css/`: application styles.
- `extension/`: Chrome extension used for desktop screen capture during active attendance.
- `docker/`: Apache, PHP, and entrypoint configuration for container deployments.
- `uploads/`: uploaded chat and task/subtask files.
- `screenshots/`: employee screenshots captured by the browser extension.
- `tmp/`: runtime or generated maintenance output.
- `lib/PHPMailer/`: bundled third-party mail library.

## 4. Configuration

Database connection is handled by `DB_connection.php`. It loads `.env.local` and `.env`, then accepts either URL-style database variables or individual MySQL variables:

- `DATABASE_URL` or `MYSQL_URL`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

The connection uses PDO with `utf8mb4`, exceptions enabled, and native prepared statements.

Other important environment variables include:

- `APP_ENV`: environment label such as `local`, `production`, or platform-specific values.
- `APP_URL`: canonical application URL.
- `MAIL_*`: SMTP configuration used by PHPMailer.
- `GOOGLE_LOGIN_CLIENT_ID` and `GOOGLE_LOGIN_CLIENT_SECRET`: Google Sign-In client for login, signup, and invite acceptance.
- `GOOGLE_WORKSPACE_CLIENT_ID` and `GOOGLE_WORKSPACE_CLIENT_SECRET`: Google OAuth client for Drive/Workspace, Calendar/Meet, and Gmail integrations.
- `PAYMONGO_SECRET_KEY`: PayMongo billing integration key.
- `ALLOW_MAINTENANCE_SCRIPTS`: allows web maintenance scripts outside CLI when explicitly enabled.
- `ALLOW_GLOBAL_MAINTENANCE`: allows global maintenance actions instead of tenant-scoped actions.
- `DISABLE_LOGIN_VERIFICATION`: bypasses login verification when intentionally enabled.
- `DISABLE_LOGIN_VERIFICATION_ON_RAILWAY`: platform-specific login verification bypass.

Upload and runtime limits are increased in `.user.ini`:

- `upload_max_filesize = 100M`
- `post_max_size = 100M`
- `memory_limit = 256M`
- `max_execution_time = 300`
- `max_input_time = 300`

## 5. Tenant and Workspace Model

The tenant system is centered in `inc/tenant.php`. A workspace is represented by an organization record and related membership/subscription records.

Core tenant tables:

- `organizations`: workspace identity, billing email, status, plan, theme, screenshot retention, and screenshot interval settings.
- `organization_members`: links users to workspaces with organization-level roles.
- `subscriptions`: subscription provider, status, plan/seat limit, trial end date, and current period end date.
- `workspace_invites`: pending, accepted, cancelled, and expired workspace invitations.

Important tenant behavior:

- Tenant-aware model functions use `tenant_get_scope()` to apply `organization_id` filters when the table supports it.
- A user must be associated with an organization to access the workspace in tenant mode.
- Workspace access can be blocked when a subscription is inactive, canceled, suspended, unpaid, expired, incomplete, or past its trial/current period.
- Workspaces have seat limits based on plan.
- Workspace settings include name, billing status, theme colors, screenshot retention days, and screenshot capture interval.

Plans in code:

- `starter`: 10 seats.
- `professional`: 20 seats.
- `enterprise`: 40 seats.

The free trial period is 2 days.

## 6. Roles and Permissions

The system has several role layers:

- Application role on `users.role`: `admin` or `employee`.
- Organization role on `organization_members.role`: `owner`, `admin`, or `member`.
- Task/group role on `task_assignees.role` or `group_members.role`: `leader` or `member`.
- Super admin: an admin user with username `admin`, used for maintenance dashboard access.

General access rules:

- Admins manage workspace-level features.
- Employees can access their dashboard, tasks, calendar, timeline, messages, reports, and payslips.
- Project/task leaders can manage timeline phases for led projects, review member subtasks, and submit final task reviews.
- Task members submit their assigned subtasks and can rate leaders after completion.
- Super admin can access `maintenance_dashboard.php` and global maintenance flows when environment flags allow it.

## 7. Authentication and Account Flows

Primary pages and handlers:

- `login.php`
- `app/login.php`
- `signup.php`
- `app/signup.php`
- `forgot-password.php`
- `app/req-reset-password.php`
- `reset-password.php`
- `app/do-reset-password.php`
- `app/google-login.php`

Signup behavior:

- Signup creates a new workspace owner/admin.
- Employees should join by invitation, not by direct public signup.
- Signup supports trial and paid modes.
- Trial workspaces are created with a trialing subscription.
- Paid workspaces are created with an incomplete subscription and then routed to checkout.
- Google signup can prefill identity data from a pending Google signup session.

Login behavior:

- Login verifies CSRF, password, app role, organization membership, and workspace subscription access.
- If billing is required, admins are routed to `workspace-billing.php`.
- Login verification can send a 4-digit email code with a 10-minute expiry.
- Successful login stores session keys for role, user id, username, full name, organization id, organization role, and organization name.
- Super admin users are routed to the maintenance dashboard.

Password reset behavior:

- Password reset tokens are stored in `password_resets`.
- Reset logic is tenant-aware where possible.
- Password policy requires at least 8 characters with uppercase, lowercase, number, and symbol.

## 8. Workspace Billing and Settings

Billing pages and handlers:

- `workspace-billing.php`
- `post-signup-checkout.php`
- `app/select-workspace-plan.php`
- `app/process-post-signup-payment.php`
- `app/process-dummy-payment.php`
- `app/paymongo-return.php`
- `app/update-workspace-seat-limit.php`

Workspace settings pages and handlers:

- `workspace-settings.php`
- `app/update-workspace-name.php`
- `app/update-workspace-theme.php`
- `app/update-workspace-screenshot-retention.php`
- `app/update-workspace-screenshot-interval.php`

Workspace billing features:

- Plan selection.
- Seat limit management.
- Trial state display.
- Subscription status display.
- PayMongo checkout return handling.
- Payment-required gating for workspaces with incomplete or inactive billing.
- Subscription reminders.

Workspace appearance features:

- Workspace name update.
- Theme presets and custom colors.
- CSS variables applied through `inc/workspace_theme.php` and `inc/workspace_theme_style.php`.

Workspace monitoring settings:

- Screenshot retention days, default 7, allowed 1 to 365.
- Screenshot interval, default random window 20 to 30 minutes, allowed 5 to 180 minutes.

## 9. User and Invitation Management

Primary pages and handlers:

- `user.php`
- `user_details.php`
- `invite-user.php`
- `join-workspace.php`
- `edit-user.php`
- `profile.php`
- `edit_profile.php`
- `app/invite-user.php`
- `app/invite-users-bulk.php`
- `app/generate-invite-link.php`
- `app/cancel-invite.php`
- `app/accept-invite.php`
- `app/update-user.php`
- `app/update-user-role.php`
- `app/update-user-rate.php`
- `app/update-profile.php`

User features:

- Admin user list and user detail pages.
- Profile editing.
- Profile image support.
- Role updates.
- Hourly rate updates for payroll.
- Google account metadata on user records.
- Online/presence state through `last_active_at`.
- Active user cards with latest screenshot and attendance state.

Invite features:

- Single email invite.
- Bulk invite parsing.
- One-time share invite links.
- Invite cancellation.
- Invite acceptance with capacity and subscription checks.
- Google invite flow.
- Automatic user and organization membership creation when an invite is accepted.

Direct add-user pages exist for compatibility, but the current workflow prefers invitations.

## 10. Groups

Primary pages and handlers:

- `groups.php`
- `app/add-group.php`
- `app/delete-group.php`
- `app/model/Group.php`

Group features:

- Admins create and delete groups.
- Groups have leaders and members.
- Groups can be used for task assignment.
- Task chat groups are automatically created and synchronized for tasks.
- Cleanup helpers remove orphaned or duplicate legacy task chat groups.
- Group ranking can use task and member performance data.

## 11. Dashboard and Navigation

Main dashboard:

- `index.php`

Navigation:

- `inc/new_sidebar.php` is the current sidebar.
- `inc/nav.php` is older sidebar code.

Employee navigation includes:

- Dashboard.
- Tasks.
- Calendar.
- Timeline.
- Messages.
- Reports.
- My Payslips.

Admin navigation includes:

- Dashboard.
- Tasks.
- Calendar.
- Timeline.
- Messages.
- Users.
- Invite Users.
- Billing.
- Groups.
- Captures.
- Reports.
- DTR Records.
- Payroll.
- Payslips.

Dashboard features:

- Role-specific dashboard cards and summaries.
- Attendance controls for employees.
- Active user and capture cards for admins.
- Task status summaries.
- Bulletin announcements and reminders.
- Subscription and billing notices.
- Top-rated users and groups.

## 12. Task and Project Workflow

Primary pages and handlers:

- `create_task.php`
- `tasks.php`
- `my_task.php`
- `edit-task.php`
- `edit-task-employee.php`
- `app/add-task.php`
- `app/update-task.php`
- `app/delete-task.php`
- `app/submit-task-review.php`
- `app/admin-review-task.php`
- `app/resubmit-task.php`
- `app/review-subtask.php`
- `app/update-subtask-submission.php`
- `app/model/Task.php`
- `app/model/Subtask.php`

Task assignment modes:

- Group assignment.
- Manual leader and member assignment.

Task roles:

- Leader: coordinates task work, manages timeline phases where allowed, reviews member subtasks, submits final work for admin review.
- Member: works on assigned subtasks and submits outputs.
- Admin: creates tasks, reviews final task submissions, accepts completed work, or sends work back for revision.

Task status concepts:

- `pending`: task has not started.
- `in_progress`: task or subtasks are actively being worked.
- `completed`: task has been submitted and/or accepted depending on rating state.
- `rejected` and `revise`: revision states used by review flows.
- Derived review state: a completed task with no rating is treated as submitted for admin review.

Task review flow:

1. Admin creates a task with assignment mode, leader, members, optional description, deadline, template, or Google document link.
2. The system creates task assignees and a task chat group.
3. Timeline phases can create or sync subtasks.
4. Members submit subtasks.
5. Leaders accept or request revision on member subtasks.
6. Leader submits the final task for admin review.
7. Admin accepts with task rating and optional leader rating, or requests revision.
8. If revision is requested, the task returns to in progress and review ratings are cleared.

Subtask behavior:

- Subtasks are primarily generated from timeline phases.
- Subtask status values include `pending`, `submitted`, `completed`, and `revise`.
- Subtask submissions can include uploaded files.
- Accepted subtasks can receive a score.
- Revision requests include feedback.
- Google Workspace subtasks can use linked Docs, Sheets, or Slides.

Upload rules for task/subtask submissions:

- Allowed extensions include `pdf`, `doc`, `docx`, `xls`, `xlsx`, `png`, `jpg`, `jpeg`, `zip`, and `json`.
- Maximum upload size is 50 MB per application validation.
- Files are saved under `uploads/`.

## 13. Timeline

Primary page and endpoints:

- `timeline.php`
- `app/model/Timeline.php`
- `app/timeline/_common.php`
- `app/timeline/get.php`
- `app/timeline/save_task.php`
- `app/timeline/save_phase.php`
- `app/timeline/add_task.php`
- `app/timeline/delete_task.php`
- `app/timeline/delete_phase.php`

Timeline data tables:

- `project_timeline_tasks`: timeline lanes/tasks under a project task.
- `project_timeline_phases`: phase bars under timeline lanes.

Timeline features:

- Admin overview across workspace projects.
- Employee view for assigned projects.
- Leader editing for projects they lead.
- Member read-only access.
- Timeline lane creation, editing, deletion, and ordering.
- Timeline phase creation, editing, deletion, drag, resize, color, icon, and type selection.
- Phase types: `standard`, `document`, `sheet`, and `slides`.
- Gantt-style visual planning.
- Project switcher, filters, search, and stats.
- Timeline phase dates are bounded by project/task timing rules.

Important timeline-to-subtask behavior:

- Saving timeline tasks or phases triggers subtask synchronization.
- Each timeline phase can map to a subtask.
- Timeline phases with Google Workspace types can create or attach Google Docs, Sheets, or Slides.

## 14. Google Integrations

Google authentication:

- `app/helpers/google_auth.php`
- `app/google-login.php`
- `app/google-invite-init.php`
- `app/google-invite-clear.php`

Google Workspace files:

- `app/model/GoogleWorkspace.php`
- `app/helpers/google_workspace.php`
- `app/helpers/subtask_google_docs.php`
- `app/google-subtask-doc.php`

Google Calendar and Meet:

- `app/helpers/google_calendar.php`
- `app/google-calendar-meeting.php`
- `app/google-calendar-callback.php`

Google Gmail:

- `app/helpers/google_gmail.php`
- `app/google-gmail-init.php`
- `app/google-gmail-callback.php`
- `app/ajax/sendGmailMessage.php`

Google features:

- Google sign-in and signup.
- Google invite acceptance support.
- OAuth token storage in `user_google_oauth_tokens`.
- Refresh token support.
- Google Docs, Sheets, and Slides creation for subtasks.
- File sharing with relevant users.
- Google Calendar event creation, updates, deletion, and Google Meet link support.
- Gmail sending from the admin formal email composer.

## 15. Calendar, Meetings, and Bulletin Posts

Primary files:

- `calendar.php`
- `app/model/CalendarMeeting.php`
- `app/model/CalendarMeetingReminder.php`
- `app/model/Bulletin.php`
- `bulletin_post.php`
- `bulletin_delete.php`
- `send_meeting_reminders.php`

Calendar features:

- Task deadlines.
- Subtask deadlines.
- Workspace meetings.
- Admin view across the workspace.
- Employee view for assigned or targeted meetings and tasks.
- Meeting audiences: everyone, group, or task.
- Leaders can create meetings for led tasks.
- Google Meet links can be created through Google Calendar integration.

Meeting reminders:

- Reminders are queued in `calendar_meeting_email_reminders`.
- The reminder job sends one-hour-before email reminders.
- Reminder status tracks sent and error states.

Bulletin posts:

- Types include announcements, reminders, and alerts.
- Bulletins can target audiences.
- Calendar meetings can create, update, or delete related bulletin reminders.

## 16. Messages, Chat, Typing, and Formal Email

Primary page and models:

- `messages.php`
- `app/model/Message.php`
- `app/model/GroupMessage.php`
- `app/model/Typing.php`
- `app/model/ChatVisibility.php`

Important AJAX endpoints:

- `app/ajax/insert.php`
- `app/ajax/insertGroupMessage.php`
- `app/ajax/getMessage.php`
- `app/ajax/getGroupMessage.php`
- `app/ajax/getChatLists.php`
- `app/ajax/search.php`
- `app/ajax/deleteMessage.php`
- `app/ajax/hideChatThread.php`
- `app/ajax/getGroupDetails.php`
- `app/ajax/getGroupMembers.php`
- `app/ajax/getChatInfo.php`
- `app/ajax/setTypingStatus.php`
- `app/ajax/getTypingStatus.php`
- `app/ajax/active_users.php`
- `app/ajax/active_user_detail.php`
- `app/ajax/presence_heartbeat.php`
- `app/ajax/notification_preview.php`
- `app/ajax/sendGmailMessage.php`

Messaging features:

- Direct one-to-one chat.
- Group chat.
- Task chat groups.
- Attachments.
- Soft delete for messages where supported.
- Read receipts.
- Unread counters.
- Chat search.
- Typing indicators.
- Hidden threads that reappear when new messages arrive.
- `@name` and `@everyone` mention formatting in group messages.
- Active user and presence indicators.

Chat attachment rules:

- Allowed extensions include `pdf`, `doc`, `docx`, `xls`, `xlsx`, `png`, `jpg`, `jpeg`, `zip`, `json`, and `txt`.
- Maximum upload size is 50 MB.
- Files are saved under `uploads/`.

Formal email:

- Admins can connect Gmail.
- Admins can send formal email to workspace members from the Messages area.

## 17. Attendance, Pauses, DTR, and Screen Monitoring

Attendance files:

- `time_in.php`
- `time_out.php`
- `check_attendance.php`
- `attendance_heartbeat.php`
- `pause_attendance.php`
- `resume_attendance.php`
- `admin_clock_out.php`
- `inc/attendance_pause.php`
- `sse_my_attendance.php`
- `sse_user_status.php`
- `user_statuses.php`

Screenshot files:

- `screenshots.php`
- `save_screenshot.php`
- `get_screenshots_api.php`
- `inc/workspace_screenshot_retention.php`
- `inc/workspace_screenshot_interval.php`
- `extension/manifest.json`
- `extension/background.js`
- `extension/content.js`
- `extension/offscreen.js`
- `extension/capture.html`

Attendance features:

- Employee time in and time out.
- Attendance heartbeat.
- Admin clock-out for active employees.
- Clock-out remarks where the schema supports them.
- Attendance pause and resume.
- Pause duration is subtracted from total working time.
- Daily time records through reports.
- Server-sent/live status helpers.

Screen monitoring features:

- Chrome extension captures screenshots only while attendance is active.
- Capture interval is workspace configurable, defaulting to a random 20 to 30 minute window.
- Allowed interval range is 5 to 180 minutes.
- Screenshots are uploaded as base64 PNG data.
- Uploaded screenshot files are stored under `screenshots/`.
- Screenshot records are stored in the `screenshots` table.
- Admins can filter captures by user and date.
- Screenshot retention cleanup deletes old database rows and files.

The attendance endpoints use `Asia/Manila` as their runtime timezone.

## 18. Reports, DTR Records, Payroll, and Payslips

Report files:

- `reports.php`
- `dtr_records.php`
- `app/model/Report.php`

Payroll files:

- `payroll.php`
- `payslips.php`
- `my_payslips.php`
- `app/model/Payroll.php`
- `app/model/AttendanceAdjustment.php`
- `app/add-payroll-deduction.php`
- `app/delete-payroll-deduction.php`
- `app/update-payroll-government-settings.php`
- `app/update-attendance-deduction.php`
- `app/update-user-rate.php`

Reports features:

- Admin reports by date, month, group, and user.
- Employee self reports.
- Task status counts.
- Completed, pending, in-progress, overdue, and on-time metrics.
- Task rating averages.
- Assignee rating averages.
- Subtask score metrics.
- Attendance hours and days.
- Screenshot/capture counts.
- Daily time records.

DTR behavior:

- `dtr_records.php` is a focused wrapper around the reports page for DTR records.
- `dtr.php` is legacy and references older attendance columns that are not part of the current main schema.

Payroll features:

- Monthly payroll periods.
- Employee hourly rates.
- Attendance-based gross pay.
- Admin attendance hour deductions or adjustments.
- Government contribution settings.
- Custom deductions.
- Payslip generation and viewing.
- Admin payslip view.
- Employee self payslip view.

Default government deduction logic in code:

- SSS: 4.5 percent, capped at PHP 1,350.
- PhilHealth: 2 percent, capped at PHP 1,800.
- Pag-IBIG: 2 percent, capped at PHP 200.
- Withholding tax: progressive monthly bands.

Manual deduction types:

- Cash advance.
- Loan.
- Laptop.
- Smartphone.
- Uniform.
- Other.

Manual deduction amount modes:

- Fixed amount.
- Percent of gross pay.

Manual deduction periods:

- Once.
- Monthly.

## 19. Notifications and Email

Notification files:

- `notifications.php`
- `app/model/Notification.php`
- `app/notification.php`
- `app/notification-read.php`
- `app/notification-read-all.php`
- `app/notification-count.php`
- `app/ajax/notification_preview.php`

Notification features:

- User notifications.
- Read and unread state.
- Unread counts.
- Notification preview.
- Optional task references.
- Optional notified timestamps.
- Tenant scoping.

Email files:

- `app/send_email.php`
- `app/mail_config.php`
- `lib/PHPMailer/`

System email types:

- Email confirmation and account emails.
- Password reset emails.
- Workspace invite emails.
- Login verification code emails.
- Subscription reminder emails.
- Meeting reminder emails.
- Gmail-sent formal workspace emails.

## 20. Security and Validation

CSRF:

- Implemented in `inc/csrf.php`.
- Tokens are scoped by form/action key.
- Default expiry is 2 hours with a minimum allowed lifetime of 5 minutes.
- Many write endpoints verify a CSRF token before mutating state.

Common CSRF form keys include:

- `login_form`
- `signup_form`
- `chat_ajax_actions`
- `attendance_ajax_actions`
- `timeline_action`
- `calendar_meeting_form`
- `calendar_meeting_delete_form`
- `admin_review_task_form`
- `review_subtask_form`
- `update_subtask_submission_form`
- `submit_task_review_form`
- `resubmit_task_form`
- workspace settings keys
- notification read keys

Input and password rules:

- Shared input helpers live in `app/helpers/input.php`.
- Password policy lives in `app/helpers/password_policy.php`.
- Passwords must include uppercase, lowercase, number, symbol, and at least 8 characters.

Database safety:

- Database access uses PDO prepared statements in the main connection.
- Runtime schema helpers create or alter some tables and columns automatically.
- Tenant-aware functions apply organization filters where supported.

File upload safety:

- Uploads use explicit extension allowlists.
- Application-level upload limit is usually 50 MB.
- Web server/PHP request limit is configured to 100 MB.

Maintenance safety:

- CLI maintenance is allowed.
- Web maintenance is blocked unless environment flags allow it or the request is local in a non-production environment.
- Tenant maintenance requires an organization scope unless global maintenance is explicitly enabled.

## 21. Database Model

The checked-in `task_management_db_mysql.sql` provides the older base schema. The current application also creates or extends tables at runtime through helper functions and migration-style scripts.

Main base tables:

- `users`
- `tasks`
- `task_assignees`
- `subtasks`
- `groups`
- `group_members`
- `chats`
- `chat_attachments`
- `group_messages`
- `group_message_attachments`
- `group_message_reads`
- `notifications`
- `attendance`
- `screenshots`
- `password_resets`
- `leader_feedback`
- `payroll_deductions`
- `payroll_government_settings`
- `user_google_oauth_tokens`
- `calendar_meetings`
- `calendar_meeting_email_reminders`

Tenant and SaaS tables:

- `organizations`
- `organization_members`
- `subscriptions`
- `workspace_invites`

Runtime or feature tables:

- `user_login_verifications`
- `bulletin_posts`
- `attendance_pauses`
- `attendance_adjustments`
- `chat_typing_statuses`
- `chat_hidden_threads`
- `project_timeline_tasks`
- `project_timeline_phases`

Important runtime-added or optional columns:

- `organization_id` on tenant-owned tables.
- `users.hourly_rate`
- `users.last_active_at`
- Google user columns such as `google_sub`, `google_email_verified`, and `google_picture`.
- `attendance.last_heartbeat_at`
- `attendance.admin_clock_out_remark`
- soft-delete columns such as `deleted_at` on chat records where supported.
- `notifications.notified_at`
- subtask timeline and Google document columns such as `timeline_phase_id`, `reviewed_by`, `reviewed_at`, `google_doc_id`, and `google_doc_url`.

Deployment note:

- The base SQL dump is not a complete reflection of every current runtime table and column.
- A fresh deployment should import the base schema, then allow the application and migration helpers to create missing tables and columns, or generate a fresh schema dump from a fully migrated environment.
- The database user needs DDL permissions if runtime schema creation/alteration remains enabled.

## 22. Maintenance and Admin Utilities

Maintenance files:

- `maintenance_dashboard.php`
- `maintenance_guard.php`
- `reset_database.php`
- `send_subscription_reminders.php`
- `send_meeting_reminders.php`
- `run_cleanup_orphan_task_chats.php`
- `run_cleanup_legacy_duplicate_group_chats.php`
- `run_cleanup_screenshot_retention.php`
- `run_migration_workspace_invites.php`
- `run_migration_dashboard_performance_indexes.php`
- `verify_phase1_hardening.php`
- debug scripts such as `debug_schema.php` and `debug_group_type_constraint.php`

Maintenance behavior:

- Super admin can access the maintenance dashboard.
- Maintenance scripts are guarded by `maintenance_guard.php`.
- Tenant-safe maintenance requires `org_id` or CLI `--org-id`.
- Global maintenance requires `ALLOW_GLOBAL_MAINTENANCE=1` and an explicit global request.
- `verify_phase1_hardening.php` is read-only and reports missing tables, indexes, asset-cache rules, OPcache state, and log directory readiness.
- `reset_database.php` can clear tenant activity data while preserving users/settings, or perform a global reset if explicitly allowed.
- Screenshot retention cleanup can remove old screenshot files and rows.
- Subscription reminders and meeting reminders can be sent through maintenance/job scripts.

## 23. Deployment Notes

Local XAMPP setup:

1. Place the project in the web root, for example `c:\xampp\htdocs\task_management`.
2. Create a MySQL or MariaDB database.
3. Import `task_management_db_mysql.sql` as the base schema.
4. Copy `.env.example` to `.env.local` or `.env` and set database, app URL, mail, Google, and PayMongo values.
5. Open the app through Apache.
6. Sign up as a workspace owner or use an existing seeded admin account if one exists in the imported database.

Docker setup:

1. Build with the provided `Dockerfile`.
2. Provide database and app environment variables.
3. Mount or persist `uploads/`, `screenshots/`, and other runtime data as needed.
4. Expose Apache from the container.

Phase 1 production hardening checklist:

1. Deploy the latest code to the production document root.
2. Confirm PHP OPcache is enabled in hosting controls or `phpinfo()`; `.user.ini` and `docker/php.ini` include recommended settings, but some hosts ignore OPcache directives outside server-level config.
3. Confirm Apache honors `.htaccess` and that `mod_headers`/`mod_expires` rules do not produce a `500` response. The rules are guarded with `<IfModule>` to keep unsupported modules safe.
4. Run `php run_migration_dashboard_performance_indexes.php` once against the production database. If running through the browser, set `ALLOW_MAINTENANCE_SCRIPTS=1` temporarily and remove it after.
5. Run `php verify_phase1_hardening.php` and review any `FAIL` or `WARN` lines. `FAIL` means a critical table or index is missing; `WARN` usually means an optional/runtime feature table is not installed yet or OPcache cannot be confirmed from the current PHP context.
6. Clear any hosting/CDN/browser cache after deployment. `.htaccess` now gives CSS/JavaScript a conservative one-day cache because some legacy pages still use unversioned stylesheet URLs, while images, fonts, videos, archives, and similar static files receive long-lived cache headers.
7. Open DevTools Network on `index.php` and `messages.php`; verify CSS/JS/images return `200` quickly and dynamic PHP/AJAX requests are not repeatedly firing while the tab is hidden.
8. Watch `tmp/performance.log` after real usage. Entries appear only for slow monitored requests, defaulting to requests over `1500ms`. You can override with `PERFORMANCE_LOG_THRESHOLD_MS`.

SaaS scaling note:

- Laravel migration is intentionally deferred. The current priority is production hardening, query/index correctness, asset caching, and measurement. Revisit Laravel later as a maintainability migration, ideally feature by feature, after Phase 1 metrics show the remaining bottlenecks.

Docker details:

- The Docker image uses `php:8.2-apache`.
- It installs MySQL extensions including `pdo_mysql` and `mysqli`.
- It enables Apache `rewrite` and `headers`.
- It copies the project into the Apache document root.
- It creates runtime folders for uploads, screenshots, temp, and data.

Important compatibility note:

- `composer.json` currently lists `ext-pdo_pgsql`, but the application and Docker image are MySQL-oriented. This should be reviewed if Composer dependency validation is used in deployment.

## 24. Current Known Legacy Areas and Gaps

These are not necessarily bugs, but they are important when maintaining the system:

- `task_management_db_mysql.sql` is older than the runtime schema and does not contain every current table/column.
- Several helper/model files perform runtime DDL with `CREATE TABLE IF NOT EXISTS` or `ALTER TABLE`. This improves forgiving installs but requires DDL privileges and can hide schema drift.
- `dtr.php` is a legacy DTR page and references old attendance columns. Current DTR access is through `dtr_records.php` and `reports.php`.
- `edit-task-employee.php` still contains legacy manual subtask UI, while `app/add-subtask.php` now disables manual subtask creation because subtasks are generated from timeline phases.
- `inc/nav.php` is older navigation code; `inc/new_sidebar.php` is the current sidebar.
- Direct add-user pages remain for compatibility, but invitations are the current intended onboarding path.
- `payslips.php`, `my_payslips.php`, and `dtr_records.php` are wrappers around larger pages.
- The bundled PHPMailer library is managed directly in `lib/PHPMailer` instead of through Composer autoloading.
- Timezone usage should be reviewed if the app must support multiple regions. Attendance endpoints currently use `Asia/Manila`, while the development environment may use a different timezone.

## 25. High-Level Feature Checklist

- Multi-tenant workspaces.
- Trial and paid signup.
- PayMongo billing flow.
- Seat limits.
- Workspace settings.
- Workspace themes.
- Screenshot interval and retention settings.
- Admin and employee roles.
- Organization owner/admin/member roles.
- Invitations and bulk invitations.
- Google signup/sign-in/invite acceptance.
- User profiles and profile images.
- User hourly rates.
- Groups with leaders and members.
- Task creation and assignment.
- Automatic task chat groups.
- Timeline planning.
- Timeline-generated subtasks.
- Subtask review and scoring.
- Task final review and rating.
- Google Docs, Sheets, and Slides for subtasks.
- Calendar task/subtask/meeting view.
- Google Calendar and Meet integration.
- Bulletin announcements and reminders.
- Meeting reminder emails.
- Direct chat.
- Group chat.
- Attachments.
- Typing indicators.
- Read receipts.
- Formal Gmail email composer.
- Employee attendance clock in/out.
- Attendance pause/resume.
- Admin clock-out.
- Browser extension screenshot monitoring.
- Screenshot capture dashboard.
- Reports and DTR records.
- Payroll calculation.
- Government deduction settings.
- Custom payroll deductions.
- Payslips.
- Notifications.
- Password reset.
- Login verification by email.
- Super admin maintenance dashboard.
- Tenant-safe maintenance scripts.

## 26. Documentation Policy

This file replaces the older scattered project Markdown files. When adding or changing system behavior, update this README instead of creating another root-level status, update, quickstart, or guide file.

The only Markdown files expected to remain outside this README are third-party/vendor documents, such as the PHPMailer README and security file in `lib/PHPMailer/`.
