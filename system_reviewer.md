## Arts Gym System – Comprehensive Reviewer

This document is designed to be **printed or exported as PDF** for review and capstone defense preparation.

You can print to PDF by opening this file in a Markdown viewer (or browser) and using **Print → Save as PDF**. Each major part is grouped to fit well on printed pages.

---

## Part 1 – Files, Purposes, and Key Functions

### 1.1 Root / Global Files

- **`connection.php`**  
  - **Purpose**: Central database connection (Supabase Postgres) and global bootstrap.  
  - **Key features**:
    - Builds PDO connection with `sslmode=require`, sets timezone to Asia/Manila.
    - Includes global security (`includes/security.php`), Supabase config, and auto-cleanup.
    - Exposes a generic `logActivity` helper used throughout the app.
  - **Important functions / logic**:
    - **PDO bootstrap** – initializes `$pdo` with exceptions enabled, sets DB session timezone.
    - **`includes/security.php` include** – applies CSP, secure headers, CSRF utilities, input limits.
    - **`includes/auto_cleanup.php::runAutoCleanup($pdo)`** – runs daily housekeeping on logs, attendance, walk-ins, lockouts, and unverified members.
    - **`includes/supabase_config.php` include** – loads Supabase URL and keys.
    - **`logActivity($pdo, $userId, $role, $action, $details)`** – inserts into `activity_log`.

- **`auth.php`**  
  - **Purpose**: Shared authentication guard for protected pages.  
  - **Key features**:
    - Redirects unauthenticated users to `login.php`.
    - Enforces that members must have active membership before accessing protected areas.
  - **Important logic**:
    - Checks `$_SESSION['user_id']` and `$_SESSION['role']`.  
    - For `role='member'`: queries latest `sales.expires_at`; if expired or missing, logs out and redirects with a restricted notice.

- **`includes/security.php`**  
  - **Purpose**: Global security layer applied on most requests.  
  - **Key features**:
    - Sets strong HTTP headers (CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy).
    - Configures secure sessions (HttpOnly, SameSite=Lax, Secure when HTTPS).
    - Provides CSRF token management (hidden fields + AJAX header).
    - Provides global input truncation and file upload size limit.
  - **Important functions**:
    - **Secure headers block** – sends CSP and other protective headers.  
    - **Session configuration** – `session_set_cookie_params` with secure options, then `session_start()`.  
    - **`csrf_field()`** – returns hidden input with CSRF token for forms.  
    - **`csrf_script()`** – JS snippet that configures jQuery to send `X-CSRF-TOKEN`.  
    - **`validate_csrf()`** – validates token for POST requests and rejects invalid submissions.  
    - **`e($string)`** – wrapper around `htmlspecialchars` for safe output.  
    - **Input / upload limits** – truncates long input strings and rejects files > 5MB.

- **`includes/auto_cleanup.php`**  
  - **Purpose**: Automated pruning of old or stale data.  
  - **Important function**:
    - **`runAutoCleanup(PDO $pdo)`**:
      - Checks `settings('last_auto_cleanup')` to run at most once per day.
      - Deletes:
        - `activity_log` older than 90 days.
        - `attendance` older than 90 days (by `attendance_date`).
        - `walk_ins` older than 30 days.
        - `ip_login_attempts` rows with `lockout_until < CURRENT_TIMESTAMP`.
        - Unverified member `users` older than 7 days (`role='member'` and `is_verified` false/null).
      - Updates `settings` with the latest run timestamp.

- **`includes/status_sync.php`**  
  - **Purpose**: Keep `users.status` in sync with membership payments.  
  - **Important functions**:
    - **`syncUserStatus($pdo, $user_id)`** – looks up latest `sales.expires_at`; sets `users.status = 'active'` or `'inactive'` accordingly and returns new status.  
    - **`bulkSyncMembers($pdo)`** – recalculates status for all members in batch.

