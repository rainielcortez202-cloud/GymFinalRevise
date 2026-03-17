<?php
session_start();
require '../auth.php';
require '../connection.php'; // Must use pgsql driver

// Access Control
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
}

$isLocalhost = function(): bool {
    $server = $_SERVER['SERVER_NAME'] ?? '';
    return ($server === 'localhost' || $server === '127.0.0.1');
};

$requireAdminPassword = function(PDO $pdo, int $adminId, string $password): bool {
    if ($adminId <= 0 || $password === '') return false;
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
    $stmt->execute([$adminId]);
    $hash = $stmt->fetchColumn();
    if (!$hash) return false;
    return password_verify($password, $hash);
};

$getSettingValue = function(PDO $pdo, string $key, string $default = ''): string {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        if ($val === false || $val === null) return $default;
        return (string)$val;
    } catch (Exception $e) {
        return $default;
    }
};

if (isset($_POST['update_retention'])) {
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    if (!$requireAdminPassword($pdo, (int)($_SESSION['user_id'] ?? 0), $confirmPassword)) {
        $error = "Admin password confirmation failed.";
    } else {
        $keys = [
            'retention_activity_log_days' => (int)($_POST['retention_activity_log_days'] ?? 180),
            'retention_attendance_days' => (int)($_POST['retention_attendance_days'] ?? 90),
            'retention_walkins_days' => (int)($_POST['retention_walkins_days'] ?? 30),
            'retention_ip_login_attempt_days' => (int)($_POST['retention_ip_login_attempt_days'] ?? 180),
            'retention_unverified_member_days' => (int)($_POST['retention_unverified_member_days'] ?? 7),
            'retention_archive_days' => (int)($_POST['retention_archive_days'] ?? 365),
        ];

        try {
            foreach ($keys as $k => $v) {
                $v = $v > 0 ? (string)$v : '1';
                $pdo->prepare("
                    INSERT INTO settings (setting_key, setting_value, updated_at)
                    VALUES (?, ?, CURRENT_TIMESTAMP)
                    ON CONFLICT (setting_key) DO UPDATE
                        SET setting_value = EXCLUDED.setting_value,
                            updated_at = CURRENT_TIMESTAMP
                ")->execute([$k, $v]);
            }
            logActivity($pdo, $_SESSION['user_id'], $_SESSION['role'], 'UPDATE_RETENTION', 'Updated retention settings');
            $message = "Retention settings updated successfully!";
        } catch (Exception $e) {
            $error = "Retention update failed: " . $e->getMessage();
        }
    }
}

// --- DATABASE EXPORT LOGIC ---
if (isset($_POST['export_db'])) {
    if (ob_get_length()) ob_end_clean();
    try {
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        if (!$requireAdminPassword($pdo, (int)($_SESSION['user_id'] ?? 0), $confirmPassword)) {
            throw new RuntimeException("Admin password confirmation failed.");
        }
        $tables = [];
        $query = $pdo->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'");
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $tables[] = $row['tablename'];
        }

        $sql_dump = "-- Arts Gym Supabase Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n";
        $sql_dump .= "SET statement_timeout = 0;\nSET client_encoding = 'UTF8';\n";
        $sql_dump .= "SET standard_conforming_strings = on;\nSET session_replication_role = 'replica';\n\n";

        $columnMetaStmt = $pdo->prepare("
            SELECT a.attname, a.attgenerated, a.attidentity
            FROM pg_attribute a
            JOIN pg_class c ON a.attrelid = c.oid
            JOIN pg_namespace n ON c.relnamespace = n.oid
            WHERE n.nspname = 'public'
              AND c.relname = ?
              AND a.attnum > 0
              AND NOT a.attisdropped
            ORDER BY a.attnum
        ");

        foreach ($tables as $table) {
            $sql_dump .= "-- Table: $table\nTRUNCATE TABLE \"$table\" RESTART IDENTITY CASCADE;\n";
            $columnMetaStmt->execute([$table]);
            $colsMeta = $columnMetaStmt->fetchAll(PDO::FETCH_ASSOC);

            $insertCols = [];
            $hasIdentityAlways = false;
            foreach ($colsMeta as $c) {
                if (!empty($c['attgenerated'])) {
                    continue;
                }
                $insertCols[] = $c['attname'];
                if (($c['attidentity'] ?? '') === 'a') {
                    $hasIdentityAlways = true;
                }
            }

            if (!$insertCols) {
                $sql_dump .= "\n";
                continue;
            }

            $selectColsSql = implode(', ', array_map(fn($col) => '"' . str_replace('"', '""', $col) . '"', $insertCols));
            $res = $pdo->query("SELECT {$selectColsSql} FROM \"$table\"");
            while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
                $keys = $insertCols;
                $values = [];
                foreach ($insertCols as $col) {
                    $values[] = $row[$col] ?? null;
                }
                $formattedValues = array_map(function($v) use ($pdo) {
                    if ($v === null) return "NULL";
                    if (is_bool($v)) return $v ? 'true' : 'false';
                    return $pdo->quote($v);
                }, $values);
                $override = $hasIdentityAlways ? " OVERRIDING SYSTEM VALUE" : "";
                $sql_dump .= "INSERT INTO \"$table\" (\"" . implode("\", \"", $keys) . "\"){$override} VALUES (" . implode(", ", $formattedValues) . ");\n";
            }
            $sql_dump .= "\n";
        }
        $sql_dump .= "SET session_replication_role = 'origin';\n";

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="artsgym_backup_' . date('Y-m-d_H-i') . '.sql"');
        echo $sql_dump;
        exit;
    } catch (Exception $e) {
        $error = "Export failed: " . $e->getMessage();
    }
}

