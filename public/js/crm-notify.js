/**
 * CRM toast facade — prefer window.crmNotify / window.crmToast for user-visible notifications.
 * Backed by iziToast.
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
})(typeof window !== 'undefined' ? window : this);
