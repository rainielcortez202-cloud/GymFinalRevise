<?php
session_start();
header('Content-Type: application/json');
require '../connection.php';

// Validate CSRF
validate_csrf();

require '../auth.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $age = intval($_POST['age'] ?? 0);
    $gender = trim($_POST['gender'] ?? '');
    $height = trim($_POST['height'] ?? '');
    $weight = trim($_POST['weight'] ?? '');
    $role = $_POST['role'] ?? 'member';

    // 1. Basic Empty Check
    if (empty($name) || empty($email) || empty($password) || empty($age) || empty($gender)) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
        exit;
    }

    // 2. Email Format Validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
        exit;
    }

    // 3. Password Strength Validation
    if (strlen($password) < 8) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters.']);
        exit;
    }
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        echo json_encode(['status' => 'error', 'message' => 'Password requires uppercase, lowercase, number, and special symbol.']);
        exit;
    }

    // 4. Duplicate Email Check (Explicit)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Email already registered.']);
        exit;
    }

    $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
    $qr = ($role === 'staff' ? "AG-S-" : "AG-M-") . strtoupper(bin2hex(random_bytes(3)));

    // is_verified = TRUE because created by admin
    // Members start as 'inactive' until they pay
    $initial_status = ($role === 'staff') ? 'active' : 'inactive';

    try {
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, status, qr_code, is_verified, created_at, age, gender, height, weight) VALUES (?, ?, ?, ?, ?, ?, TRUE, NOW(), ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $hashed_pass, $role, $initial_status, $qr, $age, $gender, $height, $weight]);

        // LOG ACTIVITY
        logActivity($pdo, $_SESSION['user_id'], $_SESSION['role'], 'CREATE_USER', "Created new $role: $name ($email)");

        echo json_encode(['status' => 'success', 'message' => ucfirst($role) . ' created successfully.']);
    }
    catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

if ($action === 'update') {
    $id = $_POST['id'] ?? 0;
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if (!$id || !$name || !$email) {
        echo json_encode(['status' => 'error', 'message' => 'Missing data.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
        exit;
    }

    // Duplicate check excluding self
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $id]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Email already taken by another user.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, status=? WHERE id=?");
    if ($stmt->execute([$name, $email, $status, $id])) {
        echo json_encode(['status' => 'success', 'message' => 'Updated successfully.']);
    }
    else {
        echo json_encode(['status' => 'error', 'message' => 'Update failed.']);
    }
}

if ($action === 'delete') {
    $id = $_POST['id'] ?? 0;
    if (!$id) {
        echo json_encode(['status' => 'error']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM sales WHERE user_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM attendance WHERE user_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        $pdo->commit();
        echo json_encode(['status' => 'success']);
    }
    catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>