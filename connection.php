<?php
// Prevent direct access
if (basename($_SERVER['PHP_SELF']) == 'connection.php') {
    die('Direct access not permitted');
}

// Include Global Security Layer
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/env.php';

// Database Configuration (Environment-driven)
$host = getenv('SUPABASE_DB_HOST') ?: ($_SERVER['SUPABASE_DB_HOST'] ?? '');
$port = getenv('SUPABASE_DB_PORT') ?: ($_SERVER['SUPABASE_DB_PORT'] ?? '6543');
$db   = getenv('SUPABASE_DB_NAME') ?: ($_SERVER['SUPABASE_DB_NAME'] ?? 'postgres');
$user = getenv('SUPABASE_DB_USER') ?: ($_SERVER['SUPABASE_DB_USER'] ?? '');
$pass = getenv('SUPABASE_DB_PASSWORD') ?: ($_SERVER['SUPABASE_DB_PASSWORD'] ?? '');

// DATA SOURCE NAME (DSN)
// We add sslmode=require because Supabase requires a secure connection.
$dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";

// SUPABASE CONFIGURATION
require_once __DIR__ . '/includes/supabase_config.php';

// SET TIMEZONE TO PHILIPPINES
date_default_timezone_set('Asia/Manila');

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    if (!$host || !$user || !$pass) {
        throw new RuntimeException('Database credentials are not configured. Create .env.local (or .env) by copying .env.example, then set SUPABASE_DB_HOST, SUPABASE_DB_USER, SUPABASE_DB_PASSWORD.');
    }
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Set PostgreSQL session timezone
    $pdo->exec("SET TIME ZONE 'Asia/Manila'");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id SERIAL PRIMARY KEY,
            setting_key VARCHAR(255) UNIQUE NOT NULL,
            setting_value TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS walk_ins (
            id SERIAL PRIMARY KEY,
            visitor_name VARCHAR(255) NOT NULL,
            amount NUMERIC(10,2) NOT NULL DEFAULT 0,
            visit_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            checked_in_by INTEGER
        )
    ");
    // Harden schema if table already exists but with missing columns
    $pdo->exec("ALTER TABLE walk_ins ADD COLUMN IF NOT EXISTS visitor_name VARCHAR(255)");
    $pdo->exec("ALTER TABLE walk_ins ADD COLUMN IF NOT EXISTS amount NUMERIC(10,2) DEFAULT 0");
    $pdo->exec("ALTER TABLE walk_ins ADD COLUMN IF NOT EXISTS visit_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    $pdo->exec("ALTER TABLE walk_ins ADD COLUMN IF NOT EXISTS checked_in_by INTEGER");
    // Sales may require expires_at for membership but walk-ins set it to NULL
    $pdo->exec("ALTER TABLE sales ADD COLUMN IF NOT EXISTS expires_at TIMESTAMP NULL");
    $pdo->exec("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS visitor_name VARCHAR(255)");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS age INTEGER");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS gender VARCHAR(10)");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS height VARCHAR(20)");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS weight VARCHAR(20)");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_log (
            id SERIAL PRIMARY KEY,
            user_id INTEGER,
            role VARCHAR(50),
            action VARCHAR(100),
            details TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS data_archive (
            id SERIAL PRIMARY KEY,
            source_table VARCHAR(100) NOT NULL,
            original_pk VARCHAR(64),
            payload JSONB NOT NULL,
            event_at TIMESTAMP NULL,
            archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cold_backup_storage (
            id SERIAL PRIMARY KEY,
            source_table VARCHAR(100) NOT NULL,
            archive_id INTEGER NULL,
            payload JSONB NOT NULL,
            event_at TIMESTAMP NULL,
            archived_at TIMESTAMP NULL,
            backed_up_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL
        )
    ");
    $pdo->exec("ALTER TABLE ip_login_attempts ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    $pdo->exec("ALTER TABLE ip_login_attempts ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    // Realign sales.id sequence to avoid duplicate primary key after data imports
    try {
        $seqName = $pdo->query("SELECT pg_get_serial_sequence('sales','id')")->fetchColumn();
        if (!$seqName) {
            // Fallback: find identity/sequence backing 'sales.id'
            $seqName = $pdo->query("
                SELECT s.relname
                FROM pg_class s
                JOIN pg_depend d ON d.objid = s.oid
                JOIN pg_class t ON d.refobjid = t.oid
                JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = d.refobjsubid
                WHERE s.relkind = 'S' AND t.relname = 'sales' AND a.attname = 'id'
                LIMIT 1
            ")->fetchColumn();
        }
        if ($seqName) {
            $maxId = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM sales")->fetchColumn();
            $target = $maxId + 1;
            // With is_called = false, next nextval() returns exactly $target
            $pdo->query("SELECT setval(" . $pdo->quote($seqName) . ", " . $target . ", false)");
        }
    } catch (Exception $e) {
        // best-effort; do not block app if this fails
        error_log('[sequence_align] ' . $e->getMessage());
    }
    try {
        $seqName = $pdo->query("SELECT pg_get_serial_sequence('attendance','id')")->fetchColumn();
        if (!$seqName) {
            $seqName = $pdo->query("
                SELECT s.relname
                FROM pg_class s
                JOIN pg_depend d ON d.objid = s.oid
                JOIN pg_class t ON d.refobjid = t.oid
                JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = d.refobjsubid
                WHERE s.relkind = 'S' AND t.relname = 'attendance' AND a.attname = 'id'
                LIMIT 1
            ")->fetchColumn();
        }
        if ($seqName) {
            $maxId = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM attendance")->fetchColumn();
            $target = $maxId + 1;
            $pdo->query("SELECT setval(" . $pdo->quote($seqName) . ", " . $target . ", false)");
        }
    } catch (Exception $e) {
        error_log('[sequence_align_attendance] ' . $e->getMessage());
    }
    try {
        $seqName = $pdo->query("SELECT pg_get_serial_sequence('walk_ins','id')")->fetchColumn();
        if (!$seqName) {
            $seqName = $pdo->query("
                SELECT s.relname
                FROM pg_class s
                JOIN pg_depend d ON d.objid = s.oid
                JOIN pg_class t ON d.refobjid = t.oid
                JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = d.refobjsubid
                WHERE s.relkind = 'S' AND t.relname = 'walk_ins' AND a.attname = 'id'
                LIMIT 1
            ")->fetchColumn();
        }
        if ($seqName) {
            $maxId = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM walk_ins")->fetchColumn();
            $target = $maxId + 1;
            $pdo->query("SELECT setval(" . $pdo->quote($seqName) . ", " . $target . ", false)");
        }
    } catch (Exception $e) {
        error_log('[sequence_align_walkins] ' . $e->getMessage());
    }
    try {
        $seqName = $pdo->query("SELECT pg_get_serial_sequence('users','id')")->fetchColumn();
        if (!$seqName) {
            $seqName = $pdo->query("
                SELECT s.relname
                FROM pg_class s
                JOIN pg_depend d ON d.objid = s.oid
                JOIN pg_class t ON d.refobjid = t.oid
                JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = d.refobjsubid
                WHERE s.relkind = 'S' AND t.relname = 'users' AND a.attname = 'id'
                LIMIT 1
            ")->fetchColumn();
        }
        if ($seqName) {
            $maxId = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM users")->fetchColumn();
            $target = $maxId + 1;
            $pdo->query("SELECT setval(" . $pdo->quote($seqName) . ", " . $target . ", false)");
        }
    } catch (Exception $e) {
        error_log('[sequence_align_users] ' . $e->getMessage());
    }
    $runCleanup = true;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute(['auto_cleanup_last_run']);
        $lastRunRaw = $stmt->fetchColumn();
        $lastRun = $lastRunRaw ? (int)$lastRunRaw : 0;
        if ($lastRun > 0 && (time() - $lastRun) < 600) {
            $runCleanup = false;
        }
    } catch (Exception $e) {
        $runCleanup = true;
    }
    if ($runCleanup) {
        require_once __DIR__ . '/includes/auto_cleanup.php';
        runAutoCleanup($pdo);
        try {
            $stmt = $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value, updated_at)
                VALUES (?, ?, NOW())
                ON CONFLICT (setting_key)
                DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = NOW()
            ");
            $stmt->execute(['auto_cleanup_last_run', (string)time()]);
        } catch (Exception $e) {
        }
    }
    // echo "Connected successfully!"; 
} catch (\Throwable $e) {
    // If on XAMPP (Localhost), show the error.
    // If on Hostinger (Live), hide it for security.
    if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1') {
        die("Connection Failed: " . $e->getMessage());
    } else {
        // Log the actual error for admin review but show a generic message to user
        error_log("DB Connection Error: " . $e->getMessage());
        die("System Error. Please try again later. (Debug: " . $e->getMessage() . ")");
    }
}

if (!function_exists('logActivity')) {
    function logActivity($pdo, $userId, $role, $action, $details) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, role, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $role, $action, $details, $ip]);
    }
}
?>
