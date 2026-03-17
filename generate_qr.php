<?php
// 1. Suppress errors so they don't break the image data
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 2. Check if library exists (Debugging)
$libPath = 'vendor/phpqrcode/qrlib.php';
if (!file_exists($libPath)) {
    header("HTTP/1.0 500 Internal Server Error");
    die("Library not found at: $libPath");
}

require $libPath;

if (isset($_GET['data'])) {
    $data = $_GET['data'];

    // 3. CRITICAL: Clear any whitespace or output sent before this point
    if (ob_get_length()) {
        ob_clean();
    }
    
    // 4. Send Image Headers
    header("Content-Type: image/png");
    
    // 5. Generate (false = stream to browser, L = Low error correction, 5 = size, 2 = margin)
    QRcode::png($data, false, QR_ECLEVEL_L, 5, 2);
    exit;
}
?>