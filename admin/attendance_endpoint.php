<?php
/**
 * Unified Attendance Endpoint
 * Handles attendance recording for both Admin and Staff modules.
 */

// 1. Basic Setup & Headers
session_start();
header('Content-Type: application/json');
require '../connection.php';
require '../includes/status_sync.php';

validate_csrf();

// 2. Logging Function
function logAttendance($message) {
    $date = date('Y-m-d H:i:s');
    $logFile = __DIR__ . '/attendance_logs.txt';
    file_put_contents($logFile, "[$date] $message" . PHP_EOL, FILE_APPEND);
}

// 3. Authorization Check
// 3. Authorization Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Return specific status for JS to handle redirection
    echo json_encode(['status' => 'not_logged_in', 'message' => 'Please log in to record attendance.']);
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'staff'])) {
    // Logged in but not authorized
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Only Admin or Staff can record attendance.']);
    exit;
}

// 4. Input Handling
$input = json_decode(file_get_contents('php://input'), true);
$raw_qr = $input['qr_code'] ?? '';
$qr_code = trim($raw_qr);

if (empty($qr_code)) {
    logAttendance("Empty QR code received.");
    echo json_encode(['status' => 'error', 'message' => 'No QR code scanned.']);
    exit;
}

logAttendance("Processing QR: " . $qr_code);

try {
    // 5. User Look-up
    // Note: We check specifically for 'member' role.
    $stmt = $pdo->prepare("SELECT id, full_name, status, role FROM users WHERE qr_code = ?");
    $stmt->execute([$qr_code]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        logAttendance("User not found for QR: $qr_code");
        echo json_encode(['status' => 'error', 'message' => 'Member not found.', 'scanned_qr' => $qr_code]);
        exit;
    }

    if ($user['role'] !== 'member') {
        logAttendance("Scanned non-member role: " . $user['role']);
        echo json_encode(['status' => 'error', 'message' => 'Scanned QR belongs to ' . $user['role'] . ', not a member.']);
        exit;
    }

    if ($user['status'] !== 'active') {
        logAttendance("Inactive member scanned: " . $user['full_name']);
        echo json_encode(['status' => 'error', 'message' => 'Member is inactive.', 'member' => $user['full_name']]);
        exit;
    }

    // --- EXPIRATION CHECK & STATUS SYNC ---
    $new_status = syncUserStatus($pdo, $user['id']);

    if ($new_status === 'inactive') {
        logAttendance("Expired or Inactive member scanned: " . $user['full_name']);
        echo json_encode(['status' => 'error', 'message' => 'Membership Expired or Inactive.<br>Kindly pay at the counter.', 'member' => $user['full_name']]);
        exit;
    }
    // ---------------------------------------------

    // 6. Duplicate Check (Today)
    // Using CURRENT_DATE from Database to be consistent
    $stmt = $pdo->prepare("SELECT id, time_in FROM attendance WHERE user_id = ? AND date = CURRENT_DATE");
    $stmt->execute([$user['id']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        logAttendance("Duplicate scan for: " . $user['full_name']);
        
        // Format existing time for friendly message
        $timeIn = date('h:i A', strtotime($existing['time_in']));
        
        echo json_encode([
            'status' => 'warning', // 'warning' allows frontend to show yellow/orange instead of red
            'message' => 'Attendance already recorded today at ' . $timeIn,
            'name' => $user['full_name']
        ]);
        exit;
    }

    // 7. Record Attendance
    $stmt = $pdo->prepare("
        INSERT INTO attendance (user_id, date, time_in, attendance_date)
        VALUES (?, CURRENT_DATE, NOW(), CURRENT_DATE)
    ");
    $stmt->execute([$user['id']]);

    logAttendance("Success: " . $user['full_name']);

    echo json_encode([
        'status' => 'success',
        'message' => 'Attendance recorded successfully!',
        'name' => $user['full_name']
    ]);

} catch (PDOException $e) {
    logAttendance("Database Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'System Error: Could not record attendance.']);
}
?>
