<?php
include 'sidebar.php';
require '../auth.php';
require '../connection.php';

if ($_SESSION['role'] !== 'member') {
    header("Location: ../login.html");
    exit;
}

// Fetch QR code
$stmt = $pdo->prepare("SELECT qr_code FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$qr = $stmt->fetch()['qr_code'];
?>

<!DOCTYPE html>
<html>
<head>
<title>Your QR Code</title>
<script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
</head>
<body>
<h2>Your QR Code (Permanent)</h2>
<canvas id="qr"></canvas>

<script>
var qr = new QRious({
    element: document.getElementById('qr'),
    value: "<?php echo $qr; ?>",
    size: 250
});
</script>
</body>
</html>
