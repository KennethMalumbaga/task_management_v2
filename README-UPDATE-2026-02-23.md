# TaskFlow Update Log - 2026-02-23

## Summary
This update focused on UI theme consistency and password-policy hardening, while preserving existing workspace signup/login behavior.

## Completed Changes

### 1) Pricing card selection highlight (Landing)
- Clicking a pricing card now moves the active violet highlight to that selected card.
- Active card styles (`popular` state, CTA emphasis, and popular tag placement) now follow selection.
- Files:
  - `landing.php`
  - `css/landing.css`

### 2) Global color palette alignment
- Standardized primary UI accents across app pages to the requested brand colors:
  - `#6C3CE1` (primary)
  - `#8B5CF6` (secondary)
- Applied across shared CSS and page-level inline styles where old legacy indigo values were still hardcoded.
- Files include:
  - `css/style.css`
  - `css/dashboard.css`
  - `css/auth.css`
  - `css/chat.css`
  - `css/task_redesign.css`
  - plus multiple PHP pages with inline style accents.

### 3) Auth left panel visual adjustment
- Removed the auth-side gradient and made it a solid brand color to match the rest of the system.
- File:
  - `css/auth.css`

### 4) Time Tracker button styling
- `Clock In` button updated to purple brand style.
- `Clock Out` remains orange (unchanged warning color behavior).
- File:
  - `css/dashboard.css`

### 5) Password policy enforcement (final agreed behavior)
- Kept create-workspace flow as before:
  - Inputs: Workspace Name, Full Name, Email.
  - System sends a temporary password to email.
  - User changes password later in profile/reset flow.
- Create-workspace email remains server-validated as a real email format.
- Added shared password policy helper:
  - Minimum 8 characters
  - At least 1 uppercase, 1 lowercase, 1 number, and 1 symbol
- Enforced in password-setting/changing flows:
  - Profile password change
  - Password reset
  - Invite-based password set
- Login behavior remains standard credential check (no extra policy gate during login).
- Files:
  - `app/helpers/password_policy.php` (new)
  - `app/update-profile.php`
  - `edit_profile.php`
  - `app/do-reset-password.php`
  - `reset-password.php`
  - `app/accept-invite.php`
  - `join-workspace.php`
  - `signup.php`
  - `app/signup.php`
  - `login.php`
  - `app/login.php`

## Validation
- PHP syntax checks (`php -l`) passed for all updated auth/profile/password-related files.

## Notes
- Existing unrelated local changes in the repository were not reverted.
- Browser hard refresh (`Ctrl+F5`) may be needed to clear cached CSS after theme updates.
