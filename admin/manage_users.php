<?php
session_start();
require '../auth.php';
require '../connection.php';
require '../includes/status_sync.php';

if ($_SESSION['role'] !== 'admin') { header("Location: ../login.php"); exit; }

$current = basename($_SERVER['PHP_SELF']);
$filter = $_GET['filter'] ?? 'all';

// --- SELECTIVE SYNCING FOR PERFORMANCE ---
// Instead of bulkSyncMembers($pdo), we sync only the members we're about to show.
// Move this below the member fetching logic.

// --- PAGINATION (Max 10 per request) ---
$m_page = isset($_GET['m_page']) ? max(1, (int)$_GET['m_page']) : 1;
$s_page = isset($_GET['s_page']) ? max(1, (int)$_GET['s_page']) : 1;
$limit = 10;
$m_offset = ($m_page - 1) * $limit;
$s_offset = ($s_page - 1) * $limit;

// --- SEARCH LOGIC ---
$m_search = $_GET['m_search'] ?? '';
$s_search = $_GET['s_search'] ?? '';

// --- SORT LOGIC ---
$m_sort = $_GET['m_sort'] ?? 'newest';
$s_sort = $_GET['s_sort'] ?? 'newest';

function getOrderBy($sort) {
    if ($sort === 'oldest') return "u.id ASC";
    if ($sort === 'az') return "u.full_name ASC";
    if ($sort === 'za') return "u.full_name DESC";
    return "u.id DESC"; // newest
}

$m_order = getOrderBy($m_sort);
$s_order = getOrderBy($s_sort);

// --- ROBUST POSTGRESQL MEMBER QUERY USING LATERAL JOIN ---
if ($filter === 'expiring') {
    $m_sql_base = "
        FROM users u 
        JOIN LATERAL (
            SELECT expires_at as latest_expiry 
            FROM sales 
            WHERE user_id = u.id 
            ORDER BY expires_at DESC 
            LIMIT 1
        ) ls ON true 
        WHERE u.role = 'member' AND u.status = 'active'
        AND ls.latest_expiry >= CURRENT_TIMESTAMP AND ls.latest_expiry <= (CURRENT_TIMESTAMP + INTERVAL '7 days')
    ";
    if ($m_search) {
        $m_sql_base .= " AND (u.full_name ILIKE " . $pdo->quote('%' . $m_search . '%') . " OR u.email ILIKE " . $pdo->quote('%' . $m_search . '%') . ")";
    }
    
    $m_sql = "SELECT u.*, ls.latest_expiry " . $m_sql_base . " ORDER BY ls.latest_expiry ASC";
    $m_total = $pdo->query("SELECT COUNT(*) " . $m_sql_base)->fetchColumn();
} else {
    $m_sql_base = "
        FROM users u 
        LEFT JOIN LATERAL (
            SELECT expires_at as latest_expiry 
            FROM sales 
            WHERE user_id = u.id 
            ORDER BY expires_at DESC 
            LIMIT 1
        ) s ON true 
        WHERE u.role = 'member'
    ";
    if ($m_search) {
        $m_sql_base .= " AND (u.full_name ILIKE " . $pdo->quote('%' . $m_search . '%') . " OR u.email ILIKE " . $pdo->quote('%' . $m_search . '%') . ")";
    }
    
    $m_sql = "SELECT u.*, s.latest_expiry " . $m_sql_base . " ORDER BY $m_order";
    $m_total = $pdo->query("SELECT COUNT(*) " . $m_sql_base)->fetchColumn();
}

$s_sql_base = "FROM users u WHERE u.role='staff'";
if ($s_search) {
    $s_sql_base .= " AND (u.full_name ILIKE " . $pdo->quote('%' . $s_search . '%') . " OR u.email ILIKE " . $pdo->quote('%' . $s_search . '%') . ")";
}
$s_total = $pdo->query("SELECT COUNT(*) " . $s_sql_base)->fetchColumn();

$m_total_pages = ceil($m_total / $limit);
$s_total_pages = ceil($s_total / $limit);

$m_sql .= " LIMIT $limit OFFSET $m_offset";
$members = $pdo->query($m_sql)->fetchAll(PDO::FETCH_ASSOC);

