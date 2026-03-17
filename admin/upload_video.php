<?php
// admin/upload_video.php
session_start();
require '../connection.php'; // ensure this path to connection is correct

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if (empty($_FILES['file'])) {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'Upload error Code: ' . $file['error']]);
    exit;
}

$allowed = ['video/mp4', 'video/webm', 'video/ogg'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only MP4/WebM are accepted. Your mime: ' . $mime]);
    exit;
}

// create target dir
$targetDir = __DIR__ . '/uploads/exercises';
if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
if (!$ext) $ext = 'mp4';
$name = bin2hex(random_bytes(8)) . '.' . $ext;
$targetPath = $targetDir . '/' . $name;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file']);
    exit;
}

// Return web-accessible path relative to app root
$relativeUrl = 'admin/uploads/exercises/' . $name;

echo json_encode(['status' => 'success', 'url' => $relativeUrl]);
?>