- **Other important root files** (purpose only):
  - **`includes/supabase_config.php`** – defines Supabase URL, anon key, and service key for backend and JS bridge.  
  - **`includes/log_activity.php`** – exposes `log_activity()` helper for structured entries in `activity_log`.  
  - **`includes/global_qr_scanner.php`** – legacy DOM-based QR scanner (mostly replaced by `global_attendance.js`).  
  - **`index.php`** – public landing page; includes `global_attendance.js` so scans on the landing can still record attendance.  
  - **`login.php`** – combined login API and UI; implements password login, lockout, membership gating, and optional Supabase Auth login.  
  - **`register.php`** – member registration with email verification and QR code creation.  
  - **`verify_email.php` / `resend_verification.php`** – email verification and resending logic.  
  - **`forgot_password.php` / `reset_password.php`** – password reset request and token-based reset.  
  - **`request_email_change.php` / `approve_email_change.php`** – two-stage email change flow via old and new addresses.  
  - **`logout.php`** – logs out and records a logout activity.  
  - **`setup_ip_lockout.php`** – creates `ip_login_attempts` table.  
  - **`global_clock.php`** – shared live date/time component.  
  - **`generate_qr.php`** – server-side QR PNG generator using `phpqrcode`.

---

### 1.2 Admin Area (`admin/`)

- **`_sidebar.php`**  
  - **Purpose**: Shared admin navigation and layout.  
  - **Key points**: loads shared layout CSS, embeds `global_attendance.js`, and defines `toggleSidebar()` for collapsible UI.

- **`dashboard.php`**  
  - **Purpose**: Admin overview of KPIs and charts.  
  - **Key functions / logic**:
    - Queries counts for active members, expiring soon, today’s attendance, walk-ins, and revenue.  
    - Aggregates monthly `sales.amount` and new members for Chart.js graphs.

- **`manage_users.php`**  
  - **Purpose**: Admin management of members and staff.  
  - **Key functions / logic**:
    - Uses `bulkSyncMembers($pdo)` to ensure `users.status` matches `sales.expires_at`.  
    - Lists members with status and expiry (via latest `sales` join).  
    - Lists staff accounts.  
    - Uses AJAX to `admin_user_actions.php` for user create/update/delete.  
    - Registers payments via AJAX to `register_payment.php`.  
    - Shows QR codes via `generate_qr.php`.  
    - Integrates Supabase `search_users` RPC for fast name/email search.

- **`admin_user_actions.php`**  
  - **Purpose**: AJAX backend for user CRUD.  
  - **Key functions**:
    - **Create** – validates data, ensures unique email, applies password policy, sets QR prefix, inserts into `users`.  
    - **Update** – updates name/email/status with uniqueness check.  
    - **Delete** – in a transaction, deletes related `sales` and `attendance` before deleting `users`.

- **`register_payment.php`**  
  - **Purpose**: Register member payments (admin side).  
  - **Key logic**:
    - Validates CSRF and role.  
    - Checks last `sales.expires_at` to avoid overlapping memberships.  
    - Inserts into `sales` with new `expires_at`, updates `users.status`, and logs activity.

- **`add_walkin.php` / `daily.php`**  
  - **Purpose**: Handling daily walk-ins.  
  - **Key logic** (combined):  
    - Insert into `walk_ins` (visitor name, amount, checked_in_by).  
    - Insert into `sales` with `user_id = NULL`.  
    - Insert into `attendance` with `visitor_name`.  
    - `daily.php` also reads/updates `settings('daily_walkin_rate')`.

- **`attendance_endpoint.php`**  
  - **Purpose**: Central attendance backend for QR scans.  
  - **Key logic**:
    - Accepts JSON `{qr_code}` from `global_attendance.js` and scanner pages.  
    - Ensures the session is admin or staff.  
    - Finds member via `users.qr_code`, verifies `role='member'` and `status='active'` (using `syncUserStatus`).  
    - Checks if member already has attendance today; if not, inserts new `attendance` record.  
    - Writes simple logs to `attendance_logs.txt`.

- **`attendance.php`**  
  - **Purpose**: Admin view of attendance logs.  
  - **Key logic**:
    - Filters by `today` or `last 7 days`.  
    - Paginates and joins `attendance` with `users` to display user names, roles, and visitor walk-ins.

- **`attendance_scan.php`**  
  - **Purpose**: Admin scanning (camera + HID).  
  - **Key logic**:
    - Uses `html5-qrcode` to scan QR codes via camera.  
    - On scan, calls backend `attendance_endpoint.php` and displays success/warning/error messages.

- **`attendance_updates.php`**  
  - **Purpose**: Fetch attendance rows newer than a given ID.  
  - **Use**: Intended for live update UIs without full reload.

- **`manage_exercises.php` / `upload_image.php`**  
  - **Purpose**: Manage exercise library and upload exercise images.  
  - **Key logic**:
    - Reads `muscle_groups`, `muscles`, and `exercises` tables.  
    - Inserts/updates/deletes exercises.  
    - `upload_image.php` validates MIME/type and moves uploaded files.

