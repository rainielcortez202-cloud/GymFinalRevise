# Arts Gym Management System - Comprehensive Technical Reviewer

**Version:** 2.0 (Simplified & Enhanced)  
**Target Audience:** Capstone Defense Panel / Technical Reviewers  
**Date:** 2026-03-01  

---

## 1. System Overview & Architecture

### 1.1 Purpose and Objectives
The **Arts Gym Management System** is a web-based platform designed to streamline gym operations. It replaces manual logbooks with digital attendance tracking, automates membership status management, and provides a centralized portal for members, staff, and administrators.

**Key Objectives:**
*   **Digitize Attendance:** Use QR codes for contactless, efficient check-ins.
*   **Automate Membership:** Automatically expire memberships based on payment history.
*   **Enhance Security:** Prevent unauthorized access and enforce role-based permissions.
*   **Improve Engagement:** Provide members with an exercise library and workout tracking.

### 1.2 High-Level Architecture
The system follows a **Monolithic Architecture** with a clear separation of concerns using the **MVC (Model-View-Controller)** pattern principles, though implemented in vanilla PHP.

*   **Frontend (View):** HTML5, CSS3 (Bootstrap 5), JavaScript (Vanilla + jQuery).
*   **Backend (Controller/Model):** Native PHP 8.2+ (Procedural + Functional).
*   **Database:** PostgreSQL 15+ (Hosted on Supabase).
*   **External Services:** Brevo (Email), Supabase (Storage/Realtime).

### 1.3 Data Flow
1.  **User Interaction:** User submits a form (e.g., Register) or scans a QR code.
2.  **Request Handling:** PHP scripts receive POST/GET requests.
3.  **Security Layer:** Middleware validates CSRF tokens, sanitizes input, and enforces rate limits.
4.  **Business Logic:** Backend processes data (e.g., hashes passwords, checks duplicates).
5.  **Database Interaction:** PDO (PHP Data Objects) executes prepared SQL statements.
6.  **Response:** Server returns JSON (for AJAX) or renders an HTML page.

---

## 2. Frontend Technologies

### 2.1 Libraries & Frameworks
| Library | Version | Purpose |
| :--- | :--- | :--- |
| **Bootstrap** | 5.3.2 | Responsive UI layout, grid system, and components. |
| **jQuery** | 3.6.0 | DOM manipulation and simplified AJAX requests. |
| **HTML5-QRCode** | 2.3.7 | Webcam-based QR code scanning in the browser. |
| **html2canvas** | Latest | Capturing DOM elements as images (for ID cards). |
| **jsPDF** | UMD | Generating PDF documents (Reports/ID cards) client-side. |
| **Bootstrap Icons** | 1.11.1 | Scalable vector icons for UI elements. |

### 2.2 Key Interactive Features (Explained)

#### **Live QR Scanning**
*   **What it is:** The ability to scan QR codes using the device's camera inside the web page, without needing a separate app or physical scanner.
*   **How it works:** We use the `html5-qrcode` library, which accesses the browser's `navigator.mediaDevices.getUserMedia` API (Webcam). It captures video frames, analyzes them for QR patterns, and extracts the text data.
*   **Code Reference:** `assets/js/global_scanner.js` initializes the camera and handles the "onScanSuccess" event.

#### **AJAX Forms (Asynchronous JavaScript and XML)**
*   **What it is:** A technique that allows the website to send data to the server (like a login attempt) and get a response without reloading the entire page.
*   **How it works:** JavaScript intercepts the "Submit" button click, prevents the default reload, and sends the form data secretly in the background using `fetch()` or `$.ajax`. The server replies with JSON (e.g., `{ "status": "success" }`), and JavaScript updates the UI.
*   **Code Reference:** `login.php` (bottom script section) uses `$.ajax` to POST credentials to the server.

#### **Debouncing**
*   **What it is:** A performance optimization that waits for the user to *stop typing* before doing something expensive (like searching the database).
*   **How it works:** When you type a letter, a timer starts (e.g., 300ms). If you type another letter before 300ms, the timer resets. The search only happens if the timer actually finishes.
*   **Code Reference:** Used in `admin/manage_users.php` search bar to prevent lagging.

---

## 3. Backend Logic

