<?php
require '../auth.php';
require '../connection.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if ($_SESSION['role'] !== 'member') {
    header("Location: ../login.php");
    exit;
}

// Redirect to profile as dashboard is removed
header("Location: profile.php");
exit;
$current_year = date('Y');
$current_month_short = strtoupper(date('M')); 

/* ===================== DATA FETCHING ===================== */
// 1. Fetch Membership info & Latest Sale
$stmt = $pdo->prepare("
    SELECT u.qr_code, u.full_name, s.id as sale_id, s.amount, s.sale_date, s.expires_at 
    FROM users u 
    LEFT JOIN sales s ON u.id = s.user_id 
    WHERE u.id = ? 
    ORDER BY s.expires_at DESC 
    LIMIT 1
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$qr_code = $user['qr_code'] ?? 'N/A';
$expires_at = $user['expires_at'];
$is_active = $expires_at && strtotime($expires_at) > time();

// Receipt Reference Number (stable per latest sale)
$receipt_ref = null;
if (!empty($user['sale_id'])) {
    $saleDate = $user['sale_date'] ?? date('Y-m-d H:i:s');
    $receipt_ref = 'AG-REC-' . str_pad((string)$user['sale_id'], 6, '0', STR_PAD_LEFT) . '-' . date('Ymd', strtotime($saleDate));
}

// --- NEW EXPIRATION WARNING LOGIC ---
$days_left = 0;
$show_expiry_warning = false;

if ($expires_at) {
    $expiry_date = strtotime($expires_at);
    $today = time();
    $diff = $expiry_date - $today;
    $days_left = ceil($diff / (60 * 60 * 24)); // Convert seconds to days

    // Trigger warning if active but expires in 7 days or less
    if ($is_active && $days_left <= 7 && $days_left >= 0) {
        $show_expiry_warning = true;
    }
}

// 2. Monthly Stats
$work_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM workout_plans 
    WHERE user_id = ? AND status = 'done' 
    AND EXTRACT(MONTH FROM planned_date) = EXTRACT(MONTH FROM CURRENT_DATE) 
    AND EXTRACT(YEAR FROM planned_date) = EXTRACT(YEAR FROM CURRENT_DATE)
");
$work_stmt->execute([$user_id]);
$workout_count = $work_stmt->fetchColumn();

// 3. Attendance Stats
$att_stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT date::date) FROM attendance 
    WHERE user_id = ? 
    AND EXTRACT(MONTH FROM date) = EXTRACT(MONTH FROM CURRENT_DATE) 
    AND EXTRACT(YEAR FROM date) = EXTRACT(YEAR FROM CURRENT_DATE)
");
$att_stmt->execute([$user_id]);
$attendance_count = $att_stmt->fetchColumn();

