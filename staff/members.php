<?php
session_start();
require '../auth.php';
require '../connection.php';
require '../includes/status_sync.php';

if ($_SESSION['role'] !== 'staff') { header("Location: ../login.php"); exit; }

// --- HELPER FUNCTION TO MASK EMAIL ---
function maskEmail($email) {
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

// --- SORT LOGIC ---
$sort = $_GET['sort'] ?? 'newest';
$order_by = "u.id DESC"; // Default

if ($sort === 'oldest') {
    $order_by = "u.id ASC";
} elseif ($sort === 'az') {
    $order_by = "u.full_name ASC";
} elseif ($sort === 'za') {
    $order_by = "u.full_name DESC";
}

// --- SEARCH LOGIC ---
$search = $_GET['search'] ?? '';

// --- FILTER LOGIC ---
$filter = $_GET['filter'] ?? 'all';

// --- OPTIMIZED QUERY USING LATERAL JOIN ---
$sql = "
    SELECT u.*, s.latest_expiry 
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

if ($search) {
    $search_quoted = $pdo->quote('%' . $search . '%');
    $sql .= " AND (u.full_name ILIKE $search_quoted OR u.email ILIKE $search_quoted)";
}

if ($filter === 'expiring') {
    $sql .= " AND s.latest_expiry >= CURRENT_DATE AND s.latest_expiry <= (CURRENT_DATE + INTERVAL '7 days')";
    $sql .= " ORDER BY s.latest_expiry ASC";
} else {
    $sql .= " ORDER BY $order_by";
}

// --- SELECTIVE SYNCING FOR PERFORMANCE ---
// Instead of bulkSyncMembers($pdo), we sync only the members we're about to show.
// We'll perform the sync AFTER fetching the paginated results.

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$sql_total_base = "FROM users u WHERE u.role = 'member'";
if ($search) {
    $sql_total_base .= " AND (u.full_name ILIKE " . $pdo->quote('%' . $search . '%') . " OR u.email ILIKE " . $pdo->quote('%' . $search . '%') . ")";
}

if ($filter === 'expiring') {
    $sql_total_base .= " AND EXISTS (SELECT 1 FROM sales s WHERE s.user_id = u.id AND s.expires_at >= CURRENT_DATE AND s.expires_at <= (CURRENT_DATE + INTERVAL '7 days'))";
}

$total = $pdo->query("SELECT COUNT(*) " . $sql_total_base)->fetchColumn();
$total_pages = ceil($total / $limit);

$sql .= " LIMIT $limit OFFSET $offset";
$members = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// --- SYNC ONLY VISIBLE MEMBERS ---
if (!empty($members)) {
    foreach ($members as &$m) {
        $calculated_active = ($m['latest_expiry'] && strtotime($m['latest_expiry']) > time());
        $new_status = $calculated_active ? 'active' : 'inactive';
        if ($m['status'] !== $new_status) {
            $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new_status, $m['id']]);
            $m['status'] = $new_status; // Update in-memory for immediate correct display
        }
    }
}

