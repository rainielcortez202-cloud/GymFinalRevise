<?php
require_once 'connection.php';

// Handle POST request (API mode)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Validate CSRF
    validate_csrf();

    require_once 'includes/status_sync.php';
    require_once 'supabase_sync.php';

    // Settings
    $max_attempts   = 5;
    $lockout_minutes = 5;

    // Get Input
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $pass  = $_POST['password'] ?? '';
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (empty($email) || empty($pass)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter both email and password.']);
        exit;
    }

    try {
        // 0. Check IP Lockout Status
        $stmt_ip = $pdo->prepare("SELECT login_attempts, lockout_until FROM ip_login_attempts WHERE ip_address = ?");
        $stmt_ip->execute([$ip]);
        $ip_data = $stmt_ip->fetch();
        
        $ip_attempts = 0;
        if ($ip_data) {
            $ip_attempts = (int)$ip_data['login_attempts'];
            if ($ip_data['lockout_until']) {
                $stmt_check = $pdo->prepare("SELECT CASE WHEN ? > NOW() THEN true ELSE false END as is_locked");
                $stmt_check->execute([$ip_data['lockout_until']]);
                $lock_result = $stmt_check->fetch();
                
                $is_locked = false;
                if (isset($lock_result['is_locked'])) {
                    $is_locked = ($lock_result['is_locked'] === true || $lock_result['is_locked'] === 't' || $lock_result['is_locked'] === 'true');
                }
                
                if ($is_locked) {
                    $lockout_time = strtotime($ip_data['lockout_until']);
                    $current_time = time();
                    if ($lockout_time !== false && $current_time < $lockout_time) {
                        $remaining_seconds = $lockout_time - $current_time;
                        $remaining_minutes = max(1, ceil($remaining_seconds / 60));
                        echo json_encode([
                            'status' => 'error', 
                            'message' => "Too many failed attempts. Please wait for $remaining_minutes minute(s)."
                        ]);
                        exit;
                    }
                } else {
                    $stmt_clear_ip = $pdo->prepare("UPDATE ip_login_attempts SET login_attempts = 0, lockout_until = NULL, updated_at = CURRENT_TIMESTAMP WHERE ip_address = ?");
                    $stmt_clear_ip->execute([$ip]);
                    $ip_attempts = 0;
                }
            }
        } else {
            $stmt_insert_ip = $pdo->prepare("
                INSERT INTO ip_login_attempts (ip_address, login_attempts, created_at, updated_at)
                VALUES (?, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON CONFLICT (ip_address) DO UPDATE
                SET updated_at = CURRENT_TIMESTAMP
            ");
            $stmt_insert_ip->execute([$ip]);
        }

        // 1. Fetch User (PostgreSQL query)
        $stmt = $pdo->prepare("SELECT id, full_name, email, password, role, is_verified, login_attempts, lockout_until FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $ip_attempts++;
            if ($ip_attempts >= $max_attempts) {
                $stmt = $pdo->prepare("UPDATE ip_login_attempts SET login_attempts = ?, lockout_until = CURRENT_TIMESTAMP + ($lockout_minutes * INTERVAL '1 minute'), updated_at = CURRENT_TIMESTAMP WHERE ip_address = ?");
                $stmt->execute([$ip_attempts, $ip]);
                echo json_encode(['status' => 'error', 'message' => "Too many failed attempts Please wait for $lockout_minutes minutes."]);
            } else {
                $stmt = $pdo->prepare("UPDATE ip_login_attempts SET login_attempts = ?, updated_at = CURRENT_TIMESTAMP WHERE ip_address = ?");
                $stmt->execute([$ip_attempts, $ip]);
                echo json_encode(['status' => 'error', 'message' => "Invalid email or password. Attempt $ip_attempts of $max_attempts."]);
            }
            exit;
        }

        // 2. Check Lockout Status
        if ($user['lockout_until']) {
            $stmt_check = $pdo->prepare("SELECT CASE WHEN ? > NOW() THEN true ELSE false END as is_locked");
            $stmt_check->execute([$user['lockout_until']]);
            $lock_result = $stmt_check->fetch();
            
            $is_locked = false;
            if (isset($lock_result['is_locked'])) {
                $is_locked = ($lock_result['is_locked'] === true || $lock_result['is_locked'] === 't' || $lock_result['is_locked'] === 'true');
            }
            
            if ($is_locked) {
                $lockout_time = strtotime($user['lockout_until']);
                $current_time = time();
                if ($lockout_time !== false && $current_time < $lockout_time) {
                    $remaining_seconds = $lockout_time - $current_time;
                    $remaining_minutes = max(1, ceil($remaining_seconds / 60));
                    echo json_encode([
                        'status' => 'error', 
                        'message' => "Too many failed attempts. Account locked. Try again in $remaining_minutes minute(s)."
                    ]);
                    exit;
                }
            } else {
                $stmt_clear = $pdo->prepare("UPDATE users SET login_attempts = 0, lockout_until = NULL WHERE id = ?");
                $stmt_clear->execute([$user['id']]);
                $user['login_attempts'] = 0;
            }
        }

        // 3. Verify Password
        if (password_verify($pass, $user['password'])) {
            if (!(bool)($user['is_verified'] ?? false)) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Email not verified. Please verify your email before logging in.'
                ]);
                exit;
            }
            // LOGIN SUCCESS
            if ($user['role'] === 'member') {
                $saleStmt = $pdo->prepare("SELECT id, amount, sale_date, expires_at FROM sales WHERE user_id = ? ORDER BY sale_date DESC LIMIT 1");
                $saleStmt->execute([$user['id']]);
                $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);

                $isPaidActive = false;
                if ($sale) {
                    if ($sale['expires_at'] === null) {
                        $isPaidActive = true;
                    } else {
                        $isPaidActive = (strtotime($sale['expires_at']) > time());
                    }
                }

                if (!$isPaidActive) {
                    syncUserStatus($pdo, $user['id']);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Entry Restricted. Membership fee has not been settled. Kindly pay at the counter to activate access.'
                    ]);
                    exit;
                } else {
                    syncUserStatus($pdo, $user['id']);
                }

                $saleId = (int)($sale['id'] ?? 0);
                $saleDate = $sale['sale_date'] ?? date('Y-m-d H:i:s');
                $refDate = date('Ymd', strtotime($saleDate));
                $receiptRef = 'AG-REC-' . str_pad((string)$saleId, 6, '0', STR_PAD_LEFT) . '-' . $refDate;

                try {
                    $amountFmt = number_format((float)($sale['amount'] ?? 0), 2);
                    $saleDateFmt = date('M d, Y', strtotime($saleDate));
                    $expiresFmt = ($sale['expires_at'] ? date('M d, Y', strtotime($sale['expires_at'])) : 'N/A');

                    $html = "
                        <div style='font-family:Arial,sans-serif;padding:20px'>
                            <h2 style='margin:0;color:#e63946'>ARTS GYM</h2>
                            <p style='margin:4px 0 16px 0;color:#333'>Official Receipt</p>
                            <div style='border:1px solid #eee;border-radius:10px;padding:16px'>
                                <p style='margin:0 0 8px 0'><strong>Reference No.:</strong> {$receiptRef}</p>
                                <p style='margin:0 0 8px 0'><strong>Member:</strong> " . htmlspecialchars($user['full_name']) . "</p>
                                <p style='margin:0 0 8px 0'><strong>Date:</strong> {$saleDateFmt}</p>
                                <p style='margin:0 0 8px 0'><strong>Amount Paid:</strong> ₱{$amountFmt}</p>
                                <p style='margin:0'><strong>Valid Until:</strong> {$expiresFmt}</p>
                            </div>
                            <p style='margin-top:14px;color:#777;font-size:12px'>Keep this receipt for your records.</p>
                        </div>
                    ";
                    require_once __DIR__ . '/includes/brevo_send.php';
                    brevo_send_email($user['email'], $user['full_name'], "Arts Gym Receipt (Ref: {$receiptRef})", $html);
                } catch (Exception $e) { }
            }
            
            $stmt = $pdo->prepare("UPDATE users SET login_attempts = 0, lockout_until = NULL WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            $stmt_ip = $pdo->prepare("UPDATE ip_login_attempts SET login_attempts = 0, lockout_until = NULL, updated_at = CURRENT_TIMESTAMP WHERE ip_address = ?");
            $stmt_ip->execute([$ip]);

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['name']    = $user['full_name'];

            syncSupabaseMetadata($pdo, $user['email'], $user['role']);
            logActivity($pdo, $user['id'], $user['role'], 'Login', 'User logged in successfully');

            $attendanceMode = isset($_POST['attendance']) && $_POST['attendance'] === '1';

            if ($attendanceMode) {
                $redirect = null;
                if ($user['role'] === 'admin') {
                    $redirect = 'admin/attendance_scan.php';
                } elseif ($user['role'] === 'staff') {
                    $redirect = 'staff/attendance_register.php';
                } else {
                    $redirect = 'member/dashboard.php?show_receipt=1';
                }
                echo json_encode([
                    'status' => 'success',
                    'role' => $user['role'],
                    'redirect' => $redirect
                ]);
            } else {
                echo json_encode([
                    'status' => 'success',
                    'role' => $user['role']
                ]);
            }
            exit;
        } else {
            // LOGIN FAILED
            $user_attempts = $user['login_attempts'] + 1;
            $ip_attempts++;
            
            // Apply IP lockout if reached
            if ($ip_attempts >= $max_attempts) {
                $stmt = $pdo->prepare("UPDATE ip_login_attempts SET login_attempts = ?, lockout_until = CURRENT_TIMESTAMP + ($lockout_minutes * INTERVAL '1 minute'), updated_at = CURRENT_TIMESTAMP WHERE ip_address = ?");
                $stmt->execute([$ip_attempts, $ip]);
            } else {
                $stmt = $pdo->prepare("UPDATE ip_login_attempts SET login_attempts = ?, updated_at = CURRENT_TIMESTAMP WHERE ip_address = ?");
                $stmt->execute([$ip_attempts, $ip]);
            }

            // Apply User lockout if reached
            if ($user_attempts >= $max_attempts) {
                $stmt = $pdo->prepare("UPDATE users SET login_attempts = ?, lockout_until = CURRENT_TIMESTAMP + ($lockout_minutes * INTERVAL '1 minute') WHERE id = ?");
                $stmt->execute([$user_attempts, $user['id']]);
                echo json_encode(['status' => 'error', 'message' => "Too many failed attempts. Account locked for $lockout_minutes minutes."]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET login_attempts = ? WHERE id = ?");
                $stmt->execute([$user_attempts, $user['id']]);
                
                if ($ip_attempts >= $max_attempts) {
                     echo json_encode(['status' => 'error', 'message' => "Too many failed attempts. Please wait for $lockout_minutes minutes."]);
                } else {
                     $display_attempts = max($user_attempts, $ip_attempts);
                     echo json_encode(['status' => 'error', 'message' => "Invalid email or password. Attempt $display_attempts of $max_attempts."]);
                }
            }
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login/Register | ARTS GYM</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-red: #e63946; --dark-red: #9d0208; --dark-bg: #0a0a0a; --darker-bg: #050505;
            --text-white: #ffffff; --text-gray: #b0b0b0; --input-bg: rgba(255, 255, 255, 0.05);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        /* Allow page scrolling if panels exceed viewport height */
        body { font-family: 'Poppins', sans-serif; background: var(--dark-bg); color: var(--text-white); min-height: 100vh; overflow: auto; }
        h1, h2, h3 { font-family: 'Oswald', sans-serif; text-transform: uppercase; letter-spacing: 2px; }

        .main-container { min-height: 100vh; display: flex; }
        .brand-side { flex: 1; background: linear-gradient(135deg, rgba(10,10,10,0.8), rgba(157,2,8,0.4)), url('https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=1920&q=80') center/cover; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 60px; }
        .brand-logo { font-size: 3rem; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 15px; }
        .brand-logo i { color: var(--primary-red); }

        .form-side { width: 550px; background: var(--darker-bg); display: flex; flex-direction: column; justify-content: center; padding: 60px; position: relative; }
        /* Ensure the inner form container is centered and constrained for consistent layout */
        .form-container { max-width: 460px; width: 100%; margin: 0 auto; }
        .form-panel { padding-top: 6px; }
        /* Make sure buttons use full width inside panels */
        .form-panel .btn-submit { width: 100%; }
        
        .back-home {
            position: absolute;
            top: 30px;
            right: 40px;
            color: var(--text-gray);
            text-decoration: none;
            font-family: 'Oswald', sans-serif;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
            z-index: 10;
        }
        .back-home:hover { color: var(--primary-red); transform: translateX(-5px); }

        .auth-tabs { display: flex; margin-bottom: 40px; border-bottom: 2px solid rgba(255,255,255,0.1); }
        .auth-tab { flex: 1; padding: 15px; text-align: center; font-family: 'Oswald'; font-size: 1.2rem; color: var(--text-gray); cursor: pointer; background: none; border: none; }
        .auth-tab.active { color: white; border-bottom: 3px solid var(--primary-red); }

        .form-panel { display: none; }
        .form-panel.active { display: block; animation: fadeIn 0.4s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .form-group { margin-bottom: 20px; position: relative; }
        .input-wrapper { position: relative; }
        .input-wrapper > i { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--text-gray); }
        .form-control { background: var(--input-bg); border: 2px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 16px 50px; color: white; }
        .form-control:focus { background: rgba(230,57,70,0.05); border-color: var(--primary-red); color: white; box-shadow: none; }
        .password-toggle { position: absolute; right: 18px; top: 50%; transform: translateY(-50%); color: var(--text-gray); cursor: pointer; z-index: 5; }

        .btn-submit { width: 100%; background: linear-gradient(135deg, var(--primary-red), var(--dark-red)); color: white; padding: 16px; border: none; border-radius: 12px; font-family: 'Oswald'; font-weight: 600; text-transform: uppercase; margin-top: 10px; transition: 0.3s; }
        .btn-submit:hover { opacity: 0.9; transform: translateY(-2px); }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
        
        .alert-message { padding: 12px 15px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; line-height: 1.4; }
        .alert-message.error { background: rgba(220,53,69,0.15); border: 1px solid rgba(220,53,69,0.3); color: #ff6b7a; }
        .alert-message.success { background: rgba(40, 167, 69, 0.15); border: 1px solid rgba(40, 167, 69, 0.3); color: #5cff7e; }
        .alert-message.warning { background: rgba(255, 193, 7, 0.15); border: 1px solid rgba(255, 193, 7, 0.3); color: #ffca2c; }

        @media (max-width: 1024px) { 
            .brand-side { display: none; } 
            .form-side { width: 100%; padding: 40px 20px; }
            .form-side { display: flex; align-items: flex-start; }
            .form-container { margin-top: 10px; }
            .back-home { top: 20px; right: 20px; }
            .remember-forgot { flex-direction: column; gap: 12px !important; }
            .remember-forgot label { order: 1; }
            .remember-forgot a { order: 2; align-self: flex-start; }
        }
    </style>
</head>
<body>

    <div class="main-container">
        <div class="brand-side text-center">
            <div class="brand-logo"><i class=""></i><span>ARTS GYM</span></div>
            <p class="text-secondary max-width-400">Join the elite. Track your progress, manage your training, and transform your life today.</p>
        </div>

        <div class="form-side">
            <a href="index.php" class="back-home">
                <i class="bi bi-arrow-left"></i> BACK TO HOME
            </a>

            <div class="form-container">
                <!-- TABS (Hidden when in Forgot Password mode) -->
                <div class="auth-tabs" id="authTabs">
                    <button class="auth-tab active" data-tab="login">Login</button>
                    <button class="auth-tab" data-tab="register">Register</button>
                </div>

                <!-- Login Panel -->
                <div class="form-panel active" id="loginPanel">
                    
                    <!-- NEW BLOCK: Password Updated Success Message -->
                    <?php if (isset($_GET['notice']) && $_GET['notice'] == 'password_updated'): ?>
                        <div class="alert-message success shadow-sm">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>Password updated successfully! You can now log in with your new password.</span>
                        </div>
                    <?php endif; ?>
                    <!-- END BLOCK -->

                    <?php if (isset($_GET['email_changed']) && $_GET['email_changed'] == '1'): ?>
                        <div class="alert-message success shadow-sm">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>
                                Email address successfully changed! Please log in with your new email address.
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['restricted']) && $_GET['restricted'] == '1'): ?>
                        <div class="alert-message warning shadow-sm">
                            <i class="bi bi-shield-lock-fill"></i>
                            <span>Entry Restricted. Membership fee has not been settled. Kindly pay at the counter to activate access.</span>
                        </div>
                    <?php endif; ?>

                    <h2 class="mb-1">Welcome Back</h2>
                    <p class="text-secondary small mb-4">Enter your credentials to access your account</p>

                    <div id="loginError" class="alert-message error" style="display: none;"><i class="bi bi-exclamation-circle"></i><span></span></div>

                    <form id="loginForm">
                        <?= csrf_field(); ?>
                        <div class="form-group">
                            <label class="small text-secondary fw-bold mb-1">Email Address</label>
                            <div class="input-wrapper">
                                <input type="email" name="email" class="form-control" placeholder="yourname@gmail.com" required>
                                <i class="bi bi-envelope"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="small text-secondary fw-bold mb-1">Password</label>
                            <div class="input-wrapper">
                                <input type="password" name="password" class="form-control" id="logPass" placeholder="Enter password" required>
                                <i class="bi bi-lock"></i>
                                <span class="password-toggle" onclick="togglePass('logPass', this)"><i class="bi bi-eye"></i></span>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-4 remember-forgot">
                            <label class="small text-secondary"><input type="checkbox" name="remember" class="me-2">Remember me</label>
                            <a href="#" class="text-danger small" id="toForgotPanel">Forgot Password?</a>
                        </div>
                        <button type="submit" class="btn-submit">
                            <span class="btn-label">Sign In to Dashboard</span>
                            <span class="btn-spinner" style="display:none;"><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Signing in...</span>
                        </button>
                    </form>
                </div>

                <!-- Register Panel -->
                <div class="form-panel" id="registerPanel">
                    <h2 class="mb-1">Create Account</h2>
                    <p class="text-secondary small mb-4">Start your fitness journey with us today</p>
                    <div id="regError" class="alert-message error" style="display: none;"><i class="bi bi-exclamation-circle"></i><span></span></div>
                    <div id="regSuccess" class="alert-message success" style="display: none;"><i class="bi bi-check-circle"></i><span></span></div>
                    
                    <form id="registerForm">
                        <?= csrf_field(); ?>
                        <div class="form-group">
                            <label class="small text-secondary fw-bold mb-1">Full Name</label>
                            <div class="input-wrapper">
                                <input type="text" name="full_name" class="form-control" placeholder="Enter your full name" required>
                                <i class="bi bi-person"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="small text-secondary fw-bold mb-1">Email Address</label>
                            <div class="input-wrapper">
                                <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                                <i class="bi bi-envelope"></i>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="small text-secondary fw-bold mb-1">Age</label>
                                    <div class="input-wrapper">
                                        <input type="number" name="age" class="form-control" placeholder="Age" required min="1">
                                        <i class="bi bi-calendar-event"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="small text-secondary fw-bold mb-1">Gender</label>
                                    <div class="input-wrapper">
                                        <select name="gender" class="form-control" required style="background: var(--input-bg); color: var(--text-white); border: 1px solid rgba(255,255,255,0.1);">
                                            <option value="">Select</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                                        <i class="bi bi-gender-ambiguous"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="small text-secondary fw-bold mb-1">Height (cm) <span class="text-muted">(Skip for now)</span></label>
                                    <div class="input-wrapper">
                                        <input type="text" name="height" class="form-control" placeholder="Skip for now">
                                        <i class="bi bi-arrows-expand"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="small text-secondary fw-bold mb-1">Weight (kg) <span class="text-muted">(Skip for now)</span></label>
                                    <div class="input-wrapper">
                                        <input type="text" name="weight" class="form-control" placeholder="Skip for now">
                                        <i class="bi bi-speedometer2"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="small text-secondary fw-bold mb-1">Password</label>
                            <div class="input-wrapper">
                                <input type="password" name="password" class="form-control" id="regPass" placeholder="Create a password" required>
                                <i class="bi bi-lock"></i>
                                <span class="password-toggle" onclick="togglePass('regPass', this)"><i class="bi bi-eye"></i></span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="small text-secondary fw-bold mb-1">Confirm Password</label>
                            <div class="input-wrapper">
                                <input type="password" name="confirm_password" class="form-control" id="regConfirm" placeholder="Re-enter your password" required>
                                <i class="bi bi-lock-fill"></i>
                                <span class="password-toggle" onclick="togglePass('regConfirm', this)"><i class="bi bi-eye"></i></span>
                            </div>
                        </div>
                        <div class="mb-3 small text-secondary" id="pwHelp">Password must be at least 8 characters, include 1 uppercase letter and 1 symbol.</div>
                        <button type="submit" class="btn-submit">Register Account</button>
                    </form>
                </div>

                <!-- Forgot Password Panel -->
                <div class="form-panel" id="forgotPanel">
                    <h2 class="mb-1">Reset Password</h2>
                    <p class="text-secondary small mb-4">We'll send a password recovery link to your email.</p>
                    
                    <div id="forgotError" class="alert-message error" style="display: none;"><i class="bi bi-exclamation-circle"></i><span></span></div>
                    <div id="forgotSuccess" class="alert-message success" style="display: none;"><i class="bi bi-check-circle"></i><span></span></div>

                    <form id="forgotForm">
                        <?= csrf_field(); ?>
                        <div class="form-group">
                            <label class="small text-secondary fw-bold mb-1">Email Address</label>
                            <div class="input-wrapper">
                                <input type="email" name="email" class="form-control" placeholder="Enter your registered email" required>
                                <i class="bi bi-envelope"></i>
                            </div>
                        </div>
                        <button type="submit" class="btn-submit" id="forgotBtn">Send Reset Link</button>
                        <div class="text-center mt-4">
                            <a href="#" class="text-secondary small back-to-login"><i class="bi bi-arrow-left me-1"></i> Back to Login</a>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal: Payment Notice After Registration -->
    <div class="modal fade" id="paymentNoticeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="background: var(--darker-bg); color: var(--text-white);">
                <div class="modal-body p-4 text-center">
                    <div class="mb-3">
                        <i class="bi bi-info-circle-fill" style="font-size:3rem;color:var(--primary-red)"></i>
                    </div>
                    <h3 class="mb-2" style="font-family:'Oswald',sans-serif;letter-spacing:1px;">Registration Complete</h3>
                    <p class="text-secondary mb-4">Please settle your payment at the counter to log in to the system.</p>
                    <button type="button" class="btn-submit" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="loginRegAgeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="background: var(--darker-bg); color: var(--text-white);">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Age and Liability Confirmation</h5>
                    <button type="button" class="btn-close btn-close-white" data-age-cancel="1"></button>
                </div>
                <div class="modal-body pt-2">
                    <p class="text-secondary">Before continuing with your registration, please confirm that you meet the minimum age requirement and understand the risks associated with physical activities.</p>
                    <p class="text-secondary">Participation in gym activities and the use of fitness equipment involve inherent risks that may result in injury. By proceeding with the registration, you acknowledge that you are voluntarily participating in physical exercise and agree to assume full responsibility for your health and safety while using the gym facilities.</p>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="loginRegAgeLiabilityConfirm">
                        <label class="form-check-label text-white" for="loginRegAgeLiabilityConfirm">I confirm that I am 18 years old or above and I acknowledge the risks of physical exercise and release the gym from liability for injuries that may occur during participation.</label>
                    </div>
                    <div id="loginRegAgeError" class="small text-danger mt-2" style="display:none;">Please confirm age and liability to continue.</div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-light" data-age-cancel="1">Cancel</button>
                    <button type="button" class="btn-submit" id="loginRegAgeContinueBtn">Continue to Terms</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="loginRegTermsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="background: var(--darker-bg); color: var(--text-white);">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Terms and Privacy Agreement</h5>
                    <button type="button" class="btn-close btn-close-white" data-terms-cancel="1"></button>
                </div>
                <div class="modal-body pt-2">
                    <p class="text-secondary">To complete your registration, you must review and agree to the gym’s membership policies and data privacy practices. These policies explain the rules and responsibilities of gym members, as well as how your personal information will be collected, used, and protected by the system.</p>
                    <p class="text-secondary">Please read the following agreements carefully before proceeding.</p>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="loginRegTermsConfirm">
                        <label class="form-check-label text-white" for="loginRegTermsConfirm">I agree to the Terms and Conditions.</label>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="loginRegPrivacyConfirm">
                        <label class="form-check-label text-white" for="loginRegPrivacyConfirm">I agree to the Privacy Policy.</label>
                    </div>
                    <div id="loginRegTermsError" class="small text-danger mt-2" style="display:none;">Please agree to both Terms and Privacy to continue.</div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-light" data-terms-cancel="1">Back</button>
                    <button type="button" class="btn-submit" id="loginRegTermsCompleteBtn">Complete Registration</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Supabase SDK -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <script src="assets/js/supabase-config.php?v=<?= time() ?>"></script>
    <script>
        // Tab switching logic
        $('.auth-tab').click(function() {
            $('.auth-tab').removeClass('active');
            $(this).addClass('active');
            $('.form-panel').removeClass('active');
            $('#' + $(this).data('tab') + 'Panel').addClass('active');
            $('#loginError, #regError, #regSuccess, #forgotError, #forgotSuccess').hide();
            
            // Clear inputs when changing tabs
            $('#loginForm')[0].reset();
            $('#registerForm')[0].reset();
            $('#forgotForm')[0].reset();
        });

        // Switch to Forgot Password
        $('#toForgotPanel').click(function(e) {
            e.preventDefault();
            $('#authTabs').fadeOut(200);
            $('.form-panel').removeClass('active');
            $('#forgotPanel').addClass('active');
        });

        // Back to Login from Forgot
        $('.back-to-login').click(function(e) {
            e.preventDefault();
            $('#authTabs').fadeIn(200);
            $('.auth-tab[data-tab="login"]').click();
        });

        function togglePass(id, btn) {
            const el = document.getElementById(id);
            const icon = btn.querySelector('i');
            el.type = (el.type === 'password') ? 'text' : 'password';
            icon.classList.toggle('bi-eye');
            icon.classList.toggle('bi-eye-slash');
        }

        const urlParams = new URLSearchParams(window.location.search);
        const attendanceMode = urlParams.get('attendance') === '1';
        const ENABLE_SUPABASE_AUTH = <?= json_encode(((getenv('ENABLE_SUPABASE_AUTH') ?: ($_SERVER['ENABLE_SUPABASE_AUTH'] ?? '')) === '1')) ?>;

        function askLoginRegAgeLiability() {
            return new Promise((resolve) => {
                const modalEl = document.getElementById('loginRegAgeModal');
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                const checkbox = document.getElementById('loginRegAgeLiabilityConfirm');
                const error = document.getElementById('loginRegAgeError');
                const continueBtn = document.getElementById('loginRegAgeContinueBtn');
                const cancelBtns = modalEl.querySelectorAll('[data-age-cancel="1"]');
                let settled = false;

                checkbox.checked = false;
                error.style.display = 'none';

                const cleanup = () => {
                    continueBtn.removeEventListener('click', onContinue);
                    cancelBtns.forEach(btn => btn.removeEventListener('click', onCancel));
                    modalEl.removeEventListener('hidden.bs.modal', onHidden);
                };

                const finish = (value) => {
                    if (settled) return;
                    settled = true;
                    cleanup();
                    resolve(value);
                };

                const onContinue = () => {
                    if (!checkbox.checked) {
                        error.style.display = 'block';
                        return;
                    }
                    error.style.display = 'none';
                    modal.hide();
                    finish(true);
                };

                const onCancel = () => {
                    modal.hide();
                    finish(false);
                };

                const onHidden = () => {
                    finish(false);
                };

                continueBtn.addEventListener('click', onContinue);
                cancelBtns.forEach(btn => btn.addEventListener('click', onCancel));
                modalEl.addEventListener('hidden.bs.modal', onHidden, { once: true });
                modal.show();
            });
        }

        function askLoginRegTermsPrivacy() {
            return new Promise((resolve) => {
                const modalEl = document.getElementById('loginRegTermsModal');
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                const terms = document.getElementById('loginRegTermsConfirm');
                const privacy = document.getElementById('loginRegPrivacyConfirm');
                const error = document.getElementById('loginRegTermsError');
                const completeBtn = document.getElementById('loginRegTermsCompleteBtn');
                const cancelBtns = modalEl.querySelectorAll('[data-terms-cancel="1"]');
                let settled = false;

                terms.checked = false;
                privacy.checked = false;
                error.style.display = 'none';

                const cleanup = () => {
                    completeBtn.removeEventListener('click', onComplete);
                    cancelBtns.forEach(btn => btn.removeEventListener('click', onCancel));
                    modalEl.removeEventListener('hidden.bs.modal', onHidden);
                };

                const finish = (value) => {
                    if (settled) return;
                    settled = true;
                    cleanup();
                    resolve(value);
                };

                const onComplete = () => {
                    if (!terms.checked || !privacy.checked) {
                        error.style.display = 'block';
                        return;
                    }
                    error.style.display = 'none';
                    modal.hide();
                    finish(true);
                };

                const onCancel = () => {
                    modal.hide();
                    finish(false);
                };

                const onHidden = () => {
                    finish(false);
                };

                completeBtn.addEventListener('click', onComplete);
                cancelBtns.forEach(btn => btn.addEventListener('click', onCancel));
                modalEl.addEventListener('hidden.bs.modal', onHidden, { once: true });
                modal.show();
            });
        }

        $('#loginForm').submit(async function(e) {
            e.preventDefault();
            $('#loginError').hide();

            // show loading state
            const $btn = $(this).find('button[type=submit]');
            $btn.prop('disabled', true);
            $btn.find('.btn-label').hide();
            $btn.find('.btn-spinner').show();

            const email = $(this).find('input[name="email"]').val();
            const password = $(this).find('input[name="password"]').val();

            try {
                // 1. Supabase Auth Login (Optional but helpful for RLS)
                if (ENABLE_SUPABASE_AUTH && window.supabaseClient && window.supabaseClient.auth) {
                    const { data: authData, error: authError } = await window.supabaseClient.auth.signInWithPassword({
                        email: email,
                        password: password,
                    });

                    if (authError) {
                        console.error("Supabase Auth Error:", authError.message);
                    } else {
                        console.log("Supabase Auth success");
                    }
                } else {
                    if (ENABLE_SUPABASE_AUTH) {
                        console.warn("Supabase Client or Auth not initialized. Proceeding with PHP login only.");
                    }
                }

                // 2. PHP Backend Login (for session/traditional logic)
                let formData = $(this).serialize();
                if (attendanceMode) {
                    formData += '&attendance=1';
                }

                $.ajax({
                    url: 'login.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(data) {
                        if (data.status === 'success') {
                            $('#loginForm')[0].reset();
                            
                            if (data.redirect) {
                                window.location.href = data.redirect;
                                return;
                            }
                            if (data.role === 'admin') window.location.href = 'admin/dashboard.php';
                            else if (data.role === 'staff') window.location.href = 'staff/dashboard.php';
                            else window.location.href = 'member/dashboard.php';
                        } else {
                                $('#loginError').css('display', 'flex').find('span').text(data.message);
                                $btn.prop('disabled', false);
                                $btn.find('.btn-label').show();
                                $btn.find('.btn-spinner').hide();
                        }
                    },
                    error: function(xhr) {
                            let msg = "Connection error.";
                            if (xhr.status === 403) msg = "Security token mismatch. Please refresh the page.";
                            $('#loginError').css('display', 'flex').find('span').text(msg);
                            $btn.prop('disabled', false);
                            $btn.find('.btn-label').show();
                            $btn.find('.btn-spinner').hide();
                    }
                });
            } catch (err) {
                console.error("Login catch:", err);
                $('#loginError').css('display', 'flex').find('span').text("Login process failed.");
                $btn.prop('disabled', false);
            }
        });

        // AJAX Register
        $('#registerForm').submit(async function(e) {
            e.preventDefault();
            $('#regError, #regSuccess').hide();
            // Client-side validation
            const full_name = $(this).find('input[name="full_name"]').val();
            const email = $(this).find('input[name="email"]').val();
            const age = $(this).find('input[name="age"]').val();
            const gender = $(this).find('select[name="gender"]').val();
            const pw = $('#regPass').val() || '';
            const confirm = $('#regConfirm').val() || '';
            const pwRule = /^(?=.*[A-Z])(?=.*[!@#$%^&*()_+\-=[\]{};':"\\|,.<>\/?]).{8,}$/;

            if (!full_name || !email || !age || !gender || !pw || !confirm) {
                $('#regError').css('display', 'flex').find('span').text('Please fill in all required fields.');
                return;
            }
            if (!pwRule.test(pw)) {
                $('#regError').css('display', 'flex').find('span').text('Password must be at least 8 characters, include an uppercase letter and a symbol.');
                return;
            }
            if (pw !== confirm) {
                $('#regError').css('display', 'flex').find('span').text('Passwords do not match.');
                return;
            }

            const ageLiabilityOk = await askLoginRegAgeLiability();
            if (!ageLiabilityOk) {
                $('#regError').css('display', 'flex').find('span').text('You must confirm age and liability to continue.');
                return;
            }

            const termsPrivacyOk = await askLoginRegTermsPrivacy();
            if (!termsPrivacyOk) {
                $('#regError').css('display', 'flex').find('span').text('You must agree to terms and privacy to continue.');
                return;
            }

            try {
                // 1. Supabase Auth Signup
                if (ENABLE_SUPABASE_AUTH && window.supabaseClient && window.supabaseClient.auth) {
                    const { data: authData, error: authError } = await window.supabaseClient.auth.signUp({
                        email: email,
                        password: pw,
                        options: {
                            data: {
                                full_name: full_name,
                            }
                        }
                    });

                    if (authError) {
                        console.error("Supabase Signup Error:", authError.message);
                    }
                }

                // 2. PHP Backend Register
                $.post('register.php', $(this).serialize(), function(res) {
                    let data = (typeof res === 'object') ? res : JSON.parse(res);
                    if(data.status === 'success') {
                        $('#registerForm')[0].reset();
                        $('#regSuccess').css('display', 'flex').find('span').text(data.message);
                        const modal = new bootstrap.Modal(document.getElementById('paymentNoticeModal'));
                        modal.show();
                        // When modal closes, switch to login tab
                        document.getElementById('paymentNoticeModal').addEventListener('hidden.bs.modal', function() {
                            $('.auth-tab[data-tab="login"]').click();
                        }, { once: true });
                    } else {
                        $('#regError').css('display', 'flex').find('span').text(data.message);
                    }
                });
            } catch (err) {
                console.error("Signup catch:", err);
                $('#regError').css('display', 'flex').find('span').text("Registration process failed.");
            }
        });

        // Live validation feedback for register password fields
        $('#regPass, #regConfirm').on('input', function() {
            const pw = $('#regPass').val() || '';
            const confirm = $('#regConfirm').val() || '';
            const pwRule = /^(?=.*[A-Z])(?=.*[!@#$%^&*()_+\-=[\]{};':"\\|,.<>\/?]).{8,}$/;
            if (pw.length === 0 && confirm.length === 0) {
                $('#regError').hide();
                return;
            }
            if (!pwRule.test(pw)) {
                $('#regError').css('display', 'flex').find('span').text('Password must be at least 8 characters, include an uppercase letter and a symbol.');
                return;
            }
            if (confirm && pw !== confirm) {
                $('#regError').css('display', 'flex').find('span').text('Passwords do not match.');
                return;
            }
            $('#regError').hide();
        });

        // AJAX Forgot Password
        $('#forgotForm').submit(function(e) {
            e.preventDefault();
            $('#forgotError, #forgotSuccess').hide();
            const btn = $('#forgotBtn');
            btn.prop('disabled', true).text('Sending Link...');

            $.post('forgot_password.php', $(this).serialize(), function(res) {
                let data = (typeof res === 'object') ? res : JSON.parse(res);
                if(data.status === 'success') {
                    $('#forgotSuccess').css('display', 'flex').find('span').text(data.message);
                } else {
                    $('#forgotError').css('display', 'flex').find('span').text(data.message);
                }
                btn.prop('disabled', false).text('Send Reset Link');
            });
        });
    </script>
    <script>window.CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;</script>
    <script src="assets/js/global_attendance.js"></script>
</body>
</html>
