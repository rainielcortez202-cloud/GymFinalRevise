<?php
require '../auth.php';
require '../connection.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if ($_SESSION['role'] !== 'member') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// --- FETCH DATA BASED ON STEPS ---
$groups = $pdo->query("SELECT * FROM muscle_groups ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$group_id  = intval($_GET['group'] ?? 0);
$muscle_id = intval($_GET['muscle'] ?? 0);

$muscles = [];
if ($group_id) {
    $stmt = $pdo->prepare("SELECT * FROM muscles WHERE muscle_group_id=? ORDER BY id ASC");
    $stmt->execute([$group_id]);
    $muscles = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$userProfileStmt = $pdo->prepare("SELECT height, weight, activity_level FROM users WHERE id = ? LIMIT 1");
$userProfileStmt->execute([$user_id]);
$userProfile = $userProfileStmt->fetch(PDO::FETCH_ASSOC) ?: [];



$parseMetric = function($value): ?float {
    if ($value === null) return null;
    $raw = trim((string)$value);
    if ($raw === '') return null;
    if (preg_match('/-?\d+(?:\.\d+)?/', $raw, $m)) {
        return (float)$m[0];
    }
    return null;
};

$heightRaw = $parseMetric($userProfile['height'] ?? null);
$weightRaw = $parseMetric($userProfile['weight'] ?? null);
$heightCm = null;
if ($heightRaw !== null && $heightRaw > 0) {
    $heightCm = ($heightRaw <= 3) ? ($heightRaw * 100) : $heightRaw;
    if ($heightCm < 90 || $heightCm > 260) $heightCm = null;
}
$weightKg = null;
if ($weightRaw !== null && $weightRaw > 0) {
    $weightKg = $weightRaw;
    if ($weightKg < 25 || $weightKg > 300) $weightKg = null;
}

$bmi = null;
if ($heightCm !== null && $weightKg !== null) {
    $heightM = $heightCm / 100;
    $bmi = $weightKg / ($heightM * $heightM);
}

// Map User Activity Level to Exercise Filter Level
$user_activity = $userProfile['activity_level'] ?? 'sedentary';
$show_all = isset($_GET['show_all']) && $_GET['show_all'] == '1';

// Custom Recommendation Logic
$recommendationReason = 'Balanced routine based on your level.';
$targetGroupNames = ['Core', 'Back', 'Legs'];
if ($bmi !== null) {
    if ($bmi < 18.5) {
        $recommendationReason = 'Focus on strength-building and progressive resistance.';
        $targetGroupNames = ['Chest', 'Back', 'Legs'];
    } elseif ($bmi < 25) {
        $recommendationReason = 'Maintain performance with balanced strength and posture work.';
    } elseif ($bmi < 30) {
        $recommendationReason = 'Prioritize high-output muscle groups to support fat loss.';
    } else {
        $recommendationReason = 'Start with low-impact compound training and core stability.';
    }
}

if ($user_activity === 'sedentary' || $user_activity === 'light') {
    $recommendationReason .= ' Focus on consistency while building habits.';
} elseif ($user_activity === 'very_active' || $user_activity === 'extra_active') {
    $recommendationReason .= ' High-intensity volume recommended for your active lifestyle.';
}

// Maintaining variables for UI compatibility
$attendanceDays30 = 'N/A';
$workoutDone30 = 'N/A';
$activityLevel = ucfirst(str_replace('_', ' ', $user_activity));

$exercises = [];
if ($muscle_id) {
    if ($show_all) {
        $stmt = $pdo->prepare("SELECT * FROM exercises WHERE muscle_id = ? ORDER BY created_at DESC");
        $stmt->execute([$muscle_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM exercises WHERE muscle_id = ? AND activity_level = ? ORDER BY created_at DESC");
        $stmt->execute([$muscle_id, $user_activity]);
    }
    $exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$normalize = function(string $v): string {
    return strtolower(trim($v));
};
$groupMap = [];
foreach ($groups as $groupRow) {
    $groupMap[$normalize((string)$groupRow['name'])] = $groupRow;
}

$recommendedGroups = [];
$seenRecommended = [];
foreach ($targetGroupNames as $name) {
    $key = $normalize($name);
    if (isset($groupMap[$key]) && !isset($seenRecommended[$key])) {
        $recommendedGroups[] = $groupMap[$key];
        $seenRecommended[$key] = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exercise Library | Arts Gym</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

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
            --card-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        body.dark-mode-active {
            --bg-body: #0a0a0a;
            --bg-card: #161616;
            --text-main: #f8f9fa;
            --text-muted: #a0a0a0;
            --card-shadow: 0 4px 15px rgba(0,0,0,0.4);
        }

        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: var(--bg-body); 
            color: var(--text-main);
            transition: var(--transition);
        }

        h1, h2, h3, h4, h5 { font-family: 'Oswald', sans-serif; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Main Content Layout */
        #main {
            margin-left: var(--sidebar-width);
            transition: var(--transition);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        #main.expanded { margin-left: 80px; }

        @media (max-width: 991px) {
            #main { margin-left: 0 !important; }
        }

        /* Top Header */
        .top-header {
            background: var(--bg-card);
            padding: 0.75rem 1.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        /* Minimized Container */
        .content-container {
            padding: 1.5rem;
            max-width: 1140px; /* Narrower width for better focus */
            margin: 0 auto;
            width: 100%;
        }

        /* Tighter Exercise Grid */
        .exercise-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); 
            gap: 1.25rem; 
        }

        /* Compact Card Styling */
        .card-box { 
            background: var(--bg-card); 
            border-radius: 12px; 
            padding: 1rem; 
            box-shadow: var(--card-shadow); 
            border: 1px solid rgba(0,0,0,0.05);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .card-box:hover { 
            transform: translateY(-5px); 
            border-color: var(--primary-red);
        }

        /* Smaller Card Images */
        .img-wrapper {
            width: 100%;
            height: 160px; /* Reduced height */
            overflow: hidden;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            background: #222;
        }

        .img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Compact Typography */
        .exercise-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; }
        .exercise-desc { 
            font-size: 0.85rem; 
            line-height: 1.4; 
            color: var(--text-muted);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Smaller Action Buttons */
        .btn-primary-gym { 
            background: var(--primary-red); 
            color: white; 
            border: none; 
            padding: 8px; 
            border-radius: 8px; 
            font-weight: 600; 
            text-transform: uppercase; 
            text-decoration: none; 
            display: block; 
            text-align: center; 
            margin-top: auto;
            font-family: 'Oswald', sans-serif;
            font-size: 0.9rem;
        }

        .btn-video {
            background: #212529;
            color: white !important;
            padding: 8px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            font-family: 'Oswald', sans-serif;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        body.dark-mode-active .btn-video { background: #333; }

        .quick-log-btn {
            padding: 8px;
            font-size: 0.85rem;
            border-radius: 8px;
            font-family: 'Oswald';
        }

        .step-tag {
            font-size: 0.7rem;
            letter-spacing: 1.5px;
            color: var(--primary-red);
            font-weight: 800;
        }

        .recommendation-panel {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid rgba(0,0,0,0.08);
            box-shadow: var(--card-shadow);
            padding: 1rem 1.1rem;
            margin-bottom: 1.25rem;
        }

        .meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-right: 8px;
            margin-bottom: 8px;
            background: rgba(230,57,70,0.12);
            color: var(--primary-red);
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
                <h5 class="mb-0 fw-bold">Training Library</h5>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php include '../global_clock.php'; ?>
            </div>
        </header>

        <div class="content-container">
            <!-- Tighter Header Section -->
            <div class="mb-4">
                <span class="step-tag text-uppercase">
                    <?php 
                        if ($muscle_id) echo "Step 3: Exercises";
                        elseif ($group_id) echo "Step 2: Muscles";
                        else echo "Step 1: Areas";
                    ?>
                </span>
                <h3 class="fw-bold mt-1 mb-0">Workout Guide</h3>
            </div>

            <!-- New Filter Header -->
            <?php if ($muscle_id): ?>
            <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded-4 shadow-sm border">
                <div>
                    <h6 class="mb-1 fw-bold text-uppercase small text-muted">Filter Options</h6>
                    <p class="mb-0 small">
                        <?php if ($show_all): ?>
                            Showing <strong>all exercises</strong> for this muscle.
                        <?php else: ?>
                            Showing exercises optimized for <strong><?= ucfirst(str_replace('_', ' ', $user_activity)) ?></strong> level.
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <?php if ($show_all): ?>
                        <a href="?group=<?= $group_id ?>&muscle=<?= $muscle_id ?>&show_all=0" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                            <i class="bi bi-stars me-1"></i> Show Recommended Only
                        </a>
                    <?php else: ?>
                        <a href="?group=<?= $group_id ?>&muscle=<?= $muscle_id ?>&show_all=1" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                            <i class="bi bi-list-ul me-1"></i> Show All Exercises
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Step 1: Muscle Groups -->
            <?php if(!$group_id): ?>
                <div class="recommendation-panel">
                    <div class="d-flex flex-wrap align-items-center mb-2">
                        <span class="meta-chip"><i class="bi bi-activity"></i> Activity Level: <?= htmlspecialchars($activityLevel) ?></span>
                        <span class="meta-chip"><i class="bi bi-calendar-check"></i> Attendance (30d): <?= $attendanceDays30 ?></span>
                        <span class="meta-chip"><i class="bi bi-check2-square"></i> Completed Workouts (30d): <?= $workoutDone30 ?></span>
                        <?php if ($bmi !== null): ?>
                            <span class="meta-chip"><i class="bi bi-heart-pulse"></i> BMI: <?= number_format($bmi, 1) ?></span>
                        <?php else: ?>
                            <span class="meta-chip"><i class="bi bi-person-lines-fill"></i> BMI unavailable</span>
                        <?php endif; ?>
                    </div>
                    <h5 class="exercise-title mb-1">Recommended Focus Areas</h5>
                    <p class="exercise-desc mb-0"><?= htmlspecialchars($recommendationReason) ?></p>
                </div>

                <?php if (!empty($recommendedGroups)): ?>
                    <div class="exercise-grid mb-4">
                        <?php foreach ($recommendedGroups as $g): ?>
                            <div class="card-box">
                                <div class="img-wrapper">
                                    <?php 
                                        $fallback_img = 'https://picsum.photos/seed/' . md5($g['name']) . '/400/300'; 
                                        $img_src = !empty($g['image']) ? str_replace('../', '', htmlspecialchars($g['image'])) : $fallback_img;
                                    ?>
                                    <img src="<?= $img_src ?>" 
                                         alt="<?= htmlspecialchars($g['name']) ?>"
                                         onerror="this.src='<?= $fallback_img ?>';">
                                </div>
                                <h5 class="exercise-title"><?= htmlspecialchars($g['name']) ?></h5>
                                <a href="?group=<?= $g['id'] ?>" class="btn btn-primary-gym">Start Recommended Area</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="exercise-grid">
                    <?php 
                    $rec_ids = array_column($recommendedGroups, 'id');
                    foreach($groups as $g): 
                        if (in_array($g['id'], $rec_ids)) continue;
                    ?>
                        <div class="card-box">
                            <div class="img-wrapper">
                                <?php 
                                    $fallback_img = 'https://picsum.photos/seed/' . md5($g['name']) . '/400/300'; 
                                    $img_src = !empty($g['image']) ? str_replace('../', '', htmlspecialchars($g['image'])) : $fallback_img;
                                ?>
                                <img src="<?= $img_src ?>" 
                                     alt="<?= htmlspecialchars($g['name']) ?>"
                                     onerror="this.src='<?= $fallback_img ?>';">
                            </div>
                            <h5 class="exercise-title"><?= htmlspecialchars($g['name']) ?></h5>
                            <a href="?group=<?= $g['id'] ?>" class="btn btn-primary-gym">Select Area</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Step 2: Muscles -->
            <?php if($group_id && !$muscle_id): ?>
                <div class="back-nav mb-3">
                    <a href="exercises.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Back
                    </a>
                </div>
                <?php if (empty($muscles)): ?>
                    <div class="card-box">
                        <h5 class="exercise-title mb-1">No muscles found</h5>
                        <p class="exercise-desc mb-0">The exercise library is not seeded yet. Please ask an admin to seed or add muscles and exercises.</p>
                    </div>
                <?php endif; ?>
                <div class="exercise-grid">
                    <?php foreach($muscles as $m): ?>
                        <div class="card-box">
                            <div class="img-wrapper">
                                <?php 
                                    $fallback_img = 'https://picsum.photos/seed/' . md5($m['name']) . '/400/300'; 
                                    $img_src = !empty($m['image']) ? str_replace('../', '', htmlspecialchars($m['image'])) : $fallback_img;
                                ?>
                                <img src="<?= $img_src ?>" 
                                     alt="<?= htmlspecialchars($m['name']) ?>"
                                     onerror="this.src='<?= $fallback_img ?>';">
                            </div>
                            <h5 class="exercise-title"><?= htmlspecialchars($m['name']) ?></h5>
                            <a href="?group=<?= $group_id ?>&muscle=<?= $m['id'] ?>" class="btn btn-primary-gym">View Exercises</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Step 3: Specific Exercises -->
            <?php if($muscle_id): ?>
                <div class="back-nav mb-3">
                    <a href="exercises.php?group=<?= $group_id ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Back
                    </a>
                </div>
                <?php if (empty($exercises)): ?>
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-patch-question text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="mt-3 text-muted">No exercises found for your current level.</p>
                        <?php if (!$show_all): ?>
                            <a href="?group=<?= $group_id ?>&muscle=<?= $muscle_id ?>&show_all=1" class="btn btn-sm btn-link text-danger">Show all exercises anyway</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="exercise-grid">
                    <?php foreach($exercises as $e): ?>
                        <div class="card-box">
                            <?php if(!empty($e['video_url'])): ?>
                                <?php 
                                    // Ensure the video URL works from the member directory
                                    $video_path = $e['video_url'];
                                    if (strpos($video_path, 'admin/') === 0) {
                                        $video_path = '../' . $video_path;
                                    }
                                ?>
                                <video class="w-100 mb-3" controls style="border-radius: 8px; background: #000; max-height: 200px; aspect-ratio: 16/9; object-fit: contain;">
                                    <source src="<?= htmlspecialchars($video_path) ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            <?php else: ?>
                                <div class="img-wrapper mb-3" style="height: 200px; border-radius: 8px; overflow: hidden;">
                                    <?php 
                                        $fallback_img = 'https://picsum.photos/seed/' . md5($e['name']) . '/400/300';
                                        $img_src = !empty($e['image_url']) ? htmlspecialchars($e['image_url']) : $fallback_img;
                                    ?>
                                    <img src="<?= $img_src ?>" 
                                         alt="<?= htmlspecialchars($e['name']) ?>"
                                         class="w-100 h-100" style="object-fit: cover;"
                                         onerror="this.src='<?= $fallback_img ?>';">
                                </div>
                            <?php endif; ?>
                            
                            <h5 class="exercise-title"><?= htmlspecialchars($e['name']) ?></h5>
                            
                            <p class="exercise-desc mb-0">
                                <?= htmlspecialchars($e['description']) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadContent(url) {
            let container = $('.content-container');
            container.css('opacity', '0.5'); // visual feedback
            $.get(url, function(data) {
                // Update the main container
                container.html($(data).find('.content-container').html()).css('opacity', '1');
                // Update the history to allow using the back button
                window.history.pushState({path:url},'',url);
            });
        }

        window.addEventListener('popstate', function(e) {
            if (e.state && e.state.path) {
                loadContent(e.state.path);
            } else {
                // Initial load might not have state, fallback to current URL
                loadContent(window.location.href);
            }
        });

        // Intercept clicks on links that change steps
        $(document).on('click', 'a.btn-primary-gym, a.btn-outline-secondary, a.btn-outline-danger, a.btn-link', function(e) {
            let href = $(this).attr('href');
            if (href && href !== '#' && href.indexOf('?') !== -1) {
                e.preventDefault();
                loadContent(href);
            } else if (href === 'exercises.php') {
                e.preventDefault();
                loadContent(href);
            }
        });

        function toggleDarkMode() {
            const isDark = !document.body.classList.contains('dark-mode-active');
            document.cookie = "theme=" + (isDark ? "dark" : "light") + ";path=/;max-age=" + (30*24*60*60);
            location.reload(); 
        }
    </script>
</body>
</html>
