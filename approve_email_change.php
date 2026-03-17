<?php
require 'connection.php';
session_start();

$token = $_GET['token'] ?? '';

if (!$token) {
    header("Location: login.php?error=invalid_token");
    exit;
}

try {
    // Find user with this approval token
    $stmt = $pdo->prepare("SELECT id, email, full_name, verification_token FROM users WHERE verification_token LIKE ?");
    $stmt->execute(["%$token%"]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$user['verification_token']) {
        header("Location: login.php?error=invalid_token");
        exit;
    }

    $token_data = json_decode($user['verification_token'], true);

    // Check if this is an email change request and token matches
    if (!isset($token_data['type']) || $token_data['type'] !== 'email_change' || 
        !isset($token_data['approval_token']) || $token_data['approval_token'] !== $token ||
        !isset($token_data['new_email'])) {
        header("Location: login.php?error=invalid_token");
        exit;
    }

    $new_email = $token_data['new_email'];
    $user_id = $user['id'];

    // Validate new email format
    if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid new email format: " . $new_email);
        header("Location: login.php?error=invalid_email");
        exit;
    }

    // Check if new email is already in use
    $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt_check->execute([$new_email, $user_id]);
    if ($stmt_check->fetch()) {
        header("Location: login.php?error=email_in_use");
        exit;
    }

    // Log for debugging
    error_log("Sending verification email to new address: " . $new_email . " for user ID: " . $user_id);

    // Generate verification token for NEW email
    $verification_token = bin2hex(random_bytes(16));
    
    // Update: store new token data for new email verification
    $new_token_data = json_encode([
        "type" => "email_change_verify",
        "verification_token" => $verification_token,
        "old_email" => $user['email'],
        "new_email" => $new_email
    ]);

    $stmt_update = $pdo->prepare("UPDATE users SET verification_token = ? WHERE id = ?");
    $stmt_update->execute([$new_token_data, $user_id]);

    // Send verification email to NEW email
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $currentDir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
    if ($currentDir == '/') { $currentDir = ''; }
    
    $verify_link = "$protocol://$host$currentDir/verify_email.php?token=$verification_token";

    $subject = "Verify Your New Email Address - Arts Gym";
    $bodyHtml = "
        <div style='font-family:Arial,sans-serif;padding:20px'>
            <h2>Verify Your New Email Address</h2>
            <p>Hi {$user['full_name']},</p>
            <p>Your email change request has been approved. Please verify your new email address by clicking the button below:</p>
            <p><strong>New email:</strong> $new_email</p>
            <p><a href='$verify_link' style='display:inline-block;padding:12px 25px;background:#e63946;color:#fff;text-decoration:none;border-radius:5px;font-weight:bold;margin-top:15px;'>VERIFY NEW EMAIL</a></p>
            <p style='margin-top:20px;font-size:12px;color:#777;'>
                If you did not request this change, please contact support immediately.
            </p>
        </div>
    ";

    require_once __DIR__ . '/includes/brevo_send.php';
    $sendRes = brevo_send_email($new_email, $user['full_name'], $subject, $bodyHtml);

    if (!$sendRes['success']) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Email Error</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #f8f9fa; }
                .card { max-width: 500px; padding: 30px; }
            </style>
        </head>
        <body>
            <div class="card shadow">
                <div class="text-center">
                    <div class="mb-3"><i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 3rem;"></i></div>
                    <h4 class="mb-3">Email Sending Error</h4>
                    <p class="text-muted">There was an error sending the verification email. Please try requesting the email change again.</p>
                    <a href="login.php" class="btn btn-danger mt-3">Go to Login</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    // Show success page only if email was sent successfully
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Change Approved</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #f8f9fa; }
            .card { max-width: 500px; padding: 30px; }
        </style>
    </head>
    <body>
        <div class="card shadow">
            <div class="text-center">
                <div class="mb-3"><i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i></div>
                <h4 class="mb-3">Email Change Approved</h4>
                <p class="text-muted">We've sent a verification email to your new email address:</p>
                <p class="text-muted"><strong><?= htmlspecialchars($new_email) ?></strong></p>
                <p class="text-muted small">Please check your inbox (and spam folder) and click the verification link to complete the email change.</p>
                <a href="login.php" class="btn btn-danger mt-3">Go to Login</a>
            </div>
        </div>
    </body>
    </html>
    <?php

} catch (Exception $e) {
    header("Location: login.php?error=server_error");
    exit;
}
