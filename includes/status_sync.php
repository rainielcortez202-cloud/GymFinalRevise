<?php
/**
 * Synchronizes a user's status based on their membership (sales) validity.
 * 
 * @param PDO $pdo
 * @param int $user_id
 * @return string The new status ('active' or 'inactive')
 */
function syncUserStatus($pdo, $user_id) {
    // Check latest sale
    $stmt = $pdo->prepare("SELECT MAX(expires_at) as latest_expiry FROM sales WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $latest_expiry = $stmt->fetchColumn();

    $is_active = ($latest_expiry && strtotime($latest_expiry) > time());
    $new_status = $is_active ? 'active' : 'inactive';

    // Update users table
    $update = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'member'");
    $update->execute([$new_status, $user_id]);

    return $new_status;
}

/**
 * Bulk synchronizes all members' statuses.
 * 
 * @param PDO $pdo
 */
function bulkSyncMembers($pdo) {
    $sql = "
        WITH LatestSales AS (
            SELECT user_id, MAX(expires_at) as latest_expiry 
            FROM sales 
            GROUP BY user_id
        )
        SELECT u.id, u.status, ls.latest_expiry 
        FROM users u 
        LEFT JOIN LatestSales ls ON u.id = ls.user_id 
        WHERE u.role = 'member'
    ";
    $members = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($members as $m) {
        $calculated_active = ($m['latest_expiry'] && strtotime($m['latest_expiry']) > time());
        $new_status = $calculated_active ? 'active' : 'inactive';

        if ($m['status'] !== $new_status) {
            $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new_status, $m['id']]);
        }
    }
}
?>