- **`reports.php`**  
  - **Purpose**: Admin reports and export.  
  - **Key logic**:
    - Aggregates members, attendance, and sales statistics.  
    - Shows latest sales.  
    - Uses `html2canvas` + `jspdf` to export report section to PDF.

- **`activity.php`**  
  - **Purpose**: Audit log viewer.  
  - **Key logic**: loads `activity_log` joined with `users`, groups actions into session, payment/registration, and profile tabs.

- **`settings.php`**  
  - **Purpose**: Backup/restore database.  
  - **Key functions**:
    - **Export** – dumps all public tables to SQL for download.  
    - **Import** – executes uploaded `.sql` against DB (used carefully).

- **`profile.php`** (admin)  
  - **Purpose**: Admin profile management.  
  - **Key logic**: update full name and password (with policy), trigger logout on password change, start email-change flow.

---

### 1.3 Staff Area (`staff/`)

- **`_sidebar.php`** – staff navigation.  
- **`dashboard.php`** – staff KPIs (active members, expiring soon, today’s attendance, walk-ins).  
- **`members.php`** – staff member list; uses `admin_user_actions.php` for create and `staff_register_payment.php` for payments; integrates Supabase search.  
- **`staff_register_payment.php`** – staff payment registration; mirrors admin version with staff-focused messages.  
- **`attendance_register.php`** – staff attendance log view by `attendance_date` (today / last 7 days).  
- **`daily.php`** – staff walk-in recording (reads `settings('daily_walkin_rate')`; inserts into `walk_ins`, `sales`, `attendance`).  
- **`profile.php`** – staff profile (same pattern as admin/member, with activity logging).

---

### 1.4 Member Area (`member/`)

- **`_sidebar.php`** – member navigation; protects routes by checking `role='member'`.  
- **`dashboard.php`**  
  - Shows membership status and expiry (via join with last `sales` row).  
  - Shows attendance consistency map and workout statistics.  
  - Displays QR pass and latest receipt, with client-side download via `html2canvas`.
- **`profile.php`** – member profile (update name/password, initiate email change, log actions).  
- **`exercises.php`** – browse exercises through `muscle_groups`, `muscles`, `exercises`.  
- **`my_workouts.php`** – workout planner and history using `workout_plans` and `workout_plan_exercises`.  
- **`calorie_calculator.php`** – front-end-only calorie/BMR calculator.  
- **`payment_history.php`** – lists member’s `sales` records and renders receipt modals.  
- **`qr_code.php`** – older page rendering member QR via the `qrious` JavaScript library.

---

### 1.5 Shared JS and Supabase Files

- **`assets/js/global_attendance.js`**  
  - **Purpose**: Global HID scanner client.  
  - **Key logic**:
    - Buffers keyboard input from scanners, ignoring active inputs/textareas.  
    - On Enter, sends JSON `{qr_code}` to `/Gym1/admin/attendance_endpoint.php`.  
    - Shows toast messages for success, warnings, errors, or not-logged-in states.  
    - Stores pending QR in `sessionStorage` to replay after login.

- **`assets/js/global_scanner.js`**  
  - **Purpose**: Legacy HID scanner logic; stores `pending_qr` and redirects to staff attendance.

- **`assets/js/supabase-config.php`**  
  - **Purpose**: JS bridge to Supabase.  
  - **Key logic**:
    - Echoes `SUPABASE_URL` and `SUPABASE_ANON_KEY` from PHP config.  
    - Initializes `window.supabaseClient` if Supabase JS SDK is loaded.

- **`supabase_setup.sql`**  
  - **Purpose**: Supabase migrations and policies.  
  - **Key content**:
    - Enables RLS on `users`, `attendance`, `sales`.  
    - Defines `users.fts` tsvector + GIN index and `search_users(query text)` function.  
    - Defines `ip_login_attempts` DDL.

- **`supabase_sync.php`**  
  - **Purpose**: Sync local `users` to Supabase Auth.  
  - **Key logic**:
    - `syncSupabaseMetadata($pdo, $email, $role)` ensures a Supabase Auth user exists for each local user with correct `app_metadata.role`.  
    - Optional `?bulk=1` mode to sync all users.

---

## Part 2 – Security Overview (Frontend, Backend, Database)

### 2.1 Backend Security

- **Session hardening** (`includes/security.php`)  
  - Uses `session_set_cookie_params` with `httponly`, `samesite=Lax`, and `secure` (when HTTPS).  
  - Reduces risk of session hijacking and CSRF.

