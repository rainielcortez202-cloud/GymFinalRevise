<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Optional: check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to project-root login.php (works from any subfolder)
    $segments = explode('/', trim(str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? ''), '/'));
    $base = '/' . ($segments[0] ?? '');
    header("Location: {$base}/login.php");
    exit;
}

// Enforce: unpaid members can't access the system
if (isset($_SESSION['role']) && $_SESSION['role'] === 'member') {
    require_once __DIR__ . '/connection.php';

    $userId = (int)($_SESSION['user_id'] ?? 0);
    $isPaidActive = false;

    if ($userId > 0) {
        // Latest payment record; NULL expires_at is treated as active/indefinite
        $stmt = $pdo->prepare("SELECT expires_at FROM sales WHERE user_id = ? ORDER BY sale_date DESC LIMIT 1");
        $stmt->execute([$userId]);
        $expiresAt = $stmt->fetchColumn();

        if ($expiresAt === false) {
            // No sales row => unpaid
            $isPaidActive = false;
        } elseif ($expiresAt === null) {
            // Has sales row but no expiry => active
            $isPaidActive = true;
        } else {
            $isPaidActive = (strtotime($expiresAt) > time());
        }
    }

    if (!$isPaidActive) {
        // Kill session and redirect to login with warning flag
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();

        $segments = explode('/', trim(str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? ''), '/'));
        $base = '/' . ($segments[0] ?? '');
        header("Location: {$base}/login.php?restricted=1");
        exit;
    }
}


