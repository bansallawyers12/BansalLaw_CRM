/**
 * CRM toast facade — prefer window.crmNotify / window.crmToast for user-visible notifications.
 * Expects window.iziToast from the Toastify shim in CRM client-detail layouts (not the iziToast library).
 */
(function (global) {
    'use strict';

    var DEFAULTS = {
        position: 'topRight',
        transitionIn: 'fadeInDown',
        transitionOut: 'fadeOutUp'
    };

    function normalize(arg) {
        if (arg == null) {
            return {};
        }
        if (typeof arg === 'string') {
            return { message: arg };
        }
        if (typeof arg === 'object') {
            return arg;
        }
        return { message: String(arg) };
    }

    function hasIzi() {
        return typeof global.iziToast !== 'undefined';
    }

    function fallbackLog(opts) {
        var t = opts.title != null ? String(opts.title) : '';
        var m = opts.message != null ? String(opts.message) : '';
        if (!t && !m) {
            return;
        }
        if (typeof console !== 'undefined' && console.warn) {
            console.warn('[crmNotify]', t ? (t + ': ' + m) : m);
        }
    }

    function hasToastBody(opts) {
        return (opts.title != null && String(opts.title).length > 0) ||
            (opts.message != null && String(opts.message).length > 0);
    }

    function defaultTitle(type) {
        if (type === 'success') {
            return 'Success';
        }
        if (type === 'error') {
            return 'Error';
        }
        if (type === 'warning') {
            return 'Warning';
        }
        return 'Notice';
    }

    function formatMessage(message) {
        return String(message || '').replace(/\n/g, '<br>');
    }

    global.crmNotify = {
        isAvailable: function () {
            return hasIzi();
        },

        destroy: function () {
            if (hasIzi() && typeof global.iziToast.destroy === 'function') {
                global.iziToast.destroy();
            }
        },

        /** Full iziToast.show options; defaults position topRight. */
        show: function (opts) {
            var merged = Object.assign({}, DEFAULTS, normalize(opts));
            if (!hasToastBody(merged)) {
                return;
            }
            if (!hasIzi()) {
                fallbackLog(merged);
                return;
            }
            global.iziToast.show(merged);
        },

        success: function (arg) {
            var o = Object.assign({}, DEFAULTS, normalize(arg));
            if (!hasToastBody(o)) {
                return;
            }
            if (!hasIzi()) {
                fallbackLog(o);
                return;
            }
            if (typeof global.iziToast.success === 'function') {
                global.iziToast.success(o);
            } else {
                global.iziToast.show(Object.assign({}, o, { color: 'green' }));
            }
        },

        error: function (arg) {
            var o = Object.assign({}, DEFAULTS, normalize(arg), { timeout: normalize(arg).timeout || 12000 });
            if (!hasToastBody(o)) {
                return;
            }
            if (!hasIzi()) {
                fallbackLog(o);
                return;
            }
            if (typeof global.iziToast.error === 'function') {
                global.iziToast.error(o);
            } else {
                global.iziToast.show(Object.assign({}, o, { color: 'red' }));
            }
        },

        warning: function (arg) {
            var o = Object.assign({}, DEFAULTS, normalize(arg));
            if (!hasToastBody(o)) {
                return;
            }
            if (!hasIzi()) {
                fallbackLog(o);
                return;
            }
            if (typeof global.iziToast.warning === 'function') {
                global.iziToast.warning(o);
            } else {
                global.iziToast.show(Object.assign({}, o, { color: 'yellow' }));
            }
        },

        info: function (arg) {
            var o = Object.assign({}, DEFAULTS, normalize(arg));
            if (!hasToastBody(o)) {
                return;
            }
            if (!hasIzi()) {
                fallbackLog(o);
                return;
            }
            if (typeof global.iziToast.info === 'function') {
                global.iziToast.info(o);
            } else {
                global.iziToast.show(Object.assign({}, o, { color: 'blue' }));
            }
        },

        /** Sticky info-style toast until dismissed (legacy crmToastLoading). */
        loading: function (arg) {
            var base = (typeof arg === 'string' || arg == null)
                ? { message: arg == null ? '' : arg }
                : normalize(arg);
            var o = Object.assign({}, DEFAULTS, base, {
                timeout: false,
                close: true
            });
            if (!hasToastBody(o)) {
                return;
            }
            if (!hasIzi()) {
                return;
            }
            if (typeof global.iziToast.info === 'function') {
                global.iziToast.info(o);
            } else {
                global.iziToast.show(Object.assign({}, o, { backgroundColor: '#4a89dc' }));
            }
        }
    };

    /**
     * Shorthand: crmToast(message, type, title)
     * type: success | error | warning | info
     */
    global.crmToast = function (message, type, title) {
        type = type || 'info';
        var opts = {
            title: title || defaultTitle(type),
            message: formatMessage(message),
            position: 'topRight',
            transitionIn: 'fadeInDown',
            transitionOut: 'fadeOutUp',
            timeout: type === 'error' ? 12000 : 4000
        };

        if (typeof global.crmNotify === 'undefined') {
            fallbackLog(opts);
            return;
        }

        if (type === 'success') {
            global.crmNotify.success(opts);
        } else if (type === 'error') {
            global.crmNotify.error(opts);
        } else if (type === 'warning' && typeof global.crmNotify.warning === 'function') {
            global.crmNotify.warning(opts);
        } else {
            global.crmNotify.info(opts);
        }
    };

    /**
     * Drop-in replacement for window.alert — never uses the native browser dialog.
     * Prefers Toastify via crmToast; falls back to SweetAlert2; otherwise console.warn.
     */
    function inferAlertType(message) {
        var lower = String(message || '').toLowerCase();
        if (/success|successfully|saved|updated|sent|copied|completed|created|uploaded|allocated|deleted/.test(lower) &&
            !/fail|error|unable|could not|invalid/.test(lower)) {
            return 'success';
        }
        if (/please |select |enter |fill |required|at least|must |missing|not found|not available|choose /.test(lower)) {
            return 'warning';
        }
        if (/error|fail|invalid|unable|could not|denied|timeout|out of bounds|network/.test(lower)) {
            return 'error';
        }
        return 'info';
    }

    global.crmAlert = function (message) {
        var msg = message == null ? '' : String(message);
        if (!msg) {
            return;
        }
        var type = inferAlertType(msg);

        if (typeof global.crmToast === 'function' && hasIzi()) {
            global.crmToast(msg, type);
            return;
        }

        if (typeof global.Swal !== 'undefined' && typeof global.Swal.fire === 'function') {
            var icon = type === 'success' ? 'success' : (type === 'error' ? 'error' : (type === 'warning' ? 'warning' : 'info'));
            global.Swal.fire({
                icon: icon,
                title: defaultTitle(type),
                html: formatMessage(msg),
                confirmButtonText: 'OK',
                confirmButtonColor: '#1e3d60'
            });
            return;
        }

        fallbackLog({ title: defaultTitle(type), message: msg });
    };

    /**
     * CRM confirmation dialog (SweetAlert2). Drop-in replacement for window.confirm().
     * @param {string|{title?: string, text?: string, html?: string, icon?: string, confirmText?: string, cancelText?: string, confirmColor?: string, cancelColor?: string}} options
     * @returns {Promise<boolean>}
     */
    global.crmConfirm = function (options) {
        var opts = typeof options === 'string'
            ? { text: options }
            : (options || {});
        var title = opts.title || 'Confirm';
        var text = opts.text || '';
        var html = opts.html || undefined;

        if (typeof global.Swal !== 'undefined' && typeof global.Swal.fire === 'function') {
            return global.Swal.fire({
                title: title,
                text: html ? undefined : text,
                html: html,
                icon: opts.icon || 'question',
                showCancelButton: true,
                reverseButtons: true,
                focusCancel: true,
                confirmButtonText: opts.confirmText || 'Yes',
                cancelButtonText: opts.cancelText || 'Cancel',
                confirmButtonColor: opts.confirmColor || '#1e3d60',
                cancelButtonColor: opts.cancelColor || '#5e7a90',
                customClass: { popup: 'crm-swal-popup' }
            }).then(function (result) {
                return !!(result && result.isConfirmed);
            });
        }

        // Last resort only when SweetAlert2 is not loaded.
        var fallback = text || title;
        if (typeof global.__nativeConfirm === 'function') {
            return Promise.resolve(!!global.__nativeConfirm(fallback));
        }
        if (typeof global.confirm === 'function') {
            return Promise.resolve(!!global.confirm(fallback));
        }
        return Promise.resolve(false);
    };

    // Flush any messages queued by the early head stub.
    if (global.__crmAlertQueue && global.__crmAlertQueue.length) {
        var queued = global.__crmAlertQueue.slice();
        global.__crmAlertQueue.length = 0;
        queued.forEach(function (m) {
            global.crmAlert(m);
        });
    }

    // Route native alert() to CRM toast/Swal wherever this script is loaded.
    if (typeof global.alert === 'function' && !global.__crmAlertPatched) {
        global.__crmAlertPatched = true;
        global.__nativeAlert = global.alert.bind(global);
        global.alert = function (message) {
            if (typeof global.crmAlert === 'function') {
                global.crmAlert(message);
                return;
            }
            if (typeof global.__nativeAlert === 'function') {
                global.__nativeAlert(message);
            }
        };
    }
})(typeof window !== 'undefined' ? window : this);
