<?php
// 1. PHP BACKEND LOGIC
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'connection.php';

// Validate CSRF
validate_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $gender = trim($_POST['gender'] ?? '');
    $height = trim($_POST['height'] ?? '');
    $weight = trim($_POST['weight'] ?? '');
    $activity_level = trim($_POST['activity_level'] ?? 'sedentary');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // --- Validation ---
    if (!$full_name || !$email || !$age || !$gender || !$height || !$weight || !$activity_level || !$password || !$confirm_password) {
        echo json_encode(["status" => "error", "message" => "Please fill in all required fields."]);
        exit;
    }
    if ($password !== $confirm_password) {
        echo json_encode(["status" => "error", "message" => "Passwords do not match."]);
        exit;
    }
    if (strlen($password) < 8) {
        echo json_encode(["status" => "error", "message" => "Password must be at least 8 characters."]);
        exit;
    }
    if (!preg_match('/[A-Z]/', $password)) {
        echo json_encode(["status" => "error", "message" => "Password must contain at least one uppercase letter."]);
        exit;
    }
    if (!preg_match('/[a-z]/', $password)) {
        echo json_encode(["status" => "error", "message" => "Password must contain at least one lowercase letter."]);
        exit;
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        echo json_encode(["status" => "error", "message" => "Password must contain at least one symbol."]);
        exit;
    }

    // --- Check Duplicate ---
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email=?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(["status" => "error", "message" => "Email already registered."]);
        exit;
    }

    // --- Prepare Data ---
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $qr_code = bin2hex(random_bytes(8));
    $verification_token = bin2hex(random_bytes(16));

    try {
        // --- FIXED SQL: Changed 0 to FALSE for PostgreSQL compatibility ---
        // Also store qr_code into qr_image so Supabase has it for every new user
        $stmt = $pdo->prepare("
            INSERT INTO users (full_name, email, password, role, status, qr_code, qr_image, is_verified, verification_token, age, gender, height, weight, activity_level)
            VALUES (?, ?, ?, 'member', 'active', ?, ?, FALSE, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$full_name, $email, $hashedPassword, $qr_code, $qr_code, $verification_token, $age, $gender, $height, $weight, $activity_level]);

        // --- BUILD VERIFICATION LINK (FIXED FOR SUBFOLDERS) ---
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];

        // This part automatically detects if your file is inside a folder like /Gym1/
        $currentDir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
        if ($currentDir == '/') { $currentDir = ''; }

        $verify_link = "$protocol://$host$currentDir/verify_email.php?token=$verification_token";
        $htmlContent = "<h3>Hi $full_name,</h3><p>Please click the button to verify your account:</p><a href='$verify_link' style='background:#e63946;color:#fff;padding:12px 25px;text-decoration:none;border-radius:5px;display:inline-block;'>VERIFY MY ACCOUNT</a>";

        require_once __DIR__ . '/includes/brevo_send.php';
        $result = brevo_send_email($email, $full_name, "Verify Your Email - Arts Gym", $htmlContent);

        if ($result['success']) {
            echo json_encode(["status" => "success", "message" => "Registration successful! Check your email."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Email failed: " . $result['message']]);
        }

    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Arts Gym</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f4f4; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: sans-serif; padding: 20px 0; }
        .reg-card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 500px; }
        .btn-danger { background-color: #e63946; border: none; }
    </style>
</head>
<body>

    <div class="reg-card">
        <h3 class="text-center fw-bold mb-4">ARTS GYM</h3>
        <form id="registerForm">
            <div class="mb-3">
                <label class="small fw-bold">Full Name</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="small fw-bold">Email Address</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="small fw-bold">Age</label>
                    <input type="number" name="age" class="form-control" required min="1">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="small fw-bold">Gender</label>
                    <select name="gender" class="form-select" required>
                        <option value="">Select</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="small fw-bold">Height (cm)</label>
                    <input type="number" name="height" class="form-control" placeholder="e.g. 170" required min="1">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="small fw-bold">Weight (kg)</label>
                    <input type="number" name="weight" class="form-control" placeholder="e.g. 70" required min="1">
                </div>
            </div>
            <div class="mb-3">
                <label class="small fw-bold">Activity Level</label>
                <select name="activity_level" class="form-select" required>
                    <option value="sedentary">Sedentary (Little or no exercise)</option>
                    <option value="light">Lightly Active (Exercise 1-3 days/week)</option>
                    <option value="moderate" selected>Moderately Active (Exercise 3-5 days/week)</option>
                    <option value="very_active">Very Active (Exercise 6-7 days/week)</option>
                    <option value="extra_active">Extra Active (Very hard exercise/job)</option>
                </select>
            </div>
            <div class="row">
                <div class="col-md-6 mb-4">
                    <label class="small fw-bold">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Min 8 chars" required minlength="8">
                </div>
                <div class="col-md-6 mb-4">
                    <label class="small fw-bold">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                </div>
            </div>

            <button type="button" id="regBtn" class="btn btn-danger w-100 fw-bold py-2" data-bs-toggle="modal" data-bs-target="#ageLiabilityModal">
                <span id="btnText">REGISTER ACCOUNT</span>
                <div id="btnSpinner" class="spinner-border spinner-border-sm d-none" role="status"></div>
            </button>
        </form>
    </div>

    <!-- Age & Liability Confirmation Modal -->
    <div class="modal fade" id="ageLiabilityModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Age and Liability Confirmation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Before continuing with your registration, please confirm that you meet the minimum age requirement and understand the risks associated with physical activities.</p>
                    <p>Participation in gym activities and the use of fitness equipment involve inherent risks that may result in injury. By proceeding with the registration, you acknowledge that you are voluntarily participating in physical exercise and agree to assume full responsibility for your health and safety while using the gym facilities.</p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="ageLiabilityConfirm">
                        <label class="form-check-label" for="ageLiabilityConfirm">
                            I confirm that I am 18 years old or above and I acknowledge the risks of physical exercise and release the gym from liability for injuries that may occur during participation.
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmAgeLiability">Continue to Terms</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms & Privacy Agreement Modal -->
    <div class="modal fade" id="termsPrivacyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Terms and Privacy Agreement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>To complete your registration, you must review and agree to the gym’s membership policies and data privacy practices. These policies explain the rules and responsibilities of gym members, as well as how your personal information will be collected, used, and protected by the system.</p>
                    <p>Please read the following agreements carefully before proceeding.</p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="termsConfirm">
                        <label class="form-check-label" for="termsConfirm">
                            I agree to the Terms and Conditions.
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="privacyConfirm">
                        <label class="form-check-label" for="privacyConfirm">
                            I agree to the Privacy Policy.
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Back</button>
                    <button type="button" class="btn btn-danger" id="confirmTermsPrivacy">Complete Registration</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function() {
            var form = document.getElementById('registerForm');
            var regBtn = document.getElementById('regBtn');
            var btnText = document.getElementById('btnText');
            var btnSpinner = document.getElementById('btnSpinner');
            var ageModalEl = document.getElementById('ageLiabilityModal');
            var termsModalEl = document.getElementById('termsPrivacyModal');
            var ageConfirm = document.getElementById('ageLiabilityConfirm');
            var termsConfirm = document.getElementById('termsConfirm');
            var privacyConfirm = document.getElementById('privacyConfirm');
            var ageContinueBtn = document.getElementById('confirmAgeLiability');
            var termsCompleteBtn = document.getElementById('confirmTermsPrivacy');
            var ageModal = new bootstrap.Modal(ageModalEl);
            var termsModal = new bootstrap.Modal(termsModalEl);

            function showError(message) {
                if (window.Swal) {
                    Swal.fire('Error!', message, 'error');
                } else {
                    alert(message);
                }
            }

            function submitRegistration() {
                regBtn.disabled = true;
                btnText.innerText = "SENDING...";
                btnSpinner.classList.remove('d-none');

                var formData = new FormData(form);
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    regBtn.disabled = false;
                    btnText.innerText = "REGISTER ACCOUNT";
                    btnSpinner.classList.add('d-none');

                    if (data.status === "success") {
                        if (window.Swal) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                html: data.message + '<br><br><strong>Please settle your payment at the counter to log in to the system.</strong>',
                                confirmButtonColor: '#e63946'
                            }).then(function() {
                                window.location.href = 'login.php';
                            });
                        } else {
                            alert(data.message);
                            window.location.href = 'login.php';
                        }
                    } else {
                        showError(data.message);
                    }
                })
                .catch(function() {
                    regBtn.disabled = false;
                    btnText.innerText = "REGISTER ACCOUNT";
                    btnSpinner.classList.add('d-none');
                    showError('Check your internet or database connection.');
                });
            }

            regBtn.addEventListener('click', function(event) {
                if (!form.reportValidity()) {
                    event.preventDefault();
                    return;
                }
                if (ageConfirm) ageConfirm.checked = false;
                if (termsConfirm) termsConfirm.checked = false;
                if (privacyConfirm) privacyConfirm.checked = false;
            });

            ageContinueBtn.addEventListener('click', function() {
                if (!ageConfirm.checked) {
                    showError('Please confirm the age and liability agreement to continue.');
                    return;
                }
                ageModal.hide();
                termsModal.show();
            });

            termsCompleteBtn.addEventListener('click', function() {
                if (!termsConfirm.checked || !privacyConfirm.checked) {
                    showError('Please agree to both Terms and Conditions and Privacy Policy to continue.');
                    return;
                }
                termsModal.hide();
                submitRegistration();
            });
        })();
    </script>
</body>
</html>
