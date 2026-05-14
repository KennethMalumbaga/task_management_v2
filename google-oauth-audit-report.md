# Google OAuth / Google Sign-In Audit Report

Audit date: May 14, 2026  
Project: TaskFlow (`task_management`)

## Executive Summary

The codebase is not using only basic Google Sign-In. It uses Google Identity Services for login/signup/invite flows, and it also contains separate OAuth authorization-code flows for Google Drive/Workspace files, Google Calendar/Meet, and Gmail sending.

The most likely reason users still see **"Google hasn't verified this app"** is that the same Google Cloud OAuth app/client is requesting Google API scopes beyond:

- `openid`
- `email`
- `profile`

The code requests these additional scopes:

- `https://www.googleapis.com/auth/drive.file`
- `https://www.googleapis.com/auth/calendar.events.owned`
- `https://www.googleapis.com/auth/gmail.send`

The strongest verification trigger is `https://www.googleapis.com/auth/gmail.send`, which Google classifies as a sensitive scope. Calendar user-data scopes can also require app verification. Branding/domain verification alone is not enough if the app requests sensitive scopes that are not declared and approved in the OAuth consent screen.

## 1. OAuth Scope Audit

### Scopes Found

| Scope | File | Purpose | Risk |
| --- | --- | --- | --- |
| `openid` | `app/helpers/google_workspace.php`, `app/helpers/google_calendar.php`, `app/helpers/google_gmail.php` | Identity | Basic/non-sensitive |
| `email` | `app/helpers/google_workspace.php`, `app/helpers/google_calendar.php`, `app/helpers/google_gmail.php` | Email claim | Basic/non-sensitive |
| `profile` | `app/helpers/google_workspace.php`, `app/helpers/google_calendar.php`, `app/helpers/google_gmail.php` | Profile claim | Basic/non-sensitive |
| `https://www.googleapis.com/auth/drive.file` | `app/helpers/google_workspace.php` | Create/manage app-created Drive files | Lower risk/non-sensitive per Google's minimum-scope guidance, but must be declared if requested |
| `https://www.googleapis.com/auth/calendar.events.owned` | `app/helpers/google_calendar.php` | Create/update/delete events on calendars the user owns | Google user-data scope; likely requires verification for public apps |
| `https://www.googleapis.com/auth/gmail.send` | `app/helpers/google_gmail.php` | Send Gmail messages | Sensitive scope; likely verification trigger |

### Sensitive / Restricted Scope Findings

Detected:

- Gmail scope: `https://www.googleapis.com/auth/gmail.send`
- Calendar scope: `https://www.googleapis.com/auth/calendar.events.owned`
- Drive scope: `https://www.googleapis.com/auth/drive.file`

Not detected:

- Contacts scopes
- YouTube scopes
- Google Cloud scopes such as `cloud-platform`
- Admin SDK scopes
- Broad Gmail scopes such as `https://mail.google.com/`, `gmail.modify`, `gmail.readonly`, `gmail.compose`
- Broad Drive scopes such as `drive`, `drive.readonly`

### Scope That Most Likely Triggers the Warning

Primary likely trigger:

```text
https://www.googleapis.com/auth/gmail.send
```

Secondary likely trigger:

```text
https://www.googleapis.com/auth/calendar.events.owned
```

Google's unverified-app warning appears when code requests sensitive or restricted scopes that are not configured, selected, or fully approved in the OAuth consent screen. It can also appear when the code-requested scopes differ from the scopes submitted for verification.

## 2. OAuth Client Configuration Audit

### Production Domain Requirement

Production should use only:

```text
https://taskflow.mensaheko.com
```

### Current Config Findings

The environment files contain these APP_URL values:

```text
.env        APP_URL=https://taskflow.mensaheko.com
.env.local  APP_URL=http://localhost/task_management
.env.example contains localhost and Railway examples
```

Important finding:

`app/mail_config.php` loads `.env.local` before `.env`. If `.env.local` exists in production, it can override production URL generation with:

```text
http://localhost/task_management
```

That can produce wrong OAuth redirect URIs.

### Required Production OAuth Client Settings

Authorized JavaScript origins:

```text
https://taskflow.mensaheko.com
```

Authorized redirect URIs:

```text
https://taskflow.mensaheko.com/app/google-workspace-callback.php
https://taskflow.mensaheko.com/app/google-calendar-callback.php
https://taskflow.mensaheko.com/app/google-gmail-callback.php
```

Remove from the production OAuth client:

