document.addEventListener('DOMContentLoaded', function() {
    const timeoutMs = Number(window.SESSION_TIMEOUT_MS || 0);
    if (timeoutMs > 0) {
        let inactivityTimer = null;
        const logoutForInactivity = () => {
            window.location.href = (window.BASE_URL || '') + 'logout.php?timeout=1';
        };
        const resetInactivityTimer = () => {
            window.clearTimeout(inactivityTimer);
            inactivityTimer = window.setTimeout(logoutForInactivity, timeoutMs);
        };

        ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'].forEach((eventName) => {
            document.addEventListener(eventName, resetInactivityTimer, { passive: true });
        });
        resetInactivityTimer();
    }

    const sidebar = document.getElementById('sidebarMenu');
    const overlay = document.getElementById('sidebarOverlay');
    const btnOpen = document.getElementById('sidebarOpen');
    const notifSidebar = document.getElementById('notifMenu');
    const btnNotifOpen = document.getElementById('notifOpen');
    const btnNotifClose = document.getElementById('notifClose');

    function showOverlay() {
        if (overlay) overlay.style.display = 'block';
    }

    function hideOverlayIfClosed() {
        if (!overlay) return;
        const sidebarOpen = sidebar && sidebar.classList.contains('active');
        const notifOpen = notifSidebar && notifSidebar.classList.contains('active');
        if (!sidebarOpen && !notifOpen) overlay.style.display = 'none';
    }

    function setSidebar(open) {
        if (!sidebar) return;
        sidebar.classList.toggle('active', open);
        if (open) showOverlay();
        hideOverlayIfClosed();
    }

    function setNotifications(open) {
        if (!notifSidebar) return;
        notifSidebar.classList.toggle('active', open);
        if (open) {
            showOverlay();
            fetch((window.BASE_URL || '') + 'notificaciones_api.php', { method: 'POST' })
                .then(() => {
                    const badge = document.getElementById('notifBadge');
                    if (badge) badge.style.display = 'none';
                })
                .catch(() => {});
        }
        hideOverlayIfClosed();
    }

    if (btnOpen) btnOpen.addEventListener('click', () => setSidebar(true));
    if (btnNotifOpen) btnNotifOpen.addEventListener('click', () => setNotifications(true));
    if (btnNotifClose) btnNotifClose.addEventListener('click', () => setNotifications(false));
    if (overlay) {
        overlay.addEventListener('click', () => {
            setSidebar(false);
            setNotifications(false);
        });
    }

    const textFields = document.querySelectorAll('input[type="text"], input[type="email"], input[type="password"], input[type="tel"], input[type="number"], textarea');
    
    textFields.forEach(field => {
        if (field.hasAttribute('maxlength') || field.hasAttribute('minlength')) {
            const max = field.hasAttribute('maxlength') ? parseInt(field.getAttribute('maxlength'), 10) : 0;
            const min = field.hasAttribute('minlength') ? parseInt(field.getAttribute('minlength'), 10) : 0;
            
            if (max === 0) return; // Need max to display properly

            const counter = document.createElement('div');
            counter.className = 'char-counter';
            counter.style.textAlign = 'right';
            counter.style.fontSize = '0.85em';
            counter.style.color = '#64748b';
            counter.style.marginTop = '2px';
            counter.style.marginBottom = '8px';
            counter.style.display = 'none'; // Initially hidden
            counter.style.transition = 'color 0.3s ease';
            
            field.parentNode.insertBefore(counter, field.nextSibling);
            
            function updateCounter() {
                const currentLen = field.value.length;
                counter.textContent = `${currentLen}/${max}`;
                
                // Change color if approaching limit (e.g., less than 10 chars left)
                if (max - currentLen <= 10 && max > 15) {
                    counter.style.color = '#ef4444'; // Red
                } else {
                    counter.style.color = '#64748b'; // Default gray
                }
                
                // Enforce max length manually just in case
                if (currentLen > max) {
                    field.value = field.value.substring(0, max);
                    counter.textContent = `${max}/${max}`;
                    counter.style.color = '#ef4444';
                }
            }

            function checkVisibility() {
                const currentLen = field.value.length;
                // Visible on focus AND when length >= min (as requested by user)
                if (document.activeElement === field && currentLen >= min) {
                    counter.style.display = 'block';
                } else {
                    counter.style.display = 'none';
                }
            }
            
            field.addEventListener('input', () => {
                updateCounter();
                checkVisibility();
            });
            
            field.addEventListener('focus', () => {
                updateCounter();
                checkVisibility();
            });
            
            field.addEventListener('blur', () => {
                counter.style.display = 'none';
            });
            
            updateCounter();
        }
    });
});
