<?php
// log_activity.php
require_once '../connection.php';

/**
 * Logs activity to activity_log table
 *
 * @param PDO $pdo
 * @param int $user_id
 * @param string $role 'admin' or 'staff'
 * @param string $action
 * @param int|null $member_id
 * @param array|null $details
 */
function log_activity($pdo, $user_id, $role, $action, $member_id = null, $details = null) {
    $details_json = $details ? json_encode($details) : null;
    $stmt = $pdo->prepare("
        INSERT INTO activity_log (user_id, role, action, member_id, details)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $role, $action, $member_id, $details_json]);
}
