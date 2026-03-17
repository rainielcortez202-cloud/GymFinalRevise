<?php
session_start();
require '../auth.php';
require '../connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$startDate = trim((string)($_GET['start_date'] ?? ''));
$endDate = trim((string)($_GET['end_date'] ?? ''));
$source = trim((string)($_GET['source'] ?? ''));

$archivePage = max(1, (int)($_GET['archive_page'] ?? 1));
$limit = 25;

$archiveWhere = [];
$archiveBind = [];
if ($source !== '') {
    $archiveWhere[] = "source_table = ?";
    $archiveBind[] = $source;
}
if ($startDate !== '') {
    $archiveWhere[] = "event_at >= ?";
    $archiveBind[] = $startDate . ' 00:00:00';
}
if ($endDate !== '') {
    $archiveWhere[] = "event_at <= ?";
    $archiveBind[] = $endDate . ' 23:59:59';
}
$archiveWhereSql = $archiveWhere ? ('WHERE ' . implode(' AND ', $archiveWhere)) : '';

$archiveCountStmt = $pdo->prepare("SELECT COUNT(*) FROM data_archive {$archiveWhereSql}");
$archiveCountStmt->execute($archiveBind);
$archiveTotal = (int)$archiveCountStmt->fetchColumn();
$archivePages = max(1, (int)ceil($archiveTotal / $limit));
$archivePage = min($archivePage, $archivePages);
$archiveOffset = ($archivePage - 1) * $limit;

$archiveDataSql = "
    SELECT id, source_table, original_pk, event_at, archived_at, payload::text AS payload_text
    FROM data_archive
    {$archiveWhereSql}
    ORDER BY archived_at DESC
    LIMIT {$limit} OFFSET {$archiveOffset}
";
$archiveDataStmt = $pdo->prepare($archiveDataSql);
$archiveDataStmt->execute($archiveBind);
$archiveRows = $archiveDataStmt->fetchAll(PDO::FETCH_ASSOC);

$sourceRows = $pdo->query("SELECT DISTINCT source_table FROM data_archive ORDER BY source_table ASC")->fetchAll(PDO::FETCH_COLUMN);

function formatArchivePayload($source, $json) {
    $data = json_decode($json, true);
    if (!$data) return htmlspecialchars($json);

    // Try to find a name in the payload
    $name = $data['full_name'] ?? $data['name'] ?? null;
    if (!$name && isset($data['first_name'])) {
        $name = trim($data['first_name'] . ' ' . ($data['last_name'] ?? ''));
    }
    $nameHtml = $name ? " (<strong>" . htmlspecialchars($name) . "</strong>)" : "";

    $out = "";
    switch ($source) {
        case 'attendance':
            $u = $data['user_id'] ?? 'N/A';
            $d = $data['date'] ?? 'N/A';
            $t_raw = $data['time_in'] ?? 'N/A';
            $t = $t_raw !== 'N/A' ? date('h:i A', strtotime($t_raw)) : 'N/A';
            $v = !empty($data['visitor_name']) ? " (Visitor: " . htmlspecialchars($data['visitor_name']) . ")" : "";
            $out = "Attendance: User #<strong>$u</strong>$nameHtml on <strong>$d</strong> at <strong>$t</strong>$v";
            break;

        case 'activity_log':
            $act = htmlspecialchars($data['action'] ?? 'Action');
            $u = $data['user_id'] ?? 'N/A';
            $out = "Activity: <strong>$act</strong> by User #<strong>$u</strong>$nameHtml";
            break;

        case 'walk_ins':
            $n = htmlspecialchars($data['name'] ?? 'Unknown');
            $d = $data['visit_date'] ?? 'N/A';
            $out = "Walk-in: <strong>$n</strong> visited on <strong>$d</strong>";
            break;

        case 'ip_login_attempts':
            $ip = htmlspecialchars($data['ip_address'] ?? 'Unknown IP');
            $att = $data['attempts'] ?? '0';
            $out = "Security: <strong>$att</strong> failed attempts from <strong>$ip</strong>";
            break;

        case 'users':
            $e = htmlspecialchars($data['email'] ?? 'N/A');
            $r = htmlspecialchars($data['role'] ?? 'member');
            $out = "User Purge: <strong>$r</strong> account <strong>$e</strong>$nameHtml (Unverified)";
            break;

        default:
            $summary = [];
            if ($name) $summary[] = "Name: <strong>" . htmlspecialchars($name) . "</strong>";
            foreach (array_slice($data, 0, 3) as $k => $v) {
                if (in_array($k, ['name', 'full_name', 'first_name', 'last_name'])) continue;
                if (is_array($v)) $v = json_encode($v);
                $summary[] = "$k: " . htmlspecialchars((string)$v);
            }
            $out = implode(', ', $summary) . (count($data) > 3 ? '...' : '');
    }
    return $out;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive Logs | Arts Gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --sidebar-width: 260px; }
        body { background: #f8f9fa; }
        #main { margin-left: var(--sidebar-width); min-height: 100vh; }
        .top-header { background: #fff; border-bottom: 1px solid #eee; padding: 14px 20px; position: sticky; top: 0; z-index: 1000; }
        .card-box { background: #fff; border: 1px solid #eee; border-radius: 12px; padding: 18px; }
        .payload-summary { font-size: 0.88rem; line-height: 1.4; color: #333; }
        @media (max-width: 991.98px) { #main { margin-left: 0 !important; } }
    </style>
</head>
<body>
<?php include __DIR__ . '/_sidebar.php'; ?>
<div id="main">
    <header class="top-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light d-lg-none" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
            <div>
                <h5 class="mb-0 fw-bold">Archive</h5>
                <small class="text-muted">Admin-only access</small>
            </div>
        </div>
        <?php include '../global_clock.php'; ?>
    </header>

    <div class="container-fluid p-4">
        <div class="card-box mb-3">
            <form class="row g-2 align-items-end" method="GET">
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Source Table</label>
                    <select name="source" class="form-select">
                        <option value="">All source tables</option>
                        <?php foreach ($sourceRows as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>" <?= $source === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <div class="col-12 col-md-3">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-dark w-100">Filter</button>
                        <a href="archive_logs.php" class="btn btn-light border w-100">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="card-box">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0 fw-bold">Archive Records</h6>
                <span class="text-muted small"><?= $archiveTotal ?> total</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>ID</th><th>Source</th><th>Event Time</th><th>Archived</th><th>Details</th></tr></thead>
                    <tbody>
                    <?php if (!$archiveRows): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No archive records.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($archiveRows as $row): ?>
                        <tr>
                            <td>#<?= (int)$row['id'] ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['source_table']) ?></span></td>
                            <td><small><?= date('M d, Y H:i', strtotime((string)$row['event_at'])) ?></small></td>
                            <td><small><?= date('M d, Y H:i', strtotime((string)$row['archived_at'])) ?></small></td>
                            <td style="min-width:320px;">
                                <div class="payload-summary">
                                    <?= formatArchivePayload($row['source_table'], $row['payload_text']) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($archivePages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <?php for ($i = 1; $i <= $archivePages; $i++): ?>
                            <li class="page-item <?= $i === $archivePage ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['archive_page' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
