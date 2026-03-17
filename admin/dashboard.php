<?php
// admin/dashboard.php
session_start();
require '../auth.php';
require '../connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$current_year = date('Y');

/* ===================== DATA FETCHING ===================== */
$total_members = $pdo->query("SELECT COUNT(*) FROM users WHERE role='member' AND status='active'")->fetchColumn();
$expiring_soon = $pdo->query("
    SELECT COUNT(*) 
    FROM users u
    JOIN (SELECT user_id, MAX(expires_at) as latest_expiry FROM sales GROUP BY user_id) s ON u.id = s.user_id
    WHERE u.role = 'member' AND u.status = 'active'
    AND s.latest_expiry::DATE >= CURRENT_DATE AND s.latest_expiry::DATE <= (CURRENT_DATE + INTERVAL '7 days')
")->fetchColumn() ?? 0;

$daily_walkins = $pdo->query("SELECT COUNT(*) FROM walk_ins WHERE visit_date::DATE = CURRENT_DATE")->fetchColumn() ?? 0;
$daily_sales = $pdo->query("SELECT SUM(amount) FROM sales WHERE sale_date::DATE = CURRENT_DATE")->fetchColumn() ?? 0;
$monthly_sales = $pdo->query("SELECT SUM(amount) FROM sales WHERE EXTRACT(MONTH FROM sale_date) = EXTRACT(MONTH FROM CURRENT_DATE) AND EXTRACT(YEAR FROM sale_date) = EXTRACT(YEAR FROM CURRENT_DATE)")->fetchColumn() ?? 0;
$daily_attendance = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE date = CURRENT_DATE AND user_id IS NOT NULL")->fetchColumn() ?? 0;

/* ===================== RECENT DATA (ADMIN ONLY) ===================== */
// Latest 5 attendance (MEMBERS ONLY)
$recent_attendance = $pdo->query("
    SELECT a.*, u.full_name 
    FROM attendance a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.date = CURRENT_DATE
    ORDER BY a.time_in DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Latest 5 walk-ins
$recent_walkins = $pdo->query("
    SELECT * FROM walk_ins 
    WHERE visit_date::date = CURRENT_DATE
    ORDER BY visit_date DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

/* ===================== CHART DATA ===================== */
$monthly_sales_data = $pdo->query("SELECT EXTRACT(MONTH FROM sale_date) AS month, SUM(amount) AS total FROM sales WHERE EXTRACT(YEAR FROM sale_date) = $current_year GROUP BY EXTRACT(MONTH FROM sale_date)")->fetchAll(PDO::FETCH_KEY_PAIR);
$monthly_members_data = $pdo->query("SELECT EXTRACT(MONTH FROM created_at) AS month, COUNT(*) AS total FROM users WHERE role='member' AND EXTRACT(YEAR FROM created_at) = $current_year GROUP BY EXTRACT(MONTH FROM created_at)")->fetchAll(PDO::FETCH_KEY_PAIR);

$months = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
$monthly_sales_js = []; $monthly_members_js = [];
foreach(range(1,12) as $m){
    $monthly_sales_js[] = $monthly_sales_data[$m] ?? 0;
    $monthly_members_js[] = $monthly_members_data[$m] ?? 0;
}
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

        .card-box {
            background: var(--bg-card); border-radius: 20px; padding: 24px; 
            display: flex; align-items: center; box-shadow: var(--card-shadow); 
            height: 100%; border: none; transition: var(--transition);
        }
        .card-box:hover { transform: translateY(-5px); }

        .icon-box {
            width: 54px; height: 54px; border-radius: 14px; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 22px; margin-right: 18px; 
            background: rgba(0, 0, 0, 0.03); color: var(--text-main);
        }

        .stat-label {
            color: var(--text-muted); font-size: 0.7rem; font-weight: 700; 
            text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;
        }
        .stat-value { font-size: 1.4rem; font-weight: 700; color: var(--text-main); }

        .chart-card { background: var(--bg-card); border-radius: 20px; padding: 30px; box-shadow: var(--card-shadow); }
        .chart-container { position: relative; height: 320px; width: 100%; }

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

<?php include '_sidebar.php'; ?>

<div id="main">
    <header class="top-header">
        <div>
            <button class="btn btn-light d-lg-none me-2" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
            <h4 class="mb-0 fw-bold">Overview</h4>
            <p class="text-muted small mb-0">Gym Performance Analytics</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php include '../global_clock.php'; ?>
        </div>
    </header>

    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4 mb-5">
        <div class="col">
            <div class="card-box">
                <div class="icon-box"><i class="bi bi-people"></i></div>
                <div><div class="stat-label">Total Members</div><div class="stat-value"><?= number_format($total_members); ?></div></div>
            </div>
        </div>
        <div class="col">
            <div class="card-box">
                <div class="icon-box" style="background:rgba(243,156,18,0.1); color:#f39c12;"><i class="bi bi-clock-history"></i></div>
                <div><div class="stat-label">Expiring Soon</div><div class="stat-value"><?= $expiring_soon; ?></div></div>
            </div>
        </div>
        <div class="col">
            <div class="card-box">
                <div class="icon-box" style="background:rgba(230,57,70,0.1); color: var(--primary-red);"><i class="bi bi-lightning-charge"></i></div>
                <div><div class="stat-label">Today's Sales</div><div class="stat-value">₱<?= number_format($daily_sales); ?></div></div>
            </div>
        </div>
        <div class="col">
            <div class="card-box">
                <div class="icon-box"><i class="bi bi-calendar-check"></i></div>
                <div><div class="stat-label">Monthly Revenue</div><div class="stat-value">₱<?= number_format($monthly_sales); ?></div></div>
            </div>
        </div>
        <div class="col">
            <div class="card-box">
                <div class="icon-box"><i class="bi bi-check2-all"></i></div>
                <div><div class="stat-label">Attendance Today</div><div class="stat-value"><?= $daily_attendance; ?></div></div>
            </div>
        </div>
        <div class="col">
            <div class="card-box">
                <div class="icon-box"><i class="bi bi-person-badge"></i></div>
                <div><div class="stat-label">Daily Walk-ins</div><div class="stat-value"><?= $daily_walkins; ?></div></div>
            </div>
        </div>
    </div>

    <!-- Analytics Charts -->
    <div class="row g-4 mb-5">
        <div class="col-12 col-xl-8">
            <div class="chart-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h6 class="fw-bold mb-0">Revenue Analytics</h6>
                    <span class="badge bg-light text-dark border fw-medium px-3 py-2" style="border-radius: 8px;"><?= $current_year ?></span>
                </div>
                <div class="chart-container"><canvas id="revenueChart"></canvas></div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="chart-card">
                <h6 class="fw-bold mb-4">New Signups</h6>
                <div class="chart-container"><canvas id="membersChart"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Recent Lists (Added for Admin) -->
    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <div class="table-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h6 class="fw-bold mb-0">Recent Attendance</h6>
                    <a href="attendance.php" class="btn btn-sm btn-light border fw-bold px-3" style="border-radius: 8px;">View All</a>
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const isDark = document.body.classList.contains('dark-mode-active');
    
    const chartOptions = { 
        maintainAspectRatio: false, plugins: { legend: { display: false } }, 
        scales: { 
            y: { grid: { color: isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.03)' }, ticks: { color: isDark ? '#86868b' : '#8e8e93', font: { family: 'Inter', size: 11 } } }, 
            x: { grid: { display: false }, ticks: { color: isDark ? '#86868b' : '#8e8e93', font: { family: 'Inter', size: 11 } } } 
        } 
    };

    new Chart(document.getElementById('revenueChart'), { 
        type: 'line', 
        data: { 
            labels: <?= json_encode($months) ?>, 
            datasets: [{ 
                data: <?= json_encode($monthly_sales_js) ?>, 
                borderColor: '#e63946', borderWidth: 3, 
                backgroundColor: 'rgba(230, 57, 70, 0.05)', fill: true, tension: 0.4,
                pointRadius: 4, pointBackgroundColor: '#e63946'
            }] 
        }, 
        options: chartOptions 
    });

    new Chart(document.getElementById('membersChart'), { 
        type: 'bar', 
        data: { 
            labels: <?= json_encode($months) ?>, 
            datasets: [{ data: <?= json_encode($monthly_members_js) ?>, backgroundColor: isDark ? '#3a3a3c' : '#1a1a1a', borderRadius: 6 }] 
        }, 
        options: chartOptions 
    });

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