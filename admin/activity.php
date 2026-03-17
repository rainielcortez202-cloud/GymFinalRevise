<?php
// admin/activity.php
session_start();
require '../auth.php';
require '../connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

/* ===================== FETCH ALL ACTIVITIES ===================== */
$activities = $pdo->query("
    SELECT al.*, u.full_name AS user_name, u.role
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

/* ===================== FILTERS ===================== */

$session_actions     = ['login', 'logout'];
$payment_reg_actions = ['create_user', 'add_walkin', 'payment', 'add_member', 'add_staff', 'register'];

/* 1. SESSION LOGS — admin & staff only */
$session_logs = array_filter($activities, function($a){
    global $session_actions;
    return in_array(strtolower($a['action']), $session_actions)
        && in_array(strtolower($a['role']), ['admin', 'staff']);
});

/* 2. PAYMENTS & REGISTRATIONS */
$payment_registration_logs = array_filter($activities, function($a){
    global $payment_reg_actions;
    return in_array(strtolower($a['action']), $payment_reg_actions);
});

/* 3. PROFILE UPDATES — everything that isn't session or payment */
$profile_update_logs = array_filter($activities, function($a){
    global $session_actions, $payment_reg_actions;
    $action = strtolower($a['action']);
    return !in_array($action, $session_actions) && !in_array($action, $payment_reg_actions);
});

/* ===================== HELPERS ===================== */
function badge($action){
    return match(strtolower($action)){
        'login'  => 'bg-success',
        'logout' => 'bg-secondary',
        'payment' => 'bg-primary',
        'add_member', 'add_staff', 'register' => 'bg-info text-dark',
        'profile_update', 'edit_profile', 'email_change' => 'bg-warning text-dark',
        'delete_user' => 'bg-danger',
        default  => 'bg-dark'
    };
}

/* ===================== PAGINATION ===================== */
$limit = 10;
$active_tab = $_GET['tab'] ?? 'sessions';

$page_sessions = max(1, (int)($_GET['page_sessions'] ?? 1));
$page_payments  = max(1, (int)($_GET['page_payments']  ?? 1));
$page_updates   = max(1, (int)($_GET['page_updates']   ?? 1));

$session_logs = array_values($session_logs);
$total_sessions = count($session_logs);
$pages_sessions = max(1, ceil($total_sessions / $limit));
$session_logs = array_slice($session_logs, ($page_sessions - 1) * $limit, $limit);

$payment_registration_logs = array_values($payment_registration_logs);
$total_payments = count($payment_registration_logs);
$pages_payments = max(1, ceil($total_payments / $limit));
$payment_registration_logs = array_slice($payment_registration_logs, ($page_payments - 1) * $limit, $limit);

$profile_update_logs = array_values($profile_update_logs);
$total_updates = count($profile_update_logs);
$pages_updates = max(1, ceil($total_updates / $limit));
$profile_update_logs = array_slice($profile_update_logs, ($page_updates - 1) * $limit, $limit);

/* Pagination helper */
function paginationNav(string $tab_id, string $page_param, int $current, int $total_pages): void {
    if ($total_pages <= 1) return;
    $base = array_merge($_GET, ['tab' => $tab_id]);
    echo '<nav class="mt-3"><ul class="pagination pagination-sm justify-content-center mb-0">';
    // Prev
    $prev = array_merge($base, [$page_param => $current - 1]);
    echo '<li class="page-item ' . ($current <= 1 ? 'disabled' : '') . '">';
    echo '<a class="page-link" href="?' . http_build_query($prev) . '">Previous</a></li>';
    // Pages
    for ($i = 1; $i <= $total_pages; $i++) {
        $pg = array_merge($base, [$page_param => $i]);
        echo '<li class="page-item ' . ($i == $current ? 'active' : '') . '">';
        echo '<a class="page-link" href="?' . http_build_query($pg) . '">' . $i . '</a></li>';
    }
    // Next
    $next = array_merge($base, [$page_param => $current + 1]);
    echo '<li class="page-item ' . ($current >= $total_pages ? 'disabled' : '') . '">';
    echo '<a class="page-link" href="?' . http_build_query($next) . '">Next</a></li>';
    echo '</ul></nav>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Activity Log | Arts Gym</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root{
    --primary-red:#e63946;
    --bg-body:#f8f9fa;
    --bg-card:#ffffff;
    --text-main:#1a1a1a;
    --text-muted:#8e8e93;
    --border:#f1f1f1;
    --sidebar-width:260px;
    --shadow:0 10px 30px rgba(0,0,0,.04);
    --transition:all .3s cubic-bezier(.4,0,.2,1);
}
body.dark-mode-active{
    --bg-body:#0a0a0a;
    --bg-card:#121212;
    --text-main:#f5f5f7;
    --text-muted:#86868b;
    --border:#1c1c1e;
    --shadow:0 10px 30px rgba(0,0,0,.2);
}
body{
    font-family:Inter,sans-serif;
    background:var(--bg-body);
    color:var(--text-main);
}
#sidebar{
    width:var(--sidebar-width);
    height:100vh;
    position:fixed;
    left:0;
    top:0;
    z-index:1100;
    transition:var(--transition);
}
#sidebar.collapsed{width:80px;}
#main{
    margin-left:var(--sidebar-width);
    padding:2rem;
    transition:var(--transition);
}
#main.expanded{margin-left:80px;}

