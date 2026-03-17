/**
 * Global Scanner Listener
 * Detects HID scanner input (rapid keystrokes ending in Enter)
 * and handles redirection/storage.
 */

(function() {
    let buffer = '';
    let lastKeyTime = Date.now();
    const SCAN_TIMEOUT = 50; // ms between keys for it to be considered a scan
    const MIN_LENGTH = 6;    // Minimum length of a QR code

    document.addEventListener('keydown', function(e) {
        const currentTime = Date.now();
        const target = e.target;

        // 1. If user is typing in an input field, DO NOT interfere
        if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA') {
            return; 
        }

        // 2. Check timing (Scanner types very fast)
        if (currentTime - lastKeyTime > SCAN_TIMEOUT) {
            buffer = ''; // Reset buffer if too slow (manual typing)
        }
        lastKeyTime = currentTime;

        // 3. Handle Enter (End of Scan)
        if (e.key === 'Enter') {
            if (buffer.length >= MIN_LENGTH) {
                handleScan(buffer);
                buffer = ''; // Clear
                e.preventDefault(); // Stop default Enter behavior
            }
        } 
        // 4. Accumulate characters
        else if (e.key.length === 1) { // Ignore special keys like Shift, Ctrl
            buffer += e.key;
        }
    });

    function handleScan(code) {
        console.log("Global Scan Detected:", code);
        
        // Store the code
        sessionStorage.setItem('pending_qr', code);

        // Visual Feedback
        showToast("QR Code Detected! Processing...");

        // Determine Action
        const currentPath = window.location.pathname;
        
        // If we are already on the attendance page, the page's own listener might handle it, 
        // OR we can reload to trigger the auto-scan logic. 
        // Let's rely on the auto-scan logic we will add to the attendance page.
        
        // If not logged in (we assume login.php is the login page)
        // We can't easily valid login status via JS alone without a cookie check or variable.
        // But the user said: "lead to login page and to attendance page after logging in"
        
        // Strategy: Always redirect to login.php if not there? 
        // No, if already logged in, we want to go to attendance.
        // Let's check a global variable or cookie if possible. 
        // For now, let's redirect to the staff attendance page. 
        // If not logged in, the PHP will redirect to login.php. 
        // PERFECT.
        
        if (!currentPath.includes('attendance_register.php')) {
             window.location.href = '/Gym1/staff/attendance_register.php'; 
             // Note: This hardcodes /Gym1/. Dynamic path needed?
             // Using relative path might be safer if we know where we are.
             // But valid absolute path is safest.
             // Let's try a smarter redirect.
        } else {
             // If already on attendance page, reload to pick up the sessionStorage? 
             // Or better, let the local script pick it up? 
             // Global script runs in parallel. 
             window.location.reload();
        }
    }

    function showToast(message) {
        // Simple toast
        let toast = document.createElement('div');
        toast.style.position = 'fixed';
        toast.style.top = '20px';
        toast.style.left = '50%';
        toast.style.transform = 'translateX(-50%)';
        toast.style.background = '#e63946';
        toast.style.color = 'white';
        toast.style.padding = '15px 30px';
        toast.style.borderRadius = '30px';
        toast.style.zIndex = '9999';
        toast.style.fontWeight = 'bold';
        toast.style.boxShadow = '0 5px 15px rgba(0,0,0,0.3)';
        toast.innerText = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2000);
    }
})();