- **Security headers and CSP** (`includes/security.php`)  
  - Sets: Content-Security-Policy, X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, Referrer-Policy.  
  - Restricts which domains can serve scripts, styles, images, fonts, and XHR.

- **CSRF protection** (global + per-endpoint)  
  - Hidden field from `csrf_field()` in forms.  
  - `csrf_script()` injects token as `X-CSRF-TOKEN` for jQuery AJAX requests.  
  - `validate_csrf()` called in sensitive POST handlers (`login.php`, `*_register_payment.php`, daily walk-ins, user CRUD).

- **Password hashing and strength**  
  - All passwords hashed with `password_hash(PASSWORD_DEFAULT)`.  
  - Password resets validated using tokens and expiry.  
  - Forms enforce complexity (length, uppercase, lowercase, number, symbol) and confirmation fields.

- **Login rate limiting and lockout** (`login.php`, `ip_login_attempts`)  
  - Tracks failed attempts per account and IP.  
  - Sets `lockout_until` when thresholds are exceeded, refusing further attempts until time passes.  
  - `auto_cleanup` removes expired lockout entries.

- **Role-based access control**  
  - `auth.php` and per-file checks restrict pages to `admin`, `staff`, or `member` roles.  
  - Sensitive endpoints (attendance, payments, uploads, user CRUD) re-check role even for AJAX.

- **Membership enforcement for members** (`auth.php`, `login.php`, `includes/status_sync.php`)  
  - Members must have valid, current `sales.expires_at`.  
  - `syncUserStatus` ensures `users.status` matches payment state.  
  - Unpaid or expired members are logged out or denied.

- **Activity logging** (`connection.php`, `includes/log_activity.php`, multiple pages)  
  - Records key actions: login, logout, payments, profile updates, walk-ins, etc.  
  - Enables admins to audit behavior via `admin/activity.php`.

- **Automated cleanup** (`includes/auto_cleanup.php`)  
  - Reduces stored sensitive history by deleting old logs, attendance, and stale unverified accounts.

- **Email verification and secure email changes**  
  - New accounts require email verification before being considered fully active.  
  - Email changes are two-stage: approval from old email, verification from new email, using JSON tokens in `users.verification_token`.

---

### 2.2 Frontend Security

- **CSP enforcement**  
  - Limits external resources, reducing risk from third-party script injection.

- **CSRF token wiring**  
  - Forms and AJAX consistently carry CSRF tokens, matching server-side checks.

- **XSS mitigation**  
  - Uses `htmlspecialchars` and `e()` for outputting user data (names, emails, visitor names).  
  - Scanner JS ignores keystrokes in inputs/textareas so QR payloads do not end up in editable fields.

---

### 2.3 Database and Supabase Security

- **Encrypted DB connection** (`connection.php`)  
  - Uses `sslmode=require` to force TLS to Supabase.

- **Row Level Security (RLS)** (`supabase_setup.sql`)  
  - Enabled on `users`, `attendance`, and `sales`.  
  - Policies control which rows are visible based on Supabase Auth user and roles.

- **Anon vs service keys**  
  - Frontend JS only uses `SUPABASE_ANON_KEY`.  
  - Backend scripts (like `supabase_sync.php`) use the more powerful `service_key`, kept on server side.

- **Lockout table** (`ip_login_attempts`)  
  - Defined as a dedicated table and integrated with login logic and cleanup.

---

## Part 3 – Architecture and System “Types”

### 3.1 Overall Architecture Type

- PHP **monolithic application** (no framework such as Laravel) using:
  - Role-based folders: `admin/`, `staff/`, `member/`.
  - Server-rendered views with Bootstrap and minimal JS enhancements.
  - Shared cross-cutting services via `connection.php` and `includes/` modules.
- Database: **Supabase Postgres** with RLS and SQL functions.

### 3.2 Type of QR Code and Scanning

- Each user has a **permanent QR code string** stored in `users.qr_code`.  
- **QR generation**:
  - Server-side: `generate_qr.php` using `phpqrcode` (PNG).  
  - Client-side (legacy): `member/qr_code.php` using `qrious`.
- **Scanning**:
  - HID scanners (act as keyboard): captured globally by `assets/js/global_attendance.js`.  
  - Camera scanner: `admin/attendance_scan.php` using `html5-qrcode`.
- **Processing**:
  - All scanning flows ultimately call `admin/attendance_endpoint.php`, which:
    - Resolves member from `users.qr_code`.  
    - Checks membership and status.  
    - Inserts into `attendance` once per day per member.