// --- SYNC ONLY VISIBLE MEMBERS ---
if (!empty($members)) {
    foreach ($members as &$m) {
        $calculated_active = ($m['latest_expiry'] && strtotime($m['latest_expiry']) > time());
        $new_status = $calculated_active ? 'active' : 'inactive';
        if ($m['status'] !== $new_status) {
            $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new_status, $m['id']]);
            $m['status'] = $new_status;
        }
    }
}
$staffs  = $pdo->query("SELECT u.* " . $s_sql_base . " ORDER BY $s_order LIMIT $limit OFFSET $s_offset")->fetchAll(PDO::FETCH_ASSOC);

function maskEmailPHP($email) {
    if (!$email) return 'N/A';
    $parts = explode("@", $email);
    if (count($parts) < 2) return $email;
    $name = $parts[0];
    $len = strlen($name);
    if ($len <= 4) { 
        $maskedName = substr($name, 0, 1) . str_repeat('*', max(3, $len - 1)); 
    } else { 
        $maskedName = substr($name, 0, 2) . str_repeat('*', $len - 3) . substr($name, -1); 
    }
    return $maskedName . "@" . $parts[1];
}

// --- AJAX HANDLER FOR LIVE SEARCH (MEMBERS & STAFF) ---
if (isset($_GET['ajax_m']) || isset($_GET['ajax_s'])) {
    ob_start();
    if (isset($_GET['ajax_m'])) {
        if (empty($members)) {
            echo '<tr><td colspan="5" class="text-center text-muted py-4">No members found matching "' . htmlspecialchars($m_search) . '"</td></tr>';
        } else {
            foreach($members as $m): 
                $latest = $m['latest_expiry'];
                $is_active = ($m['status'] === 'active');
                $qr_data = $m['qr_code'] ?: $m['id'];
                ?>
                <tr>
                    <td class="fw-bold name-cell"><?= htmlspecialchars($m['full_name']) ?></td>
                    <td style="font-family: monospace; color: #666;"><?= maskEmailPHP($m['email']) ?></td>
                    <td class="text-center"><button class="btn btn-sm btn-outline-dark border-0" onclick="viewQR('<?= $qr_data ?>','<?= addslashes($m['full_name']) ?>')"><i class="bi bi-qr-code fs-5"></i></button></td>
                    <td><span class="badge <?= $is_active?'bg-success-subtle text-success':'bg-danger-subtle text-danger' ?> border px-3"><?= $is_active?'Active':'Inactive' ?></span></td>
                    <td>
                        <?php if (!$is_active): ?>
                            <div class="d-flex gap-2">
                                <select class="form-select form-select-sm rate-select" style="width: 130px;"><option value="400">Student (400)</option><option value="500" selected>Regular (500)</option></select>
                                <button class="btn btn-dark btn-sm fw-bold" onclick="pay(<?= $m['id'] ?>, this)">PAY</button>
                            </div>
                        <?php else: ?>
                            <small class="fw-bold text-success text-uppercase">Until: <?= date('M d, Y', strtotime($m['latest_expiry'])) ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach;
        }
        $rows = ob_get_clean();
        ob_start();
        if ($m_total_pages > 1): ?>
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <li class="page-item <?= ($m_page <= 1) ? 'disabled' : '' ?>"><a class="page-link" href="?filter=<?= $filter ?>&m_sort=<?= $m_sort ?>&s_sort=<?= $s_sort ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>&m_page=<?= $m_page-1 ?>&s_page=<?= $s_page ?>">Previous</a></li>
                <?php for($i=1; $i<=$m_total_pages; $i++): ?><li class="page-item <?= ($i == $m_page) ? 'active' : '' ?>"><a class="page-link" href="?filter=<?= $filter ?>&m_sort=<?= $m_sort ?>&s_sort=<?= $s_sort ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>&m_page=<?= $i ?>&s_page=<?= $s_page ?>"><?= $i ?></a></li><?php endfor; ?>
                <li class="page-item <?= ($m_page >= $m_total_pages) ? 'disabled' : '' ?>"><a class="page-link" href="?filter=<?= $filter ?>&m_sort=<?= $m_sort ?>&s_sort=<?= $s_sort ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>&m_page=<?= $m_page+1 ?>&s_page=<?= $s_page ?>">Next</a></li>
            </ul>
        <?php endif;
        $pagination = ob_get_clean();
    } else {
        if (empty($staffs)) {
            echo '<tr><td colspan="3" class="text-center text-muted py-4">No staff found matching "' . htmlspecialchars($s_search) . '"</td></tr>';
        } else {
            foreach($staffs as $s): ?>
                <tr>
                    <td class="fw-bold name-cell"><?= htmlspecialchars($s['full_name']) ?></td>
                    <td style="font-family: monospace; color: #666;"><?= maskEmailPHP($s['email']) ?></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary border-0 me-2" onclick="editUser(<?= $s['id'] ?>,'<?= addslashes($s['full_name']) ?>','<?= addslashes($s['email']) ?>','staff','<?= $s['status'] ?>')"><i class="bi bi-pencil-square"></i></button>
                        <button class="btn btn-sm btn-outline-danger border-0" onclick="delUser(<?= $s['id'] ?>)"><i class="bi bi-trash3"></i></button>
                    </td>
                </tr>
            <?php endforeach;
        }
        $rows = ob_get_clean();
        ob_start();
        if ($s_total_pages > 1): ?>
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <li class="page-item <?= ($s_page <= 1) ? 'disabled' : '' ?>"><a class="page-link" href="?filter=<?= $filter ?>&m_sort=<?= $m_sort ?>&s_sort=<?= $s_sort ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>&m_page=<?= $m_page ?>&s_page=<?= $s_page-1 ?>">Previous</a></li>
                <?php for($i=1; $i<=$s_total_pages; $i++): ?><li class="page-item <?= ($i == $s_page) ? 'active' : '' ?>"><a class="page-link" href="?filter=<?= $filter ?>&m_sort=<?= $m_sort ?>&s_sort=<?= $s_sort ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>&m_page=<?= $m_page ?>&s_page=<?= $i ?>"><?= $i ?></a></li><?php endfor; ?>
                <li class="page-item <?= ($s_page >= $s_total_pages) ? 'disabled' : '' ?>"><a class="page-link" href="?filter=<?= $filter ?>&m_sort=<?= $m_sort ?>&s_sort=<?= $s_sort ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>&m_page=<?= $m_page ?>&s_page=<?= $s_page+1 ?>">Next</a></li>
            </ul>
        <?php endif;
        $pagination = ob_get_clean();
    }
    header('Content-Type: application/json');
    echo json_encode(['rows' => $rows, 'pagination' => $pagination]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Users | Arts Gym</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Supabase SDK -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <script src="../assets/js/supabase-config.php"></script>
    
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

        h1, h2, h3, h4, h5 { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        #sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; left: 0; top: 0; z-index: 1100; transition: var(--transition); }
        #main { margin-left: var(--sidebar-width); transition: var(--transition); min-height: 100vh; padding: 2rem; }
        #main.expanded { margin-left: 80px; }
        #sidebar.collapsed { width: 80px; }

        @media (max-width: 991.98px) {
            #main { margin-left: 0 !important; padding: 1.5rem; }
            #sidebar { left: calc(var(--sidebar-width) * -1); }
            #sidebar.show { left: 0; box-shadow: 10px 0 30px rgba(0,0,0,0.1); }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1090; }
            .sidebar-overlay.show { display: block; }
            #main.expanded { margin-left: var(--sidebar-width) !important; }
        }

        .card-table { background: var(--bg-card); border-radius: 20px; padding: 24px; box-shadow: var(--card-shadow); border: none; margin-bottom: 2rem; }
        .table thead th { background: var(--bg-card); color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; border-bottom: 1px solid var(--border-color); padding: 15px; white-space: nowrap; }
        .table tbody td { padding: 15px; color: var(black); border-bottom: 1px solid var(--border-color); }
        
        .col-qr { width: 80px; text-align: center; }

        .btn-primary-gym { background: var(--primary-red); color: white; border: none; border-radius: 10px; font-weight: 600; padding: 10px 20px; }
        .btn-primary-gym:hover { background: #d62839; color: white; }
        .top-header { background: transparent; padding: 0 0 2rem 0; display: flex; align-items: center; justify-content: space-between; }
        
        .table-responsive { max-height: 450px; overflow-y: auto; }
        .table thead th { position: sticky; top: 0; z-index: 5; }
    </style>
</head>
<body class="<?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'dark-mode-active' : '' ?>">

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<?php include '_sidebar.php'; ?>

<div id="main">
    <header class="top-header">
        <div>
            <button class="btn btn-light d-lg-none me-2" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
            <h4 class="mb-0 fw-bold">User Management</h4>
            <p class="text-muted small mb-0">ADMIN PANEL</p>
             
        </div>
        
        <div class="d-flex align-items-center gap-3">
           <?php include '../global_clock.php'; ?>  
        </div>
    </header>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="btn-group bg-white p-1 rounded-3 shadow-sm border">
            <a href="?filter=all&m_sort=<?= $m_sort ?>&s_sort=<?= $s_sort ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>" class="btn btn-sm <?= $filter=='all'?'btn-danger active':'btn-light' ?> px-4 fw-bold" style="border-radius: 8px;">All Members</a>
            <a href="?filter=expiring&m_sort=<?= $m_sort ?>&s_sort=<?= $s_sort ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>" class="btn btn-sm <?= $filter=='expiring'?'btn-danger active':'btn-light' ?> px-4 fw-bold" style="border-radius: 8px;">Expiring Soon</a>
        </div>
        <button class="btn btn-dark btn-sm fw-bold" onclick="openAddModal('member')">
            Add Member
        </button>
    </div>

    <!-- MEMBERS TABLE -->
    <div class="card-table">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h6 class="fw-bold mb-0">Members Directory</h6>
            <div class="d-flex gap-2">
                <div class="dropdown">
                    <button class="btn btn-light btn-sm fw-bold shadow-sm border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-sort-down me-1"></i> Sort
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li><a class="dropdown-item <?= $m_sort=='newest'?'active':'' ?>" href="?filter=<?= $filter ?>&m_sort=newest&s_sort=<?= $s_sort ?>&s_page=<?= $s_page ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>">Newest First</a></li>
                        <li><a class="dropdown-item <?= $m_sort=='oldest'?'active':'' ?>" href="?filter=<?= $filter ?>&m_sort=oldest&s_sort=<?= $s_sort ?>&s_page=<?= $s_page ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>">Oldest First</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item <?= $m_sort=='az'?'active':'' ?>" href="?filter=<?= $filter ?>&m_sort=az&s_sort=<?= $s_sort ?>&s_page=<?= $s_page ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>">Name (A-Z)</a></li>
                        <li><a class="dropdown-item <?= $m_sort=='za'?'active':'' ?>" href="?filter=<?= $filter ?>&m_sort=za&s_sort=<?= $s_sort ?>&s_page=<?= $s_page ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>">Name (Z-A)</a></li>
                    </ul>
                </div>
                <input type="text" id="mSearch" class="form-control bg-light border-0" style="width: 250px;" placeholder="Search name..." value="<?= htmlspecialchars($m_search) ?>">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle" id="mTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th class="col-qr">QR Pass</th>
                        <th>Status</th>
                        <th>Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($members as $m): 
                        $latest = $m['latest_expiry'];
                        $is_active = ($m['status'] === 'active');
                        $qr_data = $m['qr_code'] ?: $m['id'];
                    ?>
                    <tr>
                        <td class="fw-bold name-cell"><?= htmlspecialchars($m['full_name']) ?></td>
                        <td style="font-family: monospace; color: #666;"><?= maskEmailPHP($m['email']) ?></td>
                        <!-- QR BUTTON -->
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-dark border-0" onclick="viewQR('<?= $qr_data ?>','<?= addslashes($m['full_name']) ?>')">
                                <i class="bi bi-qr-code fs-5"></i>
                            </button>
                        </td>
                        <td><span class="badge <?= $is_active?'bg-success-subtle text-success':'bg-danger-subtle text-danger' ?> border px-3"><?= $is_active?'Active':'Inactive' ?></span></td>
                        <td>
                            <?php if (!$is_active): ?>
                                <div class="d-flex gap-2">
                                    <select class="form-select form-select-sm rate-select" style="width: 130px;">
                                        <option value="400">Student (400)</option><option value="500" selected>Regular (500)</option>
                                    </select>
                                    <button class="btn btn-dark btn-sm fw-bold" onclick="pay(<?= $m['id'] ?>, this)">PAY</button>
                                </div>
                            <?php else: ?>
                                <small class="fw-bold text-success text-uppercase">Until: <?= date('M d, Y', strtotime($m['latest_expiry'])) ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination Members -->
        <div id="mPagination" class="mt-3">
            <?php if ($m_total_pages > 1): ?>
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item <?= ($m_page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?filter=<?= $filter ?>&m_sort=<?= $m_sort ?>&s_sort=<?= $s_sort ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>&m_page=<?= $m_page-1 ?>&s_page=<?= $s_page ?>">Previous</a>
                    </li>
                    <?php for($i=1; $i<=$m_total_pages; $i++): ?>
                        <li class="page-item <?= ($i == $m_page) ? 'active' : '' ?>">
                            <a class="page-link" href="?filter=<?= $filter ?>&m_sort=<?= $m_sort ?>&s_sort=<?= $s_sort ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>&m_page=<?= $i ?>&s_page=<?= $s_page ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($m_page >= $m_total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?filter=<?= $filter ?>&m_sort=<?= $m_sort ?>&s_sort=<?= $s_sort ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>&m_page=<?= $m_page+1 ?>&s_page=<?= $s_page ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>

    <!-- STAFF TABLE -->
    <div class="card-table">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h6 class="fw-bold mb-0">Staff Management</h6>
            <div class="d-flex gap-2">
                <div class="dropdown">
                    <button class="btn btn-light btn-sm fw-bold shadow-sm border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-sort-down me-1"></i> Sort
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li><a class="dropdown-item <?= $s_sort=='newest'?'active':'' ?>" href="?filter=<?= $filter ?>&m_sort=<?= $m_sort ?>&s_sort=newest&m_page=<?= $m_page ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>">Newest First</a></li>
                        <li><a class="dropdown-item <?= $s_sort=='oldest'?'active':'' ?>" href="?filter=<?= $filter ?>&m_sort=<?= $m_sort ?>&s_sort=oldest&m_page=<?= $m_page ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>">Oldest First</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item <?= $s_sort=='az'?'active':'' ?>" href="?filter=<?= $filter ?>&m_sort=<?= $m_sort ?>&s_sort=az&m_page=<?= $m_page ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>">Name (A-Z)</a></li>
                        <li><a class="dropdown-item <?= $s_sort=='za'?'active':'' ?>" href="?filter=<?= $filter ?>&m_sort=<?= $m_sort ?>&s_sort=za&m_page=<?= $m_page ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>">Name (Z-A)</a></li>
                    </ul>
                </div>
                <input type="text" id="sSearch" class="form-control bg-light border-0" style="width: 200px;" placeholder="Search staff..." value="<?= htmlspecialchars($s_search) ?>">
                <button class="btn btn-dark btn-sm fw-bold" onclick="openAddModal('staff')">Add Staff</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle" id="sTable">
                <thead><tr><th>Name</th><th>Email</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <?php foreach($staffs as $s): ?>
                    <tr>
                        <td class="fw-bold name-cell"><?= htmlspecialchars($s['full_name']) ?></td>
                        <td style="font-family: monospace; color: #666;"><?= maskEmailPHP($s['email']) ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary border-0 me-2" onclick="editUser(<?= $s['id'] ?>,'<?= addslashes($s['full_name']) ?>','<?= addslashes($s['email']) ?>','staff','<?= $s['status'] ?>')"><i class="bi bi-pencil-square"></i></button>
                            <button class="btn btn-sm btn-outline-danger border-0" onclick="delUser(<?= $s['id'] ?>)"><i class="bi bi-trash3"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Staff -->
        <div id="sPagination" class="mt-3">
            <?php if ($s_total_pages > 1): ?>
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item <?= ($s_page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?filter=<?= $filter ?>&m_sort=<?= $m_sort ?>&s_sort=<?= $s_sort ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>&m_page=<?= $m_page ?>&s_page=<?= $s_page-1 ?>">Previous</a>
                    </li>
                    <?php for($i=1; $i<=$s_total_pages; $i++): ?>
                        <li class="page-item <?= ($i == $s_page) ? 'active' : '' ?>">
                            <a class="page-link" href="?filter=<?= $filter ?>&m_sort=<?= $m_sort ?>&s_sort=<?= $s_sort ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>&m_page=<?= $m_page ?>&s_page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($s_page >= $s_total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?filter=<?= $filter ?>&m_sort=<?= $m_sort ?>&s_sort=<?= $s_sort ?>&m_search=<?= urlencode($m_search) ?>&s_search=<?= urlencode($s_search) ?>&m_page=<?= $m_page ?>&s_page=<?= $s_page+1 ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modals -->
<div class="modal fade" id="userModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 p-4 shadow">
    <h5 class="fw-bold mb-4 text-center" id="modalTitle"></h5>
    <input type="hidden" id="uRole"><input type="hidden" id="uId">
    <div class="mb-3"><label class="small fw-bold">Full Name *</label><input type="text" id="uName" class="form-control"></div>
    <div class="mb-3"><label class="small fw-bold">Email *</label><input type="email" id="uEmail" class="form-control"></div>
    <div id="extraFields" class="d-none">
        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="small fw-bold">Age *</label>
                <input type="number" id="uAge" class="form-control" min="1">
            </div>
            <div class="col-6">
                <label class="small fw-bold">Gender *</label>
                <select id="uGender" class="form-select">
                    <option value="">Select</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>
        <div class="row g-2 mb-3">
            <div class="col-6">
                <label class="small fw-bold">Height (cm) <span class="text-muted">(Optional)</span></label>
                <input type="text" id="uHeight" class="form-control" placeholder="Optional">
            </div>
            <div class="col-6">
                <label class="small fw-bold">Weight (kg) <span class="text-muted">(Optional)</span></label>
                <input type="text" id="uWeight" class="form-control" placeholder="Optional">
            </div>
        </div>
    </div>
    <div class="mb-3 d-none" id="sGrp"><label class="small fw-bold">Status</label><select id="uStatus" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
    <div class="mb-4" id="pGrp">
        <label class="small fw-bold">Password *</label>
        <input type="password" id="uPass" class="form-control" placeholder="Min 8 chars, 1 Upper, 1 Lower, 1 Symbol">
    </div>
    <div class="mb-4 d-none" id="cpGrp">
        <label class="small fw-bold">Confirm Password *</label>
        <input type="password" id="uConfirmPass" class="form-control" placeholder="Repeat password">
    </div>
    <button class="btn btn-danger w-100 fw-bold py-3 shadow-sm" id="saveUserBtn">Save Account</button>
</div></div></div>

<!-- QR Modal -->
<div class="modal fade" id="qrModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content text-center p-3">
    <h6 id="qrName" class="fw-bold mb-3"></h6>
    <img id="qrImg" src="" class="img-fluid rounded shadow-sm">
</div></div></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <?= csrf_script(); ?>
<script>
    const uM = new bootstrap.Modal('#userModal'), qM = new bootstrap.Modal('#qrModal');
    
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const main = document.getElementById('main');
        const overlay = document.getElementById('sidebarOverlay');
        const isMobile = window.innerWidth <= 991.98;
        
        if (isMobile) {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        } else {
            sidebar.classList.toggle('collapsed');
            main.classList.toggle('expanded');
        }
    }
    
    // Updated viewQR to use LOCAL generator
    function viewQR(c, n) { 
        $('#qrName').text(n); 
        $('#qrImg').attr('src', `../generate_qr.php?data=${encodeURIComponent(c)}`); 
        qM.show(); 
    }

    function openAddModal(r) { 
        $('#uId').val(''); $('#uRole').val(r); $('#uName,#uEmail,#uPass,#uConfirmPass,#uAge,#uGender,#uHeight,#uWeight').val('');
        $('#pGrp').show(); $('#sGrp').hide(); $('#modalTitle').text('New ' + r.toUpperCase());
        
        if (r === 'member') {
            $('#extraFields').removeClass('d-none');
            $('#cpGrp').removeClass('d-none');
        } else {
            $('#extraFields').addClass('d-none');
            $('#cpGrp').addClass('d-none');
        }
        
        $('#saveUserBtn').off().click(saveCreate); uM.show(); 
    }
    
    function editUser(id,n,e,r,s) { 
        $('#uId').val(id); $('#uRole').val(r); $('#uName').val(n); $('#uEmail').val(e); $('#uStatus').val(s);
        $('#pGrp').hide(); $('#cpGrp').addClass('d-none'); $('#sGrp').show(); $('#extraFields').addClass('d-none');
        $('#modalTitle').text('Edit ' + r.toUpperCase());
        $('#saveUserBtn').off().click(saveUpdate); uM.show(); 
    }

    function saveCreate() {
        const role = $('#uRole').val();
        const data = { 
            action: 'create', 
            full_name: $('#uName').val(), 
            email: $('#uEmail').val(), 
            password: $('#uPass').val(), 
            role: role 
        };
        
        if (role === 'member') {
            const confirm = $('#uConfirmPass').val();
            if ($('#uPass').val() !== confirm) return alert('Passwords do not match');
            data.age = $('#uAge').val();
            data.gender = $('#uGender').val();
            data.height = $('#uHeight').val();
            data.weight = $('#uWeight').val();
        }
        
        $.post('admin_user_actions.php', data, (res) => { 
            if(res.status==='success') location.reload(); else alert(res.message); 
        }, 'json');
    }

    function saveUpdate() {
        $.post('admin_user_actions.php', { action:'update', id:$('#uId').val(), full_name:$('#uName').val(), email:$('#uEmail').val(), status:$('#uStatus').val() }, (res) => { if(res.status==='success') location.reload(); else alert(res.message); }, 'json');
    }

    function pay(id, btn) { 
        const amount = $(btn).siblings('.rate-select').val();
        const name = $(btn).closest('tr').find('.name-cell').text();
        
        if (!confirm(`Are you sure you want to mark ${name} as PAID (Amount: ${amount})?`)) {
            return;
        }

        $(btn).prop('disabled', true).text('...'); 
        $.post('register_payment.php', { user_id: id, amount: amount, duration: 1 }, (res) => {
            if(res.status==='success') location.reload(); else { alert(res.message); $(btn).prop('disabled', false).text('PAY'); }
        }, 'json'); 
    }

    function delUser(id) { if(confirm('Delete user?')) $.post('admin_user_actions.php', { action: 'delete', id: id }, () => location.reload()); }
    
    // --- DEBOUNCED LIVE SEARCH ---
    let mTimer, sTimer;
    
    $('#mSearch').on('keyup', function() {
        clearTimeout(mTimer);
        const v = $(this).val().trim();
        mTimer = setTimeout(() => {
            const url = new URL(window.location.href);
            url.searchParams.set('m_search', v);
            url.searchParams.set('m_page', '1');
            url.searchParams.set('ajax_m', '1');
            $.get(url.toString(), function(res) {
                $('#mTable tbody').html(res.rows);
                $('#mPagination').html(res.pagination);
                const nextUrl = new URL(window.location.href);
                nextUrl.searchParams.set('m_search', v);
                nextUrl.searchParams.set('m_page', '1');
                window.history.replaceState({}, '', nextUrl.toString());
            }, 'json');
        }, 300);
    });

    $('#sSearch').on('keyup', function() {
        clearTimeout(sTimer);
        const v = $(this).val().trim();
        sTimer = setTimeout(() => {
            const url = new URL(window.location.href);
            url.searchParams.set('s_search', v);
            url.searchParams.set('s_page', '1');
            url.searchParams.set('ajax_s', '1');
            $.get(url.toString(), function(res) {
                $('#sTable tbody').html(res.rows);
                $('#sPagination').html(res.pagination);
                const nextUrl = new URL(window.location.href);
                nextUrl.searchParams.set('s_search', v);
                nextUrl.searchParams.set('s_page', '1');
                window.history.replaceState({}, '', nextUrl.toString());
            }, 'json');
        }, 300);
    });

    // Handle Enter key for immediate search
    $('#mSearch').on('keypress', function(e) {
        if (e.which === 13) {
            clearTimeout(mTimer);
            let v = $(this).val().trim();
            window.location.href = `?filter=<?= $filter ?>&m_sort=<?= $m_sort ?>&s_sort=<?= $s_sort ?>&m_search=${encodeURIComponent(v)}&s_search=<?= urlencode($s_search) ?>`;
        }
    });

    $('#sSearch').on('keypress', function(e) {
        if (e.which === 13) {
            clearTimeout(sTimer);
            let v = $(this).val().trim();
            window.location.href = `?filter=<?= $filter ?>&m_sort=<?= $m_sort ?>&s_sort=<?= $s_sort ?>&m_search=<?= urlencode($m_search) ?>&s_search=${encodeURIComponent(v)}`;
        }
    });

    (function() { if (localStorage.getItem('arts-gym-theme') === 'dark') document.body.classList.add('dark-mode-active'); })();
</script>
</body>
</html>