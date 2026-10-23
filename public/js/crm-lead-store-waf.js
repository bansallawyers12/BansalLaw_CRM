/**
 * TEMPORARY — WAF-safe Create Lead submit (application/json).
 * Remove when CRM_TEMPORARY_WAF_SAFE_LEAD_STORE=false and hosting allows POST /leads/store.
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

    function formToPayload(form) {
        var payload = {};
        if (!form) {
            return payload;
        }
        new FormData(form).forEach(function (value, key) {
            setDeepField(payload, key, value);
        });
        return payload;
    }

    window.crmLeadFormToPayload = formToPayload;
})(window);
