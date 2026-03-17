<?php
session_start();
require '../connection.php';
require '../auth.php';

// Ensure only admin or staff can access
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

validate_csrf();

// Get POST data
$name = trim($_POST['name'] ?? '');
$amount = $_POST['amount'] ?? 0;

// Validation
if (empty($name)) {
    echo json_encode(["status" => "error", "message" => "Visitor name is required"]);
    exit;
}

if (!is_numeric($amount) || $amount <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid amount"]);
    exit;
}

try {
    // Use transaction to ensure both inserts succeed
    $pdo->beginTransaction();

    // Insert walk-in record
    $stmt = $pdo->prepare("INSERT INTO walk_ins (visitor_name, amount, visit_date, checked_in_by) VALUES (?, ?, NOW(), ?)");
    $stmt->execute([$name, $amount, $_SESSION['user_id']]);
    $walkin_id = $pdo->lastInsertId();

    // Insert corresponding sales record so admin reports reflect this walk-in
    // For walk-ins we record user_id as NULL and no expiry
    $sales_stmt = $pdo->prepare("INSERT INTO sales (user_id, amount, sale_date, expires_at) VALUES (?, ?, NOW(), NULL)");
    $sales_stmt->execute([NULL, $amount]);

    // RECORD TO ATTENDANCE AS WALK-IN
    $stmt_att = $pdo->prepare("INSERT INTO attendance (user_id, visitor_name, date, time_in, attendance_date) VALUES (NULL, ?, CURRENT_DATE, NOW(), CURRENT_DATE)");
    $stmt_att->execute([$name]);

    // LOG ACTIVITY
    logActivity($pdo, $_SESSION['user_id'], $_SESSION['role'], 'ADD_WALKIN', "Added walk-in visitor: $name (₱$amount)");

    $pdo->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Walk-in visitor '$name' recorded successfully (₱$amount)",
        "walkin_id" => $walkin_id
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>