### 3.3 Type of Authentication

- **Primary auth**: PHP **session-based** login with roles:
  - `admin` – full management and reporting.  
  - `staff` – operational actions (attendance, payments, walk-ins).  
  - `member` – self-service portal (attendance history, workouts, payments).
- **Enhancements**:
  - Email verification.  
  - Password reset via token.  
  - Email change with double confirmation.  
  - Account/IP lockout after multiple failed logins.  
  - Membership gating based on `sales.expires_at`.
- **Supabase Auth integration**:
  - `supabase_sync.php` and JS `supabaseClient.auth.signInWithPassword` provide optional second auth layer compatible with Supabase RLS.

### 3.4 Deployment and Infrastructure Type

- **Database**: Supabase-managed Postgres (cloud).  
- **App Hosting**: PHP hosting environment (e.g., Hostinger in production; XAMPP in local dev).  
- **Email service**: Brevo (Sendinblue) for verification, password reset, receipts, and email-change flows.  
- **Scheduling**: No OS cron; a **lazy cron** pattern using `auto_cleanup` triggered on first DB connection of the day.  
- **Backup**: Manual SQL export/import via `admin/settings.php`.

---

## Part 4 – Feature / File / Database Mapping

### 4.1 Attendance (Members and Walk-ins)

- **Files involved**:
  - Frontend: `assets/js/global_attendance.js`, `admin/attendance_scan.php`, staff/admin dashboards and attendance views.  
  - Backend: `admin/attendance_endpoint.php`, `admin/attendance.php`, `staff/attendance_register.php`, `admin/daily.php`, `staff/daily.php`, `includes/auto_cleanup.php`.
- **Tables involved**:
  - `attendance`, `users`, `sales`, `walk_ins`.
- **Flow summary**:
  - Scanner or camera reads QR → JS sends `{qr_code}` to `attendance_endpoint.php`.  
  - Endpoint checks auth and membership, and writes new `attendance` row (once per day).  
  - Walk-ins also create `attendance` entries with `visitor_name`.  
  - Admin/staff attendance pages display joined records.  
  - Cleanup removes old records.

### 4.2 Membership Payments and Status

- **Files**:
  - `admin/register_payment.php`, `staff/staff_register_payment.php`, `admin/manage_users.php`, `staff/members.php`, `member/dashboard.php`, `member/payment_history.php`, `login.php`, `auth.php`, `includes/status_sync.php`, admin/staff `daily.php`.
- **Tables**:
  - `sales`, `users`, `walk_ins`, `attendance`, `settings`.
- **Flow summary**:
  - Payment registration inserts into `sales` and updates `users.status`.  
  - Status synchronization ensures members’ `status` matches expiry.  
  - Member login and access depend on active membership.  
  - Member UI reads from `sales` to show receipts and history.  
  - Walk-in flows add `sales(user_id=NULL)` and use `settings('daily_walkin_rate')`.

### 4.3 Users and Authentication

- **Files**:
  - `register.php`, `login.php`, `verify_email.php`, `resend_verification.php`, `forgot_password.php`, `reset_password.php`, `request_email_change.php`, `approve_email_change.php`, `auth.php`, profile pages, `admin/admin_user_actions.php`, `setup_ip_lockout.php`, `includes/auto_cleanup.php`.
- **Tables**:
  - `users`, `activity_log`, `ip_login_attempts`, `sales`.
- **Flow summary**:
  - Registration creates `users` entries and sends verification emails.  
  - Login authenticates and rate-limits based on `users` and `ip_login_attempts`.  
  - Email verification and reset flows modify tokens and statuses in `users`.  
  - Admin/staff profile edits and user management update `users` and log actions.

### 4.4 Exercise Library and Workout Planning

- **Files**:
  - `admin/manage_exercises.php`, `admin/upload_image.php`, `member/exercises.php`, `member/my_workouts.php`.
- **Tables**:
  - `muscle_groups`, `muscles`, `exercises`, `workout_plans`, `workout_plan_exercises`, `users`.
- **Flow summary**:
  - Admin defines exercise taxonomy and exercise details.  
  - Members browse this data and plan workouts (plans + exercises).  
  - Planner uses `workout_plans` and `workout_plan_exercises` to build calendar and history.

### 4.5 Supabase Integration and Search

