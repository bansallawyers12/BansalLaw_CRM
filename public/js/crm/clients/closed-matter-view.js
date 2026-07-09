/**
 * Read-only mode when viewing a closed matter from global search or closed matters list.
 */
(function () {
    'use strict';

    var cfg = window.ClientDetailConfig || {};
    if (!cfg.isClosedMatterView) {
        return;
    }

    window.__CRM_CLOSED_MATTER_VIEW__ = true;
    document.body.classList.add('crm-closed-matter-view');

    function isAllowedTarget(el) {
        if (!el || el.nodeType !== 1) {
            return false;
        }
        if (el.closest('.crm-closed-matter-allow')) {
            return true;
        }
        if (el.matches('[data-bs-toggle="tab"], [data-bs-toggle="pill"], .nav-link')) {
            return true;
        }
        if (el.matches('a[href], .download-file, .preview-file, [data-document-id]')) {
            return true;
        }
        if (el.closest('.modal') && el.matches('[data-bs-dismiss="modal"], .btn-close, .close')) {
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
}());
