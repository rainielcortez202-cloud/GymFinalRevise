<?php
// admin/reports.php
session_start();
require '../auth.php';
require '../connection.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

/* ===================== DATA FETCHING (Supabase/Postgres Fixes) ===================== */

// Members Data
// Added ::text to s.expires_at for PostgreSQL compatibility with the COALESCE '-' string
$members = $pdo->query("
    SELECT u.id, u.full_name, u.email,
           COALESCE(s.amount, 0) AS last_amount,
           COALESCE(s.expires_at::text, '-') AS expires_at
    FROM users u
    LEFT JOIN (
        SELECT s1.*
        FROM sales s1
        INNER JOIN (
            SELECT user_id, MAX(expires_at) AS max_exp
            FROM sales
            GROUP BY user_id
        ) s2 ON s1.user_id = s2.user_id AND s1.expires_at = s2.max_exp
    ) s ON u.id = s.user_id
    WHERE u.role = 'member'
    ORDER BY u.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Attendance Stats
$daily_attendance = $pdo->query("SELECT COUNT(*) FROM attendance WHERE time_in::DATE = CURRENT_DATE")->fetchColumn();
$monthly_attendance = $pdo->query("SELECT COUNT(*) FROM attendance WHERE EXTRACT(MONTH FROM time_in) = EXTRACT(MONTH FROM CURRENT_DATE) AND EXTRACT(YEAR FROM time_in) = EXTRACT(YEAR FROM CURRENT_DATE)")->fetchColumn();

// Sales Stats
$daily_sales = $pdo->query("SELECT SUM(amount) FROM sales WHERE sale_date::DATE = CURRENT_DATE")->fetchColumn() ?? 0;
$monthly_sales = $pdo->query("SELECT SUM(amount) FROM sales WHERE EXTRACT(MONTH FROM sale_date) = EXTRACT(MONTH FROM CURRENT_DATE) AND EXTRACT(YEAR FROM sale_date) = EXTRACT(YEAR FROM CURRENT_DATE)")->fetchColumn() ?? 0;

$total_members = count($members);
$total_paid = 0;
foreach ($members as $m) {
    if ($m['expires_at'] !== '-' && strtotime($m['expires_at']) > time()) {
        $total_paid++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports | Arts Gym</title>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="../assets/js/html2canvas.min.js?v=2"></script>
    <script src="../assets/js/jspdf.umd.min.js?v=2"></script>
    <style>
        :root {
            --primary-red: #e63946;
            --bg-body: #f8f9fa;
            --bg-card: #ffffff;
            --text-main: #212529;
            --text-muted: #6c757d;
            --sidebar-width: 260px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body.dark-mode-active {
            --bg-body: #050505; --bg-card: #111111; --text-main: #ffffff; --text-muted: #b0b0b0;
        }

        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: var(--bg-body); color: var(--text-main);
            transition: var(--transition); overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 { font-family: 'Oswald', sans-serif; text-transform: uppercase; letter-spacing: 1px; }

        /* Layout Structure Synchronization */
        #main {
            margin-left: var(--sidebar-width);
            transition: var(--transition);
            min-height: 100vh;
        }

        #main.expanded {
            margin-left: 80px; /* Mini-sidebar desktop width */
        }

        .top-header {
            background: var(--bg-card); padding: 15px 25px; border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 1000;
        }

        .report-card { 
            background: var(--bg-card); border-radius: 15px; padding: 20px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.05);
            height: 100%; transition: var(--transition);
        }

        .stat-small-label { color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-large-value { font-size: 22px; font-weight: 700; }

        .table thead th { 
            background: rgba(0,0,0,0.02); color: var(--text-muted); 
            font-size: 11px; text-transform: uppercase; padding: 15px; border: none;
        }

        .btn-red { 
            background: var(--primary-red); color: white; border-radius: 8px; 
            padding: 8px 20px; font-weight: 600; border: none; text-transform: uppercase; 
            font-family: 'Oswald'; transition: 0.3s; 
        }
        .btn-red:hover { background: #9d0208; color: white; transform: translateY(-2px); }

        @media (max-width: 991.98px) {
            #main { margin-left: 0 !important; }
        }

        @media print {
            .sidebar, .sidebar-overlay, .btn-actions, .top-header { display: none !important; }
            #main { margin: 0 !important; padding: 0 !important; }
            .report-card { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body class="<?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'dark-mode-active' : '' ?>">

    <!-- Included Sidebar (Handles IDs: sidebar, overlay) -->
    <?php include __DIR__ . '/_sidebar.php'; ?>

    <div id="main">
        <!-- Top Navbar -->
        <header class="top-header">
            <div class="d-flex align-items-center">
                <button class="btn btn-light d-lg-none me-3" onclick="toggleSidebar()">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <div class="d-none d-sm-block">
                    <h5 class="mb-0 fw-bold">Gym Reports</h5>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 btn-actions">
                <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
                <button class="btn btn-red btn-sm" id="downloadPdf">
                    <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                </button>
            </div>
        </header>

        <div class="container-fluid p-3 p-md-4" id="printableReport">
            <div class="mb-4">
                <h2 class="fw-bold mb-1">Executive Summary</h2>
                <p class="text-secondary small fw-bold">GENERATED ON: <?= date("F d, Y") ?></p>
            </div>

            <!-- KEY VALUES -->
            <div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
                <div class="col"><div class="report-card text-center"><div class="stat-small-label">Total Revenue (Daily)</div><div class="stat-large-value tex-success">₱<?= number_format($daily_sales) ?></div></div></div>
                <div class="col"><div class="report-card text-center"><div class="stat-small-label">Total Revenue (Monthly)</div><div class="stat-large-value text-success">₱<?= number_format($monthly_sales) ?></div></div></div>
                <div class="col"><div class="report-card text-center"><div class="stat-small-label">Active Membership</div><div class="stat-large-value text-primary"><?= $total_paid ?> / <?= $total_members ?></div></div></div>
                <div class="col"><div class="report-card text-center"><div class="stat-small-label">Attendance Today</div><div class="stat-large-value"><?= $daily_attendance ?></div></div></div>
            </div>

            <!-- BREAKDOWN CHARTS / LISTS -->
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white border-0 py-3"><h6 class="fw-bold m-0">Membership Health</h6></div>
                        <div class="card-body">
                            <?php 
                                $inactive = $total_members - $total_paid;
                                $width_active = $total_members > 0 ? ($total_paid / $total_members) * 100 : 0;
                                $width_inactive = $total_members > 0 ? ($inactive / $total_members) * 100 : 0;
                            ?>
                            <div class="mb-4">
                                <div class="d-flex justify-content-between text-muted small fw-bold mb-1"><span>Active Members</span><span><?= $total_paid ?></span></div>
                                <div class="progress" style="height: 10px;"><div class="progress-bar bg-success" style="width: <?= $width_active ?>%"></div></div>
                            </div>
                            <div>
                                <div class="d-flex justify-content-between text-muted small fw-bold mb-1"><span>Inactive / Expired</span><span><?= $inactive ?></span></div>
                                <div class="progress" style="height: 10px;"><div class="progress-bar bg-danger" style="width: <?= $width_inactive ?>%"></div></div>
                            </div>
                            <div class="mt-4 p-3 bg-light rounded-3 text-center small text-muted">
                                Active members accounts for <strong><?= number_format($width_active, 1) ?>%</strong> of the total user base.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white border-0 py-3"><h6 class="fw-bold m-0">Financial Overview</h6></div>
                        <div class="card-body d-flex flex-column justify-content-center text-center">
                            <h3 class="fw-bold text-dark mb-0">₱<?= number_format($monthly_sales) ?></h3>
                            <span class="text-muted small text-uppercase fw-bold">Revenue This Month</span>
                            
                            <hr class="alert-secondary my-4">
                            
                            <div class="row text-center">
                                <div class="col">
                                    <h5 class="fw-bold mb-0"><?= $daily_attendance ?></h5>
                                    <span class="text-muted small" style="font-size: 10px;">WALK-INS/VISITS TODAY</span>
                                </div>
                                <div class="col">
                                    <h5 class="fw-bold mb-0"><?= $monthly_attendance ?></h5>
                                    <span class="text-muted small" style="font-size: 10px;">VISITS THIS MONTH</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RECENT TRANSACTIONS (Instead of Full Member List) -->
            <div class="card border-0 shadow-sm rounded-4 mt-4 overflow-hidden">
                <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0">Recent Transactions (Last 5)</h6>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Member Email</th>
                                <th>Amount</th>
                                <th>Expiration</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                            // Fetch last 5 sales
                            $recent_sales = $pdo->query("SELECT s.*, u.email FROM sales s JOIN users u ON s.user_id = u.id ORDER BY s.sale_date DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Mask Helper (Duplicate definition check?)
                            if (!function_exists('maskEmailReport')) {
                                function maskEmailReport($email) {
                                    if (!$email) return 'N/A';
                                    $parts = explode("@", $email);
                                    if (count($parts) < 2) return $email;
                                    $name = $parts[0];
                                    $len = strlen($name);
                                    if ($len <= 4) { $maskedName = substr($name, 0, 1) . str_repeat('*', max(3, $len - 1)); } 
                                    else { $maskedName = substr($name, 0, 2) . str_repeat('*', $len - 3) . substr($name, -1); }
                                    return $maskedName . "@" . $parts[1];
                                }
                            }

                            foreach ($recent_sales as $rs): 
                        ?>
                            <tr>
                                <td class="text-secondary small"><?= date('M d, H:i', strtotime($rs['sale_date'])) ?></td>
                                <td style="font-family: monospace; color: #555;"><?= maskEmailReport($rs['email']) ?></td>
                                <td class="fw-bold text-success">₱<?= number_format($rs['amount'], 2) ?></td>
                                <td class="small"><?= date('M d, Y', strtotime($rs['expires_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="mt-4 text-center text-muted small">
                <em>Detailed member lists have been removed for privacy compliance. Use "Manage Users" for individual lookups.</em>
            </div>
        </div>
    </div>

    <!-- PDF Libraries moved to head -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // PDF Generation
        document.getElementById('downloadPdf').addEventListener('click', async () => {
            try {
                const element = document.getElementById('printableReport');
                const btns = document.querySelector('.btn-actions');
                
                btns.style.visibility = 'hidden';

                const canvas = await html2canvas(element, { scale: 2, useCORS: true });
                const imgData = canvas.toDataURL('image/png');
                const pdf = new window.jspdf.jsPDF('p', 'mm', 'a4');
                const pdfWidth = pdf.internal.pageSize.getWidth();
                const imgProps = pdf.getImageProperties(imgData);
                const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;

                pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
                pdf.save('Arts_Gym_Report_<?= date("Y-m-d") ?>.pdf');
                
                btns.style.visibility = 'visible';
            } catch (err) {
                console.error("PDF Error: ", err);
                alert("Failed to generate PDF. Check console logs.");
                const btns = document.querySelector('.btn-actions');
                if (btns) btns.style.visibility = 'visible';
            }
        });

        // Toggle Dark Mode
        function toggleDarkMode() {
            const isDark = !document.body.classList.contains('dark-mode-active');
            document.cookie = "theme=" + (isDark ? "dark" : "light") + ";path=/;max-age=" + (30*24*60*60);
            location.reload(); 
        }
    </script>
</body>
</html>