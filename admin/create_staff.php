<?php
session_start();
require '../connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status"=>"error","message"=>"Unauthorized"]);
    exit;
}

$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$contact = trim($_POST['contact_number'] ?? '');
$password = $_POST['password'] ?? '';

if (!$full_name || !$email || !$password || !$contact) {
    echo json_encode(["status"=>"error","message"=>"All fields are required"]);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email=?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode(["status"=>"error","message"=>"Email already exists"]);
    exit;
}

$hashed = password_hash($password, PASSWORD_DEFAULT);
$qr = "AG-S-" . strtoupper(bin2hex(random_bytes(3)));

$stmt = $pdo->prepare("INSERT INTO users (full_name, email, contact_number, password, role, status, qr_code, is_verified) VALUES (?, ?, ?, ?, 'staff', 'active', ?, 1)");
if ($stmt->execute([$full_name, $email, $contact, $hashed, $qr])) {
    echo json_encode(["status"=>"success","message"=>"Staff account created"]);
}