- **Files**:
  - `includes/supabase_config.php`, `assets/js/supabase-config.php`, `supabase_setup.sql`, `supabase_sync.php`, `admin/manage_users.php`, `staff/members.php`, `login.php`.
- **Tables**:
  - `users`, `attendance`, `sales` (with RLS).
- **Flow summary**:
  - JS bridge exposes `supabaseClient`.  
  - Admin/staff member pages call `search_users` RPC for full-text search.  
  - `supabase_sync.php` keeps Auth accounts aligned with local `users` and roles.

### 4.6 Maintenance and Backup

- **Files**:
  - `includes/auto_cleanup.php`, `admin/settings.php`, `connection.php`.
- **Tables**:
  - `settings` plus all public tables for backup/restore.
- **Flow summary**:
  - Daily cleanup is triggered via `connection.php` and tracked in `settings`.  
  - Admin can export an SQL backup and import it later via `settings.php`.

---

## Part 5 – Database Schema, Relationships, and Supabase Features

### 5.1 Core Tables

- **`users`**  
  - **Purpose**: Store all users (admin, staff, members) and security metadata.  
  - **Key columns**:
    - `id` – primary key.  
    - `full_name`, `email` – identity and contact.  
    - `password` – password hash.  
    - `role` – `admin`, `staff`, `member`.  
    - `status` – active/inactive (especially for members).  
    - `qr_code` – unique QR payload for attendance.  
    - `qr_image` – stored QR representation (used in some features).  
    - `is_verified` – email verification flag.  
    - `verification_token` – token or JSON for email verification / email changes.  
    - `login_attempts`, `lockout_until` – account lockout.  
    - `reset_token`, `reset_expires` – password reset.  
    - `created_at` – creation timestamp.  
    - `fts` – generated tsvector for full-text search (Supabase).
  - **Relationships**:
    - Referenced by `attendance`, `sales`, `walk_ins`, `workout_plans`, `activity_log`.

- **`attendance`**  
  - **Purpose**: Record member and walk-in attendance.  
  - **Key columns**:
    - `id` – primary key.  
    - `user_id` – FK to `users` (nullable for walk-ins).  
    - `date`, `time_in` – check-in date/time.  
    - `attendance_date` – normalized date used for reports/cleanup.  
    - `visitor_name` – walk-in name if `user_id` is NULL.

- **`sales`**  
  - **Purpose**: Record payments for memberships and walk-ins.  
  - **Key columns**:
    - `id` – primary key.  
    - `user_id` – FK to `users` or NULL for walk-ins.  
    - `amount` – payment amount.  
    - `sale_date` – timestamp.  
    - `expires_at` – membership expiry (may be NULL for walk-ins).

- **`walk_ins`**  
  - **Purpose**: Track daily walk-in entries.  
  - **Key columns**:
    - `id` – primary key.  
    - `visitor_name` – name of the visitor.  
    - `amount` – amount paid.  
    - `visit_date` – timestamp.  
    - `checked_in_by` – staff/admin user id.

- **`activity_log`**  
  - **Purpose**: Audit trail for important actions.  
  - **Key columns**:
    - `id` – primary key.  
    - `user_id` – who performed the action.  
    - `role` – role of actor.  
    - `action` – short code (Login, Logout, ADD_WALKIN, Mark Payment, etc.).  
    - `member_id` – optional target member for contextual actions.  
    - `details` – text/JSON details of event.  
    - `ip_address` – IP when recorded.  
    - `created_at` – timestamp.

- **`settings`**  
  - **Purpose**: Store configurable key/value pairs.  
  - **Key columns**:
    - `setting_key`, `setting_value`, `created_at`, `updated_at`.  
  - **Usage**:
    - `last_auto_cleanup`, `daily_walkin_rate`, and any future configuration.

- **`ip_login_attempts`**  
  - **Purpose**: IP-based login attempt and lockout tracking.  
  - **Key columns**:
    - `ip_address` – primary key.  
    - `login_attempts` – recent failures.  
    - `lockout_until` – until when login is blocked.

### 5.2 Exercise and Workout Tables

- **`muscle_groups`** – top-level categories (e.g., Chest, Back, Arms).  
- **`muscles`** – individual muscles; linked to `muscle_groups`.  
- **`exercises`** – specific exercises with media and description.  
- **`workout_plans`** – per-user per-day workout plans.  
- **`workout_plan_exercises`** – junction between `workout_plans` and `exercises`.

### 5.3 Supabase-Specific Features in Use

