# TaskFlow Update Log - 2026-03-10

## Summary
This update focused on timeline admin access, automatic subtask creation for timeline phases, and a more interactive phase editor (drag + resize).

## Completed Changes

### 1) Admin timeline access and editing
- Admin users can access and edit the timeline views.
- Timeline access checks were updated to permit admin role usage.
- Files:
  - `timeline.php`
  - `app/timeline/_common.php`
  - `inc/new_sidebar.php`
  - `index.php`
  - `my_task.php`
  - `inc/pages/my_task_scripts.php`

### 2) Timeline phases auto-create subtasks
- Timeline phases now create/update corresponding subtasks under the parent task.
- Added/updated save endpoints for phases and tasks to persist timeline changes.
- Files:
  - `app/model/Timeline.php`
  - `app/model/Subtask.php`
  - `app/timeline/save_phase.php`
  - `app/timeline/save_task.php`
  - `app/add-subtask.php`
  - `app/review-subtask.php`

### 3) Timeline phase editing UX (drag + resize)
- Timeline phases are now draggable and extendable for easier schedule adjustments.
- Added/updated styles for phase handles and interactions.
- Files:
  - `app/timeline/timeline-page.js`
  - `css/timeline-page.css`
  - `timeline.php`

### 4) Supporting updates
- Small supporting adjustments tied to the timeline work.
- Files:
  - `capture.html`
  - `screenshot_debug.log`

## Validation
- Not run (not requested).
