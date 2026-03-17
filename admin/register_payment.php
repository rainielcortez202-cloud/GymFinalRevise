<?php
session_start();
require '../connection.php';

// Validate CSRF
validate_csrf();

require '../auth.php';

// Security: Only admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status"=>"error","message"=>"Unauthorized"]);
    exit;
}

$user_id  = intval($_POST['user_id'] ?? 0);
$amount   = floatval($_POST['amount'] ?? 0);
$duration = intval($_POST['duration'] ?? 0);

if (!$user_id || $amount <= 0 || !$duration) {
    echo json_encode(["status"=>"error","message"=>"Invalid payment data"]);
    exit;
}

// --- PREVENT DOUBLE ENTRY ---
$check = $pdo->prepare("SELECT expires_at FROM sales WHERE user_id = ? ORDER BY expires_at DESC LIMIT 1");
$check->execute([$user_id]);
$latest = $check->fetchColumn();

if ($latest && strtotime($latest) > time()) {
    echo json_encode(["status"=>"error","message"=>"Member is still active until " . date('M d, Y', strtotime($latest))]);
    exit;
}

// Calculate Expiry
$expires_at = date('Y-m-d H:i:s', strtotime("+$duration month"));

// 1. Insert into sales
$stmt = $pdo->prepare("INSERT INTO sales (user_id, amount, sale_date, expires_at) VALUES (?, ?, NOW(), ?)");
if ($stmt->execute([$user_id, $amount, $expires_at])) {
    
    // 2. UPDATE USER STATUS TO ACTIVE
    $update_status = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
    $update_status->execute([$user_id]);

    // 3. RECORD ACTIVITY LOG (Embedded Logic)
    $log_stmt = $pdo->prepare("INSERT INTO activity_log (user_id, role, action, member_id, details) VALUES (?, ?, 'Mark Payment', ?, ?)");
    $details = "Registered ₱$amount for $duration month(s). New Expiry: $expires_at";
    $log_stmt->execute([$_SESSION['user_id'], $_SESSION['role'], $user_id, $details]);

    echo json_encode(["status" => "success", "message" => "Payment registered successfully!"]);
} else {
    echo json_encode(["status" => "error", "message" => "Database error"]);
}
exit;