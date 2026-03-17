<?php
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$parts = explode('/', trim($scriptName, '/'));
$base = '/' . ($parts[0] ?? '');
$attendanceEndpoint = $base . '/admin/attendance_register.php';
$loginUrl = $base . '/login.php?attendance=1';
?>
<!-- GLOBAL QR SCANNER (ADMIN + STAFF) -->

<input type="text" id="qrInput" style="position:absolute;opacity:0;pointer-events:none;">

<div id="qrToast" style="
    position:fixed;
    bottom:30px;
    right:30px;
    background:#333;
    color:#fff;
    padding:15px 20px;
    border-radius:6px;
    display:none;
    z-index:9999;
"></div>

<script>
const qrScanEndpoint = "<?= $attendanceEndpoint ?>";
const loginUrl = "<?= $loginUrl ?>";

const qrInput = document.getElementById('qrInput');
const qrToast = document.getElementById('qrToast');

setInterval(() => {
    if (qrInput) qrInput.focus();
}, 500);

function showQRToast(msg, type) {
    if (!qrToast) return;
    qrToast.style.background = type === 'success' ? '#2ecc71' : '#e74c3c';
    qrToast.textContent = msg;
    qrToast.style.display = 'block';
    setTimeout(() => {
        qrToast.style.display = 'none';
    }, 3000);
}

if (qrInput) {
    qrInput.addEventListener('change', function () {
        const qr = this.value.trim();
        this.value = '';

        if (!qr) return;

        fetch(qrScanEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ qr_code: qr })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const name = data.name || '';
                const msg = data.message || (name ? 'Attendance recorded for ' + name : 'Attendance recorded');
                showQRToast(msg, 'success');
            } else if (data.status === 'login_required') {
                window.location.href = loginUrl;
            } else {
                const msg = data.message || 'Error processing scan';
                showQRToast(msg, 'error');
            }
        })
        .catch(() => {
            showQRToast('Server error', 'error');
        });
    });
}
</script>