@media(max-width:991.98px){
    #sidebar{left:calc(var(--sidebar-width) * -1);}
    #sidebar.show{
        left:0;
        box-shadow:10px 0 30px rgba(0,0,0,.15);
    }
    #main{margin-left:0 !important;}
}

.sidebar-overlay{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.5);
    z-index:1090;
}
.sidebar-overlay.show{display:block;}

.card-box{
    background:var(--bg-card);
    border-radius:20px;
    box-shadow:var(--shadow);
    padding:24px;
    margin-bottom:32px;
}
.top-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:2rem;
}
.table-container{
    max-height:420px;
    overflow:auto;
}
.table thead th{
    position:sticky;
    top:0;
    background:var(--bg-card);
    font-size:.7rem;
    text-transform:uppercase;
    color:var(--text-muted);
    border-bottom:1px solid var(--border);
}
.details-box{
    background:rgba(0,0,0,.03);
    border-left:3px solid var(--primary-red);
    padding:8px 10px;
    border-radius:8px;
    font-size:.8rem;
}
.dark-mode-active .details-box{
    background:rgba(255,255,255,.05);
}
.search-wrapper{
    position:relative;
    width: 100%;
}
@media (min-width: 768px) {
    .search-wrapper { max-width:260px; }
}
.search-wrapper i{
    position:absolute;
    top:50%;
    left:12px;
    transform:translateY(-50%);
    color:var(--text-muted);
}
.search-wrapper input{
    padding-left:34px;
    border-radius:20px;
}
.nav-pills {
    gap: 6px;
}
.nav-pills .nav-link {
    color: var(--text-muted);
    background: transparent;
    border: 1px solid rgba(128,128,128,0.2);
    border-radius: 8px;
    padding: 6px 16px;
    font-size: 0.8rem;
    font-weight: 500;
    letter-spacing: 0.3px;
    transition: var(--transition);
}
.nav-pills .nav-link:hover {
    background: rgba(128,128,128,0.08);
    color: var(--text-main);
    border-color: rgba(128,128,128,0.35);
}
.nav-pills .nav-link.active {
    background-color: #1a1a1a;
    color: #ffffff;
    border-color: #1a1a1a;
}
body.dark-mode-active .nav-pills .nav-link.active {
    background-color: #f0f0f0;
    color: #111111;
    border-color: #f0f0f0;
}

@media (max-width: 767.98px) {
    .nav-pills { width: 100%; display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-start; }
    .nav-pills .nav-item { flex: 1 1 calc(50% - 4px); min-width: 120px; }
    .nav-pills .nav-link { width: 100%; padding: 8px 10px; font-size: 0.75rem; text-align: center; white-space: nowrap; display: flex; align-items: center; justify-content: center; }
    .nav-pills .nav-link i { margin-right: 4px; }
}
</style>
</head>

<body class="<?= ($_COOKIE['theme'] ?? '') === 'dark' ? 'dark-mode-active' : '' ?>">

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<?php include '_sidebar.php'; ?>

<div id="main">

<header class="top-header">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-light d-lg-none" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
        <div>
            <h4 class="mb-0 fw-bold">Activity Logs</h4>
            <small class="text-muted">System Overview</small>
        </div>
    </div>
    <?php include '../global_clock.php'; ?>
</header>

