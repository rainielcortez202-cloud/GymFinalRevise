<?php
require '../auth.php';
require '../connection.php';

    if ($_SESSION['role'] !== 'admin') {
        header("Location: ../login.php");
        exit;
    }

    // --- 2. PAGINATION & FILTERING ---
    $filter = isset($_GET['filter']) && $_GET['filter'] === '7days' ? '7days' : 'today';
    $per_page = 10;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $per_page;

    // Build WHERE clause based on filter
    if ($filter === 'today') {
        $where_clause = "a.date = CURRENT_DATE";
    } else {
        $where_clause = "a.date >= CURRENT_DATE - INTERVAL '7 days'";
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
    $stmt = $pdo->query("
        SELECT a.id, u.full_name, u.role, a.time_in, a.date as attendance_date, a.visitor_name
        FROM attendance a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE $where_clause
        ORDER BY a.date DESC, a.time_in DESC
        LIMIT $per_page OFFSET $offset
    ");
    $attendances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Attendance | Arts Gym</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

        <style>
            :root {
                --primary-red: #e63946;
                --bg-body: #f8f9fa;
                --bg-card: #ffffff;
                --text-main: #1a1a1a;
                --text-muted: #8e8e93;
                --border-color: #f1f1f1;
                --sidebar-width: 260px;
                --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
                --transition: all 0.3s ease;
            }

            body.dark-mode-active {
                --bg-body: #0a0a0a; --bg-card: #121212; --text-main: #f5f5f7;
                --text-muted: #86868b; --border-color: #1c1c1e; --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            }

            body { 
                font-family: 'Inter', sans-serif; 
                background-color: var(--bg-body); color: var(--text-main);
                transition: var(--transition); letter-spacing: -0.01em;
            }

            #main {
                margin-left: var(--sidebar-width);
                transition: var(--transition);
                min-height: 100vh;
                padding: 2rem;
            }
            #main.expanded { margin-left: 80px; }

            .top-header { background: transparent; padding: 0 0 2rem 0; display: flex; align-items: center; justify-content: space-between; }

            /* Table Container Refinement */
            .table-container {
                background: var(--bg-card); border-radius: 20px;
                box-shadow: var(--card-shadow); overflow: hidden;
                border: none;
            }

            .table { margin-bottom: 0; }
            .table thead th {
                background: var(--bg-card); text-transform: uppercase;
                font-size: 0.7rem; font-weight: 700; color: var(--text-muted);
                padding: 20px 24px; border-bottom: 1px solid var(--border-color);
                letter-spacing: 0.05em;
            }

            .table tbody td { padding: 18px 24px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
            .table tbody tr:last-child td { border-bottom: none; }

            /* Role Pills */
            .role-pill {
                font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
                padding: 4px 10px; border-radius: 6px; display: inline-block;
            }
            .pill-admin { background: rgba(0, 0, 0, 0.05); color: var(--text-main); }
            .pill-staff { background: rgba(52, 152, 219, 0.1); color: #3498db; }
            .pill-member { background: rgba(230, 57, 70, 0.1); color: var(--primary-red); }

            .time-text { font-weight: 600; font-size: 0.9rem; color: var(--text-main); }
            .date-text { color: var(--text-muted); font-size: 0.85rem; }

            @media (max-width: 991.98px) { #main { margin-left: 0 !important; padding: 1.5rem; } }
        </style>
    </head>
    <body class="<?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'dark-mode-active' : '' ?>">

    <?php include __DIR__ . '/_sidebar.php'; ?>

    <div id="main">
        <header class="top-header">
            <div>
                <button class="btn btn-light d-lg-none me-2" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
                <h4 class="mb-0 fw-bold">Attendance Logs</h4>
                <p class="text-muted small mb-0">Review activity from the last 30 days</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php include '../global_clock.php'; ?>
            </div>
        </header>

        <!-- Filter Buttons -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="btn-group" role="group">
                <a href="?filter=today" class="btn btn-sm <?= $filter === 'today' ? 'btn-dark' : 'btn-outline-secondary' ?>">Today</a>
                <a href="?filter=7days" class="btn btn-sm <?= $filter === '7days' ? 'btn-dark' : 'btn-outline-secondary' ?>">Last 7 Days</a>
            </div>
            <span class="text-muted small">Showing <?= count($attendances) ?> of <?= $total_records ?> records</span>
        </div>

        <div class="table-container">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Log ID</th>
                            <th>User Name</th>
                            <th>Account Type</th>
                            <th>Check-in</th>
                            <th class="text-end">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendances as $a): ?>
                        <tr>
                            <td>
                                <span class="text-muted font-monospace small">#<?= str_pad($a['id'], 5, '0', STR_PAD_LEFT) ?></span>
                            </td>
                            <td>
                                <?php if ($a['visitor_name']): ?>
                                    <div class="fw-bold text-main"><?= htmlspecialchars($a['visitor_name']) ?></div>
                                <?php else: ?>
                                    <div class="fw-semibold text-main"><?= htmlspecialchars($a['full_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($a['visitor_name']): ?>
                                    <span class="role-pill pill-staff">Daily Walk-in</span>
                                <?php elseif($a['role'] == 'admin'): ?>
                                    <span class="role-pill pill-admin">Admin</span>
                                <?php elseif($a['role'] == 'staff'): ?>
                                    <span class="role-pill pill-staff">Staff</span>
                                <?php else: ?>
                                    <span class="role-pill pill-member">Member</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="time-text">
                                    <i class="bi bi-clock me-2 text-muted" style="font-size: 0.8rem;"></i>
                                    <?= date("g:i A", strtotime($a['time_in'])) ?>
                                </div>
                            </td>
                            <td class="text-end">
                                <span class="date-text"><?= date("M d, Y", strtotime($a['attendance_date'])) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($attendances)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="opacity-50">
                                        <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                                        <span class="small">No logs found for this month.</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center align-items-center gap-2 p-3 border-top" style="border-color: var(--border-color) !important;">
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
