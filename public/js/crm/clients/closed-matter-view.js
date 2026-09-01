/**
 * Read-only mode when viewing a closed matter from global search or closed matters list.
 * Personal / matter document tabs: browse folders and preview files only.
 */
(function () {
    'use strict';

    var cfg = window.ClientDetailConfig || {};
    if (!cfg.isClosedMatterView) {
        return;
    }

    window.__CRM_CLOSED_MATTER_VIEW__ = true;
    document.body.classList.add('crm-closed-matter-view');

    /**
     * Context menus are appended to document.body (outside .crm-container),
     * so hide every non-preview item when the menu opens.
     */
    function applyClosedMatterContextMenuFilter(menu) {
        if (!menu) {
            return;
        }
        menu.classList.add('crm-closed-matter-context-menu');
        Array.prototype.forEach.call(menu.querySelectorAll('.context-menu-item'), function (item) {
            var onclick = item.getAttribute('onclick') || '';
            var isPreview = onclick.indexOf("'preview'") !== -1 || onclick.indexOf('"preview"') !== -1;
            item.style.display = isPreview ? 'block' : 'none';
            if (isPreview) {
                item.classList.add('crm-closed-matter-allow');
            } else {
                item.classList.remove('crm-closed-matter-allow');
            }
        });
    }

    window.applyClosedMatterContextMenuFilter = applyClosedMatterContextMenuFilter;

    function isClosedMatterMutationAction(action) {
        return action && action !== 'preview';
    }

    window.crmClosedMatterBlocksAction = function (action) {
        return isClosedMatterMutationAction(action);
    };

    function isDocumentReadOnlyAllowed(el) {
        if (!el || el.nodeType !== 1) {
            return false;
        }
        if (el.matches('.subtab2-button, .subtab6-button, .subtab-button, .subtab3-button, .subtab8-button, .cdn-doc-subtab-btn')) {
            return true;
        }
        if (el.matches('.client-nav-button[data-tab="notuseddocuments"]')) {
            return true;
        }
        if (el.matches('.btn-not-used-preview') || el.closest('.btn-not-used-preview')) {
            return true;
        }
        if (el.closest('.doc-row')) {
            if (el.matches('a[href]') || el.closest('a[href]')) {
                return true;
            }
        }
        var contextItem = el.closest('.context-menu-item');
        if (contextItem) {
            var onclick = contextItem.getAttribute('onclick') || '';
            return onclick.indexOf("'preview'") !== -1 || onclick.indexOf('"preview"') !== -1;
        }
        return false;
    }

    function isAllowedTarget(el) {
        if (!el || el.nodeType !== 1) {
            return false;
        }
        if (el.closest('.crm-closed-matter-allow')) {
            return true;
        }
        if (isDocumentReadOnlyAllowed(el)) {
            return true;
        }
        if (el.matches('[data-bs-toggle="tab"], [data-bs-toggle="pill"], .nav-link, .client-nav-button')) {
            return true;
        }
        if (el.matches('a[href], .preview-file')) {
            return true;
        }
        if (el.closest('.modal') && el.matches('[data-bs-dismiss="modal"], .btn-close, .close, .crm-modal-close')) {
            return true;
        }
        return false;
    }

    document.addEventListener('submit', function (event) {
        if (!event.target.closest('.crm-container--closed-matter-view')) {
            return;
        }
        if (event.target.closest('.crm-closed-matter-allow-submit')) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
    }, true);

    document.addEventListener('click', function (event) {
        var contextItem = event.target.closest('.context-menu-item');
        if (contextItem) {
            var onclick = contextItem.getAttribute('onclick') || '';
            var isPreview = onclick.indexOf("'preview'") !== -1 || onclick.indexOf('"preview"') !== -1;
            if (!isPreview) {
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
            }
            return;
        }

        var target = event.target.closest('button, input[type="button"], input[type="submit"], a.btn, .btn');
        if (!target || !target.closest('.crm-container--closed-matter-view')) {
            return;
        }
        if (isAllowedTarget(target)) {
            return;
        }
        if (target.closest('.tab-content') || target.closest('.cdn-client-hero')) {
            event.preventDefault();
            event.stopPropagation();
        }
    }, true);

    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('clientTabContentLoaded', function () {
            document.body.classList.add('crm-closed-matter-view');
        });
    }
}());
