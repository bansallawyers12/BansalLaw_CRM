/**
 * CRM toast facade — prefer window.crmNotify for user-visible notifications.
 * Backed by iziToast (loaded immediately before this file in CRM layouts).
 */
(function (global) {
    'use strict';

    var DEFAULTS = { position: 'topRight' };

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

    function fallbackAlert(opts) {
        var t = opts.title != null ? String(opts.title) : '';
        var m = opts.message != null ? String(opts.message) : '';
        if (!t && !m) {
            return;
        }
        global.alert(t ? (t + (m ? ': ' + m : '')) : m);
    }

    function hasToastBody(opts) {
        return (opts.title != null && String(opts.title).length > 0) ||
            (opts.message != null && String(opts.message).length > 0);
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
                fallbackAlert(merged);
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
                fallbackAlert(o);
                return;
            }
            if (typeof global.iziToast.success === 'function') {
                global.iziToast.success(o);
            } else {
                global.iziToast.show(Object.assign({}, o, { color: 'green' }));
            }
        },

        error: function (arg) {
            var o = Object.assign({}, DEFAULTS, normalize(arg));
            if (!hasToastBody(o)) {
                return;
            }
            if (!hasIzi()) {
                fallbackAlert(o);
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
                fallbackAlert(o);
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
                fallbackAlert(o);
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
})(typeof window !== 'undefined' ? window : this);
