<?php
require '../auth.php';
require '../connection.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if ($_SESSION['role'] !== 'member') {
    header("Location: ../login.php");
    exit;
}

// Fetch user metrics for auto-fill
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT age, gender, height, weight, activity_level FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_metrics = $stmt->fetch(PDO::FETCH_ASSOC);

// Map activity level to numeric multipliers
$activity_map = [
    'sedentary' => '1.2',
    'light' => '1.375',
    'moderate' => '1.55',
    'very_active' => '1.725',
    'extra_active' => '1.9'
];
$current_activity = $activity_map[$user_metrics['activity_level'] ?? 'moderate'] ?? '1.55';

// Handle AJAX Metric Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_metrics'])) {
    header('Content-Type: application/json');
    $age = intval($_POST['age'] ?? 0);
    $gender = trim($_POST['gender'] ?? '');
    $height = trim($_POST['height'] ?? '');
    $weight = trim($_POST['weight'] ?? '');
    $activity_val = $_POST['activity'] ?? '1.55';

    // Reverse map activity multiplier back to level name
    $reverse_activity_map = array_flip($activity_map);
    $activity_level = $reverse_activity_map[$activity_val] ?? 'moderate';

    try {
        $stmt = $pdo->prepare("UPDATE users SET age = ?, gender = ?, height = ?, weight = ?, activity_level = ? WHERE id = ?");
        $stmt->execute([$age, $gender, $height, $weight, $activity_level, $user_id]);
        echo json_encode(["status" => "success"]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calorie Calculator | Arts Gym</title>
    
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

        /* Form Card */
        .card-box {
            background: var(--bg-card);
            padding: 40px;
            border-radius: 20px;
            max-width: 700px;
            margin: 20px auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .form-control, .form-select {
            background-color: var(--bg-body);
            border: 1px solid rgba(0,0,0,0.1);
            color: var(--text-main);
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            transition: 0.3s;
        }

        .dark-mode-active .form-control, .dark-mode-active .form-select {
            border-color: rgba(255,255,255,0.1);
            background-color: rgba(255,255,255,0.02);
        }

        .form-control:focus, .form-select:focus {
            background-color: var(--bg-body);
            color: var(--text-main);
            border-color: var(--primary-red);
            box-shadow: none;
        }

        .btn-brand {
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            color: white !important;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            font-weight: bold;
            width: 100%;
            transition: 0.3s;
        }

        .btn-brand:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(230, 57, 70, 0.4);
        }

        /* Results Display */
        .goal-card {
            background: var(--bg-body);
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 15px;
            padding: 20px;
            height: 100%;
            transition: 0.3s;
        }

        .dark-mode-active .goal-card {
            background: rgba(255,255,255,0.02);
            border-color: rgba(255,255,255,0.1);
        }

        .suggested-food-header {
            color: var(--primary-red);
            font-size: 0.9rem;
            font-weight: 700;
            margin-top: 15px;
            text-transform: uppercase;
        }
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
                <h5 class="mb-0 fw-bold">Nutrition Tools</h5>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php include '../global_clock.php'; ?>
            </div>
        </header>

        <div class="container-fluid p-4">
            <div class="text-center mb-4">
                <h1 class="fw-bold mb-1">Calorie Calculator</h1>
                <p class="text-secondary small fw-bold text-uppercase">Optimize your diet for your goals</p>
            </div>

            <div class="card-box">
                <h3 class="mb-4 fw-bold text-center border-bottom pb-3">Your Metrics</h3>
                <form id="calcForm">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="small fw-bold text-uppercase mb-1">Gender</label>
                            <select id="gender" class="form-select" required>
                                <option value="">Select Gender</option>
                                <option value="male" <?= strtolower($user_metrics['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                                <option value="female" <?= strtolower($user_metrics['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-uppercase mb-1">Age</label>
                            <input type="number" id="age" class="form-control" placeholder="e.g. 25" required value="<?= htmlspecialchars($user_metrics['age'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <label class="small fw-bold text-uppercase mb-1">Weight (kg)</label>
                            <input type="number" id="weight" class="form-control" placeholder="e.g. 75" required value="<?= htmlspecialchars($user_metrics['weight'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-uppercase mb-1">Height (cm)</label>
                            <input type="number" id="height" class="form-control" placeholder="e.g. 180" required value="<?= htmlspecialchars($user_metrics['height'] ?? '') ?>">
                        </div>
                    </div>

                    <label class="small fw-bold text-uppercase mb-1">Activity Level</label>
                    <select id="activity" class="form-select">
                        <option value="1.2" <?= $current_activity === '1.2' ? 'selected' : '' ?>>Sedentary (Little to no exercise)</option>
                        <option value="1.375" <?= $current_activity === '1.375' ? 'selected' : '' ?>>Lightly Active (1-3 days/week)</option>
                        <option value="1.55" <?= $current_activity === '1.55' ? 'selected' : '' ?>>Moderately Active (3-5 days/week)</option>
                        <option value="1.725" <?= $current_activity === '1.725' ? 'selected' : '' ?>>Very Active (6-7 days/week)</option>
                        <option value="1.9" <?= $current_activity === '1.9' ? 'selected' : '' ?>>Extra Active (Athlete/Physical Job)</option>
                    </select>

                    <button type="submit" class="btn btn-brand mt-3">Calculate Recommendations</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Result Modal -->
    <div class="modal fade" id="resultModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="fw-bold mb-0">Your Personalized Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" id="modalContent">
                    <!-- Results injected via JS -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Synchronized Dark Mode
        function toggleDarkMode() {
            const isDark = !document.body.classList.contains('dark-mode-active');
            document.cookie = "theme=" + (isDark ? "dark" : "light") + ";path=/;max-age=" + (30*24*60*60);
            location.reload(); 
        }

        // Calculation Logic
        document.getElementById('calcForm').addEventListener('submit', function(e){
            e.preventDefault();

            let gender = document.getElementById('gender').value;
            let weight = parseFloat(document.getElementById('weight').value);
            let height = parseFloat(document.getElementById('height').value);
            let age = parseFloat(document.getElementById('age').value);
            let activity = document.getElementById('activity').value; // Keep as string for mapping

            // --- AUTO UPDATE PROFILE VIA AJAX ---
            $.post('calorie_calculator.php', {
                update_metrics: 1,
                gender: gender,
                weight: weight,
                height: height,
                age: age,
                activity: activity
            });

            let activityNum = parseFloat(activity);

            // Mifflin-St Jeor Equation
            let bmr = gender === 'male' ? 
                (10 * weight) + (6.25 * height) - (5 * age) + 5 : 
                (10 * weight) + (6.25 * height) - (5 * age) - 161;
            
            let maintenance = Math.round(bmr * activityNum);

            const goals = [
                {name:"Maintain Weight", percent:1.0, class:"border-primary"},
                {name:"Weight Loss", percent:0.8, class:"border-success"},
                {name:"Muscle Gain", percent:1.15, class:"border-danger"}
            ];

            let html = '<div class="row g-3">';
            goals.forEach(goal => {
                let cals = Math.round(maintenance * goal.percent);
                html += `
                    <div class="col-md-4">
                        <div class="goal-card border-top border-4 ${goal.class}">
                            <p class="text-muted small fw-bold mb-1 text-uppercase">${goal.name}</p>
                            <h3 class="fw-bold mb-0">${cals} <small class="fs-6">kcal</small></h3>
                            <div class="suggested-food-header">Recommended Diet</div>
                            <p class="small text-muted mb-0">${getFoodList(goal.name)}</p>
                        </div>
                    </div>`;
            });
            html += '</div>';

            document.getElementById('modalContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('resultModal')).show();
        });

        function getFoodList(goal) {
            const diet = {
                "Maintain Weight": "Balanced intake of complex carbs (oats, rice), lean proteins (chicken, fish), and healthy fats (nuts).",
                "Weight Loss": "High protein (egg whites, turkey) to maintain muscle, lots of fibrous greens, and low-GI fruits.",
                "Muscle Gain": "Caloric surplus with high protein (beef, shakes) and heavy carbs (pasta, potatoes, bananas)."
            };
            return diet[goal] || "";
        }
    </script>
</body>
</html>