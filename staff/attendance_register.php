<?php
session_start();
require '../connection.php';

// Authorization Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../login.php");
    exit;
}


// --- PAGINATION & FILTERING ---
$filter = isset($_GET['filter']) && $_GET['filter'] === '7days' ? '7days' : 'today';
$per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// Build WHERE clause based on filter
if ($filter === 'today') {
    $where_clause = "a.attendance_date = CURRENT_DATE";
} else {
    $where_clause = "a.attendance_date >= CURRENT_DATE - INTERVAL '7 days'";
}

// Count total records
$count_stmt = $pdo->query("
    SELECT COUNT(*) FROM attendance a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE $where_clause
");
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Fetch paginated attendance records
$attendanceRecords = $pdo->query("
    SELECT a.id, u.full_name, a.attendance_date, a.time_in, a.visitor_name, u.role
    FROM attendance a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE $where_clause
    ORDER BY a.time_in DESC
    LIMIT $per_page OFFSET $offset
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attendance | Arts Gym</title>
    
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
            --sidebar-width: 260px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body.dark-mode-active {
            --bg-body: #050505;
            --bg-card: #111111;
            --text-main: #ffffff;
        }

        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: var(--bg-body); 
            color: var(--text-main);
            transition: var(--transition);
        }

        h2, h5, h6 { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        #main {
            margin-left: var(--sidebar-width);
            transition: var(--transition);
            min-height: 100vh;
        }

        #main.expanded { margin-left: 80px; }

        /* Sidebar Layout */
        #sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1100;
            transition: var(--transition);
        }
        #sidebar.collapsed { width: 80px; }

        .top-header {
            background: var(--bg-card);
            padding: 15px 25px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 1000;
        }

        .card-box { 
            background: var(--bg-card); 
            border-radius: 12px; 
            padding: 25px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .table { color: var(--text-main); }
        .dark-mode-active .table { --bs-table-color: #fff; --bs-table-bg: transparent; }

        @media (max-width: 991.98px) {
            #main { margin-left: 0 !important; }
            #sidebar { left: calc(var(--sidebar-width) * -1); }
            #sidebar.show { left: 0; box-shadow: 10px 0 30px rgba(0,0,0,0.1); }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1090; }
            .sidebar-overlay.show { display: block; }
        }
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
                <h5 class="mb-0 fw-bold">Attendance Records</h5>
            </div>
            <div class="d-flex align-items-center">
                <?php include '../global_clock.php'; ?>
            </div>
        </header>

        <div class="container-fluid p-4">
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="card-box">
                        <!-- Filter Buttons -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="btn-group" role="group">
                                <a href="?filter=today" class="btn btn-sm <?= $filter === 'today' ? 'btn-dark' : 'btn-outline-secondary' ?>">Today</a>
                                <a href="?filter=7days" class="btn btn-sm <?= $filter === '7days' ? 'btn-dark' : 'btn-outline-secondary' ?>">Last 7 Days</a>
                            </div>
                            <span class="text-muted small">Showing <?= count($attendanceRecords) ?> of <?= $total_records ?> records</span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="m-0 fw-bold">Recent Scans</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr class="small text-muted">
                                        <th>NAME</th>
                                        <th>TYPE</th>
                                        <th>TIME</th>
                                        <th>DATE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($attendanceRecords) > 0): ?>
                                        <?php foreach($attendanceRecords as $rec): ?>
                                        <tr>
                                            <td class="fw-bold">
                                                <?php if ($rec['visitor_name']): ?>
                                                    <?= htmlspecialchars($rec['visitor_name']) ?>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($rec['full_name']) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($rec['visitor_name']): ?>
                                                    <span class="badge bg-info text-dark">Daily Walk-in</span>
                                                <?php elseif($rec['role'] == 'staff'): ?>
                                                    <span class="badge bg-secondary">Staff</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Member</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-dark"><?= date('h:i A', strtotime($rec['time_in'])) ?></span>
                                            </td>
                                            <td class="text-secondary small">
                                                <?= date('M d, Y', strtotime($rec['attendance_date'])) ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center text-muted">No records today.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="d-flex justify-content-center align-items-center gap-2 mt-3 pt-3 border-top">
                            <?php if ($page > 1): ?>
                                <a href="?filter=<?= $filter ?>&page=<?= $page - 1 ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-chevron-left"></i> Previous
                                </a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary" disabled>
                                    <i class="bi bi-chevron-left"></i> Previous
                                </button>
                            <?php endif; ?>

                            <span class="text-muted small">Page <?= $page ?> of <?= $total_pages ?></span>

                            <?php if ($page < $total_pages): ?>
                                <a href="?filter=<?= $filter ?>&page=<?= $page + 1 ?>" class="btn btn-sm btn-outline-secondary">
                                    Next <i class="bi bi-chevron-right"></i>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary" disabled>
                                    Next <i class="bi bi-chevron-right"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </script>
</body>
</html>