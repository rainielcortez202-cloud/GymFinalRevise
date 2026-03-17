/**
 * Global Attendance System (The Brain)
 * Handles HID Scanner input from ANY page.
 */

(function () {
    console.log("Global Attendance System Loaded");

    let buffer = '';
    let lastKeyTime = Date.now();
    const SCAN_TIMEOUT = 100; // ms - Relaxed for screen scanning capabilities
    const MIN_LENGTH = 6;    // Minimum length of a QR code

    // --- UTILS ---
    function showToast(message, type = 'info') {
        const colors = {
            'success': '#10b981', // Emerald 500
            'warning': '#f59e0b', // Amber 500
            'error': '#ef4444', // Red 500
            'info': '#3b82f6'  // Blue 500
        };

        let icon = 'ℹ️';
        if (type === 'success') icon = '✅';
        if (type === 'warning') icon = '⚠️';
        if (type === 'error') icon = '⛔';

        const color = colors[type] || colors['info'];

        // Remove existing toast
        const existing = document.getElementById('global-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.id = 'global-toast';
        toast.style.cssText = `
            position: fixed; top: 30px; left: 50%; transform: translateX(-50%) translateY(-20px);
            background: rgba(255, 255, 255, 0.98); color: #1f2937; padding: 16px 24px;
            border-radius: 12px; z-index: 10000; font-weight: 600;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15); font-family: 'Inter', system-ui, sans-serif;
            border: 1px solid rgba(0,0,0,0.05); display: flex; align-items: center; gap: 12px;
            opacity: 0; transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); pointer-events: none;
            backdrop-filter: blur(8px); min-width: 300px; justify-content: flex-start;
            border-left: 5px solid ${color};
        `;

        toast.innerHTML = `<span style="font-size: 1.4em; line-height: 1;">${icon}</span> <span style="font-size: 0.95em;">${message}</span>`;
        document.body.appendChild(toast);

        // Animate In
        requestAnimationFrame(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(-50%) translateY(0)';
        });

        // Animate Out
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(-50%) translateY(-10px)';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }

    // --- ACTIONS ---

    function handleScan(code) {
        console.log("Scanned:", code);

        // Build endpoint that works both on local subfolder (/Gym1) and production root
        const resolveBase = () => {
            const p = window.location.pathname || '';
            const match = p.match(/^(.*)\/(admin|staff|member)(\/|$)/);
            if (match && typeof match[1] === 'string') return match[1];
            if (p.includes('/Gym1/')) return '/Gym1';
            return '';
        };
        const base = resolveBase();
        const endpoint = `${base}/admin/attendance_endpoint.php`;
        const loginUrl = `${base}/login.php`;

        showToast("Processing Scan...", "info");

        const csrfToken =
            (typeof window !== 'undefined' && window.CSRF_TOKEN) ||
            (document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]').getAttribute('content')) ||
            '';

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
            },
            credentials: 'include',
            body: JSON.stringify({ qr_code: code })
        })
            .then(response => response.text().then(text => ({ response, text })))
            .then(({ response, text }) => {
                const contentType = response.headers.get('content-type') || '';
                
                // If the response is a redirect to login page (HTML), handle it as not logged in
                if (response.redirected && response.url.includes('login.php')) {
                     return { status: 'not_logged_in' };
                }

                if (!response.ok) {
                    return { status: 'error', message: `Server error (${response.status}).` };
                }
                
                // Sometimes PHP redirects manually with header('Location: ...') which fetch follows automatically.
                // If the final URL is login.php, we are not logged in.
                if (response.url && response.url.includes('login.php')) {
                    return { status: 'not_logged_in' };
                }
                
                if (!contentType.includes('application/json')) {
                    // Check if the text content looks like the login page HTML
                    if (text.includes('<!DOCTYPE html>') && (text.includes('login') || text.includes('Login'))) {
                        return { status: 'not_logged_in' };
                    }
                    return { status: 'error', message: 'Unexpected server response. Please refresh and try again.' };
                }
                try {
                    return JSON.parse(text);
                } catch (e) {
                    return { status: 'error', message: 'Invalid server response. Please try again.' };
                }
            })
            .then(data => {
                if (data.status === 'success') {
                    showToast(`${data.message}<br><small>${data.name || ''}</small>`, 'success');
                    if (window.location.href.includes('attendance')) {
                        setTimeout(() => location.reload(), 1000);
                    }
                } else if (data.status === 'warning') {
                    showToast(`${data.message}`, 'warning');
                } else if (data.status === 'not_logged_in') {
                    console.log("Not logged in. Storing and redirecting.");
                    sessionStorage.setItem('pending_qr', code);
                    showToast("Please Login to Record Attendance", "warning");

                    if (!window.location.href.includes('login.php')) {
                        setTimeout(() => window.location.href = loginUrl, 1000);
                    }
                } else {
                    showToast(`${data.message}`, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast("System Error. See Console.", 'error');
            });
    }

    // --- EVENT LISTENER ---
    document.addEventListener('keydown', function (e) {
        const currentTime = Date.now();
        const target = e.target;

        if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA') {
            return;
        }

        if (currentTime - lastKeyTime > SCAN_TIMEOUT) {
            buffer = '';
        }
        lastKeyTime = currentTime;

        if (e.key === 'Enter') {
            if (buffer.length >= MIN_LENGTH) {
                e.preventDefault();
                const code = buffer;
                buffer = '';
                handleScan(code);
            }
        }
        else if (e.key.length === 1) {
            buffer += e.key;
        }
    });


    // --- ON LOAD: CHECK PENDING ---
    document.addEventListener('DOMContentLoaded', () => {
        const pending = sessionStorage.getItem('pending_qr');
        if (pending) {
            if (!window.location.href.includes('login.php')) {
                console.log("Found pending QR from storage:", pending);
                sessionStorage.removeItem('pending_qr');
                handleScan(pending);
            }
        }
    });

})();
