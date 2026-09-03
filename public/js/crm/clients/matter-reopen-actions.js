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
        return Promise.resolve(window.confirm(message || 'Reopen this matter? It will be moved back to active matters.'));
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
}(window, document));
