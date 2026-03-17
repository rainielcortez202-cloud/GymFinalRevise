<?php
/**
 * auto_cleanup.php
 *
 * Retention lifecycle:
 * 1) Active tables keep records for configured days.
 * 2) Expired active records are moved to data_archive.
 * 3) Archive records older than configured archive days are purged.
 * 4) Daily local backup snapshots are written to storage/local_backups.
 */

function runAutoCleanup(PDO $pdo): void {

    try {
        // Throttle: only run once per day using the settings table
        $last_run_stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'last_auto_cleanup'");
        $last_run_stmt->execute();
        $last_run = $last_run_stmt->fetchColumn();

        if ($last_run && strtotime($last_run) >= strtotime('today')) {
            return; // Already ran today
        }
    } catch (Exception $e) {
        // settings table may not exist yet; proceed anyway
    }

    try {
        $getSettingDays = function(string $key, int $default) use ($pdo): int {
            try {
                $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
                $stmt->execute([$key]);
                $val = $stmt->fetchColumn();
                $days = is_numeric($val) ? (int)$val : $default;
                return $days > 0 ? $days : $default;
            } catch (Exception $e) {
                return $default;
            }
        };

        $activityDays = $getSettingDays('retention_activity_log_days', 180);
        $attendanceDays = $getSettingDays('retention_attendance_days', 90);
        $walkinsDays = $getSettingDays('retention_walkins_days', 30);
        $ipAttemptDays = $getSettingDays('retention_ip_login_attempt_days', 180);
        $unverifiedDays = $getSettingDays('retention_unverified_member_days', 7);
        $archiveDays = $getSettingDays('retention_archive_days', 365);

        $pdo->beginTransaction();

        $archiveByCondition = function(
            string $tableName,
            string $whereSql,
            array $bind,
            string $eventExpr = 'CURRENT_TIMESTAMP',
            string $pkExpr = 'id::text',
            string $joinSql = '',
            string $selectExpr = 'to_jsonb(src)'
        ) use ($pdo): void {
            $insertSql = "
                INSERT INTO data_archive (source_table, original_pk, payload, event_at, archived_at)
                SELECT ?, {$pkExpr}, {$selectExpr}, {$eventExpr}, CURRENT_TIMESTAMP
                FROM {$tableName} src
                {$joinSql}
                WHERE {$whereSql}
            ";
            $stmtInsert = $pdo->prepare($insertSql);
            $stmtInsert->execute(array_merge([$tableName], $bind));

            $deleteSql = "DELETE FROM {$tableName} WHERE id IN (SELECT id FROM {$tableName} src WHERE {$whereSql})";
            if ($pkExpr === 'ip_address') {
                $deleteSql = "DELETE FROM {$tableName} WHERE ip_address IN (SELECT ip_address FROM {$tableName} src WHERE {$whereSql})";
            }
            $stmtDelete = $pdo->prepare($deleteSql);
            $stmtDelete->execute($bind);
        };

        $archiveByCondition(
            'activity_log',
            "src.created_at < CURRENT_TIMESTAMP - (? || ' days')::interval",
            [(string)$activityDays],
            'src.created_at',
            'src.id::text',
            "LEFT JOIN users u ON src.user_id = u.id",
            "to_jsonb(src) || jsonb_build_object('full_name', u.full_name)"
        );
        $archiveByCondition(
            'attendance',
            "src.attendance_date < (CURRENT_DATE - (? || ' days')::interval)",
            [(string)$attendanceDays],
            "COALESCE(src.attendance_date::timestamp, src.date)",
            'src.id::text',
            "LEFT JOIN users u ON src.user_id = u.id",
            "to_jsonb(src) || jsonb_build_object('full_name', u.full_name)"
        );
        $archiveByCondition(
            'walk_ins',
            "src.visit_date < CURRENT_TIMESTAMP - (? || ' days')::interval",
            [(string)$walkinsDays],
            'src.visit_date'
        );
        $archiveByCondition(
            'ip_login_attempts',
            "COALESCE(src.updated_at, src.created_at, CURRENT_TIMESTAMP) < CURRENT_TIMESTAMP - (? || ' days')::interval",
            [(string)$ipAttemptDays],
            "COALESCE(src.updated_at, src.created_at, CURRENT_TIMESTAMP)",
            'src.ip_address'
        );
        $archiveByCondition(
            'users',
            "src.role = 'member' AND (src.is_verified = FALSE OR src.is_verified IS NULL) AND src.created_at < CURRENT_TIMESTAMP - (? || ' days')::interval",
            [(string)$unverifiedDays],
            'src.created_at'
        );

        $deleteArchivedStmt = $pdo->prepare("
            DELETE FROM data_archive
            WHERE archived_at < CURRENT_TIMESTAMP - (? || ' days')::interval
        ");
        $deleteArchivedStmt->execute([(string)$archiveDays]);

        // Update last run timestamp in settings
        $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at)
            VALUES ('last_auto_cleanup', NOW()::text, NOW())
            ON CONFLICT (setting_key) DO UPDATE
                SET setting_value = NOW()::text,
                    updated_at    = NOW()
        ")->execute();

        $pdo->commit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Silently fail — cleanup is best-effort, must not break the app
        error_log('[auto_cleanup] Error: ' . $e->getMessage());
    }
}