- **Row Level Security (RLS)**  
  - Enabled on `users`, `attendance`, and `sales`.  
  - Ensures users see only their own data, while admins/staff can view all (via role metadata).

- **Full-text search (`search_users`)**  
  - `users.fts` generated from name and email.  
  - GIN index for fast search.  
  - RPC function `search_users(query text)` used from admin/staff pages for fast member search.

- **Supabase Auth integration**  
  - `supabase_sync.php` creates/updates Supabase Auth users for each local `users` row and sets `app_metadata.role`.  
  - Frontend may log into Supabase via `supabaseClient.auth.signInWithPassword` to enable RLS-safe queries.

- **Lockout infrastructure**  
  - `ip_login_attempts` defined and used in PHP login handler to implement IP-level rate limiting.

---

## Part 6 – Capstone Final Defense Style Q&A

Use these questions and model answers to practice for your oral defense.

### 6.1 System Overview and Motivation

- **Q1: What problem does your system solve for the gym?**  
  **A:** It centralizes membership, attendance, payments, and workout planning into a single system. Staff and admins can quickly register members and walk-ins, track payments and membership validity, and monitor attendance trends. Members get self-service access to their QR pass, attendance history, and personalized workout planner.

- **Q2: What are the main user roles in your system and what can each do?**  
  **A:** There are three roles: **admin**, **staff**, and **member**. Admins manage users, membership plans, reports, backups, and overall settings. Staff handle daily operations like scanning attendance, registering payments, and recording walk-ins. Members log in to view their status, scan in, review payments, browse exercises, and manage their workout plans.

- **Q3: Why did you choose a PHP monolith with Supabase instead of another stack?**  
  **A:** PHP is easy to deploy on common hosting platforms like Hostinger and integrates naturally with HTML-based views. Supabase provides a fully managed Postgres database with built-in RLS, SQL migrations, and full-text search. The combination gives a simple yet powerful architecture with strong security and minimal infrastructure overhead.

---

### 6.2 Architecture and Design

- **Q4: How is your project organized at the file and folder level?**  
  **A:** It is organized by role and responsibility: global files at the root (connection, auth, login/register, email flows), and three role-based folders (`admin/`, `staff/`, `member/`). Shared helpers live in `includes/` and shared JS/CSS in `assets/`. Supabase-specific scripts and SQL are at the root (`supabase_setup.sql`, `supabase_sync.php`).

- **Q5: What design decisions did you make around QR-based attendance?**  
  **A:** Each user has a permanent QR code stored in the database. This code is rendered into QR images and scanned by either HID barcode scanners or camera-based scanners. All scan flows converge to a single backend endpoint that validates members and records attendance, which keeps the logic centralized, consistent, and easier to maintain.

- **Q6: How does your system handle walk-in visitors without accounts?**  
  **A:** Walk-ins are recorded with a visitor name and amount in a dedicated `walk_ins` table and are also reflected in `sales` with `user_id=NULL` and in `attendance` with `visitor_name`. This keeps reporting accurate while avoiding full user accounts for one-time visitors.

---

### 6.3 Security and Reliability

- **Q7: What are the main security features implemented in your system?**  
  **A:** Key features include CSRF protection, strong session configuration, Content-Security-Policy, password hashing with reset tokens, IP and account-based login lockout, email verification and secure email change, role-based access control, membership gating, and periodic cleanup of old logs and unverified accounts.

- **Q8: How do you protect against cross-site request forgery (CSRF)?**  
  **A:** The backend generates a CSRF token per session, and `csrf_field()` inserts it into forms while `csrf_script()` sends it in AJAX headers. Sensitive POST handlers call `validate_csrf()` which rejects requests where the token does not match, preventing forged submissions from other sites.

- **Q9: How do you ensure that passwords are stored and handled securely?**  
  **A:** Passwords are never stored in plain text. On registration and reset, they are hashed using PHP’s `password_hash` with the default modern algorithm. Authentication uses `password_verify`. Password complexity rules enforce stronger passwords, and reset links use time-limited tokens stored in the database.

- **Q10: How does your login lockout mechanism work and why is it important?**  
  **A:** The system tracks failed login attempts both per user account and per IP in `users` and `ip_login_attempts`. After a threshold of failed attempts, `lockout_until` is set, blocking further logins for a period. This slows down brute-force attacks and protects both user accounts and the server.

- **Q11: How do you prevent unauthorized access to admin and staff features?**  
  **A:** Every protected page calls `auth.php` and also checks `$_SESSION['role']` explicitly. Sensitive endpoints like attendance, payment registration, and user CRUD validate the session and role again. Users who are not authorized are redirected to the login page or receive an error JSON response.

