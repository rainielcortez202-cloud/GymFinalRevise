<?php
// admin/manage_exercises.php
session_start();
require '../auth.php';
require '../connection.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

/* GET SELECTIONS */
$group_id  = $_GET['group']  ?? null;
$muscle_id = $_GET['muscle'] ?? null;
$activity_filter = $_GET['activity_level'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
}

$activity_options = [
    'sedentary' => 'Sedentary',
    'light' => 'Lightly Active',
    'moderate' => 'Moderately Active',
    'very_active' => 'Very Active',
    'extra_active' => 'Extra Active'
];

/* FETCH GROUPS */
$groups = $pdo->query("SELECT * FROM muscle_groups ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

/* SEED LIBRARY (if empty) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seed_library'])) {
    require_once __DIR__ . '/../includes/seed_exercise_library.php';
    seed_exercise_library($pdo);
    header("Location: manage_exercises.php?seed=1&activity_level=$activity_filter");
    exit;
}

/* FETCH MUSCLES */
$muscles = [];
if ($group_id) {
    $stmt = $pdo->prepare("SELECT * FROM muscles WHERE muscle_group_id=? ORDER BY id");
    $stmt->execute([$group_id]);
    $muscles = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$muscles_total = (int)$pdo->query("SELECT COUNT(*) FROM muscles")->fetchColumn();
$exercises_total = (int)$pdo->query("SELECT COUNT(*) FROM exercises")->fetchColumn();
$library_empty = ($muscles_total === 0 && $exercises_total === 0);

/* ADD / UPDATE */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_exercise'])) {
    if (empty($_POST['exercise_id'])) {
        $stmt = $pdo->prepare("
            INSERT INTO exercises
            (muscle_group_id, muscle_id, name, video_url, description, activity_level)
            VALUES (?,?,?,?,?,?)
        ");
        $stmt->execute([
            $_POST['muscle_group_id'],
            $_POST['muscle_id'],
            $_POST['name'],
            $_POST['video_url'],
            $_POST['description'],
            $_POST['activity_level']
        ]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE exercises SET
                name=?, video_url=?, description=?, activity_level=?
            WHERE id=?
        ");
        $stmt->execute([
            $_POST['name'],
            $_POST['video_url'],
            $_POST['description'],
            $_POST['activity_level'],
            $_POST['exercise_id']
        ]);
    }
    header("Location: manage_exercises.php?group=$group_id&muscle=$muscle_id&activity_level=$activity_filter");
    exit;
}

/* DELETE */
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM exercises WHERE id=?");
    $stmt->execute([$_GET['delete']]);
    header("Location: manage_exercises.php?group=$group_id&muscle=$muscle_id&activity_level=$activity_filter");
    exit;
}

/* FETCH EXERCISES */
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$total_pages = 1;

$exercises = [];
if ($muscle_id) {
    $where = ["muscle_id = ?"];
    $bind = [$muscle_id];
    
    if ($activity_filter) {
        $where[] = "activity_level = ?";
        $bind[] = $activity_filter;
    }
    
    $whereSql = "WHERE " . implode(" AND ", $where);

    $t_stmt = $pdo->prepare("SELECT COUNT(*) FROM exercises $whereSql");
    $t_stmt->execute($bind);
    $total = $t_stmt->fetchColumn();
    $total_pages = ceil($total / $limit);

    $stmt = $pdo->prepare("SELECT * FROM exercises $whereSql ORDER BY id DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($bind);
    $exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* EDIT */
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM exercises WHERE id=?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Exercise | Arts Gym</title>

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

        /* Minimalist Cards */
        .card-custom {
            background: var(--bg-card); border-radius: 20px; padding: 24px;
            box-shadow: var(--card-shadow); border: none; margin-bottom: 24px;
        }

        /* Guided Step Indicator */
        .step-pill {
            display: inline-flex; align-items: center; justify-content: center;
            width: 28px; height: 28px; border-radius: 50%;
            background: rgba(230, 57, 70, 0.1); color: var(--primary-red);
            font-size: 0.75rem; font-weight: 700; margin-right: 12px;
        }

        .section-title { font-size: 0.9rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-main); }

        /* Form Controls */
        .form-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; }
        
        .form-control-minimal {
            background: var(--bg-body); border: 1px solid var(--border-color);
            border-radius: 12px; padding: 12px 16px; font-weight: 500; font-size: 0.9rem; transition: all 0.2s;
            color: var(--text-main);
        }
        .form-control-minimal:focus {
            background: var(--bg-card); border-color: var(--primary-red);
            box-shadow: 0 0 0 4px rgba(230, 57, 70, 0.1); outline: none;
        }

        /* Buttons */
        .btn-main {
            background: var(--text-main); color: var(--bg-card);
            border: none; border-radius: 12px; padding: 12px 24px;
            font-weight: 600; font-size: 0.85rem; transition: all 0.2s;
        }
        .btn-main:hover { opacity: 0.9; transform: translateY(-1px); }

        /* Table Style */
        .table thead th {
            background: transparent; font-size: 0.7rem; font-weight: 700;
            text-transform: uppercase; color: var(--text-muted);
            padding: 16px 20px; border-bottom: 1px solid var(--border-color);
        }
        .table tbody td { padding: 16px 20px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }

        .media-icon { font-size: 1.1rem; opacity: 0.7; margin-right: 8px; }

        @media (max-width: 991.98px) { #main { margin-left: 0 !important; padding: 1.5rem; } }
    </style>
</head>
<body class="<?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'dark-mode-active' : '' ?>">

    <?php include __DIR__ . '/_sidebar.php'; ?>

    <div id="main">
        <header class="top-header">
            <div>
                <button class="btn btn-light d-lg-none me-2" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
                <h4 class="mb-0 fw-bold">Exercise Library</h4>
                <p class="text-muted small mb-0">Manage movements and instructions</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php include '../global_clock.php'; ?>
            </div>
        </header>

        <div class="row g-4">
            <!-- Left Column: Navigation -->
            <div class="col-12 col-xl-4">
                <?php if ($library_empty): ?>
                <div class="card-custom">
                    <div class="d-flex align-items-center mb-3">
                        <span class="step-pill">!</span>
                        <span class="section-title">Library Empty</span>
                    </div>
                    <p class="text-muted small mb-3">Muscles and exercises are missing. Seed starter data to restore the member library.</p>
                    <form method="POST" id="seedForm">
                        <?= csrf_field(); ?>
                        <button type="submit" name="seed_library" class="btn btn-danger w-100 fw-bold">
                            Seed Exercise Library
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <div class="card-custom">
                    <div class="d-flex align-items-center mb-4">
                        <span class="step-pill">1</span>
                        <span class="section-title">Activity Level</span>
                    </div>
                    <form method="GET">
                        <select class="form-control-minimal w-100 step-select" name="activity_level">
                            <option value="">All Activity Levels</option>
                            <?php foreach($activity_options as $val => $label): ?>
                                <option value="<?= $val ?>" <?= $activity_filter==$val?'selected':'' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($group_id): ?>
                            <input type="hidden" name="group" value="<?= $group_id ?>">
                        <?php endif; ?>
                        <?php if ($muscle_id): ?>
                            <input type="hidden" name="muscle" value="<?= $muscle_id ?>">
                        <?php endif; ?>
                    </form>
                </div>

                <div class="card-custom">
                    <div class="d-flex align-items-center mb-4">
                        <span class="step-pill">2</span>
                        <span class="section-title">Muscle Group</span>
                    </div>
                    <form method="GET">
                        <?php if ($activity_filter): ?>
                            <input type="hidden" name="activity_level" value="<?= $activity_filter ?>">
                        <?php endif; ?>
                        <select class="form-control-minimal w-100 step-select" name="group">
                            <option value="">Select Category...</option>
                            <?php foreach($groups as $g): ?>
                                <option value="<?= $g['id'] ?>" <?= $group_id==$g['id']?'selected':'' ?>>
                                    <?= htmlspecialchars($g['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <?php if ($group_id): ?>
                <div class="card-custom">
                    <div class="d-flex align-items-center mb-4">
                        <span class="step-pill">3</span>
                        <span class="section-title">Specific Muscle</span>
                    </div>
                    <form method="GET">
                        <?php if ($activity_filter): ?>
                            <input type="hidden" name="activity_level" value="<?= $activity_filter ?>">
                        <?php endif; ?>
                        <input type="hidden" name="group" value="<?= $group_id ?>">
                        <select class="form-control-minimal w-100 step-select" name="muscle">
                            <option value="">Select Muscle...</option>
                            <?php foreach($muscles as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= $muscle_id==$m['id']?'selected':'' ?>>
                                    <?= htmlspecialchars($m['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column: Content -->
            <div class="col-12 col-xl-8">
                <?php if ($muscle_id): ?>
                <div class="card-custom">
                    <div class="d-flex align-items-center mb-4">
                        <span class="step-pill">4</span>
                        <span class="section-title"><?= $edit ? 'Edit Exercise' : 'Add New Exercise' ?></span>
                    </div>
                    
                    <form method="POST" id="exerciseForm">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="exercise_id" value="<?= $edit['id'] ?? '' ?>">
                        <input type="hidden" name="muscle_group_id" value="<?= $group_id ?>">
                        <input type="hidden" name="muscle_id" value="<?= $muscle_id ?>">

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Exercise Name</label>
                                <input class="form-control-minimal w-100" name="name" placeholder="e.g. Incline DB Press" required value="<?= $edit['name'] ?? '' ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Video</label>
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="hidden" id="videoUrlHidden" name="video_url" value="<?= htmlspecialchars($edit['video_url'] ?? '') ?>">
                                    <button type="button" class="btn-main" id="chooseVideoBtn" title="Upload video">
                                        <i class="bi bi-camera-video"></i> Upload MP4 Video
                                    </button>
                                </div>
                                <input type="file" id="videoFileInput" accept="video/mp4,video/webm" style="display:none">
                                <div id="videoPreview" class="mt-3">
                                    <?php if(!empty($edit['video_url'])): ?>
                                        <?php 
                                            // Ensure the video URL works from the admin directory
                                            $preview_path = $edit['video_url'];
                                            if (strpos($preview_path, 'admin/') === 0) {
                                                $preview_path = '../' . $preview_path;
                                            }
                                        ?>
                                        <video src="<?= htmlspecialchars($preview_path) ?>" controls style="max-height:100px; border-radius:8px; background:#000;"></video>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted d-block mt-2">Upload a short MP4 video. Uploaded videos are stored in <code>admin/uploads/exercises/</code></small>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Coaching Cues</label>
                                <textarea class="form-control-minimal w-100" name="description" rows="3" placeholder="Explain the technique..."><?= $edit['description'] ?? '' ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Activity Level Suitability</label>
                                <select class="form-control-minimal w-100" name="activity_level" required>
                                    <?php 
                                    $selected_level = $edit['activity_level'] ?? ($activity_filter ?? 'moderate');
                                    foreach($activity_options as $val => $label): ?>
                                        <option value="<?= $val ?>" <?= $selected_level==$val?'selected':'' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 text-end">
                                <button class="btn-main" name="save_exercise">
                                    <?= $edit ? "Update Exercise" : "Create Exercise" ?>
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="mt-5">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h6 class="section-title mb-0">Existing Exercises</h6>
                            <?php if ($activity_filter): ?>
                                <span class="badge bg-danger">Level: <?= $activity_options[$activity_filter] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Activity Level</th>
                                        <th>Media</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($exercises as $e): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($e['name']) ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark border small">
                                                <?= ucfirst(str_replace('_', ' ', $e['activity_level'] ?? 'sedentary')) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if(!empty($e['video_url'])): ?>
                                                <?php 
                                                    // Ensure the video URL works from the admin directory
                                                    $video_path = $e['video_url'];
                                                    if (strpos($video_path, 'admin/') === 0) {
                                                        $video_path = '../' . $video_path;
                                                    }
                                                ?>
                                                <video src="<?= htmlspecialchars($video_path) ?>" controls style="max-height:80px; max-width:140px; border-radius:6px; background:#000; display:block; object-fit:contain;"></video>
                                            <?php else: ?>
                                                <span class="text-muted small">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-light border me-1 edit-link" href="?group=<?= $group_id ?>&muscle=<?= $muscle_id ?>&edit=<?= $e['id'] ?>">
                                                <i class="bi bi-pencil small"></i>
                                            </a>
                                            <a class="btn btn-sm btn-light border text-danger delete-link" href="?group=<?= $group_id ?>&muscle=<?= $muscle_id ?>&delete=<?= $e['id'] ?>">
                                                <i class="bi bi-trash small"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($total_pages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination pagination-sm justify-content-center mb-0">
                                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?group=<?= $group_id ?>&muscle=<?= $muscle_id ?>&activity_level=<?= $activity_filter ?>&page=<?= $page-1 ?>">Previous</a>
                                </li>
                                <?php for($i=1; $i<=$total_pages; $i++): ?>
                                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                        <a class="page-link" href="?group=<?= $group_id ?>&muscle=<?= $muscle_id ?>&activity_level=<?= $activity_filter ?>&page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?group=<?= $group_id ?>&muscle=<?= $muscle_id ?>&activity_level=<?= $activity_filter ?>&page=<?= $page+1 ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="card-custom text-center py-5 d-flex flex-column align-items-center justify-content-center" style="min-height: 400px;">
                    <i class="bi bi-layers text-muted opacity-25" style="font-size: 4rem;"></i>
                    <p class="mt-4 text-muted fw-medium">Select a muscle group to begin managing the database.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadContent(url) {
            let mainArea = $('.row.g-4');
            mainArea.css('opacity', '0.5');
            $.get(url, function(data) {
                mainArea.html($(data).find('.row.g-4').html()).css('opacity', '1');
                window.history.pushState({path:url},'',url);
                initVideoUploader();
            });
        }

        window.addEventListener('popstate', function(e) {
            if (e.state && e.state.path) {
                loadContent(e.state.path);
            }
        });

        // Step selections
        $(document).on('change', '.step-select', function() {
            let url = 'manage_exercises.php?' + $(this).closest('form').serialize();
            loadContent(url);
        });

        // Pagination and Edit links
        $(document).on('click', '.pagination a.page-link, .edit-link', function(e) {
            e.preventDefault();
            loadContent($(this).attr('href'));
        });

        // Delete links
        $(document).on('click', '.delete-link', function(e) {
            e.preventDefault();
            if (confirm('Delete this exercise?')) {
                loadContent($(this).attr('href'));
            }
        });

        // Form submission for save/update
        $(document).on('submit', '#exerciseForm', function(e) {
            e.preventDefault();
            let data = new FormData(this);
            data.append('save_exercise', '1');
            
            let mainArea = $('.row.g-4');
            mainArea.css('opacity', '0.5');
            
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: data,
                processData: false,
                contentType: false,
                success: function(response) {
                    mainArea.html($(response).find('.row.g-4').html()).css('opacity', '1');
                    initVideoUploader();
                }
            });
        });

        // Seed library submission
        $(document).on('submit', '#seedForm', function(e) {
            e.preventDefault();
            let data = new FormData(this);
            data.append('seed_library', '1');
            
            let mainArea = $('.row.g-4');
            mainArea.css('opacity', '0.5');
            
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: data,
                processData: false,
                contentType: false,
                success: function(response) {
                    mainArea.html($(response).find('.row.g-4').html()).css('opacity', '1');
                    initVideoUploader();
                }
            });
        });

        // Video chooser + upload
        function initVideoUploader() {
            const chooseBtn = document.getElementById('chooseVideoBtn');
            const fileInput = document.getElementById('videoFileInput');
            const videoUrlInput = document.getElementById('videoUrlHidden');
            const preview = document.getElementById('videoPreview');

            if (!chooseBtn || !fileInput) return;

            $(chooseBtn).off('click').on('click', function(){
                fileInput.click();
            });

            $(fileInput).off('change').on('change', function(){
                if (!fileInput.files || !fileInput.files[0]) return;
                const f = fileInput.files[0];
                const fd = new FormData();
                fd.append('file', f);

                // show temporary preview
                const url = URL.createObjectURL(f);
                preview.innerHTML = '<video src="'+url+'" controls style="max-height:100px;border-radius:8px;"></video>';

                // upload
                chooseBtn.disabled = true;
                chooseBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading...';

                $.ajax({
                    url: 'upload_video.php',
                    method: 'POST',
                    data: fd,
                    contentType: false,
                    processData: false,
                    dataType: 'json'
                }).done(function(res){
                    chooseBtn.disabled = false;
                    chooseBtn.innerHTML = '<i class="bi bi-check-circle"></i> Uploaded';
                    setTimeout(() => {
                        chooseBtn.innerHTML = '<i class="bi bi-camera-video"></i> Upload MP4 Video';
                    }, 2000);

                    if (res.status === 'success') {
                        videoUrlInput.value = res.url;
                    } else {
                        alert('Upload error: ' + (res.message||'Unknown'));
                    }
                }).fail(function(){
                    chooseBtn.disabled = false;
                    chooseBtn.innerHTML = '<i class="bi bi-camera-video"></i> Upload MP4 Video';
                    alert('Upload failed');
                });
            });
        }
        
        // Initial setup
        initVideoUploader();
    </script>
</body>
</html>
