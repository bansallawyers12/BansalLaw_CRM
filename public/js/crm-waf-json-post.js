/**
 * TEMPORARY — WAF-safe JSON POST (application/json) instead of multipart/form-data.
 * Remove when CRM_TEMPORARY_WAF_SAFE_JSON_POST=false and hosting allows normal POST.
 */
(function (window) {
    'use strict';

    function parsePhpFormKey(key) {
        var parts = [];
        var rest = String(key);
        var first = rest.match(/^([^\[]+)/);
        if (!first) {
            return [key];
        }
        parts.push(first[1]);
        rest = rest.slice(first[1].length);
        while (rest.indexOf('[') === 0) {
            var m = rest.match(/^\[([^\]]*)\]/);
            if (!m) {
                break;
            }
            parts.push(m[1]);
            rest = rest.slice(m[0].length);
        }
        return parts;
    }

    function setDeepField(obj, key, value) {
        var parts = parsePhpFormKey(key);
        var cur = obj;
        var i;
        for (i = 0; i < parts.length - 1; i++) {
            var p = parts[i];
            var next = parts[i + 1];
            if (cur[p] === undefined) {
                cur[p] = (next === '' || /^\d+$/.test(next)) ? [] : {};
            }
            cur = cur[p];
        }
        var last = parts[parts.length - 1];
        if (last === '' && Array.isArray(cur)) {
            cur.push(value);
            return;
        }
        if (cur[last] !== undefined) {
            if (!Array.isArray(cur[last])) {
                cur[last] = [cur[last], value];
            } else {
                cur[last].push(value);
            }
        } else {
            cur[last] = value;
        }
    }

    function formDataToPayload(formData) {
        var payload = {};
        if (!formData || typeof formData.forEach !== 'function') {
            return payload;
        }
        formData.forEach(function (value, key) {
            setDeepField(payload, key, value);
        });
        return payload;
    }

    function formToPayload(form) {
        if (!form) {
            return {};
        }
        return formDataToPayload(new FormData(form));
    }

    function formDataHasFiles(formData) {
        if (!formData || typeof formData.forEach !== 'function') {
            return false;
        }
        var has = false;
        formData.forEach(function (value) {
            if (value && typeof File !== 'undefined' && value instanceof File && value.size > 0) {
                has = true;
            }
        });
        return has;
    }

    function normalizeUrl(url) {
        try {
            return new URL(String(url), window.location.origin).href;
        } catch (e) {
            return String(url);
        }
    }

    function resolveWafJsonPostUrl(url) {
        if (!window.CRM_WAF_JSON_POST_ENABLED || !url) {
            return url;
        }
        var map = window.CRM_WAF_JSON_POST_MAP || {};
        var normalized = normalizeUrl(url);
        if (map[normalized]) {
            return map[normalized];
        }
        if (map[url]) {
            return map[url];
        }
        var path;
        try {
            path = new URL(normalized).pathname;
        } catch (e2) {
            return url;
        }
        var keys = Object.keys(map);
        for (var i = 0; i < keys.length; i++) {
            try {
                if (new URL(keys[i], window.location.origin).pathname === path) {
                    return map[keys[i]];
                }
            } catch (e3) { /* ignore */ }
        }
        return url;
    }

    function enhanceAjaxSettingsForWafJson(settings) {
        if (!window.CRM_WAF_JSON_POST_ENABLED || !settings) {
            return settings;
        }
        var next = Object.assign({}, settings);
        var targetUrl = resolveWafJsonPostUrl(next.url);
        if (!targetUrl || targetUrl === next.url) {
            return settings;
        }

        if (next.data instanceof FormData) {
            if (formDataHasFiles(next.data)) {
                return settings;
            }
            next.url = targetUrl;
            next.contentType = 'application/json; charset=UTF-8';
            next.processData = true;
            next.data = JSON.stringify({ payload: formDataToPayload(next.data) });
            return next;
        }

        if (next.data && typeof next.data === 'object') {
            next.url = targetUrl;
            next.contentType = 'application/json; charset=UTF-8';
            next.processData = true;
            next.data = JSON.stringify({ payload: next.data });
            return next;
        }

        return settings;
    }

    window.crmLeadFormToPayload = formToPayload;
    window.crmFormToPayload = formToPayload;
    window.crmResolveWafJsonPostUrl = resolveWafJsonPostUrl;
    window.crmEnhanceAjaxSettingsForWafJson = enhanceAjaxSettingsForWafJson;
})(window);
