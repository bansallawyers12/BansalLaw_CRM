/**
 * Bootstrap 5 jQuery compatibility shim
 * Provides $.fn.modal, $.fn.collapse, $.fn.dropdown, $.fn.alert, $.fn.popover for legacy code
 * that expects Bootstrap 4's jQuery API. Delegates to Bootstrap 5's native API.
 */
(function() {
    if (typeof bootstrap === 'undefined' || typeof jQuery === 'undefined') return;

    var $ = jQuery;

    // Modal: $('#modal').modal('show'|'hide'|'toggle')
    // Always assign (like $.fn.popover below): legacy plugins may define a broken $.fn.modal
    // that targets Bootstrap 4; BS5 markup requires delegating to bootstrap.Modal.
    $.fn.modal = function(action) {
        return this.each(function() {
            var el = this;
            if (!el || el.nodeType !== 1) return;
            try {
                var instance = bootstrap.Modal.getOrCreateInstance(el);
                if (action === 'show') instance.show();
                else if (action === 'hide') instance.hide();
                else if (action === 'toggle') instance.toggle();
            } catch (e) { console.warn('Bootstrap modal:', e); }
        });
    };

    // Collapse: $(target).collapse('toggle'|'show'|'hide')
    if (!$.fn.collapse) {
        $.fn.collapse = function(action) {
            return this.each(function() {
                try {
                    var instance = bootstrap.Collapse.getOrCreateInstance(this);
                    if (action === 'toggle') instance.toggle();
                    else if (action === 'show') instance.show();
                    else if (action === 'hide') instance.hide();
                } catch (e) { console.warn('Bootstrap collapse:', e); }
            });
        };
    }

    // Dropdown: $('.dropdown-toggle').dropdown()
    if (!$.fn.dropdown) {
        $.fn.dropdown = function() {
            return this.each(function() {
                try {
                    bootstrap.Dropdown.getOrCreateInstance(this).toggle();
                } catch (e) { console.warn('Bootstrap dropdown:', e); }
            });
        };
    }

    // Alert: $('.alert').alert('close')
    if (!$.fn.alert) {
        $.fn.alert = function(action) {
            if (action === 'close') {
                return this.each(function() {
                    try {
                        var alert = bootstrap.Alert.getOrCreateInstance(this);
                        if (alert) alert.close();
                    } catch (e) { /* no-op */ }
                });
            }
            return this;
        };
    }

    /**
     * Popover: $(el).popover({ ... }) or .popover('hide'|'show'|'toggle'|'dispose')
     * Reads legacy data-content / data-placement / data-container when options omit them.
     * Always assign (overwrite) so a broken or missing BS4-era plugin cannot leave $.fn.popover undefined.
     */
    $.fn.popover = function(config) {
        return this.each(function() {
            var el = this;
            if (typeof config === 'string') {
                try {
                    var instCmd = bootstrap.Popover.getInstance(el);
                    if (!instCmd) return;
                    if (config === 'hide') instCmd.hide();
                    else if (config === 'show') instCmd.show();
                    else if (config === 'toggle') instCmd.toggle();
                    else if (config === 'dispose') instCmd.dispose();
                    else if (config === 'enable') instCmd.enable();
                    else if (config === 'disable') instCmd.disable();
                } catch (e) {
                    console.warn('Bootstrap popover:', e);
                }
                return;
            }

            try {
                var existingPop = bootstrap.Popover.getInstance(el);
                if (existingPop) existingPop.dispose();
            } catch (e2) { /* no-op */ }

            var opts = $.extend({
                trigger: 'click',
                html: false,
                placement: 'top',
                fallbackPlacements: ['top', 'right', 'bottom', 'left']
            }, config || {});

            if (opts.content === undefined || opts.content === null) {
                opts.content = el.getAttribute('data-bs-content') || el.getAttribute('data-content') || '';
            }
            if (opts.title === undefined || opts.title === null) {
                opts.title = el.getAttribute('data-bs-title') || el.getAttribute('data-title') || '';
            }
            if (!config || !config.placement) {
                var dp = el.getAttribute('data-bs-placement') || el.getAttribute('data-placement');
                if (dp) opts.placement = dp;
            }
            if (!config || !Object.prototype.hasOwnProperty.call(config, 'container')) {
                var dc = el.getAttribute('data-bs-container') || el.getAttribute('data-container');
                if (dc) opts.container = dc;
            }
            var dh = el.getAttribute('data-bs-html') || el.getAttribute('data-html');
            if (dh === 'true' && opts.html !== true) {
                opts.html = true;
            }
            if (opts.html) {
                opts.sanitize = false;
            }

            if (el.getAttribute('data-role') === 'popover' && !el.getAttribute('data-bs-toggle')) {
                el.setAttribute('data-bs-toggle', 'popover');
            }

            try {
                new bootstrap.Popover(el, opts);
            } catch (e3) {
                console.warn('Bootstrap Popover init:', e3);
            }
        });
    };

    /**
     * Tooltip: $(el).tooltip() or .tooltip('dispose')
     * Legacy markup uses data-toggle="tooltip" and title="..."; BS5 expects data-bs-toggle + title.
     */
    $.fn.tooltip = function(config) {
        return this.each(function() {
            var el = this;
            if (typeof config === 'string') {
                try {
                    var ti = bootstrap.Tooltip.getInstance(el);
                    if (!ti) return;
                    if (config === 'dispose') ti.dispose();
                    else if (config === 'show') ti.show();
                    else if (config === 'hide') ti.hide();
                } catch (e) {
                    console.warn('Bootstrap tooltip:', e);
                }
                return;
            }
            try {
                var existingTip = bootstrap.Tooltip.getInstance(el);
                if (existingTip) existingTip.dispose();
            } catch (e2) { /* no-op */ }
            var dtLegacy = el.getAttribute('data-toggle');
            if (dtLegacy && !el.getAttribute('data-bs-toggle')) {
                el.setAttribute('data-bs-toggle', dtLegacy);
            }
            try {
                new bootstrap.Tooltip(el, $.extend({ container: 'body' }, config || {}));
            } catch (e3) {
                console.warn('Bootstrap Tooltip init:', e3);
            }
        });
    };
})();
