<?php
session_start();
require 'connection.php';

// Set Timezone to Philippines (matches your workflow)
date_default_timezone_set('Asia/Manila');

$token = $_GET['token'] ?? '';
$error = '';
$user = null; // Initialize user variable

// 1. VERIFY TOKEN ON PAGE LOAD
if (!$token) {
    die("Invalid request: No token provided.");
}

// PostgreSQL Logic: Check if token matches AND time has not passed
$stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > CURRENT_TIMESTAMP");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $error = "This link is invalid or has expired. Please request a new one via the Forgot Password page.";
}

// 2. HANDLE PASSWORD UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Hash and Update
        $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
        
        // PostgreSQL: Set NULL explicitly
        $updateStmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        
        if ($updateStmt->execute([$hashedPassword, $user['id']])) {
            // Success! Redirect to login
            header("Location: index.php?notice=password_updated"); // Changed to index.php (your login page)
            exit;
        } else {
            $error = "Database error. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | ARTS GYM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@600&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-red: #e63946; --dark-bg: #0a0a0a; --darker-bg: #050505;
            --text-white: #ffffff; --text-gray: #b0b0b0; --input-bg: rgba(255, 255, 255, 0.05);
        }
        body { font-family: 'Poppins', sans-serif; background: var(--dark-bg); color: var(--text-white); height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; }
        .reset-card { width: 100%; max-width: 450px; background: var(--darker-bg); padding: 40px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
        h2 { font-family: 'Oswald', sans-serif; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; }
        .form-group { margin-bottom: 20px; }
        .form-control { background: var(--input-bg); border: 2px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 14px; color: white; }
        .form-control:focus { background: rgba(230,57,70,0.05); border-color: var(--primary-red); color: white; box-shadow: none; }
        .btn-submit { width: 100%; background: linear-gradient(135deg, var(--primary-red), #9d0208); color: white; padding: 14px; border: none; border-radius: 12px; font-family: 'Oswald'; font-weight: 600; text-transform: uppercase; margin-top: 10px; }
        .alert-error { background: rgba(220,53,69,0.15); border: 1px solid rgba(220,53,69,0.3); color: #ff6b7a; padding: 12px; border-radius: 10px; font-size: 0.9rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
    </style>
</head>
<body>

    <div class="reset-card">
        <div class="text-center mb-4">
            <h2 class="text-white">Create New Password</h2>
            <p class="text-secondary small">Set a new secure password for your account.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-error">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?= $error ?></span>
            </div>
            <?php if (!$user): ?>
                <a href="index.php" class="btn btn-outline-secondary w-100 mt-2">Back to Login</a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($user): ?>
            <form method="POST">
                <!-- Using query string token to ensure it persists on post -->
                
                <div class="form-group">
                    <label class="small text-secondary fw-bold mb-1">New Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Minimum 8 characters" minlength="8" required>
                </div>

                <div class="form-group">
                    <label class="small text-secondary fw-bold mb-1">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" minlength="8" required>
                </div>

                <button type="submit" class="btn-submit">Update Password</button>
            </form>
        <?php endif; ?>
    </div>

</body>
</html>
