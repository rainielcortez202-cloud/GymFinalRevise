<?php
session_start();
require '../connection.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    validate_csrf();

    $qr_code = $_POST['qr_code'] ?? '';
    if (!$qr_code) {
        echo json_encode(["status" => "error", "message" => "QR code missing"]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, full_name, status, role FROM users WHERE qr_code = ?");
    $stmt->execute([$qr_code]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member || $member['role'] !== 'member' || $member['status'] !== 'active') {
        echo json_encode(["status" => "error", "message" => "Member not found or inactive"]);
        exit;
    }

    $member_id = $member['id'];

    $stmt = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = CURRENT_DATE");
    $stmt->execute([$member_id]);
    if ($stmt->fetch()) {
        echo json_encode(["status" => "success", "message" => "Attendance already recorded"]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO attendance (user_id, date, time_in, attendance_date) VALUES (?, CURRENT_DATE, NOW(), CURRENT_DATE)");
    $stmt->execute([$member_id]);

    echo json_encode(["status" => "success", "message" => "Attendance recorded"]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>QR Scanner</title>
<style>
html, body { margin:0; padding:0; height:100%; background:#000; display:flex; justify-content:center; align-items:center; }
#reader { width: 100%; max-width: 600px; height: 100%; border: 2px solid #fff; }
#message { position:absolute; top:10px; width:100%; text-align:center; color:white; font-size:18px; }
</style>
</head>
<body>
<div id="message">Point camera at QR code</div>
<div id="reader"></div>

<script src="https://unpkg.com/html5-qrcode"></script>
<script>
window.CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
const reader = new Html5Qrcode("reader");

function onScanSuccess(decodedText) {
    reader.stop().then(() => {
        fetch("qr_code.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded", "X-CSRF-TOKEN": (window.CSRF_TOKEN || "") },
            body: "qr_code=" + encodeURIComponent(decodedText)
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            window.location.href = "attendance.php";
        });
    }).catch(err => console.error("Stop error:", err));
}

const config = {
    fps: 10,
    qrbox: { width:250, height:250 },
    experimentalFeatures: { useBarCodeDetectorIfSupported: true }
};

// Try environment first, fallback to user-facing
navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } })
.then(stream => {
    reader.start({ facingMode: "environment" }, config, onScanSuccess);
})
.catch(err => {
    console.warn("Environment camera failed, trying user-facing");
    reader.start({ facingMode: "user" }, config, onScanSuccess)
    .catch(err => alert("Camera error: " + err));
});
</script>
</body>
</html>
