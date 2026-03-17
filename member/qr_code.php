<?php
// ----------------------
// member/qr_code.php
// ----------------------

// Include database connection
require '../connection.php';

// Include PHPQRCode library (check your path)
require '../vendor/phpqrcode/qrlib.php';

// Disable any output before headers
ini_set('display_errors', 0);
error_reporting(0);

// Get QR code string from URL parameter
$qr_code = $_GET['qr'] ?? '';

if (!$qr_code) {
    die('QR code missing!');
}

// Prepare safe query using named parameter
$stmt = $pdo->prepare('SELECT full_name FROM users WHERE qr_code = :qr');
$stmt->execute(['qr' => $qr_code]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die('Member not found!');
}

// Ensure the QR code string is UTF-8
$qr_code = mb_convert_encoding($qr_code, 'UTF-8');

// Clear buffer to prevent corrupted images
if (ob_get_length()) ob_clean();

// Set content type header for PNG
header('Content-Type: image/png');

// Automatically adjust size and error correction based on length
$length = strlen($qr_code);
if ($length < 20) {
    $size = 6;
    $eclevel = QR_ECLEVEL_L;
} elseif ($length < 50) {
    $size = 8;
    $eclevel = QR_ECLEVEL_M;
} else {
    $size = 10;
    $eclevel = QR_ECLEVEL_H;
}

// Generate the QR code directly to the browser
QRcode::png($qr_code, false, $eclevel, $size);
exit;
