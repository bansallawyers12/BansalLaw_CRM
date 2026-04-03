/**
 * Human-readable local datetime for CRM UI (ISO / Laravel JSON → "24 Mar 2026, 8:32 am").
 * Invalid or unparseable values return '' (never echoes arbitrary strings into HTML).
 */
(function (global) {
    'use strict';

    var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    function partsFromDate(d) {
        var day = d.getDate();
        var mon = MONTHS[d.getMonth()];
        var y = d.getFullYear();
        var h24 = d.getHours();
        var m = d.getMinutes();
        var ap = h24 >= 12 ? 'pm' : 'am';
        var h12 = h24 % 12;
        if (h12 === 0) h12 = 12;
        var mm = m < 10 ? '0' + m : String(m);
        return day + ' ' + mon + ' ' + y + ', ' + h12 + ':' + mm + ' ' + ap;
    }

    function formatDisplayDateTime(iso) {
        if (iso == null || iso === '') return '';

        var d;
        if (typeof iso === 'number' && isFinite(iso)) {
            d = new Date(iso);
        } else {
            var s = typeof iso === 'string' ? iso.trim() : String(iso);
            if (!s) return '';
            d = new Date(s);
        }

        if (isNaN(d.getTime())) return '';

        return partsFromDate(d);
    }

    global.formatDisplayDateTime = formatDisplayDateTime;
    global.formatGrantWhen = formatDisplayDateTime;
}(typeof window !== 'undefined' ? window : this));