// --- AJAX HANDLER FOR LIVE SEARCH ---
if (isset($_GET['ajax'])) {
    ob_start();
    if (empty($members)) {
        echo '<tr><td colspan="5" class="text-center text-muted py-4">No members found matching "' . htmlspecialchars($search) . '"</td></tr>';
    } else {
        foreach($members as $m): 
            $latest = $m['latest_expiry'];
            $is_active = ($m['status'] === 'active');
            $qr_data = $m['qr_code'] ?: $m['id'];
            ?>
            <tr data-id="<?= $m['id'] ?>">
                <td class="fw-bold name-cell"><?= htmlspecialchars($m['full_name']) ?></td>
                <td style="font-family: monospace; color: #666;"><?= maskEmail($m['email']) ?></td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-dark border-0" onclick="viewQR('<?= $qr_data ?>','<?= addslashes($m['full_name']) ?>')">
                        <i class="bi bi-qr-code fs-5"></i>
                    </button>
                </td>
                <td><span class="badge <?= $is_active?'bg-success-subtle text-success':'bg-danger-subtle text-danger' ?> border px-3"><?= $is_active?'Active':'Inactive' ?></span></td>
                <td>
                    <?php if($is_active): ?>
                        <small class="fw-bold text-success text-uppercase">Until: <?= $latest ? date('M d, Y', strtotime($latest)) : 'INDEFINITE' ?></small>
                    <?php else: ?>
                        <div class="d-flex gap-2 align-items-center">
                            <select class="form-select form-select-sm rate-select amount" style="width: 130px;">
                                <option value="400">Student (400)</option><option value="500" selected>Regular (500)</option>
                            </select>
                            <button class="btn btn-dark btn-sm fw-bold process-payment">PAY</button>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach;
    }
    $rows_html = ob_get_clean();

    ob_start();
    if ($total_pages > 1): ?>
        <ul class="pagination pagination-sm justify-content-center mb-0">
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?filter=<?= $filter ?>&sort=<?= $sort ?>&search=<?= urlencode($search) ?>&page=<?= $page-1 ?>">Previous</a>
            </li>
            <?php for($i=1; $i<=$total_pages; $i++): ?>
                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                    <a class="page-link" href="?filter=<?= $filter ?>&sort=<?= $sort ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?filter=<?= $filter ?>&sort=<?= $sort ?>&search=<?= urlencode($search) ?>&page=<?= $page+1 ?>">Next</a>
            </li>
        </ul>
    <?php endif;
    $pagination_html = ob_get_clean();

    header('Content-Type: application/json');
    echo json_encode(['rows' => $rows_html, 'pagination' => $pagination_html]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Members | Arts Gym</title>
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
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-body); color: var(--text-main); transition: var(--transition); letter-spacing: -0.01em; }
        
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
        .top-header { background: transparent; padding: 0 0 2rem 0; display: flex; align-items: center; justify-content: space-between; }
        
        .table-responsive { max-height: 550px; overflow-y: auto; }
        .table thead th { position: sticky; top: 0; z-index: 5; }
    </style>
</head>
<body class="<?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'dark-mode-active' : '' ?>">

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<?php include '_sidebar.php'; ?>

    <div id="main">
        <header class="top-header">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleBtn" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
                <h4 class="mb-0 fw-bold">Members Management</h4>
            </div>
            <div class="d-flex align-items-center gap-3"><?php include '../global_clock.php'; ?></div>
        </header>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div class="btn-group bg-white p-1 rounded-3 shadow-sm border">
                <a href="?filter=all&sort=<?= $sort ?>&search=<?= urlencode($search) ?>" class="btn btn-sm <?= $filter=='all'?'btn-danger active':'btn-light' ?> px-4 fw-bold" style="border-radius: 8px;">All Members</a>
                <a href="?filter=expiring&sort=<?= $sort ?>&search=<?= urlencode($search) ?>" class="btn btn-sm <?= $filter=='expiring'?'btn-danger active':'btn-light' ?> px-4 fw-bold" style="border-radius: 8px;">Expiring Soon</a>
            </div>
            <div class="d-flex gap-2 ms-auto">
                <div class="dropdown">
                    <button class="btn btn-white btn-sm fw-bold shadow-sm border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-sort-down me-1"></i> Sort By
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li><a class="dropdown-item <?= $sort=='newest'?'active':'' ?>" href="?filter=<?= $filter ?>&sort=newest&search=<?= urlencode($search) ?>">Newest First</a></li>
                        <li><a class="dropdown-item <?= $sort=='oldest'?'active':'' ?>" href="?filter=<?= $filter ?>&sort=oldest&search=<?= urlencode($search) ?>">Oldest First</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item <?= $sort=='az'?'active':'' ?>" href="?filter=<?= $filter ?>&sort=az&search=<?= urlencode($search) ?>">Name (A-Z)</a></li>
                        <li><a class="dropdown-item <?= $sort=='za'?'active':'' ?>" href="?filter=<?= $filter ?>&sort=za&search=<?= urlencode($search) ?>">Name (Z-A)</a></li>
                    </ul>
                </div>
                <input type="text" id="mSearch" class="form-control bg-white border-0 shadow-sm" placeholder="Search name..." value="<?= htmlspecialchars($search) ?>" style="width: 200px;">
                <button class="btn btn-dark btn-sm fw-bold" onclick="openAddModal()">Add Member</button>
            </div>
        </div>

        <!-- Members Table -->
        <div class="card-table">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="fw-bold mb-0">Members Directory</h6>
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
                            $days_left = 0;
                            if($is_active && $latest) {
                                $diff = strtotime($latest) - time();
                                $days_left = ceil($diff / (60 * 60 * 24));
                            }
                        ?>
                        <tr data-id="<?= $m['id'] ?>">
                            <td class="fw-bold name-cell"><?= htmlspecialchars($m['full_name']) ?></td>
                            <td style="font-family: monospace; color: #666;"><?= maskEmail($m['email']) ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-dark border-0" onclick="viewQR('<?= $qr_data ?>','<?= addslashes($m['full_name']) ?>')">
                                    <i class="bi bi-qr-code fs-5"></i>
                                </button>
                            </td>
                            <td>
                                <span class="badge <?= $is_active?'bg-success-subtle text-success':'bg-danger-subtle text-danger' ?> border px-3">
                                    <?= $is_active?'Active':'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <?php if($is_active): ?>
                                    <small class="fw-bold text-success text-uppercase">
                                        Until: <?= $latest ? date('M d, Y', strtotime($latest)) : 'INDEFINITE' ?>
                                    </small>
                                <?php else: ?>
                                    <div class="d-flex gap-2 align-items-center">
                                        <select class="form-select form-select-sm rate-select amount" style="width: 130px;">
                                            <option value="400">Student (400)</option>
                                            <option value="500" selected>Regular (500)</option>
                                        </select>
                                        <button class="btn btn-dark btn-sm fw-bold process-payment">PAY</button>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Members -->
        <div id="mPagination" class="mt-3">
            <?php if ($total_pages > 1): ?>
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?filter=<?= $filter ?>&sort=<?= $sort ?>&search=<?= urlencode($search) ?>&page=<?= $page-1 ?>">Previous</a>
                    </li>
                    <?php for($i=1; $i<=$total_pages; $i++): ?>
                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?filter=<?= $filter ?>&sort=<?= $sort ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?filter=<?= $filter ?>&sort=<?= $sort ?>&search=<?= urlencode($search) ?>&page=<?= $page+1 ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>

