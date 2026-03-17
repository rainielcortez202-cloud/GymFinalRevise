<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Role check remains as per your member logic
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'member') {
    header("Location: ../login.php");
    exit;
}
 $current = basename($_SERVER['PHP_SELF']);
?>
<script>window.CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;</script>

<link rel="stylesheet" href="../assets/css/shared_layout.css">

<style>
    /* Unifying with Admin Design Language */
    :root {
        --sidebar-width: 260px;
        --sidebar-collapsed-width: 80px;
        --primary-red: #e63946;
        --dark-red: #9d0208;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .member-shared-sidebar .sidebar {
        width: var(--sidebar-width);
        height: 100vh;
        background: #0a0a0a;
        color: #ffffff;
        position: fixed;
        top: 0; left: 0;
        z-index: 1050;
        display: flex;
        flex-direction: column;
        border-right: 1px solid rgba(255,255,255,0.05);
        transition: var(--transition);
    }

    .member-shared-sidebar .sidebar-header {
        padding: 20px 18px;
        background: #050505;
        display: flex;
        align-items: center;
        justify-content: space-between;
        white-space: nowrap;
        overflow: hidden;
    }

    .member-shared-sidebar .sidebar-header h2 span { color: var(--primary-red); }

    .member-shared-sidebar .sidebar-content {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 12px 15px;
    }

    .member-shared-sidebar .sidebar a {
        display: flex;
        align-items: center;
        color: #b0b0b0;
        text-decoration: none;
        padding: 12px 16px;
        margin-bottom: 8px;
        border-radius: 8px;
        transition: 0.2s;
        font-weight: 500;
        white-space: nowrap;
    }

    .member-shared-sidebar .sidebar a i { 
        font-size: 1.2rem; 
        min-width: 36px; 
        color: var(--primary-red); 
    }

    .member-shared-sidebar .sidebar a:hover { 
        background: rgba(230,57,70,0.06); 
        color: #fff; 
    }

    .member-shared-sidebar .sidebar a.active {
        background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
        color: #fff !important;
    }
    
    .member-shared-sidebar .sidebar a.active i { color: #fff; }
    
    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.6);
        backdrop-filter: blur(4px);
        z-index: 1040;
    }
    
    .sidebar-overlay.show { display: block; }

    /* DESKTOP COLLAPSE (Matches Admin Reference) */
    @media (min-width: 992px) {
        .member-shared-sidebar .sidebar.collapsed { width: var(--sidebar-collapsed-width); }
        .member-shared-sidebar .sidebar.collapsed .sidebar-header h2, 
        .member-shared-sidebar .sidebar.collapsed a span { display: none; }
        .member-shared-sidebar .sidebar.collapsed .sidebar-header { justify-content: center; }
        .member-shared-sidebar .sidebar.collapsed a i { min-width: 0; margin: 0 auto; }
    }

    /* MOBILE SLIDE (Matches Admin Reference) */
    @media (max-width: 991px) {
        .member-shared-sidebar .sidebar { left: -260px; }
        .member-shared-sidebar .sidebar.show { left: 0; }
    }
</style>

<div class="member-shared-sidebar">
    <div class="sidebar-overlay" id="overlay" onclick="toggleSidebar()"></div>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2 class="m-0 fs-4 fw-bold">ARTS<span>GYM</span></h2>
            <!-- Desktop Toggle inside sidebar (Ref: Admin Sidebar) -->
            <button class="btn btn-sm text-white d-none d-lg-block" onclick="toggleSidebar()">
                <i class="bi bi-list fs-4"></i>
            </button>
        </div>

        <div class="sidebar-content">
            <a href="profile.php" class="<?= $current === 'profile.php' ? 'active' : '' ?>">
                <i class="bi bi-person-circle"></i><span>Profile</span>
            </a>
            
            <a href="exercises.php" class="<?= $current === 'exercises.php' ? 'active' : '' ?>">
                <i class="bi bi-activity"></i><span>Exercises</span>
            </a>
            <a href="calorie_calculator.php" class="<?= $current === 'calorie_calculator.php' ? 'active' : '' ?>">
                <i class="bi bi-calculator"></i><span>Calorie Calc</span>
            </a>
            
            <a href="payment_history.php" class="<?= $current === 'payment_history.php' ? 'active' : '' ?>">
                <i class="bi bi-wallet2"></i><span>Payment History</span>
            </a>
            
            
           
            <hr class="my-4" style="border-color: rgba(255,255,255,0.1)">
            
            <a href="javascript:void(0)" onclick="toggleDarkMode()">
                <i class="bi bi-moon-stars"></i><span>Dark Mode</span>
            </a>
            <a href="javascript:void(0)" onclick="confirmLogout()" class="text-danger">
                <i class="bi bi-box-arrow-right"></i><span>Logout</span>
            </a>
        </div>
    </div>
</div>

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
            <div class="modal-header border-0 pb-2">
                <h5 class="modal-title fw-bold" id="logoutModalLabel">
                    <i class="bi bi-box-arrow-right text-danger me-2"></i>Confirm Logout
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2 pb-4">
                <p class="text-muted mb-0">Are you sure you want to logout? You'll need to login again to access the system.</p>
            </div>
            <div class="modal-footer border-top pt-3">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                <a href="../logout.php" class="btn btn-danger" style="border-radius: 8px; text-decoration: none;">
                    <i class="bi bi-check-lg me-2"></i>Yes, Logout
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
/**
 * Synchronized Toggle Logic
 * Works with #main element found in dashboard layout
 */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const main = document.getElementById('main'); 
    
    if (window.innerWidth >= 992) {
        // Desktop: Toggle mini-sidebar and expand content
        sidebar.classList.toggle('collapsed');
        if (main) main.classList.toggle('expanded');
    } else {
        // Mobile: Toggle slide-in and overlay
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    }
}

function confirmLogout() {
    const logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
    logoutModal.show();
}

// Ensure clean state on window resize
window.addEventListener('resize', () => {
    if (window.innerWidth >= 992) {
        document.getElementById('sidebar').classList.remove('show');
        document.getElementById('overlay').classList.remove('show');
    }
});
</script>