### 3.1 Core Files & Structure
*   **`connection.php`**: The "Heart" of the system. It connects PHP to the PostgreSQL database.
*   **`auth.php`**: The "Gatekeeper". It checks if a user is logged in and what role they have (Admin, Staff, or Member).
*   **`includes/security.php`**: The "Shield". It adds security headers and filters bad inputs.
*   **`admin/attendance_endpoint.php`**: The "Receiver". It listens for QR code scans sent from the frontend.

### 3.2 Authentication & Session Management
*   **Session Storage:** When you log in, the server gives your browser a unique "Session ID" cookie. The server keeps a file matching that ID with your user details (`user_id`, `role`).
*   **RBAC (Role-Based Access Control):**
    *   **Concept:** Not everyone can do everything.
    *   **Implementation:** Before loading a page, we check: `if ($_SESSION['role'] !== 'admin') { stop_and_redirect(); }`.
    *   **Code Reference:** Top of `admin/dashboard.php`.

### 3.3 Algorithms & Techniques

#### **Password Hashing (Bcrypt)**
*   **What it is:** We never save actual passwords (like "123456"). We save a scrambled "hash" that cannot be reversed.
*   **How it works:** PHP's `password_hash()` function turns "123456" into something like `$2y$10$e...`. When logging in, `password_verify()` checks if the entered password matches the hash.
*   **Why:** Even if hackers steal the database, they can't read the passwords.

#### **Lazy Membership Sync**
*   **What it is:** Instead of a program running 24/7 to check if memberships expired, we only check *when it matters*.
*   **How it works:** When a user logs in or scans their QR code, the system quickly calculates: "Is today after their expiry date?". If yes, it marks them "Inactive" right then and there.
*   **Code Reference:** `includes/status_sync.php` -> `syncUserStatus()`.

#### **Auto Cleanup**
*   **What it is:** A self-cleaning mechanism to keep the database fast and compliant with privacy laws.
*   **How it works:** Every time `connection.php` is loaded (which is on every page load), it checks "Did I run cleanup today?". If no, it runs a quick delete command for old logs (older than 90 days).
*   **Code Reference:** `includes/auto_cleanup.php`.

---

## 4. Database Schema (PostgreSQL)

### 4.1 Key Tables
| Table | What it stores | Why it matters |
| :--- | :--- | :--- |
| **`users`** | Login info, Name, Role | The central table for everyone. |
| **`sales`** | Payments, Expiry Date | Determines if a member is "Active" or "Paid". |
| **`attendance`** | Check-in Time, Date | The main logbook. Replaces paper logs. |
| **`activity_log`** | "Who did what?" | Security audit trail (e.g., "Admin deleted user X"). |

### 4.2 Relationships (How tables talk)
*   **One-to-Many (Users -> Attendance):** One User can have *many* attendance records (one for each day they visit).
*   **One-to-Many (Users -> Sales):** One User can have *many* payments over time.

### 4.3 Why PostgreSQL?
*   **Reliability:** It is stricter than MySQL, meaning less corrupted data.
*   **Advanced Features:** It supports `INTERVAL` math (e.g., "Today minus 30 days") natively, which makes our reports accurate.

---

## 5. Integrations (Connecting to Outside World)

### 5.1 Supabase
*   **What it is:** A cloud platform that hosts our PostgreSQL database.
*   **Why:** It allows the system to be accessed from anywhere (internet) instead of just one computer.

### 5.2 Brevo (Email API)
*   **What it is:** A professional email sending service.
*   **How it works:** Our PHP code sends a "cURL" request (like a programmatic web browser) to Brevo's servers saying "Send this email to John".
*   **Code Reference:** `includes/brevo_send.php`.

---

## 6. Security & Compliance

### 6.1 Multi-Layered Security (The "Defense in Depth")
1.  **CSRF Protection (Cross-Site Request Forgery):**
    *   **The Threat:** A hacker tricks you into clicking a link that secretly deletes your account.
    *   **The Fix:** Every form has a secret "Token" (`csrf_token`). If the form is submitted without it (or with the wrong one), the server rejects it.
    *   **How it Protects Data:** Ensures that state-changing actions (like changing passwords or deleting data) are only performed by the user intentionally, preventing unauthorized data modification.