// --- DATABASE IMPORT LOGIC ---
if (isset($_POST['import_db'])) {
    try {
        $allowImport = (getenv('ALLOW_DB_IMPORT') ?: ($_SERVER['ALLOW_DB_IMPORT'] ?? '')) === '1';
        if (!$allowImport || !$isLocalhost()) {
            throw new RuntimeException("Database restore is disabled in this environment.");
        }
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        if (!$requireAdminPassword($pdo, (int)($_SESSION['user_id'] ?? 0), $confirmPassword)) {
            throw new RuntimeException("Admin password confirmation failed.");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }

    if (!$error) {
        if ($_FILES['sql_file']['error'] == 0) {
            try {
                $sql = file_get_contents($_FILES['sql_file']['tmp_name']);
                $pdo->exec($sql);
                $message = "Database restored successfully!";
            } catch (Exception $e) {
                $error = "Import failed: " . $e->getMessage();
            }
        } else {
            $error = "Please select a valid .sql file.";
        }
    }
}

$retention_activity_log_days = (int)$getSettingValue($pdo, 'retention_activity_log_days', '180');
$retention_attendance_days = (int)$getSettingValue($pdo, 'retention_attendance_days', '90');
$retention_walkins_days = (int)$getSettingValue($pdo, 'retention_walkins_days', '30');
$retention_ip_login_attempt_days = (int)$getSettingValue($pdo, 'retention_ip_login_attempt_days', '180');
$retention_unverified_member_days = (int)$getSettingValue($pdo, 'retention_unverified_member_days', '7');
$retention_archive_days = (int)$getSettingValue($pdo, 'retention_archive_days', '365');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settings | Arts Gym</title>
    <!-- REFERENCE: Unified Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-red: #e63946;
            --dark-red: #9d0208;
            --bg-body: #f8f9fa;
            --bg-card: #ffffff;
            --text-main: #212529;
            --text-muted: #6c757d;
            --sidebar-width: 260px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body.dark-mode-active {
            --bg-body: #050505;
            --bg-card: #111111;
            --text-main: #ffffff;
            --text-muted: #b0b0b0;
        }

        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: var(--bg-body); 
            color: var(--text-main);
            transition: var(--transition);
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        /* REFERENCE: Dashboard Layout Logic */
        #main {
            margin-left: var(--sidebar-width);
            transition: var(--transition);
            min-height: 100vh;
        }

        .top-header {
            background: var(--bg-card);
            padding: 15px 25px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        /* REFERENCE: Dashboard Card Styling */
        .card-box { 
            background: var(--bg-card); 
            border-radius: 12px; 
            padding: 25px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            height: 100%;
            border: 1px solid rgba(0,0,0,0.05);
            transition: var(--transition);
        }

        .icon-box { 
            width: 48px; height: 48px; border-radius: 10px; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 20px; margin-bottom: 20px; 
            background: rgba(230, 57, 70, 0.1); color: var(--primary-red); 
        }

        /* REFERENCE: Dashboard Button Styling */
        .btn-red { 
            background: var(--primary-red); color: white; border-radius: 8px; 
            padding: 12px 20px; font-weight: 600; border: none; text-transform: uppercase; 
            font-family: 'Oswald'; transition: var(--transition); width: 100%;
        }
        .btn-red:hover { background: var(--dark-red); transform: translateY(-2px); color: white; }

        .btn-dark-custom {
            background: #212529; color: white; border-radius: 8px;
            padding: 12px 20px; font-weight: 600; border: none; text-transform: uppercase;
            font-family: 'Oswald'; transition: var(--transition); width: 100%;
        }
        .btn-dark-custom:hover { background: #000; transform: translateY(-2px); color: white; }

        .form-control {
            background: var(--bg-body);
            border: 1px solid rgba(0,0,0,0.1);
            color: var(--text-main);
            padding: 10px;
        }
        .dark-mode-active .form-control {
            background: #1a1a1a;
            border-color: #333;
            color: #fff;
        }

        @media (max-width: 991.98px) { #main { margin-left: 0 !important; } }
    </style>
</head>
<body class="<?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'dark-mode-active' : '' ?>">

    <?php include __DIR__ . '/_sidebar.php'; ?>

    <div id="main">
        <header class="top-header">
            <div class="d-flex align-items-center">
                <button class="btn btn-light d-lg-none me-3" onclick="toggleSidebar()">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <div class="d-none d-sm-block">
                    <h5 class="mb-0 fw-bold">Arts Gym Management</h5>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php include '../global_clock.php'; ?>
                <button class="btn btn-outline-secondary btn-sm rounded-circle" onclick="toggleDarkMode()">
                    <i class="bi <?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'bi-sun' : 'bi-moon' ?>"></i>
                </button>
            </div>
        </header>

        <div class="container-fluid p-3 p-md-4">
            <div class="mb-4">
                <h2 class="fw-bold mb-1">System Settings</h2>
                <p class="text-secondary small fw-bold">DATABASE BACKUP & RECOVERY TOOLS</p>
            </div>

            <!-- Alerts -->
            <?php if ($message): ?>
                <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show mb-4">
                    <i class="bi bi-check-circle-fill me-2"></i><?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger border-0 shadow-sm alert-dismissible fade show mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- EXPORT CARD -->
                <div class="col-12 col-xl-6">
                    <div class="card-box">
                        <div class="icon-box"><i class="bi bi-cloud-download"></i></div>
                        <h4 class="fw-bold mb-2">Export Records</h4>
                        <p class="text-muted small mb-4">Download a full PostgreSQL backup (.sql) of your entire gym system. This includes all active members, transactions, and historical data.</p>
                        <form method="POST">
                            <?= csrf_field(); ?>
                            <input type="password" name="confirm_password" class="form-control mb-3" placeholder="Confirm Admin Password" required>
                            <button type="submit" name="export_db" class="btn-red">
                                <i class="bi bi-file-earmark-arrow-down me-2"></i>Generate Backup
                            </button>
                        </form>
                    </div>
                </div>

                <!-- IMPORT CARD -->
                <div class="col-12 col-xl-6">
                    <div class="card-box" style="border-top: 4px solid var(--primary-red);">
                        <div class="icon-box"><i class="bi bi-cloud-upload"></i></div>
                        <h4 class="fw-bold mb-2">Restore Database</h4>
                        <p class="text-muted small mb-3">Upload an Arts Gym backup file. <span class="text-danger fw-bold">Warning: This will truncate current tables and replace them with backup data.</span></p>
                        <form method="POST" enctype="multipart/form-data">
                            <?= csrf_field(); ?>
                            <input type="file" name="sql_file" class="form-control mb-3" accept=".sql" required>
                            <input type="password" name="confirm_password" class="form-control mb-3" placeholder="Confirm Admin Password" required>
                            <button type="submit" name="import_db" class="btn-dark-custom" onclick="return confirm('WARNING: Are you sure you want to restore? Current data will be overwritten.')">
                                <i class="bi bi-arrow-repeat me-2"></i>Execute Restore
                            </button>
                            <div class="text-muted small mt-3">
                                Restore requires <span class="fw-bold">ALLOW_DB_IMPORT=1</span> and localhost environment.
                            </div>
                        </form>
                    </div>
                </div>

            </div>
            
            <div class="row g-4 mt-1">
                <div class="col-12 col-xl-6">
                    <div class="card-box">
                        <div class="icon-box"><i class="bi bi-shield-check"></i></div>
                        <h4 class="fw-bold mb-2">Retention Policy (Configurable)</h4>
                        <p class="text-muted small mb-4">Configure how long records are retained before automatic cleanup runs.</p>
                        <form method="POST">
                            <?= csrf_field(); ?>
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="small fw-bold text-uppercase text-muted mb-1 d-block">Activity Log (days)</label>
                                    <input type="number" min="1" name="retention_activity_log_days" class="form-control" value="<?= (int)$retention_activity_log_days ?>" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="small fw-bold text-uppercase text-muted mb-1 d-block">Attendance (days)</label>
                                    <input type="number" min="1" name="retention_attendance_days" class="form-control" value="<?= (int)$retention_attendance_days ?>" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="small fw-bold text-uppercase text-muted mb-1 d-block">Walk-ins (days)</label>
                                    <input type="number" min="1" name="retention_walkins_days" class="form-control" value="<?= (int)$retention_walkins_days ?>" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="small fw-bold text-uppercase text-muted mb-1 d-block">IP Login Attempts (days)</label>
                                    <input type="number" min="1" name="retention_ip_login_attempt_days" class="form-control" value="<?= (int)$retention_ip_login_attempt_days ?>" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="small fw-bold text-uppercase text-muted mb-1 d-block">Unverified Members (days)</label>
                                    <input type="number" min="1" name="retention_unverified_member_days" class="form-control" value="<?= (int)$retention_unverified_member_days ?>" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="small fw-bold text-uppercase text-muted mb-1 d-block">Archive Stage (days)</label>
                                    <input type="number" min="1" name="retention_archive_days" class="form-control" value="<?= (int)$retention_archive_days ?>" required>
                                </div>
                            </div>
                            <input type="password" name="confirm_password" class="form-control mt-3" placeholder="Confirm Admin Password" required>
                            <button type="submit" name="update_retention" class="btn-red mt-3">
                                <i class="bi bi-save me-2"></i>Save Retention Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleDarkMode() {
            const isDark = !document.body.classList.contains('dark-mode-active');
            document.cookie = "theme=" + (isDark ? "dark" : "light") + ";path=/;max-age=" + (30*24*60*60);
            location.reload(); 
        }
    </script>
</body>
</html>
