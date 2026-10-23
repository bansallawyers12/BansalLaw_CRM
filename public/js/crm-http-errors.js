/**
 * Navigate to branded CRM error pages (resources/views/errors/*).
 */
(function (window) {
    'use strict';

    var ALLOWED = [401, 403, 404, 419, 500, 503];

    function isEdgeForbiddenHtml(html) {
        if (!html) {
            return false;
        }
        var lower = String(html).toLowerCase();
        if (lower.indexOf("don't have permission to access this resource") !== -1) {
            return true;
        }
        return lower.indexOf('<h1>forbidden</h1>') !== -1 && lower.indexOf('laravel') === -1;
    }

    function goToErrorPage(status, message, extraQuery) {
        var code = parseInt(status, 10);
        if (ALLOWED.indexOf(code) === -1) {
            code = 403;
        }
        var base = window.CRM_ERROR_PAGE_URL;
        if (!base) {
            base = '/error/' + code;
        } else if (base.indexOf('{code}') !== -1) {
            base = base.replace('{code}', String(code));
        } else if (!/\/\d+$/.test(base)) {
            base = base.replace(/\/?$/, '') + '/' + code;
        }
        var params = new URLSearchParams();
        if (extraQuery && typeof extraQuery === 'object') {
            Object.keys(extraQuery).forEach(function (key) {
                if (extraQuery[key] != null && extraQuery[key] !== '') {
                    params.set(key, String(extraQuery[key]));
                }
            });
        }
        if (message) {
            params.set('message', String(message).slice(0, 500));
        }
        var qs = params.toString();
        window.location.assign(qs ? base + '?' + qs : base);
    }

    /**
     * @param {number} status HTTP status
     * @param {string} [rawBody] response text
     * @param {object} [json] parsed JSON body
     * @returns {boolean} true if navigation was triggered
     */
    function redirectHttpErrorFromAjax(status, rawBody, json) {
        var code = parseInt(status, 10);
        if (ALLOWED.indexOf(code) === -1) {
            return false;
        }
        if (json && json.error_page_url) {
            window.location.assign(json.error_page_url);
            return true;
        }
        if (code === 403) {
            if (isEdgeForbiddenHtml(rawBody)) {
                goToErrorPage(403, '', { context: 'blocked' });
                return true;
            }
            var msg = (json && json.message) ? json.message : '';
            goToErrorPage(403, msg);
            return true;
        }
        goToErrorPage(code, (json && json.message) ? json.message : '');
        return true;
    }

    window.crmIsEdgeForbiddenHtml = isEdgeForbiddenHtml;
    window.crmGoToErrorPage = goToErrorPage;
    window.crmRedirectHttpErrorFromAjax = redirectHttpErrorFromAjax;
})(window);
