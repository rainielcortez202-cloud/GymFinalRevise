<?php
require 'connection.php';

$token = $_GET['token'] ?? '';

if (!$token) {
    header("Location: login.php?error=invalid_request");
    exit;
}

try {
    // Check if token is in JSON format (email change) or plain (regular verification)
    $stmt = $pdo->prepare("SELECT id, email, verification_token, is_verified FROM users WHERE verification_token LIKE ?");
    $stmt->execute(["%$token%"]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: login.php?error=invalid_token");
        exit;
    }

    $token_data = json_decode($user['verification_token'], true);

    // Handle email change verification
    if (is_array($token_data) && isset($token_data['type']) && $token_data['type'] === 'email_change_verify') {
        if (!isset($token_data['verification_token']) || $token_data['verification_token'] !== $token) {
            header("Location: login.php?error=invalid_token");
            exit;
        }

        $new_email = $token_data['new_email'];
        $old_email = $token_data['old_email'];

        // Check if new email is already in use
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt_check->execute([$new_email, $user['id']]);
        if ($stmt_check->fetch()) {
            header("Location: login.php?error=email_in_use");
            exit;
        }

        // Update email and mark as verified
        $stmt_update = $pdo->prepare("
            UPDATE users
            SET email = ?,
                is_verified = TRUE,
                verification_token = NULL
            WHERE id = ?
        ");
        $stmt_update->execute([$new_email, $user['id']]);

        // Destroy any active session to force re-login
        session_destroy();

        header("Location: login.php?email_changed=1");
        exit;
    }

    // Handle regular email verification (plain token)
    if ($user['is_verified']) {
        header("Location: login.php?error=already_verified");
        exit;
    }

    // Check if token matches exactly (for regular verification)
    if ($user['verification_token'] !== $token) {
        header("Location: login.php?error=invalid_token");
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE users
        SET is_verified = TRUE,
            verification_token = NULL
        WHERE id = ?
    ");
    $stmt->execute([$user['id']]);

    header("Location: login.php?verified=1");
    exit;

} catch (Exception $e) {
    header("Location: login.php?error=server_error");
    exit;
}