```text
http://localhost
http://localhost/task_management
https://your-app.up.railway.app
any old Hostinger/Railway/Vercel/staging domains
any unused subdomains
```

## 3. Firebase / Google SDK Audit

No Firebase Auth usage was found.

Not found:

- `signInWithPopup`
- `signInWithRedirect`
- `GoogleAuthProvider`
- `addScope()`
- Firebase config files
- Passport config
- Laravel Socialite config
- Node.js OAuth config

Google Identity Services is used directly:

- `login.php`
- `signup.php`
- `join-workspace.php`

These pages call:

```js
google.accounts.id.initialize(...)
google.accounts.id.renderButton(...)
```

The basic Sign-In buttons submit a Google ID token credential to the backend. They do not explicitly request Gmail, Calendar, or Drive scopes.

## 4. OAuth Flow Audit

### Basic Google Sign-In / Signup / Invite

Files:

- `login.php`
- `signup.php`
- `join-workspace.php`
- `app/google-login.php`
- `app/google-signup-init.php`
- `app/google-invite-init.php`
- `app/helpers/google_auth.php`

Flow:

1. Frontend renders Google Identity Services button.
2. Google returns an ID token credential.
3. Backend verifies token signature, issuer, audience, expiry, subject, and email.
4. Backend links or finds the local user.
5. Session is regenerated after successful login.

Security notes:

- GSI CSRF token is checked in `app/google-login.php`.
- App CSRF token is also checked as fallback.
- ID token audience is checked against `GOOGLE_LOGIN_CLIENT_ID`.
- Token issuer and expiry are checked.

### Google Workspace / Drive OAuth Flow

Files:

- `app/helpers/google_workspace.php`
- `app/google-subtask-doc.php`
- `app/google-workspace-callback.php`
- `app/helpers/subtask_google_docs.php`
- `app/model/GoogleWorkspace.php`

Scope:

```text
openid email profile https://www.googleapis.com/auth/drive.file
```

OAuth parameters:

```text
response_type=code
access_type=offline
include_granted_scopes=true
prompt=select_account or consent select_account
```

### Google Calendar / Meet OAuth Flow

Files:

- `app/helpers/google_calendar.php`
- `app/google-calendar-meeting.php`
- `app/google-calendar-callback.php`

Scope:

```text
openid email profile https://www.googleapis.com/auth/calendar.events.owned
```

OAuth parameters:

```text
response_type=code
access_type=offline
include_granted_scopes=true
prompt=select_account or consent select_account
```

### Gmail OAuth Flow

Files:

- `app/helpers/google_gmail.php`
- `app/google-gmail-init.php`
- `app/google-gmail-callback.php`
- `app/ajax/sendGmailMessage.php`

Scope:

```text
openid email profile https://www.googleapis.com/auth/gmail.send
```

OAuth parameters:

```text
response_type=code
access_type=offline
include_granted_scopes=true
prompt=select_account or consent select_account
```

## 5. Environment & Production Audit

### Findings

- `.env` contains production `APP_URL`.
- `.env.local` contains local `APP_URL`.
- `.env.local` is loaded before `.env`.
- If `.env.local` is present on production, production may generate localhost OAuth callback URLs.
- Google client ID/secret are present in local env files. Do not commit or share them.

### Required Production Environment

Production should contain:

```text
APP_URL=https://taskflow.mensaheko.com
GOOGLE_LOGIN_CLIENT_ID=<production-login-web-client-id>.apps.googleusercontent.com
GOOGLE_LOGIN_CLIENT_SECRET=<production-login-client-secret>
GOOGLE_WORKSPACE_CLIENT_ID=<production-workspace-web-client-id>.apps.googleusercontent.com
GOOGLE_WORKSPACE_CLIENT_SECRET=<production-workspace-client-secret>
```

Production should not contain:

```text
APP_URL=http://localhost/task_management
Railway URLs
Vercel URLs
staging URLs
old Hostinger preview URLs
old Google client IDs/secrets
```

## 6. Security & Best Practice Audit

### Good Controls Found

- CSRF helper exists in `inc/csrf.php`.
- Google ID tokens are verified server-side.
- GSI CSRF token is checked.
- OAuth state is generated and validated for Workspace, Calendar, and Gmail callbacks.
- `session_regenerate_id(true)` is used after Google login.

### Risks Found

1. Refresh tokens are stored as plain text.

   Table:

   ```text
   user_google_oauth_tokens.refresh_token
   ```

   Recommendation: encrypt refresh tokens at rest or move them to a protected secret store.

