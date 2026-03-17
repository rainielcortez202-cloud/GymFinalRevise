<?php
session_start();
require 'connection.php';

if (isset($_SESSION['user_id'])) {
    // 1. LOG THE LOGOUT ACTIVITY
    $log_stmt = $pdo->prepare("
        INSERT INTO activity_log (user_id, role, action, details, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $log_stmt->execute([
        $_SESSION['user_id'], 
        $_SESSION['role'], 
        'Logout', 
        'User logged out safely'
    ]);
}

// 2. Destroy Session
session_unset();
session_destroy();

header("Location: login.php");
exit;