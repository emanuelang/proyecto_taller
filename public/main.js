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
            fetch((window.BASE_URL || '') + 'notificaciones_api.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.CSRF_TOKEN || '' }
            })
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
    document.querySelectorAll('.notif-action').forEach((link) => {
        link.addEventListener('click', (event) => {
            const href = link.getAttribute('href');
            if (!href || href === '#') return;
            event.preventDefault();
            window.location.assign(href);
        });
    });
    document.querySelectorAll('.notif-delete').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const notificationId = button.dataset.notificationId;
            if (!notificationId) return;
            const body = new URLSearchParams();
            body.set('notification_id', notificationId);
            body.set('csrf_token', window.CSRF_TOKEN || '');

            fetch((window.BASE_URL || '') + 'notificaciones_eliminar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body
            })
                .then((response) => {
                    if (!response.ok) throw new Error('delete failed');
                    const item = button.closest('.notif-item');
                    if (item) item.remove();
                    const list = document.querySelector('.notif-list');
                    if (list && !list.querySelector('.notif-item')) {
                        list.innerHTML = '<p class="text-muted" style="text-align:center;">No tenes notificaciones.</p>';
                    }
                })
                .catch(() => {});
        });
    });
    if (overlay) {
        overlay.addEventListener('click', () => {
            setSidebar(false);
            setNotifications(false);
        });
    }

    if (window.CSRF_TOKEN) {
        document.querySelectorAll('form[method="post"], form[method="POST"]').forEach((form) => {
            if (!form.querySelector('input[name="csrf_token"]')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'csrf_token';
                input.value = window.CSRF_TOKEN;
                form.appendChild(input);
            }
        });
    }

    document.querySelectorAll('.password-field').forEach((field) => {
        const input = field.querySelector('input');
        const toggle = field.querySelector('.password-toggle');
        if (!input || !toggle) return;

        toggle.addEventListener('click', () => {
            const shouldShow = input.type === 'password';
            input.type = shouldShow ? 'text' : 'password';
            toggle.setAttribute('aria-label', shouldShow ? 'Ocultar contrasena' : 'Mostrar contrasena');
            toggle.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
        });
    });

    const cityInputs = document.querySelectorAll('.city-autocomplete');
    cityInputs.forEach((input) => {
        const wrapper = input.closest('.autocomplete-field');
        const suggestions = wrapper ? wrapper.querySelector('.city-suggestions') : null;
        let cities = [];

        try {
            cities = JSON.parse(input.dataset.cities || '[]');
        } catch (error) {
            cities = [];
        }

        const normalize = (value) => value
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');

        const closeSuggestions = () => {
            if (suggestions) {
                suggestions.classList.remove('active');
                suggestions.innerHTML = '';
            }
        };

        const renderSuggestions = () => {
            if (!suggestions) return;

            const query = normalize(input.value.trim());
            const matches = cities
                .filter((city) => query === '' || normalize(city).includes(query))
                .slice(0, 12);

            suggestions.innerHTML = '';

            if (matches.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'city-suggestion-empty';
                empty.textContent = 'No hay ciudades con ese nombre';
                suggestions.appendChild(empty);
            } else {
                matches.forEach((city) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'city-suggestion';
                    option.textContent = city;
                    option.addEventListener('mousedown', (event) => {
                        event.preventDefault();
                        input.value = city;
                        closeSuggestions();
                    });
                    suggestions.appendChild(option);
                });
            }

            suggestions.classList.add('active');
        };

        input.addEventListener('input', renderSuggestions);
        input.addEventListener('focus', renderSuggestions);
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeSuggestions();
        });
        input.addEventListener('blur', () => {
            window.setTimeout(closeSuggestions, 120);
        });
    });

    const textFields = document.querySelectorAll('input[type="text"], input[type="email"], input[type="password"], input[type="tel"], input[type="number"], textarea');
    
    textFields.forEach(field => {
        if (field.closest('.search-card')) return;

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