<!-- Modals -->
<div class="modal fade" id="addModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 p-4 shadow">
    <h5 class="fw-bold mb-4 text-center">Register Member</h5>
    <div class="mb-3"><label class="small fw-bold">Full Name *</label><input type="text" id="addName" class="form-control"></div>
    <div class="mb-3"><label class="small fw-bold">Email Address *</label><input type="email" id="addEmail" class="form-control"></div>
    <div class="row g-2 mb-3">
        <div class="col-6">
            <label class="small fw-bold">Age *</label>
            <input type="number" id="addAge" class="form-control" min="1">
        </div>
        <div class="col-6">
            <label class="small fw-bold">Gender *</label>
            <select id="addGender" class="form-select">
                <option value="">Select</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
            </select>
        </div>
    </div>
    <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="small fw-bold">Height (cm) <span class="text-muted">(Skip for now)</span></label>
                    <input type="text" id="addHeight" class="form-control" placeholder="Skip for now">
                </div>
                <div class="col-6">
                    <label class="small fw-bold">Weight (kg) <span class="text-muted">(Skip for now)</span></label>
                    <input type="text" id="addWeight" class="form-control" placeholder="Skip for now">
                </div>
            </div>
    <div class="mb-3">
        <label class="small fw-bold">Password *</label>
        <input type="password" id="addPass" class="form-control" placeholder="Min 8 chars, 1 Upper, 1 Symbol">
    </div>
    <div class="mb-4">
        <label class="small fw-bold">Confirm Password *</label>
        <input type="password" id="addConfirmPass" class="form-control" placeholder="Repeat password">
    </div>
    <button class="btn btn-danger w-100 fw-bold py-3 shadow-sm" onclick="processCreate()">Register Account</button>
</div></div></div>

<div class="modal fade" id="qrModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content text-center p-3">
    <h6 id="qrName" class="fw-bold mb-3"></h6><img id="qrImg" src="" class="img-fluid rounded shadow-sm">
</div></div></div>

<!-- Payment Confirmation Modal -->
<div class="modal fade" id="paymentConfirmModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 p-4 shadow">
    <h5 class="fw-bold mb-3 text-center">Confirm Payment</h5>
    <p id="paymentConfirmText" class="text-center text-muted mb-4"></p>
    <div class="d-flex gap-3">
        <button type="button" class="btn btn-light w-100 fw-bold py-2" data-bs-dismiss="modal">No</button>
        <button type="button" id="confirmPaymentBtn" class="btn btn-danger w-100 fw-bold py-2">Confirm</button>
    </div>
