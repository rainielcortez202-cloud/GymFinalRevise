<?php $current = basename($_SERVER['PHP_SELF']); ?>
<script>window.CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;</script>

<link rel="stylesheet" href="../assets/css/shared_layout.css">

<style>
    :root {
        --sidebar-width: 260px;
        --sidebar-collapsed-width: 80px;
        --primary-red: #e63946;
        --dark-red: #9d0208;
        --bg-dark: #0a0a0a;
        --bg-darker: #050505;
    }

    /* Base Sidebar Styling */
    .staff-shared-sidebar .sidebar {
        width: var(--sidebar-width);
        height: 100vh;
        background: var(--bg-dark);
        color: #ffffff;
        position: fixed;
        top: 0; left: 0;
        z-index: 1050;
        display: flex;
        flex-direction: column;
        border-right: 1px solid rgba(255,255,255,0.05);
        transition: all 0.35s ease;
    }

    /* Desktop Collapse */
    @media (min-width: 992px) {
        .staff-shared-sidebar .sidebar.collapsed { width: var(--sidebar-collapsed-width); }
        .staff-shared-sidebar .sidebar.collapsed .sidebar-header h2, 
        .staff-shared-sidebar .sidebar.collapsed a span { display: none; }
        .staff-shared-sidebar .sidebar.collapsed a i { min-width: 0; margin: 0 auto; }
    }

    /* Mobile Logic */
    @media (max-width: 991px) {
        .staff-shared-sidebar .sidebar { left: -100%; }
        .staff-shared-sidebar .sidebar.show { left: 0; }
        .staff-shared-sidebar .sidebar-overlay.show { display: block; }
    }

    /* Links & Interaction */
    .staff-shared-sidebar .sidebar a {
        display: flex;
        align-items: center;
        color: #b0b0b0;
        text-decoration: none;
        padding: 12px 16px;
        margin-bottom: 8px;
        border-radius: 8px;
        transition: 0.2s;
        font-weight: 500;
    }

    .staff-shared-sidebar .sidebar a i { font-size: 1.2rem; min-width: 36px; color: var(--primary-red); }

    .staff-shared-sidebar .sidebar a.active {
        background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
        color: #fff;
    }
    .staff-shared-sidebar .sidebar a.active i { color: #fff; }
</style>

<div class="staff-shared-sidebar">
    <div class="sidebar-overlay" id="overlay" onclick="toggleSidebar()"></div>

    <div class="sidebar" id="sidebar" role="navigation">
        <div class="sidebar-header" style="padding: 20px 18px; background: var(--bg-darker); display:flex; justify-content: space-between; align-items: center;">
            <h2 class="m-0 fs-4 fw-bold text-white">ARTS<span style="color: var(--primary-red);">GYM</span></h2>
            <button class="btn btn-sm text-white d-none d-lg-block" onclick="toggleSidebar()">
                <i class="bi bi-list fs-4"></i>
            </button>
        </div>

        <div class="sidebar-content" style="flex: 1; overflow-y: auto; padding: 12px 15px;">
            <a href="dashboard.php" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
            <a href="members.php" class="<?= $current === 'members.php' ? 'active' : '' ?>"><i class="bi bi-people"></i><span>Members</span></a>
            <a href="daily.php" class="<?= $current === 'daily.php' ? 'active' : '' ?>"><i class="bi bi-calendar-day"></i><span>Daily Plan</span></a>
            <a href="attendance_register.php" class="<?= $current === 'attendance_register.php' ? 'active' : '' ?>"><i class="bi bi-calendar-check"></i><span>Attendance</span></a>
            <a href="profile.php" class="<?= $current === 'profile.php' ? 'active' : '' ?>"><i class="bi bi-person-circle"></i><span>My Profile</span></a>
            <hr class="my-4" style="border-color: rgba(255,255,255,0.1)">
            <a href="javascript:void(0)" onclick="toggleDarkMode()"><i class="bi bi-moon-stars"></i><span>Dark Mode</span></a>
            <a href="javascript:void(0)" onclick="confirmLogout()" class="text-danger"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
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
function toggleSidebar(){
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const main = document.getElementById('main') || document.querySelector('.main-content');
    
    if (window.innerWidth >= 992) {
        sidebar.classList.toggle('collapsed');
        if (main) main.classList.toggle('expanded');
    } else {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    }
}

function confirmLogout() {
    const logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
    logoutModal.show();
}

// Cleanup on resize
window.addEventListener('resize', () => {
    if (window.innerWidth >= 992) {
        document.getElementById('sidebar')?.classList.remove('show');
        document.getElementById('overlay')?.classList.remove('show');
    }
});
</script>
<script src="../assets/js/global_attendance.js"></script>
