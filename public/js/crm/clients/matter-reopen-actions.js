/**
 * Shared reopen-matter confirm + POST for closed-matter banner / urgent alerts.
 * Expects window.crmMatterReopenConfig = { url, csrfToken } (optional if meta csrf exists).
 */
(function (window, document) {
    'use strict';

    function csrfToken() {
        var cfg = window.crmMatterReopenConfig || {};
        if (cfg.csrfToken) {
            return cfg.csrfToken;
        }
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function reopenUrl() {
        var cfg = window.crmMatterReopenConfig || {};
        return cfg.url || '/clients/matter/reopen';
    }

    function confirmReopen(message) {
        if (typeof window.crmConfirm === 'function') {
            return window.crmConfirm({
                title: 'Reopen matter?',
                text: message || 'Reopen this matter? It will be moved back to active matters.',
                confirmText: 'Yes, reopen',
                confirmColor: '#28a745'
            });
        }
        if (window.Swal && typeof window.Swal.fire === 'function') {
            return window.Swal.fire({
                title: 'Reopen matter?',
                text: message || 'Reopen this matter? It will be moved back to active matters.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, reopen',
                confirmButtonColor: '#28a745',
                cancelButtonText: 'Cancel'
            }).then(function (result) {
                return !!(result && result.isConfirmed);
            });
        }
        return Promise.resolve(false);
    }

    function postReopen(matterId, buttonEl) {
        var payload = {
            matter_id: matterId,
            current_tab: (document.querySelector('.client-nav-button.active') || {}).getAttribute
                ? (document.querySelector('.client-nav-button.active').getAttribute('data-tab') || '')
                : '',
            source: 'reopen_alert'
        };

        if (buttonEl) {
            buttonEl.disabled = true;
            buttonEl.setAttribute('data-orig-html', buttonEl.innerHTML);
            buttonEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Reopening...';
        }

        return fetch(reopenUrl(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.status) {
                    if (data.redirect_url) {
                        window.location.href = data.redirect_url;
                    } else {
                        window.location.reload();
                    }
                    return;
                }
                var msg = (data && data.message) || 'Failed to reopen matter.';
                if (typeof window.crmAlert === 'function') {
                    window.crmAlert(msg);
                } else {
                    window.alert(msg);
                }
                if (buttonEl) {
                    buttonEl.disabled = false;
                    buttonEl.innerHTML = buttonEl.getAttribute('data-orig-html') || 'Reopen';
                }
            })
            .catch(function () {
                if (typeof window.crmAlert === 'function') {
                    window.crmAlert('An error occurred. Please try again.');
                } else {
                    window.alert('An error occurred. Please try again.');
                }
                if (buttonEl) {
                    buttonEl.disabled = false;
                    buttonEl.innerHTML = buttonEl.getAttribute('data-orig-html') || 'Reopen';
                }
            });
    }

    /**
     * @param {string|number} matterId
     * @param {{button?: HTMLElement, confirmMessage?: string}} opts
     */
    window.crmRequestReopenMatter = function (matterId, opts) {
        opts = opts || {};
        if (!matterId) {
            return Promise.resolve();
        }
        return confirmReopen(opts.confirmMessage).then(function (ok) {
            if (!ok) {
                return;
            }
            return postReopen(matterId, opts.button || null);
        });
    };

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-crm-reopen-matter]');
        if (!btn) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        var matterId = btn.getAttribute('data-matter-id') || btn.getAttribute('data-crm-reopen-matter');
        var requester = btn.getAttribute('data-requester-name') || '';
        var msg = requester
            ? ('Reopen this matter requested by ' + requester + '? It will be moved back to active matters.')
            : '';
        window.crmRequestReopenMatter(matterId, { button: btn, confirmMessage: msg || undefined });
    }, true);

    window.crmMaybePromptReopenFromQuery = function () {
        try {
            var params = new URLSearchParams(window.location.search || '');
            if (params.get('show_reopen') !== '1') {
                return;
            }
            var cfg = window.ClientDetailConfig || {};
            if (!cfg.canReopenClosedMatter || !cfg.clientMatterId) {
                return;
            }
            var requester = cfg.reopenRequestedByName || '';
            var msg = requester
                ? ('Reopen this matter requested by ' + requester + '? It will be moved back to active matters.')
                : 'Reopen this matter? It will be moved back to active matters.';
            // Strip flag so refresh does not re-prompt forever.
            params.delete('show_reopen');
            var next = window.location.pathname + (params.toString() ? ('?' + params.toString()) : '') + (window.location.hash || '');
            window.history.replaceState({}, '', next);

            setTimeout(function () {
                window.crmRequestReopenMatter(cfg.clientMatterId, { confirmMessage: msg });
            }, 500);
        } catch (err) {
            // ignore
        }
    };

    /**
     * Snooze the urgent reopen banner/modal until a future time (localStorage).
     * Config via #matterReopenUrgentBar data attributes:
     *   data-staff-id, data-alert-fingerprint
     */
    var snoozeTimer = null;

    function snoozeStorageKey(staffId) {
        return 'crm_matter_reopen_snooze_' + String(staffId || '0');
    }

    function readSnooze(staffId) {
        try {
            var raw = window.localStorage.getItem(snoozeStorageKey(staffId));
            if (!raw) {
                return null;
            }
            var data = JSON.parse(raw);
            if (!data || !data.until || !data.fingerprint) {
                return null;
            }
            if (Date.now() >= Number(data.until)) {
                window.localStorage.removeItem(snoozeStorageKey(staffId));
                return null;
            }
            return data;
        } catch (err) {
            return null;
        }
    }

    function writeSnooze(staffId, fingerprint, minutes) {
        var until = Date.now() + (Math.max(1, Number(minutes) || 5) * 60 * 1000);
        window.localStorage.setItem(
            snoozeStorageKey(staffId),
            JSON.stringify({ until: until, fingerprint: String(fingerprint || ''), minutes: Number(minutes) })
        );
        return until;
    }

    function clearSnooze(staffId) {
        try {
            window.localStorage.removeItem(snoozeStorageKey(staffId));
        } catch (err) {
            // ignore
        }
    }

    function setUrgentUiVisible(visible) {
        var bar = document.getElementById('matterReopenUrgentBar');
        var modal = document.getElementById('matterReopenUrgentModal');
        if (bar) {
            bar.classList.toggle('is-snoozed', !visible);
            bar.setAttribute('aria-hidden', visible ? 'false' : 'true');
            bar.style.display = visible ? '' : 'none';
        }
        if (!visible && modal && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var instance = bootstrap.Modal.getInstance(modal);
            if (instance) {
                instance.hide();
            }
        }
    }

    function scheduleSnoozeWake(staffId, fingerprint, until) {
        if (snoozeTimer) {
            clearTimeout(snoozeTimer);
            snoozeTimer = null;
        }
        var remaining = Number(until) - Date.now();
        if (remaining <= 0) {
            clearSnooze(staffId);
            setUrgentUiVisible(true);
            return;
        }
        // Cap individual timer to avoid huge delays; page navigations re-check.
        var wait = Math.min(remaining, 2147483647);
        snoozeTimer = setTimeout(function () {
            var current = readSnooze(staffId);
            if (!current || current.fingerprint !== fingerprint || Date.now() >= Number(current.until)) {
                clearSnooze(staffId);
                setUrgentUiVisible(true);
                return;
            }
            scheduleSnoozeWake(staffId, fingerprint, current.until);
        }, wait);
    }

    window.crmSnoozeMatterReopenAlerts = function (minutes) {
        var bar = document.getElementById('matterReopenUrgentBar');
        if (!bar) {
            return;
        }
        var staffId = bar.getAttribute('data-staff-id') || '0';
        var fingerprint = bar.getAttribute('data-alert-fingerprint') || '';
        var until = writeSnooze(staffId, fingerprint, minutes);
        setUrgentUiVisible(false);
        scheduleSnoozeWake(staffId, fingerprint, until);

        var label = Number(minutes) === 60 ? '1 hour' : (String(minutes) + ' minutes');
        if (typeof iziToast !== 'undefined') {
            iziToast.info({
                title: 'Remind me later',
                message: 'Reopen request alert will return in ' + label + '.',
                position: 'topRight',
                timeout: 3500
            });
        }
    };

    window.crmInitMatterReopenSnooze = function () {
        var bar = document.getElementById('matterReopenUrgentBar');
        if (!bar) {
            return false;
        }
        var staffId = bar.getAttribute('data-staff-id') || '0';
        var fingerprint = bar.getAttribute('data-alert-fingerprint') || '';
        var snooze = readSnooze(staffId);
        if (snooze && snooze.fingerprint === fingerprint) {
            setUrgentUiVisible(false);
            scheduleSnoozeWake(staffId, fingerprint, snooze.until);
            return true; // currently snoozed
        }
        if (snooze && snooze.fingerprint !== fingerprint) {
            // New/changed pending requests — clear old snooze and show again.
            clearSnooze(staffId);
        }
        setUrgentUiVisible(true);
        return false;
    };

    document.addEventListener('click', function (e) {
        var opt = e.target.closest('[data-crm-reopen-snooze]');
        if (!opt) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        var minutes = parseInt(opt.getAttribute('data-crm-reopen-snooze'), 10);
        if (!minutes || minutes < 1) {
            return;
        }
        window.crmSnoozeMatterReopenAlerts(minutes);
    }, true);
}(window, document));
