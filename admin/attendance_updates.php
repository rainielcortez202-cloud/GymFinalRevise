<?php
session_start();
header('Content-Type: application/json');
require '../connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

try {
    $stmt = $pdo->prepare("
        SELECT a.id, u.full_name, u.role, a.time_in, a.attendance_date
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        WHERE a.id > ?
        ORDER BY a.id DESC
    ");
    $stmt->execute([$last_id]);
    $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $updates]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>