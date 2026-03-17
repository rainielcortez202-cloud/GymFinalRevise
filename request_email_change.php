<?php
session_start();
require 'connection.php';
require 'auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Not authenticated"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
    exit;
}

validate_csrf();

$user_id = $_SESSION['user_id'];
$new_email = trim($_POST['new_email'] ?? '');

if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Please provide a valid email address"]);
    exit;
}

try {
    // Get current user data
    $stmt = $pdo->prepare("SELECT id, email, full_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(["status" => "error", "message" => "User not found"]);
        exit;
    }

    $current_email = $user['email'];

    // Check if new email is same as current
    if ($new_email === $current_email) {
        echo json_encode(["status" => "error", "message" => "New email must be different from current email"]);
        exit;
    }

    // Check if new email is already in use
    $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt_check->execute([$new_email, $user_id]);
    if ($stmt_check->fetch()) {
        echo json_encode(["status" => "error", "message" => "This email is already registered"]);
        exit;
    }

    // Generate approval token (for old email approval)
    $approval_token = bin2hex(random_bytes(16));
    
    // Store pending_email and approval_token
    // We'll use a JSON field or separate columns. For simplicity, store in verification_token as JSON
    // Format: {"type":"email_change","approval_token":"...","new_email":"..."}
    $token_data = json_encode([
        "type" => "email_change",
        "approval_token" => $approval_token,
        "new_email" => $new_email
    ]);

    $stmt_update = $pdo->prepare("UPDATE users SET verification_token = ? WHERE id = ?");
    $stmt_update->execute([$token_data, $user_id]);

    // Send approval email to CURRENT (old) email
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $currentDir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
    if ($currentDir == '/') { $currentDir = ''; }
    
    $approval_link = "$protocol://$host$currentDir/approve_email_change.php?token=$approval_token";

    $subject = "Email Change Request - Please Confirm";
    $bodyHtml = "
        <div style='font-family:Arial,sans-serif;padding:20px'>
            <h2>Email Change Request</h2>
            <p>Hi {$user['full_name']},</p>
            <p>We received a request to change the email address on your Arts Gym account.</p>
            <p><strong>Current email:</strong> $current_email</p>
            <p><strong>New email:</strong> $new_email</p>
            <p>If you requested this change, please click the button below to approve:</p>
            <p><a href='$approval_link' style='display:inline-block;padding:12px 25px;background:#e63946;color:#fff;text-decoration:none;border-radius:5px;font-weight:bold;margin-top:15px;'>APPROVE EMAIL CHANGE</a></p>
            <p style='margin-top:20px;font-size:12px;color:#777;'>
                If you did not request this email change, you can safely ignore this message. No changes will be made to your account.
            </p>
            <p style='font-size:12px;color:#777;'>
                This link will expire in 24 hours.
            </p>
        </div>
    ";

    require_once __DIR__ . '/includes/brevo_send.php';
    $sendRes = brevo_send_email($current_email, $user['full_name'], $subject, $bodyHtml);

    if ($sendRes['success']) {
        echo json_encode([
            "status" => "success",
            "message" => "Email change request sent! Please check your current email ($current_email) to approve the change."
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => $sendRes['message'] ?? "Failed to send email. Please try again later."
        ]);
    }

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Server error: " . $e->getMessage()]);
}

