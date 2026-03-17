<?php
session_start();
require '../auth.php';
require '../connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'member') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch member name
$name_stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$name_stmt->execute([$user_id]);
$member_name = $name_stmt->fetchColumn();

// Fetch all payments with expires_at
try {
    $stmt = $pdo->prepare("SELECT id, amount, sale_date, expires_at FROM sales WHERE user_id = ? ORDER BY sale_date DESC");
    $stmt->execute([$user_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $payments = [];
}

// Build full reference numbers and record totals
$total = 0;
foreach ($payments as &$p) {
    $saleId  = (int)$p['id'];
    $refDate = date('Ymd', strtotime($p['sale_date']));
    $p['ref'] = 'AG-REC-' . str_pad((string)$saleId, 6, '0', STR_PAD_LEFT) . '-' . $refDate;
    $total += floatval($p['amount']);
}
unset($p);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment History | Arts Gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Oswald:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #e63946;
            --dark-red: #9d0208;
            --bg: #f8f9fa;
            --card: #fff;
            --text: #111;
            --muted: #6c757d;
            --border: #f0f0f0;
        }
        body.dark-mode-active {
            --bg: #0a0a0a;
            --card: #141414;
            --text: #f0f0f0;
            --muted: #888;
            --border: #222;
        }
        body { font-family: 'Poppins', sans-serif; background: var(--bg); color: var(--text); transition: background .3s, color .3s; }
        #sidebar { width: 260px; height: 100vh; position: fixed; left: 0; top: 0; z-index: 1100; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        #main { margin-left: 260px; padding: 2rem; min-height: 100vh; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        #main.expanded { margin-left: 80px; }
        #sidebar.collapsed { width: 80px; }
        .card-box { background: var(--card); padding: 24px; border-radius: 16px; box-shadow: 0 6px 24px rgba(0,0,0,0.05); }
        @media (max-width:991.98px) {
            #main { margin-left: 0 !important; padding: 1rem; }
            #sidebar { left: calc(260px * -1); }
            #sidebar.show { left: 0; box-shadow: 10px 0 30px rgba(0,0,0,0.1); }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1090; }
            .sidebar-overlay.show { display: flex; }
            #main.expanded { margin-left: 260px !important; }
        }

        .table thead th { font-size: .72rem; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--border); }
        .table tbody td { border-color: var(--border); vertical-align: middle; }
        .ref-code { font-family: monospace; font-size: .82rem; color: var(--red); font-weight: 600; }
        .btn-receipt {
            background: transparent;
            border: 1px solid rgba(128,128,128,0.25);
            border-radius: 8px;
            padding: 4px 12px;
            font-size: .78rem;
            font-weight: 500;
            color: var(--text);
            cursor: pointer;
            transition: all .2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }
        .btn-receipt:hover { background: var(--red); color: #fff; border-color: var(--red); }

        /* ── Receipt Modal (Dashboard Style) ─────────────────────────────── */
        .receipt-modal .modal-content {
            background: var(--card); border: none; border-radius: 20px;
        }
        #receiptArea {
            background: #fff;
            padding: 40px;
            border: 2px dashed #ddd;
            color: #333;
            font-family: monospace;
            text-align: center;
        }
        #receiptArea hr { border-top: 1px dashed #333; opacity: 1; margin: 15px 0; }
        .receipt-detail { font-size: 0.85rem; display: flex; justify-content: space-between; margin-bottom: 5px; }
        .receipt-detail strong { color: #111; }
        .receipt-total { font-weight: 700; font-size: 1.5rem; color: var(--red); margin-top: 10px; }
        .discount-info { color: #28a745; font-weight: 600; font-size: 0.8rem; }
        .btn-download { width: 100%; border-radius: 10px; padding: 12px; font-weight: 600; margin-top: 20px; }
    </style>
</head>
<body class="<?= isset($_COOKIE['theme']) && $_COOKIE['theme']=='dark' ? 'dark-mode-active':'' ?>">

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<?php include __DIR__ . '/_sidebar.php'; ?>

<div id="main">
    <div class="container-fluid p-0">
        <div class="mb-4 d-flex align-items-center gap-3">
            <button class="btn btn-light d-lg-none" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
            <h2 class="fw-bold mb-0">Payment History</h2>
        </div>

        

        <div class="card-box">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Transactions</h5>
                <div class="text-end">
                    <div class="text-muted small">Total Paid</div>
                    <div class="fw-bold">₱ <?= number_format($total, 2) ?></div>
                </div>
            </div>

            <?php if (count($payments) === 0): ?>
                <div class="text-center text-muted py-5">No payments found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Expires</th>
                                <th class="text-end">Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td><span class="ref-code"><?= htmlspecialchars($p['ref']) ?></span></td>
                                    <td class="fw-semibold">₱<?= number_format($p['amount'], 2) ?></td>
                                    <td class="text-muted small"><?= date('M d, Y', strtotime($p['sale_date'])) ?></td>
                                    <td class="text-muted small">
                                        <?= $p['expires_at'] ? date('M d, Y', strtotime($p['expires_at'])) : '<span class="text-success small">Indefinite</span>' ?>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn-receipt"
                                            onclick="showReceipt(
                                                <?= htmlspecialchars(json_encode($p['ref'])) ?>,
                                                <?= htmlspecialchars(json_encode($member_name)) ?>,
                                                <?= htmlspecialchars(json_encode(date('M d, Y', strtotime($p['sale_date'])))) ?>,
                                                <?= (float)$p['amount'] ?>,
                                                <?= htmlspecialchars(json_encode($p['expires_at'] ? date('M d, Y', strtotime($p['expires_at'])) : 'N/A')) ?>
                                            )">
                                            <i class="bi bi-receipt"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Receipt Modal ─────────────────────────────────────── -->
<div class="modal fade receipt-modal" id="receiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
        <div class="modal-content">
            <div class="modal-body p-4">
                <div id="receiptArea">
                    <h4 class="fw-bold m-0" style="font-family:'Oswald'; letter-spacing:2px;">ARTS GYM</h4>
                    <p class="small m-0 text-uppercase" style="letter-spacing:1px; opacity:0.7;">Official Receipt</p>
                    <p class="small m-0 mt-2" style="font-size:0.7rem;" id="r-ref">AG-REC-000000-00000000</p>
                    <hr>
                    <div class="receipt-detail"><span>Member:</span> <strong id="r-member">—</strong></div>
                    <div class="receipt-detail"><span>Date:</span> <strong id="r-date">—</strong></div>
                    <div class="receipt-detail"><span>Plan Type:</span> <strong id="r-type">—</strong></div>
                    <div class="receipt-detail"><span>Valid Until:</span> <strong id="r-expires">—</strong></div>
                    <hr>
                    <div id="discountBlock" style="display:none;">
                        <div class="receipt-detail"><span>Original Payable:</span> <strong>₱500.00</strong></div>
                        <div class="receipt-detail"><span class="discount-info">Discount (20%):</span> <strong class="discount-info">-₱100.00</strong></div>
                        <hr>
                    </div>
                    <span class="small text-muted text-uppercase fw-bold" style="font-size:0.7rem;">Total Paid</span>
                    <div class="receipt-total" id="r-amount">₱0.00</div>
                    <div class="mt-3 small text-muted italic" style="font-size:0.7rem; opacity:0.6;">Keep this receipt for your records.</div>
                </div>
                <button class="btn btn-danger btn-download fw-bold" data-bs-dismiss="modal">CLOSE</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));

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

function showReceipt(ref, member, date, amount, expires) {
    document.getElementById('r-ref').textContent    = 'Ref No: ' + ref;
    document.getElementById('r-member').textContent = member;
    document.getElementById('r-date').textContent   = date;
    document.getElementById('r-amount').textContent = '₱' + amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('r-expires').textContent = expires;

    // Detect Plan Type
    const type = Math.abs(amount - 400) < 0.01 ? 'Student' : (Math.abs(amount - 500) < 0.01 ? 'Regular' : 'Other');
    document.getElementById('r-type').textContent = type;

    // Discount check: if amount is 400, show discount block
    const discBlock = document.getElementById('discountBlock');
    if (Math.abs(amount - 400) < 0.01) {
        discBlock.style.display = 'block';
    } else {
        discBlock.style.display = 'none';
    }

    receiptModal.show();
}
</script>
</body>
</html>