2. `.env` and `.env.local` contain live-looking secrets.

   Recommendation: rotate secrets if the repo has ever been shared, pushed, or exposed.

3. `.htaccess` does not enforce HTTPS or HSTS.

   Recommendation: enforce HTTPS on production and add HSTS after confirming HTTPS is stable.

4. Same OAuth client/project appears to be used for Sign-In and sensitive API integrations.

   Recommendation: separate basic Sign-In from Gmail/Calendar/Drive authorization flows.

## 7. Exact Reason Google May Still Show The Warning

Most likely reason:

The OAuth consent screen/domain branding may be verified, but the app still requests sensitive Google API scopes through OAuth authorization-code flows:

```text
https://www.googleapis.com/auth/gmail.send
https://www.googleapis.com/auth/calendar.events.owned
```

If these scopes are not declared, justified, submitted, and approved in Google Cloud Console, users can still see:

```text
Google hasn't verified this app
```

Another possible cause:

The code-requested scopes differ from what was selected or verified in the OAuth consent screen. Google specifically warns that mismatched scopes can trigger the unverified-app screen.

Environment-related cause:

If production loads `.env.local`, OAuth redirect URLs may be generated with:

```text
http://localhost/task_management
```

instead of:

```text
https://taskflow.mensaheko.com
```

This can cause OAuth client mismatch or wrong-environment behavior.

## 8. Step-by-Step Fixes

1. Decide whether production should support Gmail, Calendar, and Drive integrations now.

2. If production only needs Google login/signup:

   - Disable or hide Gmail/Calendar/Drive connect flows.
   - Use only Google Identity Services Sign-In.
   - Keep only basic identity scopes on the production consent screen.

3. If production needs Gmail/Calendar/Drive:

   - Add all requested scopes to OAuth consent screen Data Access.
   - Submit verification for sensitive/user-data scopes.
   - Provide justification and demo video for each scope.
   - Ensure Privacy Policy explains Google data access, use, storage, and sharing.

4. Clean production environment:

   - Remove `.env.local` from production.
   - Ensure production `APP_URL` is exactly `https://taskflow.mensaheko.com`.

5. Clean Google Cloud Console OAuth client:

   Authorized JavaScript origins:

   ```text
   https://taskflow.mensaheko.com
   ```

   Authorized redirect URIs:

   ```text
   https://taskflow.mensaheko.com/app/google-workspace-callback.php
   https://taskflow.mensaheko.com/app/google-calendar-callback.php
   https://taskflow.mensaheko.com/app/google-gmail-callback.php
   ```

6. Remove stale OAuth URLs:

   ```text
   http://localhost
   http://localhost/task_management
   Railway URLs
   Vercel URLs
   staging URLs
   old Hostinger preview URLs
   unused subdomains
   ```

7. Rotate exposed secrets:

   - Google client secret
   - Database password
   - Mail password
   - PayMongo key

8. Encrypt stored Google refresh tokens.

9. Add HTTPS enforcement and HSTS in production.

## 9. Recommended Production-Ready OAuth Setup

Best practice is to separate basic authentication from Google API access.

### OAuth Client A: Basic Sign-In

Purpose:

```text
Login, signup, invite acceptance
```

Scopes:

```text
openid
email
profile
```

JavaScript origin:

```text
https://taskflow.mensaheko.com
```

No Gmail, Calendar, or Drive scopes.

### OAuth Client B: Google Workspace Integrations

Purpose:

```text
Google Docs/Sheets/Slides creation
Google Calendar/Meet event creation
Gmail sending
```

Scopes:

```text
openid
email
profile
https://www.googleapis.com/auth/drive.file
https://www.googleapis.com/auth/calendar.events.owned
https://www.googleapis.com/auth/gmail.send
```

Redirect URIs:

```text
https://taskflow.mensaheko.com/app/google-workspace-callback.php
https://taskflow.mensaheko.com/app/google-calendar-callback.php
https://taskflow.mensaheko.com/app/google-gmail-callback.php
```

This client/project should go through Google OAuth verification for the sensitive scopes.

## 10. References

- Google API Console Help: Unverified apps  
  https://support.google.com/googleapi/answer/7454865

- Google Developers: Sensitive scope verification  
  https://developers.google.com/identity/protocols/oauth2/production-readiness/sensitive-scope-verification

- Google Cloud Help: Requesting minimum scopes  
  https://support.google.com/cloud/answer/13807380

- Google Calendar API scopes  
  https://developers.google.com/workspace/calendar/api/auth
