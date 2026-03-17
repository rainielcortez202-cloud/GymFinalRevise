<?php
require '../connection.php'; 

// --- 1. HANDLE AJAX MUSCLE FETCHING (Step 2) ---
if (isset($_GET['fetch_muscles'])) {
    header('Content-Type: application/json');
    $group_ids = $_GET['group_ids'] ?? [];
    if (empty($group_ids)) { echo json_encode([]); exit; }
    $placeholders = implode(',', array_fill(0, count($group_ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name FROM muscles WHERE muscle_group_id IN ($placeholders) ORDER BY name ASC");
    $stmt->execute($group_ids);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// --- 2. HANDLE AJAX EXERCISE FETCHING (Step 3) ---
if (isset($_GET['fetch_exercises'])) {
    header('Content-Type: application/json');
    $muscle_ids = $_GET['muscle_ids'] ?? [];
    if (empty($muscle_ids)) { echo json_encode([]); exit; }
    $placeholders = implode(',', array_fill(0, count($muscle_ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name FROM exercises WHERE muscle_id IN ($placeholders) ORDER BY name ASC");
    $stmt->execute($muscle_ids);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

require '../auth.php';

if ($_SESSION['role'] !== 'member') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// --- 3. SAVE PLAN ---
if (isset($_POST['save_plan'])) {
    $date = $_POST['planned_date'];
    $exercise_ids = $_POST['exercise_ids'] ?? [];
    if ($date < $today) { die("Past date restricted."); }

    $stmt = $pdo->prepare("INSERT INTO workout_plans (user_id, planned_date, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$user_id, $date]);
    $plan_id = $pdo->lastInsertId();

    if ($plan_id) {
        foreach ($exercise_ids as $ex_id) {
            $pdo->prepare("INSERT INTO workout_plan_exercises (plan_id, exercise_id) VALUES (?, ?)")->execute([$plan_id, $ex_id]);
        }
    }
    header("Location: my_workouts.php");
    exit;
}

// --- 4. ACTIONS: MARK DONE & DELETE ---
if (isset($_GET['mark_done'])) {
    $pdo->prepare("UPDATE workout_plans SET status = 'done' WHERE id = ? AND user_id = ?")->execute([$_GET['mark_done'], $user_id]);
    header("Location: my_workouts.php"); exit;
}
if (isset($_GET['delete_id'])) {
    $pdo->prepare("DELETE FROM workout_plans WHERE id = ? AND user_id = ?")->execute([$_GET['delete_id'], $user_id]);
    header("Location: my_workouts.php"); exit;
}

// --- 5. CALENDAR DATA ---
$events_stmt = $pdo->prepare("
    SELECT wp.id, wp.planned_date, wp.status, STRING_AGG(DISTINCT e.name, ', ') as exercises
    FROM workout_plans wp
    JOIN workout_plan_exercises wpe ON wp.id = wpe.plan_id
    JOIN exercises e ON wpe.exercise_id = e.id
    WHERE wp.user_id = ? 
    GROUP BY wp.id, wp.planned_date, wp.status
");
$events_stmt->execute([$user_id]);
$events = [];
foreach ($events_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $done = ($row['status'] === 'done');
    $events[] = [
        'id' => $row['id'], 
        'title' => ($done ? '✅ ' : '🏋️ ') . $row['exercises'], 
        'start' => $row['planned_date'], 
        'color' => $done ? '#28a745' : '#e63946'
    ];
}

// --- 6. HISTORY TABLE ---
$hist_stmt = $pdo->prepare("
    SELECT 
        STRING_AGG(DISTINCT mg.name, ', ') as muscle_groups,
        STRING_AGG(DISTINCT m.name, ', ') as muscles,
        STRING_AGG(DISTINCT e.name, ', ') as exercises,
        wp.planned_date as dt
    FROM workout_plans wp
    JOIN workout_plan_exercises wpe ON wp.id = wpe.plan_id
    JOIN exercises e ON wpe.exercise_id = e.id
    JOIN muscles m ON e.muscle_id = m.id
    JOIN muscle_groups mg ON m.muscle_group_id = mg.id
    WHERE wp.user_id = ? AND wp.status = 'done' 
      AND EXTRACT(MONTH FROM wp.planned_date) = EXTRACT(MONTH FROM CURRENT_DATE)
    GROUP BY wp.id, wp.planned_date ORDER BY dt DESC
");
$hist_stmt->execute([$user_id]);
$logs = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);

$muscle_groups_list = $pdo->query("SELECT id, name FROM muscle_groups ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workout Planner | Arts Gym</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">

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
        }

        h1, h2, h3, h5 { font-family: 'Oswald', sans-serif; text-transform: uppercase; letter-spacing: 1px; }

        /* Synchronized Layout */
        #main {
            margin-left: var(--sidebar-width);
            transition: var(--transition);
            min-height: 100vh;
        }

        #main.expanded { margin-left: 80px; }

        @media (max-width: 991px) {
            #main { margin-left: 0 !important; }
        }

        /* Top Header */
        .top-header {
            background: var(--bg-card);
            padding: 15px 25px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky; top: 0; z-index: 1000;
        }

        /* Calendar & Card Styles */
        .fc { 
            background: var(--bg-card); 
            padding: 20px; 
            border-radius: 15px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.03); 
            border: 1px solid rgba(0,0,0,0.05);
        }

        .custom-card { 
            background: var(--bg-card); 
            border-radius: 15px; 
            padding: 25px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.03); 
            border: 1px solid rgba(0,0,0,0.05);
        }

       /* Improved Multi-list for a scrollable modal */
.multi-list { 
    max-height: 250px; /* Increased from 150px */
    overflow-y: auto; 
    border: 1px solid rgba(0,0,0,0.1); 
    padding: 10px; 
    border-radius: 8px; 
    background: rgba(0,0,0,0.02); 
    margin-bottom: 15px; 
}

/* Ensure the modal body itself handles overflow properly */
.modal-body {
    padding: 20px;
    max-height: 70vh; /* Limits height to 70% of screen height */
}

.dark-mode-active .multi-list { 
    background: rgba(255,255,255,0.03); 
    border-color: rgba(255,255,255,0.1); 
}
        
        .dark-mode-active .multi-list { background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.1); }

        .multi-list label { 
            display: flex; align-items: center; gap: 10px; 
            margin-bottom: 5px; cursor: pointer; padding: 5px; 
            border-radius: 4px; font-size: 0.85rem; transition: 0.2s;
        }
        .multi-list label:hover { background: rgba(230, 57, 70, 0.1); }

        .btn-brand {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            color: white !important; border: none; font-weight: 700; font-family: 'Oswald';
        }

        .fc-toolbar-title { font-family: 'Oswald' !important; text-transform: uppercase; }
        .fc .fc-button-primary { background-color: var(--primary-red); border-color: var(--primary-red); }
        .fc .fc-button-primary:hover { background-color: var(--dark-red); border-color: var(--dark-red); }
    </style>
</head>
<body class="<?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'dark-mode-active' : '' ?>">

    <!-- INCLUDE UNIFIED SIDEBAR -->
    <?php include __DIR__ . '/_sidebar.php'; ?>

    <div id="main">
        <!-- Top Header -->
        <header class="top-header">
            <div class="d-flex align-items-center">
                <button class="btn btn-light d-lg-none me-3" onclick="toggleSidebar()">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <h5 class="mb-0 fw-bold">Workout Planner</h5>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php include '../global_clock.php'; ?>
            </div>
        </header>

        <div class="container-fluid p-4">
            <div class="mb-4">
                <h2 class="fw-bold mb-1">My Routine</h2>
                <p class="text-secondary small fw-bold text-uppercase">Schedule workouts & Track consistency</p>
            </div>

            <!-- THE CALENDAR (Preserved Logic) -->
            <div id="calendar" class="mb-5"></div>

            <!-- History Section -->
            <div class="custom-card shadow-sm">
                <h4 class="fw-bold mb-3 text-danger"><i class="bi bi-clock-history me-2"></i>Completed this Month</h4>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="text-muted small text-uppercase">
                            <tr>
                                <th>Focus Area</th>
                                <th>Muscles</th>
                                <th>Date</th>
                                <th>Exercises Done</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php if(empty($logs)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No completed workouts yet this month.</td></tr>
                            <?php endif; ?>
                            <?php foreach($logs as $l): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($l['muscle_groups']) ?></td>
                                <td><?= htmlspecialchars($l['muscles']) ?></td>
                                <td><?= date('M d, Y', strtotime($l['dt'])) ?></td>
                                <td class="text-secondary"><?= htmlspecialchars($l['exercises']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<!-- Planning Modal -->
<div class="modal fade" id="planModal" tabindex="-1" aria-labelledby="planModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="fw-bold mb-0" id="planModalLabel">Schedule Workout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form method="POST" id="planForm">
                <div class="modal-body">
                    <input type="hidden" name="planned_date" id="planned_date">
                    
                    <!-- Step 1 -->
                    <div class="mb-3">
                        <label class="small fw-bold mb-2 d-block text-uppercase text-danger">1. Muscle Group(s)</label>
                        <div class="multi-list">
                            <?php foreach($muscle_groups_list as $mg): ?>
                                <label class="w-100">
                                    <input type="checkbox" name="mg_ids[]" class="mg-check" value="<?= $mg['id'] ?>"> 
                                    <span><?= htmlspecialchars($mg['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="mb-3">
                        <label class="small fw-bold mb-2 d-block text-uppercase text-danger">2. Specific Muscle(s)</label>
                        <div id="muscle_list" class="multi-list text-muted small">
                            <p class="mb-0 py-2 text-center">Choose focus area(s) above...</p>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="mb-3">
                        <label class="small fw-bold mb-2 d-block text-uppercase text-danger">3. Exercise(s)</label>
                        <div id="exercise_list" class="multi-list text-muted small">
                            <p class="mb-0 py-2 text-center">Choose muscle(s) above...</p>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 bg-light">
                    <div class="d-flex w-100 gap-2">
                        <!-- Buttons stay pinned to bottom because of modal-dialog-scrollable -->
                        <button type="button" class="btn btn-success flex-grow-1 fw-bold d-none" id="doneBtn">Mark Done</button>
                        <button type="submit" name="save_plan" class="btn btn-brand flex-grow-1" id="saveBtn">Save Plan</button>
                        <button type="button" class="btn btn-outline-secondary d-none px-3" id="delBtn"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    
    <script>
        // Synchronized Dark Mode (Cookie-based)
        function toggleDarkMode() {
            const isDark = !document.body.classList.contains('dark-mode-active');
            document.cookie = "theme=" + (isDark ? "dark" : "light") + ";path=/;max-age=" + (30*24*60*60);
            location.reload(); 
        }

        // CALENDAR SCRIPT (Unchanged)
        document.addEventListener('DOMContentLoaded', function() {
            var calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
                initialView: 'dayGridMonth',
                events: <?= json_encode($events) ?>,
                headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
                firstDay: 1,
                dateClick: function(info) {
                    if (info.dateStr < '<?= $today ?>') return;
                    $('#planned_date').val(info.dateStr); 
                    $('#planForm')[0].reset();
                    $('#muscle_list').html('Choose group(s) above...');
                    $('#exercise_list').html('Choose muscle(s) above...');
                    $('#saveBtn').show(); $('#doneBtn, #delBtn').addClass('d-none');
                    new bootstrap.Modal(document.getElementById('planModal')).show();
                },
                eventClick: function(info) {
                    $('#doneBtn').removeClass('d-none').off().click(() => location.href='my_workouts.php?mark_done='+info.event.id);
                    $('#delBtn').removeClass('d-none').off().click(() => { if(confirm('Delete plan?')) location.href='my_workouts.php?delete_id='+info.event.id; });
                    $('#saveBtn').hide();
                    new bootstrap.Modal(document.getElementById('planModal')).show();
                }
            });
            calendar.render();

            // AJAX Cascading Logic (Unchanged)
            $(document).on('change', '.mg-check', function() {
                let ids = []; $('.mg-check:checked').each(function(){ ids.push($(this).val()); });
                if(!ids.length) { $('#muscle_list').html('Choose group(s)...'); $('#exercise_list').html('...'); return; }
                $.get('my_workouts.php', { fetch_muscles: 1, group_ids: ids }, function(data){
                    let html = ''; data.forEach(m => { html += `<label><input type="checkbox" name="m_ids[]" class="m-check" value="${m.id}"> <span>${m.name}</span></label>`; });
                    $('#muscle_list').html(html || 'No muscles.');
                });
            });

            $(document).on('change', '.m-check', function() {
                let ids = []; $('.m-check:checked').each(function(){ ids.push($(this).val()); });
                if(!ids.length) { $('#exercise_list').html('Choose muscle(s)...'); return; }
                $.get('my_workouts.php', { fetch_exercises: 1, muscle_ids: ids }, function(data){
                    let html = ''; data.forEach(e => { html += `<label><input type="checkbox" name="exercise_ids[]" value="${e.id}"> <span>${e.name}</span></label>`; });
                    $('#exercise_list').html(html || 'No exercises.');
                });
            });
        });
    </script>
</body>
</html>