<?php
// admin/profile.php
session_start();
require '../auth.php';
require '../connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";
$status = "";

// Helper function to mask email (show first 3 and last 3 chars before @)
function maskEmail($email) {
    if (!$email) return 'N/A';
    $parts = explode("@", $email);
    if (count($parts) < 2) return $email;
    $name = $parts[0];
    $len = strlen($name);
    if ($len <= 6) {
        return str_repeat('*', $len) . "@" . $parts[1];
    }
    $first = substr($name, 0, 3);
    $last = substr($name, -3);
    $middle = str_repeat('*', $len - 6);
    return $first . $middle . $last . "@" . $parts[1];
}

// --- HANDLE PROFILE UPDATE (only name, contact, password; email change is separate) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name    = trim($_POST['full_name']);
    $new_password = $_POST['new_password'] ?? '';
    $update_success   = false;
    $should_logout    = false;

    try {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?");
        if($stmt->execute([$full_name, $user_id])) {
            $_SESSION['name'] = $full_name;
            $message = "Profile details updated successfully. ";
            $update_success = true;
        }
        
        // Handle password change
        if (!empty($new_password)) {
            // Validate password requirements: 8+ chars, uppercase, lowercase, symbol
            if (strlen($new_password) < 8) {
                $message .= "Password NOT updated: Must be at least 8 characters.";
                $status = "danger";
                $update_success = false;
            } elseif (!preg_match('/[A-Z]/', $new_password)) {
                $message .= "Password NOT updated: Must contain at least one uppercase letter.";
                $status = "danger";
                $update_success = false;
            } elseif (!preg_match('/[a-z]/', $new_password)) {
                $message .= "Password NOT updated: Must contain at least one lowercase letter.";
                $status = "danger";
                $update_success = false;
            } elseif (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
                $message .= "Password NOT updated: Must contain at least one symbol.";
                $status = "danger";
                $update_success = false;
            } else {
                $confirm_pass = $_POST['confirm_password'] ?? '';
                if ($new_password !== $confirm_pass) {
                    $message .= "Password NOT updated: Passwords do not match.";
                    $status = "danger";
                    $update_success = false;
                } else {
                    $hashed_pass = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt_pass = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt_pass->execute([$hashed_pass, $user_id]);
                    $message .= "Password updated!";
                    $update_success = true;
                    $should_logout = true;
                }
            }
        }

        if ($update_success && empty($status)) { 
            $status = "success"; 
        }

        // If password was changed, force logout
        if ($update_success && $should_logout) {
            session_destroy();
            header("Location: ../login.php?notice=password_updated");
            exit;
        }
    } catch (Exception $e) {
        $message = "Error updating profile.";
        $status = "danger";
    }
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Profile | Arts Gym</title>
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
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        #main {
            margin-left: var(--sidebar-width);
            transition: var(--transition);
            min-height: 100vh;
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

        /* Profile Card Styling - Matching Dashboard Card-Box */
        .profile-container {
            background: var(--bg-card); 
            border-radius: 12px; 
            padding: 30px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.05);
            max-width: 900px;
            margin: 0 auto;
        }

        .form-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .form-control {
            background-color: var(--bg-body);
            border: 1px solid rgba(0,0,0,0.1);
            color: var(--text-main);
            padding: 12px;
            border-radius: 8px;
        }

        .form-control:focus {
            background-color: var(--bg-card);
            border-color: var(--primary-red);
            color: var(--text-main);
            box-shadow: none;
        }

        .form-control:disabled {
            background-color: rgba(0,0,0,0.05);
            opacity: 0.7;
        }

        .btn-update {
            background: var(--primary-red);
            color: white;
            font-family: 'Oswald';
            padding: 12px 30px;
            border-radius: 8px;
            border: none;
            transition: var(--transition);
            text-transform: uppercase;
            font-weight: 600;
        }

        .btn-update:hover {
            background: var(--dark-red);
            transform: translateY(-2px);
        }

        .section-title {
            color: var(--primary-red);
            font-size: 1.2rem;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--primary-red);
            display: inline-block;
            padding-bottom: 5px;
        }

        @media (max-width: 991.98px) {
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
                <div class="d-none d-sm-block">
                    <h5 class="mb-0 fw-bold">Arts Gym Management</h5>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php include '../global_clock.php'; ?>
            </div>
        </header>

        <div class="container-fluid p-3 p-md-4">
            <div class="mb-4">
                <h2 class="fw-bold mb-1">My Account</h2>
                <p class="text-secondary small fw-bold">MANAGE YOUR PROFILE AND SECURITY SETTINGS</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $status ?> border-0 shadow-sm mb-4">
                    <i class="bi <?= $status == 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle' ?> me-2"></i>
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <div class="profile-container">
                <form method="POST">
                    <div class="row g-4">
                        <div class="col-12">
                            <h5 class="section-title"><i class="bi bi-person-badge me-2"></i>Personal Details</h5>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($admin['full_name']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="text" class="form-control" value="<?= htmlspecialchars(maskEmail($admin['email'])) ?>" disabled style="flex: 1;">
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="openEmailChangeModal()">
                                    <i class="bi bi-envelope me-1"></i>Request Change
                                </button>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">System Role</label>
                            <input type="text" class="form-control" value="ADMINISTRATOR" disabled>
                        </div>

                        <div class="col-12 mt-5">
                            <h5 class="section-title"><i class="bi bi-shield-lock me-2"></i>Security</h5>
                            <div class="p-3 border rounded bg-light bg-opacity-10">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" id="newPasswordInput" class="form-control" placeholder="Leave blank to keep current password">
                                <p class="text-muted small mt-2 mb-0">
                                    Must be 8+ characters with at least one uppercase, one lowercase, and one symbol. Changing your password will immediately log you out and require you to sign in again.
                                </p>
                            </div>
                            <div class="p-3 border rounded bg-light bg-opacity-10 mt-2" id="confirmPasswordGroup" style="display: none;">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" id="confirmPasswordInput" class="form-control" placeholder="Re-enter password">
                            </div>
                        </div>

                        <div class="col-12 text-end mt-4">
                            <button type="submit" class="btn btn-update">Save Changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Email Change Modal -->
    <div class="modal fade" id="emailChangeModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Request Email Change</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Current Email</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars(maskEmail($admin['email'])) ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Email Address</label>
                        <input type="email" id="newEmailInput" class="form-control" placeholder="Enter new email address" required>
                        <div class="form-text">A confirmation email will be sent to your current email address.</div>
                    </div>
                    <div id="emailChangeError" class="alert alert-danger d-none" role="alert"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="submitEmailChange()">
                        <span id="emailChangeBtnText">Request Change</span>
                        <span id="emailChangeSpinner" class="spinner-border spinner-border-sm d-none"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleDarkMode() {
            const isDark = !document.body.classList.contains('dark-mode-active');
            document.cookie = "theme=" + (isDark ? "dark" : "light") + ";path=/;max-age=" + (30*24*60*60);
            location.reload(); 
        }

        // Show/hide confirm password field
        document.getElementById('newPasswordInput').addEventListener('input', function() {
            const confirmGroup = document.getElementById('confirmPasswordGroup');
            if (this.value.length > 0) {
                confirmGroup.style.display = 'block';
                document.getElementById('confirmPasswordInput').required = true;
            } else {
                confirmGroup.style.display = 'none';
                document.getElementById('confirmPasswordInput').required = false;
                document.getElementById('confirmPasswordInput').value = '';
            }
        });

        // Email Change Modal
        const emailChangeModal = new bootstrap.Modal(document.getElementById('emailChangeModal'));

        function openEmailChangeModal() {
            document.getElementById('newEmailInput').value = '';
            document.getElementById('emailChangeError').classList.add('d-none');
            emailChangeModal.show();
        }

        function submitEmailChange() {
            const newEmail = document.getElementById('newEmailInput').value.trim();
            const errorDiv = document.getElementById('emailChangeError');
            const btn = document.querySelector('#emailChangeModal .btn-danger');
            const btnText = document.getElementById('emailChangeBtnText');
            const spinner = document.getElementById('emailChangeSpinner');

            if (!newEmail || !newEmail.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                errorDiv.textContent = 'Please enter a valid email address';
                errorDiv.classList.remove('d-none');
                return;
            }

            btn.disabled = true;
            btnText.textContent = 'Sending...';
            spinner.classList.remove('d-none');
            errorDiv.classList.add('d-none');

            $.ajax({
                url: '../request_email_change.php',
                method: 'POST',
                data: { new_email: newEmail },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        emailChangeModal.hide();
                        alert(response.message);
                        location.reload();
                    } else {
                        errorDiv.textContent = response.message || 'An error occurred';
                        errorDiv.classList.remove('d-none');
                        btn.disabled = false;
                        btnText.textContent = 'Request Change';
                        spinner.classList.add('d-none');
                    }
                },
                error: function() {
                    errorDiv.textContent = 'Network error. Please try again.';
                    errorDiv.classList.remove('d-none');
                    btn.disabled = false;
                    btnText.textContent = 'Request Change';
                    spinner.classList.add('d-none');
                }
            });
        }
    </script>
</body>
</html>