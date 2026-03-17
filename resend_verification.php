<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require 'connection.php';

// =====================
// HANDLE AJAX POST
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    validate_csrf();

    $email = trim($_POST['email'] ?? '');

    if (!$email) {
        echo json_encode(["status" => "error", "message" => "Email is required"]);
        exit;
    }

    // --- Fetch user ---
    $stmt = $pdo->prepare("SELECT id, full_name, is_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(["status" => "error", "message" => "Email not registered"]);
        exit;
    }

    if ((bool)$user['is_verified']) {
        echo json_encode(["status" => "error", "message" => "Email is already verified"]);
        exit;
    }

    // --- Generate new token ---
    $token = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("UPDATE users SET verification_token = ? WHERE id = ?");
    $stmt->execute([$token, $user['id']]);

    // --- Build verification link (with subfolder support) ---
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $currentDir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
    if ($currentDir == '/') { $currentDir = ''; }
    $verify_link = "$protocol://$host$currentDir/verify_email.php?token=$token";

    $htmlContent = "
        <div style='font-family:Arial,sans-serif;padding:20px'>
            <h2>Hi {$user['full_name']} 👋</h2>
            <p>Please verify your email by clicking below:</p>
            <a href='$verify_link'
               style='display:inline-block;background:#e63946;color:#fff;
               padding:12px 25px;text-decoration:none;border-radius:5px;font-weight:bold'>
               VERIFY EMAIL
            </a>
            <p style='margin-top:15px;font-size:12px;color:#777'>
                Or copy this link:<br>$verify_link
            </p>
        </div>
    ";

    require_once __DIR__ . '/includes/brevo_send.php';
    $result = brevo_send_email($email, $user['full_name'], "Verify Your Email - Arts Gym", $htmlContent);

    if ($result['success']) {
        echo json_encode(["status" => "success", "message" => "Verification email resent!"]);
    } else {
        echo json_encode(["status" => "error", "message" => $result['message']]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Resend Verification | Arts Gym</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body {
        background: #f4f4f4;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100vh;
    }
    .card {
        padding: 30px;
        border-radius: 15px;
        max-width: 400px;
        width: 100%;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
</style>
</head>

<body>

<div class="card">
    <h4 class="text-center fw-bold mb-3">Resend Verification Email</h4>

    <form id="resendForm">
        <?= csrf_field(); ?>
        <div class="mb-3">
            <input type="email" name="email" class="form-control" placeholder="Registered email" required>
        </div>

        <button type="submit" id="resendBtn" class="btn btn-danger w-100 fw-bold">
            <span id="btnText">RESEND EMAIL</span>
            <span id="spinner" class="spinner-border spinner-border-sm d-none"></span>
        </button>
    </form>

    <div id="message" class="mt-3 text-center small"></div>
</div>

<script>
document.getElementById("resendForm").addEventListener("submit", function(e){
    e.preventDefault();

    const btn = document.getElementById("resendBtn");
    const btnText = document.getElementById("btnText");
    const spinner = document.getElementById("spinner");
    const msg = document.getElementById("message");

    btn.disabled = true;
    spinner.classList.remove("d-none");
    btnText.textContent = "SENDING...";

    fetch("resend_verification.php", {
        method: "POST",
        body: new FormData(this)
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        spinner.classList.add("d-none");
        btnText.textContent = "RESEND EMAIL";

        msg.textContent = data.message;
        msg.className = "mt-3 text-center " + (data.status === "success" ? "text-success" : "text-danger");
    })
    .catch(() => {
        btn.disabled = false;
        spinner.classList.add("d-none");
        btnText.textContent = "RESEND EMAIL";
        msg.textContent = "Network error. Try again.";
        msg.className = "mt-3 text-center text-danger";
    });
});
</script>

</body>
</html>