</div></div></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <?= csrf_script(); ?>
<script>
    const addM = new bootstrap.Modal('#addModal'), qM = new bootstrap.Modal('#qrModal');
    const paymentConfirmModal = new bootstrap.Modal('#paymentConfirmModal');
    let pendingPayment = { row: null, amount: null };

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
    
    function viewQR(c, n) { 
        $('#qrName').text(n); 
        $('#qrImg').attr('src', `../generate_qr.php?data=${encodeURIComponent(c)}`); 
        qM.show(); 
    }
    
    function openAddModal() { $('#addName,#addEmail,#addPass,#addConfirmPass,#addAge,#addGender,#addHeight,#addWeight').val(''); addM.show(); }

    function processCreate() {
        const name = $('#addName').val().trim(), 
              email = $('#addEmail').val().trim(), 
              pass = $('#addPass').val().trim(),
              confirm = $('#addConfirmPass').val().trim(),
              age = $('#addAge').val().trim(),
              gender = $('#addGender').val(),
              height = $('#addHeight').val().trim(),
              weight = $('#addWeight').val().trim();
              
        if(!name || !email || !pass || !confirm || !age || !gender) return alert('Please fill in all required fields (*)');
        if(pass !== confirm) return alert('Passwords do not match');
        
        $.post('../admin/admin_user_actions.php', { 
            action: 'create', 
            role: 'member', 
            full_name: name, 
            email: email, 
            password: pass,
            age: age,
            gender: gender,
            height: height,
            weight: weight
        }, function(res){
            if(res.status === 'success') location.reload(); else alert(res.message);
        }, 'json');
    }

    $(document).on('click', '.process-payment', function() {
        const row = $(this).closest('tr');
        const amount = row.find('.amount').val();
        const name = row.find('.name-cell').text();
        
        // Store pending payment info
        pendingPayment = { row: row, amount: amount };
        
        // Show confirmation modal
        $('#paymentConfirmText').html(`Are you sure you want to mark <strong>${name}</strong> as PAID?<br><small class="text-muted">Amount: ₱${amount}</small>`);
        paymentConfirmModal.show();
    });

    // Handle payment confirmation
    $('#confirmPaymentBtn').on('click', function() {
        const btn = $(this);
        const row = pendingPayment.row;
        const amount = pendingPayment.amount;

        if (!row || !amount) return;

        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Processing...');
        
        $.post('staff_register_payment.php', {
            user_id: row.data('id'), amount: amount, duration: 1
        }, function(res) {
            if(res.status === 'success') {
                paymentConfirmModal.hide();
                location.reload();
            } else { 
                alert(res.message); 
                btn.prop('disabled', false).html('Confirm');
            }
        }, 'json').fail(function() {
            alert('Request failed');
            btn.prop('disabled', false).html('Confirm');
        });
    });

    // --- DEBOUNCED LIVE SEARCH ---
    let searchTimer;
    $('#mSearch').on('keyup', function() {
        clearTimeout(searchTimer);
        const v = $(this).val().trim();
        
        searchTimer = setTimeout(() => {
            const url = new URL(window.location.href);
            url.searchParams.set('search', v);
            url.searchParams.set('page', '1');
            url.searchParams.set('ajax', '1');

            $.get(url.toString(), function(res) {
                $('#mTable tbody').html(res.rows);
                $('#mPagination').html(res.pagination);
                
                // Update URL without reload to persist state
                const nextUrl = new URL(window.location.href);
                nextUrl.searchParams.set('search', v);
                nextUrl.searchParams.set('page', '1');
                window.history.replaceState({}, '', nextUrl.toString());
            }, 'json');
        }, 300);
    });

    // Handle Enter key for immediate search
    $('#mSearch').on('keypress', function(e) {
        if (e.which === 13) {
            clearTimeout(searchTimer);
            let v = $(this).val().trim();
            window.location.href = `?filter=<?= $filter ?>&sort=<?= $sort ?>&search=${encodeURIComponent(v)}`;
        }
    });

    (function() { if (localStorage.getItem('arts-gym-theme') === 'dark') document.body.classList.add('dark-mode-active'); })();
</script>
</body>
</html>