import './bootstrap';

import Alpine from 'alpinejs';
import SignaturePad from 'signature_pad';

// Make global
window.Alpine = Alpine;
window.SignaturePad = SignaturePad;

Alpine.start();

/*
|--------------------------------------------------------------------------
| Notification Bell Update (always available — used by polling / matter tab)
|--------------------------------------------------------------------------
*/
window.updateNotificationBell = function (count, options = {}) {
    const el = document.getElementById('countbell_notification');
    if (!el) return;
    const prevCount = parseInt(String(el.textContent || '0'), 10) || 0;
    const newCount = typeof count === 'number' ? count : parseInt(String(count), 10) || 0;
    el.textContent = newCount > 0 ? String(newCount) : '';
    el.style.removeProperty('display');

    const parent = el.closest('.notification-toggle') || el.parentElement;
    if (parent) {
        parent.classList.add('notification-bell-flash');
        setTimeout(function () { parent.classList.remove('notification-bell-flash'); }, 600);
    }
    if (options.showToast !== false && newCount > prevCount) {
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
        if (typeof window.crmNotify !== 'undefined' && typeof window.crmNotify.show === 'function') {
            window.crmNotify.show(toastConfig);
        } else if (typeof window !== 'undefined' && window.iziToast && window.iziToast.show) {
            window.iziToast.show(toastConfig);
        }
    }
};

// Polling for notification badge (updates without page refresh)
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
                    badgeEl.style.removeProperty('display');
                }
            })
            .catch(() => {});
    }

    setInterval(fetchCount, 30000);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') fetchCount();
    });
})();

/*
|--------------------------------------------------------------------------
| FullCalendar v6 (single integration path)
|--------------------------------------------------------------------------
| Exposes globals for the booking admin calendar only (Blade: calendar-v6).
| Plugins are bundled here — do not load legacy fullcalendar.min.js or jQuery
| .fullCalendar() anywhere. Page init: calendar-v6 Blade (DOMContentLoaded →
| waitForFullCalendar). Deferred modules usually run before that handler;
| polling covers failed builds or script-order edge cases.
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