<div class="card-box">
    <!-- Navigation Tabs -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <ul class="nav nav-pills" id="activityTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="sessions-tab" data-bs-toggle="pill" data-bs-target="#sessions" type="button" role="tab" aria-controls="sessions" aria-selected="true">
                    <i class="bi bi-person-badge me-1"></i> Sessions
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="payments-tab" data-bs-toggle="pill" data-bs-target="#payments" type="button" role="tab" aria-controls="payments" aria-selected="false">
                    <i class="bi bi-credit-card me-1"></i> Payments & Registrations
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="updates-tab" data-bs-toggle="pill" data-bs-target="#updates" type="button" role="tab" aria-controls="updates" aria-selected="false">
                    <i class="bi bi-pencil-square me-1"></i> Profile Updates
                </button>
            </li>
        </ul>
        
        <div class="search-wrapper">
            <i class="bi bi-search"></i>
            <input class="form-control table-search" placeholder="Search user">
        </div>
    </div>

    <!-- Tab Contents -->
    <div class="tab-content" id="activityTabsContent">
        
        <!-- ===================== SESSIONS TAB ===================== -->
        <div class="tab-pane fade show active" id="sessions" role="tabpanel" aria-labelledby="sessions-tab">
            <div class="table-container">
                <table class="table align-middle filterable-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Date</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($session_logs)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No session logs found.</td></tr>
                        <?php endif; ?>
                        <?php foreach($session_logs as $a): ?>
                        <tr>
                            <td class="user-column fw-semibold"><?= htmlspecialchars($a['user_name'] ?? 'System') ?></td>
                            <td><span class="badge bg-light text-dark border"><?= strtoupper($a['role'] ?? 'UNKNOWN') ?></span></td>
                            <td><span class="badge <?= badge($a['action']) ?>"><?= strtoupper($a['action']) ?></span></td>
                            <td><span class="text-muted small"><?= htmlspecialchars($a['details'] ?? '') ?></span></td>
                            <td><?= date("M d, Y",strtotime($a['created_at'])) ?></td>
                            <td class="fw-bold"><?= date("h:i A",strtotime($a['created_at'])) ?></td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php paginationNav('sessions', 'page_sessions', $page_sessions, $pages_sessions); ?>
        </div>

        <!-- ===================== PAYMENTS & REGISTRATIONS TAB ===================== -->
        <div class="tab-pane fade" id="payments" role="tabpanel" aria-labelledby="payments-tab">
            <div class="table-container">
                <table class="table align-middle filterable-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Date</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($payment_registration_logs)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No payments or registrations found.</td></tr>
                        <?php endif; ?>
                        <?php foreach($payment_registration_logs as $a): ?>
                        <tr>
                            <td class="user-column fw-semibold"><?= htmlspecialchars($a['user_name'] ?? 'System') ?></td>
                            <td><span class="badge bg-light text-dark border"><?= strtoupper($a['role'] ?? 'UNKNOWN') ?></span></td>
                            <td><span class="badge <?= badge($a['action']) ?>"><?= strtoupper($a['action']) ?></span></td>
                            <td><div class="details-box"><?= htmlspecialchars($a['details']) ?></div></td>
                            <td><?= date("M d, Y",strtotime($a['created_at'])) ?></td>
                            <td class="fw-bold"><?= date("h:i A",strtotime($a['created_at'])) ?></td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php paginationNav('payments', 'page_payments', $page_payments, $pages_payments); ?>
        </div>

        <!-- ===================== PROFILE UPDATES TAB ===================== -->
        <div class="tab-pane fade" id="updates" role="tabpanel" aria-labelledby="updates-tab">
            <div class="table-container">
                <table class="table align-middle filterable-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Date</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($profile_update_logs)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No profile updates found.</td></tr>
                        <?php endif; ?>
                        <?php foreach($profile_update_logs as $a): ?>
                        <tr>
                            <td class="user-column fw-semibold"><?= htmlspecialchars($a['user_name'] ?? 'System') ?></td>
                            <td><span class="badge bg-light text-dark border"><?= strtoupper($a['role'] ?? 'UNKNOWN') ?></span></td>
                            <td><span class="badge <?= badge($a['action']) ?>"><?= strtoupper($a['action']) ?></span></td>
                            <td><div class="details-box"><?= htmlspecialchars($a['details']) ?></div></td>
                            <td><?= date("M d, Y",strtotime($a['created_at'])) ?></td>
                            <td class="fw-bold"><?= date("h:i A",strtotime($a['created_at'])) ?></td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php paginationNav('updates', 'page_updates', $page_updates, $pages_updates); ?>
        </div>

    </div>
</div>




</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar(){
    const sidebar = document.getElementById('sidebar');
    const main = document.getElementById('main');
    const overlay = document.getElementById('sidebarOverlay');

    if(window.innerWidth < 992){
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    }else{
        sidebar.classList.toggle('collapsed');
        main.classList.toggle('expanded');
    }
}

$('.table-search').on('keyup',function(){
    let v = $(this).val().toLowerCase();
    // Only search the active tab's table
    $('.tab-pane.active .filterable-table tbody tr').each(function(){
        $(this).toggle($(this).find('.user-column').text().toLowerCase().includes(v));
    });
});

// Re-apply search when switching tabs
$('button[data-bs-toggle="pill"]').on('shown.bs.tab', function (e) {
    let v = $('.table-search').val().toLowerCase();
    if(v) {
        $('.tab-pane.active .filterable-table tbody tr').each(function(){
            $(this).toggle($(this).find('.user-column').text().toLowerCase().includes(v));
        });
    }
});

// Restore active tab from URL on page load
(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    if (tab) {
        const tabBtn = document.getElementById(tab + '-tab');
        if (tabBtn) {
            new bootstrap.Tab(tabBtn).show();
        }
    }
})();
</script>

</body>
</html>
