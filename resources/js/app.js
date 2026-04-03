import './bootstrap';

import Alpine from 'alpinejs';
import SignaturePad from 'signature_pad';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make global
window.Alpine = Alpine;
window.SignaturePad = SignaturePad;
window.Pusher = Pusher;

Alpine.start();

/*
|--------------------------------------------------------------------------
| Notification Bell Update (always available - used by Echo and client_portal)
|--------------------------------------------------------------------------
*/
window.updateNotificationBell = function (count, options = {}) {
    const el = document.getElementById('countbell_notification');
    if (!el) return;
    const prevCount = parseInt(String(el.textContent || '0'), 10) || 0;
    const newCount = typeof count === 'number' ? count : parseInt(String(count), 10) || 0;
    el.textContent = newCount > 0 ? String(newCount) : '';
    el.style.display = newCount > 0 ? 'inline' : 'none';

    const parent = el.closest('.notification-toggle') || el.parentElement;
    if (parent) {
        parent.classList.add('notification-bell-flash');
        setTimeout(function () { parent.classList.remove('notification-bell-flash'); }, 600);
    }
    if (options.showToast !== false && newCount > prevCount) {
        const izi = typeof window !== 'undefined' && window.iziToast;
        if (izi && izi.show) {
            const toastMessage = options.message || (newCount === 1 ? 'You have a new notification' : 'You have ' + (newCount - prevCount) + ' new notification(s)');
            const toastConfig = {
                title: 'Notification',
                message: toastMessage,
                position: 'topRight',
                color: 'blue',
                timeout: 5000,
                closeOnClick: true
            };
            if (options.url) {
                toastConfig.onClick = function () {
                    window.location.href = options.url;
                };
            }
            izi.show(toastConfig);
        }
    }
};

/*
|--------------------------------------------------------------------------
| Laravel Echo + Reverb
|--------------------------------------------------------------------------
| - Local: ws://localhost:8080 (REVERB_SCHEME=http, REVERB_PORT=8080)
| - Production: wss://host:443 (REVERB_SCHEME=https, Nginx proxies to Reverb)
*/

if (import.meta.env.VITE_REVERB_APP_KEY) {
    try {
        const useTLS = import.meta.env.VITE_REVERB_SCHEME === 'https';
        const port = parseInt(import.meta.env.VITE_REVERB_PORT, 10);
        const wsPort = !isNaN(port) ? port : (useTLS ? 443 : 8080);

        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY,

            wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
            wsPort,
            wssPort: wsPort,

            forceTLS: useTLS,
            enabledTransports: useTLS ? ['wss'] : ['ws', 'wss'],

            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content'),
                },
            },
        });

        console.log('✅ Laravel Echo initialized with Reverb', useTLS ? '(wss)' : '(ws)');

        // Subscribe to notification count updates (works whether Messages tab is open or not)
        const userId = document.querySelector('meta[name="current-user-id"]')?.content;
        if (userId) {
            const userChannel = window.Echo.private('user.' + userId);
            userChannel.listen('.notification.count.updated', function (e) {
                try {
                    const count = e.unread_count !== undefined ? parseInt(e.unread_count, 10) : 0;
                    const opts = { showToast: true };
                    if (e.message) opts.message = e.message;
                    if (e.url) opts.url = e.url;
                    window.updateNotificationBell(count, opts);
                } catch (err) {
                    console.warn('Notification count update error:', err);
                }
            });
        }
    } catch (error) {
        console.warn('⚠️ Failed to initialize Laravel Echo:', error);
        window.EchoDisabled = true;
    }
} else {
    window.EchoDisabled = true;
}

// Polling fallback for notification badge (updates without page refresh when WebSocket unavailable)
(function pollNotificationCount() {
    const badgeEl = document.getElementById('countbell_notification');
    const userId = document.querySelector('meta[name="current-user-id"]')?.content;
    if (!badgeEl || !userId) return;

    function fetchCount() {
        if (document.visibilityState === 'hidden') return;
        fetch('/fetch-notification', {
            method: 'GET',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'include'
        })
            .then((r) => r.json())
            .then((data) => {
                const count = parseInt(data.unseen_notification || 0, 10) || 0;
                if (typeof window.updateNotificationBell === 'function') {
                    window.updateNotificationBell(count, { showToast: false });
                } else if (badgeEl) {
                    badgeEl.textContent = count > 0 ? String(count) : '';
                    badgeEl.style.display = count > 0 ? 'inline' : 'none';
                }
            })
            .catch(() => {});
    }

    setTimeout(fetchCount, 5000);
    setInterval(fetchCount, 30000);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') fetchCount();
    });
})();

/*
|--------------------------------------------------------------------------
| FullCalendar v6
|--------------------------------------------------------------------------
*/

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';

window.FullCalendar = { Calendar };
window.FullCalendarPlugins = {
    dayGridPlugin,
    timeGridPlugin,
    interactionPlugin,
    listPlugin,
};