// 4. Consistency Map Data
$year_att_stmt = $pdo->prepare("
    SELECT EXTRACT(MONTH FROM date) as month, EXTRACT(DAY FROM date) as day 
    FROM attendance 
    WHERE user_id = ? AND EXTRACT(YEAR FROM date) = ?
");
$year_att_stmt->execute([$user_id, $current_year]);
$attendance_map = [];
foreach ($year_att_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $attendance_map[(int)$row['month']][] = (int)$row['day'];
}

$qr_url = "qr_code.php?qr=" . urlencode($qr_code);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard | Arts Gym</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/html2canvas.min.js?v=2"></script>
    <script src="../assets/js/jspdf.umd.min.js?v=2"></script>
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

        h1, h2, h3, h5 { font-family: 'Oswald', sans-serif; text-transform: uppercase; letter-spacing: 1px; }

        #main {
            margin-left: var(--sidebar-width);
            transition: var(--transition);
            min-height: 100vh;
            padding: 0;
        }

        #main.expanded { margin-left: 80px; }

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

        .stat-card { 
            background: var(--bg-card); 
            border-radius: 15px; 
            padding: 25px; 
            text-align: center; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.03); 
            border-bottom: 4px solid var(--primary-red); 
            height: 100%; 
        }

        .consistency-card { 
            background: var(--bg-card); 
            border-radius: 15px; 
            padding: 30px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
        }

        .month-row { display: flex; align-items: center; margin-bottom: 12px; width: 100%; }
        .month-name { flex: 0 0 100px; font-family: 'Oswald'; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); }
        .dots-container { display: flex; flex-wrap: wrap; gap: 5px; align-items: center; }
        
        .dot-attended { color: var(--primary-red); font-size: 1.4rem; line-height: 1; font-weight: 900; }
        .dot-missed { color: var(--text-muted); opacity: 0.3; font-size: 1.4rem; line-height: 1; font-weight: 900; }

        .qr-card-box { 
            background: var(--bg-card); 
            border-radius: 20px; 
            padding: 30px; 
            text-align: center; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
            position: sticky; 
            top: 100px; 
        }

        @media (max-width: 991px) {
            #main { margin-left: 0 !important; }
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
                <h5 class="mb-0 fw-bold">Member Portal</h5>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php include '../global_clock.php'; ?>
            </div>
        </header>

        <div class="container-fluid p-4">
            <div class="mb-4">
                <h1 class="fw-bold mb-0">Welcome, <?= explode(' ', $user['full_name'])[0] ?>!</h1>
                <p class="text-secondary small fw-bold text-uppercase">Consistency Map | <?= $current_year ?></p>
            </div>

            <div class="row g-4">
                <!-- Left Column: Stats & Map -->
                <div class="col-lg-8">
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <div class="stat-card">
                                <h2 class="fw-bold m-0"><?= $attendance_count ?></h2>
                                <p class="small text-muted m-0 text-uppercase fw-bold">Days Attended (<?= $current_month_short ?>)</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-card">
                                <h2 class="fw-bold m-0"><?= $workout_count ?></h2>
                                <p class="small text-muted m-0 text-uppercase fw-bold">Workouts Done (<?= $current_month_short ?>)</p>
                            </div>
                        </div>
                    </div>

                    <div class="consistency-card">
                        <h5 class="mb-4 border-start border-danger border-4 ps-3">Annual Progress</h5>
                        <div class="row">
                            <?php 
                            for ($m = 1; $m <= 12; $m++): 
                                $month_label = date('M', mktime(0, 0, 0, $m, 1));
                                $days_in_month = cal_days_in_month(CAL_GREGORIAN, $m, (int)$current_year);
                                if ($m == 1 || $m == 7) echo '<div class="col-md-6 px-3">';
                            ?>
                                <div class="month-row">
                                    <div class="month-name"><?= $month_label ?></div>
                                    <div class="dots-container">
                                        <?php for ($d = 1; $d <= $days_in_month; $d++): 
                                            $is_past_or_today = ($m < date('n')) || ($m == date('n') && $d <= date('j'));
                                            $attended = (isset($attendance_map[$m]) && in_array($d, $attendance_map[$m]));
                                        ?>
                                            <span class="<?= ($attended) ? 'dot-attended' : 'dot-missed' ?>" 
                                                  title="<?= $month_label ?> <?= $d ?>"
                                                  style="<?= (!$is_past_or_today) ? 'opacity: 0.05;' : '' ?>">
                                                ●
                                            </span>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            <?php if ($m == 6 || $m == 12) echo '</div>'; endfor; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column: QR Pass -->
                <div class="col-lg-4">
                    <div class="qr-card-box">
                        <h5 class="fw-bold mb-4">YOUR ACCESS PASS</h5>
                        <div class="bg-white p-3 rounded mb-3 shadow-sm d-inline-block border">
                            <img src="<?= $qr_url ?>" alt="QR Pass" class="img-fluid" style="max-width: 160px;">
                        </div>
                        <div class="mb-3">
                            <span class="badge <?= $is_active ? 'bg-success' : 'bg-danger' ?> px-3 py-2">
                                <?= $is_active ? 'MEMBERSHIP ACTIVE' : 'MEMBERSHIP EXPIRED' ?>
                            </span>
                        </div>
                        <div class="text-start small mb-4 p-3 rounded bg-light border text-dark">
                            <p class="mb-1 d-flex justify-content-between"><span>MEMBER ID:</span> <strong>#<?= str_pad($user_id, 5, '0', STR_PAD_LEFT) ?></strong></p>
                            <p class="mb-0 d-flex justify-content-between"><span>VALID UNTIL:</span> <strong><?= $expires_at ? date('M d, Y', strtotime($expires_at)) : 'N/A' ?></strong></p>
                        </div>
                        <button class="btn btn-outline-danger w-100 fw-bold" data-bs-toggle="modal" data-bs-target="#receiptModal">
                            <i class="bi bi-receipt me-2"></i>VIEW RECEIPT
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Membership Expiration Warning -->
    <div class="modal fade" id="expiryWarningModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-body p-5 text-center">
                    <div class="mb-4">
                        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 4rem;"></i>
                    </div>
                    <h3 class="fw-bold text-uppercase" style="font-family:'Oswald';">Membership Expiring!</h3>
                    <p class="text-secondary">Your membership will expire in <span class="text-danger fw-bold"><?= $days_left ?> days</span>.</p>
                    <p class="small text-muted mb-4">Please visit the front desk to renew your plan and maintain your access to the gym facility.</p>
                    <button type="button" class="btn btn-danger w-100 py-3 fw-bold text-uppercase" data-bs-dismiss="modal">I Understand</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipt Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-body p-4 text-center">
                    <div id="receiptArea" style="background:white; padding:40px; border:2px dashed #ddd; color:#333; font-family:monospace;">
                        <h4 class="fw-bold m-0">ARTS GYM</h4>
                        <p class="small m-0">Official Receipt</p>
                        <?php if ($receipt_ref): ?>
                            <p class="small m-0"><strong>Reference No.:</strong> <?= htmlspecialchars($receipt_ref) ?></p>
                        <?php endif; ?>
                        <hr>
                        <div class="small d-flex justify-content-between"><span>Member:</span> <strong><?= htmlspecialchars($user['full_name']) ?></strong></div>
                        <div class="small d-flex justify-content-between"><span>Date:</span> <strong><?= date('M d, Y', strtotime($user['sale_date'] ?? 'now')) ?></strong></div>
                        
                        <?php if (isset($user['amount'])): ?>
                            <div class="small d-flex justify-content-between">
                                <span>Plan Type:</span> 
                                <strong><?= abs($user['amount'] - 400) < 0.01 ? 'Student' : (abs($user['amount'] - 500) < 0.01 ? 'Regular' : 'Other') ?></strong>
                            </div>
                            
                            <?php if (abs($user['amount'] - 400) < 0.01): ?>
                                <hr style="border-top:1px dashed #333;">
                                <div class="small d-flex justify-content-between"><span>Original:</span> <strong>₱500.00</strong></div>
                                <div class="small d-flex justify-content-between text-success"><span>Discount (20%):</span> <strong>-₱100.00</strong></div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <hr>
                        <span class="small text-muted">Total Paid</span>
                        <h2 class="fw-bold">₱<?= number_format($user['amount'] ?? 0, 2) ?></h2>
                    </div>
                    <button class="btn btn-danger w-100 mt-4 fw-bold" onclick="downloadReceipt()">DOWNLOAD AS IMAGE</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Trigger the expiry warning modal if PHP condition is met
            <?php if ($show_expiry_warning): ?>
            const expiryModal = new bootstrap.Modal(document.getElementById('expiryWarningModal'));
            expiryModal.show();
            <?php endif; ?>

            // Auto-open receipt modal after successful login redirect
            <?php if (isset($_GET['show_receipt']) && $_GET['show_receipt'] == '1'): ?>
            const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
            receiptModal.show();
            <?php endif; ?>
        });

        function toggleDarkMode() {
            const isDark = !document.body.classList.contains('dark-mode-active');
            document.cookie = "theme=" + (isDark ? "dark" : "light") + ";path=/;max-age=" + (30*24*60*60);
            location.reload(); 
        }

        function downloadReceipt() {
            html2canvas(document.getElementById('receiptArea'), {backgroundColor: "#ffffff", scale: 2}).then(canvas => {
                const link = document.createElement('a');
                const ref = <?= $receipt_ref ? json_encode($receipt_ref) : "''" ?>;
                link.download = (ref ? `ArtsGym-Receipt-${ref}.png` : 'ArtsGym-Receipt.png');
                link.href = canvas.toDataURL("image/png");
                link.click();
            });
        }
    </script>
</body>
</html>