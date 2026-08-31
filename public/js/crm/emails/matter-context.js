/**
 * Shared client-matter resolution for email UIs (Outlook + legacy lead list).
 * Prefers the live matter dropdown, then ClientDetailConfig, then container data attribute.
 */
(function (window) {
    'use strict';

    function fromDropdown() {
        var el = document.getElementById('sel_matter_id_client_detail');
        if (!el || !el.value) {
            return '';
        }
        return String(el.value).trim();
    }

    function fromConfig() {
        var cfg = window.ClientDetailConfig || {};
        if (cfg.clientMatterId != null && cfg.clientMatterId !== '') {
            return String(cfg.clientMatterId);
        }
        return '';
    }

    function fromContainer(container) {
        if (!container) {
            return '';
        }
        var raw = container.getAttribute('data-matter-id');
        return raw == null ? '' : String(raw).trim();
    }

    function resolve(container) {
        return fromDropdown() || fromConfig() || fromContainer(container) || '';
    }

    window.EmailMatterContext = {
        resolve: resolve,
        fromDropdown: fromDropdown,
        fromConfig: fromConfig,
        fromContainer: fromContainer,
    };
})(window);
