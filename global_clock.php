<!-- Global Clock Component -->
<div class="text-end global-clock-wrapper">
    <div id="live-clock" class="fw-bold"></div>
    <div id="live-date" class="text-secondary small fw-bold text-uppercase"></div>
</div>

<script>
    (function() {
        function updateClock() {
            const now = new Date();
            
            // Time Format: 04:30:15 PM
            const timeStr = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit', 
                hour12: true 
            });
            
            // Date Format: Friday, January 30, 2026
            const dateStr = now.toLocaleDateString('en-US', { 
                weekday: 'long', 
                month: 'long', 
                day: 'numeric', 
                year: 'numeric' 
            });

            const clockEl = document.getElementById('live-clock');
            const dateEl = document.getElementById('live-date');

            if(clockEl) clockEl.textContent = timeStr;
            if(dateEl) dateEl.textContent = dateStr;
        }
        
        setInterval(updateClock, 1000);
        updateClock(); 
    })();
</script>

<style>
    .global-clock-wrapper #live-clock {
        font-family: 'Oswald', sans-serif;
        font-size: 1.6rem;
        letter-spacing: 1px;
        line-height: 1;
        color: #0a0a0a; /* Default Light Mode Color */
        transition: color 0.3s;
    }

    /* Support for your existing Dark Mode class */
    .dark-mode-active .global-clock-wrapper #live-clock {
        color: #ffffff;
    }

    .global-clock-wrapper #live-date {
        letter-spacing: 0.5px;
        margin-top: 2px;
    }
</style>