2.  **SQL Injection Prevention:**
    *   **The Threat:** A hacker types `' OR 1=1 --` into the login box to trick the database.
    *   **The Fix:** We use **PDO Prepared Statements** (`$pdo->prepare`). This treats user input as just text, never as computer code.
    *   **How it Protects Data:** Prevents attackers from accessing, modifying, or deleting database records they shouldn't have access to (e.g., dumping the entire user table).
3.  **XSS Protection (Cross-Site Scripting):**
    *   **The Threat:** A hacker registers with the name `<script>alert('hacked')</script>`.
    *   **The Fix:** We use `htmlspecialchars()` when showing names. This turns `<` into `&lt;`, making it harmless text.
    *   **How it Protects Data:** Prevents malicious scripts from running in other users' browsers, protecting their session cookies and personal data from theft.
4.  **Input Validation & Sanitization:**
    *   **The Fix:** Global truncation of inputs to 255 characters and strict type casting (e.g., `intval()`) for IDs and amounts.
    *   **How it Protects Data:** Ensures data integrity by rejecting malformed or excessively large inputs that could crash the system or cause buffer overflows.
5.  **Secure Headers & CSP:**
    *   **The Fix:** Headers like `X-Frame-Options` and `Content-Security-Policy`.
    *   **How it Protects Data:** Prevents clickjacking and restricts where resources can be loaded from, reducing the attack surface for data exfiltration.
6.  **Row Level Security (RLS) & SSL:**
    *   **The Fix:** RLS policies on Supabase and `sslmode=require` in connections.
    *   **How it Protects Data:** Encrypts data in transit to prevent interception (Man-in-the-Middle) and ensures users can only access their own data at the database level.

### 6.2 Data Privacy Act (DPA) Compliance
*   **Data Minimization:** We only ask for Name and Email. No phone numbers, no addresses, no birthdays. Less data = Less risk.
*   **Right to be Forgotten:** The `auto_cleanup.php` script automatically deletes old data, respecting the user's right not to have their data kept forever.

---

## 7. System Workflows

### 7.1 Registration Flow
1.  **Input:** User fills form.
2.  **Validation:** System checks "Is password strong?", "Is email already used?".
3.  **Creation:** User is saved in DB but marked `is_verified = FALSE`.
4.  **Verification:** System generates a random code, emails it. User clicks link -> System marks `is_verified = TRUE`.

### 7.2 Attendance Flow
1.  **Scan:** Camera reads QR code.
2.  **Identify:** System looks up the code in `users` table.
3.  **Check:**
    *   Is it a Member? (Not Staff?)
    *   Is their membership paid? (Check `sales` table expiry)
    *   Did they already scan today? (Check `attendance` table for today's date)
4.  **Record:** If all checks pass, save to `attendance` table.

---

## 8. Panel Preparation (Q&A)

### 8.1 Likely Questions & Senior Answers

**Q: How do you handle membership expiration?**
*   **A:** "We use a **Lazy Sync** approach. Instead of a heavy background process constantly checking everyone, we check a specific user's status **at the moment they try to enter**. This is highly efficient (O(1) complexity) and ensures the status is always accurate in real-time."

**Q: Why use Supabase/PostgreSQL instead of MySQL?**
*   **A:** "PostgreSQL is an enterprise-grade database. It offers better data integrity and concurrency handling than standard MySQL. Supabase gives us this power as a managed service, handling backups and scaling for us."

**Q: Is the system secure against SQL Injection?**
*   **A:** "Absolutely. We strictly use **PDO Prepared Statements** for 100% of our database queries. We never concatenate user input directly into SQL strings, which eliminates the injection vector entirely."

**Q: What happens if the internet goes down?**
*   **A:** "Since this is a cloud-hosted web application (SaaS model), connectivity is required. However, the app is optimized to be lightweight, so it functions smoothly even on mobile data connections."

**Q: How does the QR Code generation work?**
*   **A:** "We use the `phpqrcode` library. When a user registers, we generate a cryptographically secure random string (using `random_bytes`), save that string to their profile, and the library converts that string into a PNG image on the fly."

### 8.2 Unique Selling Points (USP)
*   **Auto-Cleanup:** Self-maintaining database that automatically complies with privacy laws (DPA).
*   **Hybrid Scanning:** Works with both cheap webcams and professional barcode scanners.
*   **Exercise Library:** Provides added value to members, making it more than just a "logbook" app.
