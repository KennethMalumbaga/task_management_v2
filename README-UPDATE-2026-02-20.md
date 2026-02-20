# TaskFlow Update Log - February 20, 2026

This document summarizes the updates completed today.

## 1. Shared Top Header Bar Added Across Dashboard Pages

- Added a shared white top header bar with dynamic page title.
- Kept mobile behavior intact by hiding the desktop topbar on small screens.
- Added top-right utility area in the header (notification bell + profile menu).

Files:
- `inc/new_sidebar.php`
- `css/dashboard.css`

---

## 2. Profile and Logout Moved to Profile Circle Dropdown

- Removed `Profile` and `Logout` from the left sidebar.
- Added both actions inside the top-right profile dropdown.
- Kept existing logout confirmation modal behavior.

File:
- `inc/new_sidebar.php`

---

## 3. Notification Dropdown Added in Header

- Notification bell now opens a dropdown preview list.
- Added unread badge and unread styling in dropdown items.
- Clicking a notification goes through read handler and then redirects.

Files:
- `inc/new_sidebar.php`
- `app/notification-read.php`

---

## 4. Notification Read State and Badge Fix

- Fixed unread count logic to support mixed DB boolean styles (`0/1`, `false/true`, `f/t`).
- Added reusable unread helper for consistent UI rendering.
- Ensured read action updates unread count correctly.

Files:
- `app/model/Notification.php`
- `app/helpers/notification.php`
- `app/notification.php`
- `notifications.php`

---

## 5. "Read all" Action Implemented

- Added top dropdown action `Read all` (header area) that marks all notifications as read.
- Added notifications page `Read all` button.
- After read-all, unread badge/alert should clear.
- Kept dropdown footer text as `View all notifications`.

Files:
- `app/notification-read-all.php` (new)
- `inc/new_sidebar.php`
- `notifications.php`

---

## 6. Duplicate In-Page Titles Removed (Using Header Title Instead)

- Removed repeated page titles where the same title is already shown in the new top header.
- Kept functional controls (filters, actions, back links) intact.

Files:
- `messages.php`
- `tasks.php`
- `my_task.php`
- `calendar.php`
- `notifications.php`
- `screenshots.php`
- `user.php`
- `invite-user.php`
- `groups.php`
- `workspace-billing.php`
- `edit-task-employee.php`
- `create_task.php`

---

## 7. Users Page Header Actions Cleaned Up

- Removed `Invite User` and `Employees` controls from Users page header as requested.

File:
- `user.php`

---

## 8. Header Size/Title Tuning

- Reduced top header height and title font size for a slimmer appearance.

File:
- `css/dashboard.css`

---

## Notes

- Syntax checks were run for updated PHP files using `php -l`.
- Existing unrelated project changes were not reverted.
