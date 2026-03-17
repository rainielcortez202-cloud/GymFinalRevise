<?php
// admin/daily.php
session_start();
require '../auth.php';
require '../connection.php';

// --- AJAX REQUEST HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    header('Content-Type: application/json');
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
        echo json_encode(["status" => "error", "message" => "Unauthorized"]);
        exit;
    }

    // Handle rate update (admin only)
    if (isset($_POST['update_rate'])) {
        if ($_SESSION['role'] !== 'admin') {
            echo json_encode(["status" => "error", "message" => "Unauthorized"]);
            exit;
        }
        $new_rate = floatval($_POST['new_rate'] ?? 0);
        if ($new_rate <= 0) {
            echo json_encode(["status" => "error", "message" => "Rate must be greater than 0"]);
            exit;
        }
        try {
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?");
            $stmt->execute([$new_rate, 'daily_walkin_rate']);
            
            if ($stmt->rowCount() == 0) {
                $stmt_insert = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                $stmt_insert->execute(['daily_walkin_rate', $new_rate]);
            }
            echo json_encode(["status" => "success", "message" => "Rate updated!", "new_rate" => $new_rate]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
        }
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $daily_rate = 40; 
    try {
        $rate_stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $rate_stmt->execute(['daily_walkin_rate']);
        $rate_result = $rate_stmt->fetch(PDO::FETCH_ASSOC);
        if ($rate_result) { $daily_rate = floatval($rate_result['setting_value']); }
    } catch (PDOException $e) { }

    $amount = $daily_rate;
    if (empty($name)) {
        echo json_encode(["status" => "error", "message" => "Visitor name is required."]);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt_walkin = $pdo->prepare("INSERT INTO walk_ins (visitor_name, amount, checked_in_by) VALUES (?, ?, ?)");
        $stmt_walkin->execute([$name, $amount, $_SESSION['user_id']]);
        
        // For walk-ins, record a sale with no associated user and no expiry
        $stmt_sales = $pdo->prepare("INSERT INTO sales (user_id, amount, sale_date, expires_at) VALUES (NULL, ?, NOW(), NULL)");
        $stmt_sales->execute([$amount]);

        $stmt_att = $pdo->prepare("INSERT INTO attendance (user_id, visitor_name, date, time_in, attendance_date) VALUES (NULL, ?, CURRENT_DATE, NOW(), CURRENT_DATE)");
        $stmt_att->execute([$name]);
        
        logActivity($pdo, $_SESSION['user_id'], $_SESSION['role'], 'ADD_WALKIN', "Added walk-in visitor: $name (₱$amount)");

        $pdo->commit();
        echo json_encode(["status" => "success", "message" => "Walk-in recorded!"]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
    exit;
}

if ($_SESSION['role'] !== 'admin') { header("Location: ../login.php"); exit; }

// Ensure settings table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id SERIAL PRIMARY KEY,
        setting_key VARCHAR(255) UNIQUE NOT NULL,
        setting_value TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    // Table might already exist
}

$daily_rate = 40;
try {
    $rate_stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $rate_stmt->execute(['daily_walkin_rate']);
    $rate_result = $rate_stmt->fetch(PDO::FETCH_ASSOC);
    if ($rate_result) { $daily_rate = floatval($rate_result['setting_value']); }
} catch (PDOException $e) { }

$today = date('Y-m-d');
$walkins = $pdo->prepare("SELECT * FROM walk_ins WHERE visit_date::DATE = ? ORDER BY visit_date DESC");
$walkins->execute([$today]);
$walkins = $walkins->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daily Plan | Arts Gym</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Oswald:wght@500;700&display=swap" rel="stylesheet">
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
        }

        .top-header { background: transparent; padding: 0 0 2rem 0; display: flex; align-items: center; justify-content: space-between; }

        /* Action Card */
        .action-card { 
            background: var(--bg-card); border-radius: 20px; padding: 24px; 
            box-shadow: var(--card-shadow); border: none; margin-bottom: 2rem; 
        }
        
        /* Table Styling */
        .card-table { background: var(--bg-card); border-radius: 20px; padding: 24px; box-shadow: var(--card-shadow); border: none; }
        .table thead th { 
            background: var(--bg-card); color: var(--text-muted); font-size: 0.75rem; 
            text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; 
            border-bottom: 1px solid var(--border-color); padding: 15px; 
            position: sticky; top: 0; z-index: 5;
        }
        .table tbody td { padding: 15px; border-bottom: 1px solid var(--border-color); }
        
        .amount-badge {
            background: rgba(0, 184, 148, 0.1); color: #00b894;
            padding: 5px 12px; border-radius: 8px; font-weight: 700; font-size: 0.85rem;
        }

        .form-control-custom {
            background: var(--bg-body); border: 1px solid var(--border-color);
            border-radius: 10px; padding: 12px; font-weight: 500; color: var(--text-main);
        }
        .form-control-custom:focus { border-color: var(--primary-red); box-shadow: none; outline: none; }
        
        .form-control-custom.error { border-color: var(--primary-red); background-color: rgba(230, 57, 70, 0.05); }
        .error-message { color: var(--primary-red); font-size: 0.85rem; font-weight: 500; margin-top: 0.5rem; display: none; }
    </style>
</head>
<body class="<?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'dark-mode-active' : '' ?>">

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<?php include __DIR__ . '/_sidebar.php'; ?>

<div id="main">
    <header class="top-header">
        <div>
            <button class="btn btn-light d-lg-none me-2" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
            <h4 class="mb-0 fw-bold">Daily Walk-ins</h4>
        </div>
        <div class="d-flex align-items-center gap-3"><?php include '../global_clock.php'; ?></div>
    </header>

    <!-- Entry Card -->
    <div class="action-card">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-5">
                <label class="small fw-bold text-uppercase">Visitor Name</label>
                <input type="text" id="walkinName" class="form-control-custom w-100" placeholder="Enter Name...">
                <div class="error-message" id="nameErrorMsg"><i class="bi bi-exclamation-circle me-1"></i>Visitor name is required</div>
            </div>
            <div class="col-12 col-md-4">
                <label class="small fw-bold text-uppercase">Rate</label>
                <div class="d-flex gap-2 align-items-center">
                    <input type="text" class="form-control-custom flex-grow-1" id="dailyRate" value="₱ <?= number_format($daily_rate, 2) ?>" readonly>
                    <button class="btn btn-outline-secondary" type="button" title="Change Rate" onclick="openRateModal()" style="padding: 0.5rem 0.75rem; border-radius: 8px;">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <button class="btn btn-dark w-100 fw-bold" onclick="addWalkin()">
                    <i class="bi bi-plus-lg me-2"></i>Record Entry
                </button>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card-table">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h6 class="fw-bold mb-0">Today's Visitors</h6>
            <input type="text" id="walkinSearch" class="form-control form-control-sm bg-light border-0" placeholder="Search name..." style="width: 200px;">
        </div>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Visitor Name</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th class="text-end">Check-in Time</th>
                    </tr>
                </thead>
                <tbody id="walkinList">
                    <?php if (count($walkins) > 0): ?>
                        <?php foreach($walkins as $w): ?>
                        <tr>
                            <td class="fw-semibold name-cell"><?= htmlspecialchars($w['visitor_name']) ?></td>
                            <td><span class="text-muted small"><i class="bi bi-check-circle-fill text-success me-1"></i> Paid</span></td>
                            <td><span class="amount-badge">₱<?= number_format($w['amount'], 2) ?></span></td>
                            <td class="text-end text-muted small">
                                <?= date('h:i A', strtotime($w['visit_date'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>
                                <p class="mb-0 opacity-50">No walk-ins recorded today.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Rate Change Modal -->
<div class="modal fade" id="rateModal" tabindex="-1" aria-labelledby="rateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-2">
                <h5 class="modal-title fw-bold"><i class="bi bi-currency-dollar me-2 text-danger"></i>Change Daily Rate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3 pb-4">
                <label class="small fw-bold text-uppercase mb-2 d-block">New Rate (₱)</label>
                <input type="number" id="newRateInput" class="form-control-custom w-100 mb-3" step="0.01" min="0.01" placeholder="Enter new rate">
                <small class="text-muted d-block">
                    <i class="bi bi-info-circle me-1"></i>This rate will apply to all new walk-in entries.
                </small>
            </div>
            <div class="modal-footer border-top pt-3">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="updateRate()" style="border-radius: 8px;">
                    <i class="bi bi-check-lg me-2"></i>Update Rate
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?= csrf_script(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const rateModal = new bootstrap.Modal(document.getElementById('rateModal'), { backdrop: 'static', keyboard: false });
    
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

    function openRateModal() {
        const currentRate = document.getElementById('dailyRate').value.replace('₱ ', '').trim();
        document.getElementById('newRateInput').value = currentRate;
        document.getElementById('newRateInput').focus();
        rateModal.show();
    }

    document.getElementById('newRateInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            updateRate();
        }
    });

    function updateRate() {
        const newRate = parseFloat(document.getElementById('newRateInput').value);
        if (!newRate || newRate <= 0) {
            alert('Please enter a valid rate greater than 0');
            return;
        }

        $.post('', { update_rate: true, new_rate: newRate }, function(res) {
            if (res.status === 'success') {
                document.getElementById('dailyRate').value = '₱ ' + newRate.toFixed(2);
                rateModal.hide();
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed';
                alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);';
                alertDiv.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i><strong>Success!</strong> Rate updated to ₱' + newRate.toFixed(2) + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                document.body.appendChild(alertDiv);
                setTimeout(() => alertDiv.remove(), 3000);
            } else {
                alert('Error: ' + res.message);
            }
        }, 'json').fail(function() {
            alert('Connection error. Please try again.');
        });
    }

    function addWalkin() {
        const nameInput = document.getElementById('walkinName');
        const name = nameInput.value.trim();
        
        if (!name) {
            nameInput.classList.add('error');
            document.getElementById('nameErrorMsg').style.display = 'block';
            nameInput.focus();
            return;
        }
        
        const btn = $('.btn-dark');
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.post('', { name: name }, function(res) {
            if (res.status === 'success') location.reload();
            else {
                alert(res.message);
                btn.prop('disabled', false).html('<i class="bi bi-plus-lg me-2"></i>Record Entry');
            }
        }, 'json');
    }

    document.getElementById('walkinName').addEventListener('input', function() {
        this.classList.remove('error');
        document.getElementById('nameErrorMsg').style.display = 'none';
    });

    $('#walkinSearch').on('keyup', function() {
        let v = $(this).val().toLowerCase();
        $('#walkinList tr').filter(function() { 
            $(this).toggle($(this).find('.name-cell').text().toLowerCase().indexOf(v) > -1); 
        });
    });

    (function() { if (localStorage.getItem('arts-gym-theme') === 'dark') document.body.classList.add('dark-mode-active'); })();
</script>
</body>
</html>