- **Q12: What measures do you take to protect against SQL injection?**  
  **A:** The system uses prepared statements (`$pdo->prepare` and `execute`) for almost all dynamic queries, which separate SQL structure from user input and prevent injection. Additionally, global input truncation and validation reduces the attack surface.

---

### 6.4 Database and Data Modeling

- **Q13: Can you explain the relationship between `users`, `sales`, and `attendance`?**  
  **A:** `users` is the central entity for all accounts. `sales` attaches payment records to users with `user_id`, amount, and `expires_at`, which defines membership validity. `attendance` records daily check-ins using `user_id` and date/time. `sales` controls membership status, and `attendance` shows how often and when members visit.

- **Q14: How does your system determine if a member is active or inactive?**  
  **A:** It checks the latest `sales.expires_at` for a user. If `expires_at` is in the future, the member is considered active; otherwise inactive. The `includes/status_sync.php` helper updates `users.status` based on this logic, and `auth.php` and `login.php` enforce this before granting access.

- **Q15: What is the purpose of the `settings` table?**  
  **A:** It stores configurable system values as key-value pairs, such as `daily_walkin_rate` and `last_auto_cleanup`. This allows the application to change behavior (like walk-in pricing) without modifying code and to track when maintenance tasks were last executed.

- **Q16: Why did you add an `activity_log` table and what is stored there?**  
  **A:** `activity_log` provides an audit trail. It stores who performed an action (`user_id`, `role`), what they did (`action`), optional target member (`member_id`), details, IP address, and timestamp. This is important for diagnosing issues, detecting abuse, and demonstrating accountability.

- **Q17: How do your workout-related tables represent a member’s plan?**  
  **A:** `workout_plans` represents a scheduled workout for a specific user and date, with a status like `pending` or `done`. `workout_plan_exercises` is a junction table linking each plan to multiple exercises from the `exercises` table. This creates a flexible many-to-many relationship between plans and exercises.

---

### 6.5 QR Codes, Attendance, and Supabase

- **Q18: Why did you use QR codes instead of RFID or manual input?**  
  **A:** QR codes are inexpensive and easy to generate and print. Members only need their printed card or a screenshot on their phone. Scanners simply act as keyboards, so the solution works with basic USB barcode/QR scanners and web-based scanners without requiring specialized hardware.

- **Q19: How do you prevent multiple attendance entries for the same member on the same day?**  
  **A:** When the attendance endpoint receives a QR code, it checks `attendance` for an existing record for that `user_id` with today’s date. If one exists, it returns a warning instead of inserting a new row, ensuring only one attendance record per day per member.

- **Q20: What role does Supabase play in your system beyond just being a database?**  
  **A:** Supabase provides managed Postgres with Row Level Security, SQL migrations, and full-text search. It also offers an Auth service that we sync with our local users via `supabase_sync.php`. The full-text search and RLS features are used in admin and staff pages for secure and efficient user search.

- **Q21: How does your full-text search for members work?**  
  **A:** Supabase defines a generated `fts` column on `users` containing a tsvector of names and emails, with a GIN index. A SQL function `search_users(query text)` ranks results. The frontend calls this function through `supabaseClient.rpc`, then filters the HTML table to match the returned IDs.

---

### 6.6 Limitations and Future Work

- **Q22: What are the current limitations of your system?**  
  **A:** Current limitations include minimal reporting around advanced analytics, limited role customization beyond the three main roles, no native mobile application, and manual triggering of backup/restore. The auto-cleanup relies on traffic to the site instead of a dedicated cron job.

- **Q23: If you had more time, what improvements would you implement?**  
  **A:** I would add more granular roles and permissions, improve the UI for real-time attendance dashboards, enhance analytics (for example churn prediction and cohort analysis), integrate push notifications or SMS reminders, and containerize the app for easier deployment. I would also add automated test coverage and continuous deployment.

- **Q24: How would your system scale if the gym grows or multiple branches are added?**  
  **A:** Because it uses Supabase Postgres and standard PHP, we can scale vertically by increasing database and server resources. For multiple branches, we could introduce a `branch` table and foreign keys in key tables (users, attendance, sales) and then add branch-level filters and permissions while reusing most of the existing logic.

---

End of reviewer.  
You can use this document as a **printed reviewer**, a **slide outline**, or as a **script** for your capstone defense.

