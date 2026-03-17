<?php
session_start();
require '../auth.php';
require '../connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../login.php");
    exit;
}

// --- DATA FETCHING (PostgreSQL Syntax) ---
// 1. Total Active members
$total_members = $pdo->query("SELECT COUNT(*) FROM users WHERE role='member' AND status='active'")->fetchColumn();

// 2. Expiring Soon (Next 7 Days)
$expiring_soon = $pdo->query("
    SELECT COUNT(*) 
    FROM (
        SELECT user_id, MAX(expires_at) as latest_expiry 
        FROM sales 
        GROUP BY user_id
    ) sub
    WHERE latest_expiry BETWEEN NOW() AND NOW() + INTERVAL '7 days'
")->fetchColumn() ?? 0;

// 3. Attendance Today (MEMBERS ONLY)
$daily_attendance = $pdo->query("SELECT COUNT(*) FROM attendance WHERE date = CURRENT_DATE AND user_id IS NOT NULL")->fetchColumn() ?? 0;

// 4. Daily walk-in visitors count (WALK-INS ONLY)
$daily_walkins_count = $pdo->query("SELECT COUNT(*) FROM walk_ins WHERE visit_date::date = CURRENT_DATE")->fetchColumn() ?? 0;

// Recent attendance (last 5 MEMBERS)
$recent_attendance = $pdo->query("
    SELECT a.*, u.full_name 
    FROM attendance a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.date = CURRENT_DATE
    ORDER BY a.time_in DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Recent walk-ins (last 5 today)
$recent_walkins = $pdo->query("
    SELECT * FROM walk_ins 
    WHERE visit_date::date = CURRENT_DATE
    ORDER BY visit_date DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | Arts Gym</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-red: #e63946; --bg-body: #f8f9fa; --bg-card: #ffffff;
            --text-main: #1a1a1a; --text-muted: #8e8e93; --border-color: #f1f1f1;
            --sidebar-width: 260px; --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body.dark-mode-active {
            --bg-body: #0a0a0a; --bg-card: #121212; --text-main: #f5f5f7;
            --text-muted: #86868b; --border-color: #1c1c1e; --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        body { 
            font-family: 'Inter', sans-serif; background-color: var(--bg-body); 
            color: var(--text-main); transition: var(--transition); letter-spacing: -0.01em;
        }

        /* Layout Architecture */
        #sidebar {
            width: var(--sidebar-width); height: 100vh; position: fixed;
            left: 0; top: 0; z-index: 1100; transition: var(--transition);
        }

        #main {
            margin-left: var(--sidebar-width); transition: var(--transition);
            min-height: 100vh; padding: 2rem;
        }

        #main.expanded { margin-left: 80px; }
        #sidebar.collapsed { width: 80px; }

        .top-header { background: transparent; padding: 0 0 2rem 0; display: flex; align-items: center; justify-content: space-between; }

        /* Card Components */
        .card-box { 
            background: var(--bg-card); 
            border-radius: 20px; 
            padding: 24px; 
            display: flex; 
            align-items: center; 
            box-shadow: var(--card-shadow);
            height: 100%;
            border: none;
            transition: var(--transition);
        }
        .card-box:hover { transform: translateY(-5px); }

        .icon-box { 
            width: 54px; height: 54px; border-radius: 14px; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 22px; margin-right: 18px; 
            background: rgba(0, 0, 0, 0.03); color: var(--text-main);
            flex-shrink: 0;
        }

        .stat-label { color: var(--text-muted); font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
        .stat-value { font-size: 1.4rem; font-weight: 700; color: var(--text-main); }

        /* Table Styling */
        .table-card { background: var(--bg-card); border-radius: 20px; padding: 30px; box-shadow: var(--card-shadow); }
        .table { color: var(--text-main); }
        .table thead th { border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; padding: 15px; }
        .table tbody td { padding: 15px; border-bottom: 1px solid var(--border-color); }
        .dark-mode-active .table { --bs-table-bg: transparent; --bs-table-color: #fff; }

        @media (max-width: 991.98px) {
            #main { margin-left: 0 !important; padding: 1.5rem; }
            #sidebar { left: calc(var(--sidebar-width) * -1); }
            #sidebar.show { left: 0; box-shadow: 10px 0 30px rgba(0,0,0,0.1); }
            .sidebar-overlay {
                display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.5); z-index: 1090;
            }
            .sidebar-overlay.show { display: block; }
        }
    </style>
</head>
<body class="<?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'dark-mode-active' : '' ?>">

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <?php include __DIR__ . '/_sidebar.php'; ?>

    <div id="main">
        <header class="top-header">
            <div>
                <button class="btn btn-light d-lg-none me-2" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
                <h4 class="mb-0 fw-bold">Overview</h4>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php include '../global_clock.php'; ?>
            </div>
        </header>

        <!-- Stats Grid -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4 mb-5">
            <div class="col">
                <div class="card-box">
                    <div class="icon-box"><i class="bi bi-people"></i></div>
                    <div><div class="stat-label">Total Active</div><div class="stat-value"><?= number_format($total_members) ?></div></div>
                </div>
            </div>
            <div class="col">
                <div class="card-box">
                    <div class="icon-box" style="background:rgba(243,156,18,0.1); color:#f39c12;"><i class="bi bi-clock-history"></i></div>
                    <div><div class="stat-label">Expiring Soon</div><div class="stat-value"><?= $expiring_soon ?></div></div>
                </div>
            </div>
            <div class="col">
                <div class="card-box">
                    <div class="icon-box"><i class="bi bi-check2-all"></i></div>
                    <div><div class="stat-label">Attendance Today</div><div class="stat-value"><?= $daily_attendance ?></div></div>
                </div>
            </div>
            <div class="col">
                <div class="card-box">
                    <div class="icon-box"><i class="bi bi-person-badge"></i></div>
                    <div><div class="stat-label">Daily Walk-ins</div><div class="stat-value"><?= $daily_walkins_count ?></div></div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Tables -->
        <div class="row g-4">
            <div class="col-12 col-xl-6">
                <div class="table-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="fw-bold mb-0">Recent Attendance</h6>
                        <a href="attendance_register.php" class="btn btn-sm btn-light border fw-bold px-3" style="border-radius: 8px;">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Member Name</th><th class="text-end">Time In</th></tr></thead>
                            <tbody>
                                <?php if(count($recent_attendance) > 0): ?>
                                    <?php foreach($recent_attendance as $r): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($r['full_name']) ?></td>
                                        <td class="text-end">
                                            <span class="badge bg-danger-subtle text-danger border px-3">
                                                <?= date('h:i A', strtotime($r['time_in'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="2" class="text-center text-muted py-4">No attendance recorded today.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="table-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="fw-bold mb-0">Latest Walk-ins</h6>
                        <a href="daily.php" class="btn btn-sm btn-light border fw-bold px-3" style="border-radius: 8px;">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Visitor Name</th><th class="text-end">Arrival</th></tr></thead>
                            <tbody>
                                <?php if(count($recent_walkins) > 0): ?>
                                    <?php foreach($recent_walkins as $w): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($w['visitor_name']) ?></td>
                                        <td class="text-end">
                                            <span class="badge bg-success-subtle text-success border px-3">
                                                <?= date('h:i A', strtotime($w['visit_date'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="2" class="text-center text-muted py-4">No walk-ins registered today.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const main = document.getElementById('main');

            if (window.innerWidth < 992) {
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                main.classList.toggle('expanded');
            }
        }

        function toggleDarkMode() {
            const isDark = !document.body.classList.contains('dark-mode-active');
            document.cookie = "theme=" + (isDark ? "dark" : "light") + ";path=/;max-age=" + (30*24*60*60);
            location.reload(); 
        }
    </script>
</body>
</html>