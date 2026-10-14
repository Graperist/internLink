/*
 * Audit click tracker.
 *
 * Reports "user clicked <button/link label>" to the server so it lands in the
 * audit trail. Invisible - it draws nothing on the page.
 *
 * What it sends: the visible label of the clicked button/link, its type, the
 * link's path, and the current page path. What it NEVER sends: text typed into
 * fields, field values, or query strings.
 *
 * Elements inside [data-audit-ignore] (e.g. the audit pop-up itself) are
 * skipped, otherwise reading the audit trail would fill the audit trail.
 */
(function () {
    'use strict';

    var tag = document.getElementById('il-audit-tracker');
    if (!tag) return;

    var endpoint = tag.getAttribute('data-endpoint');
    var token = tag.getAttribute('data-token');
    if (!endpoint || !token) return;

    var SELECTOR = 'a[href], button, input[type="submit"], input[type="button"], [role="button"], [data-audit]';
    var lastKey = '';
    var lastAt = 0;
    var sentThisMinute = 0;

    setInterval(function () { sentThisMinute = 0; }, 60000);

    function clean(text, max) {
        return (text || '').replace(/\s+/g, ' ').trim().slice(0, max);
    }

    function labelOf(el) {
        var label =
            el.getAttribute('data-audit') ||
            el.getAttribute('aria-label') ||
            el.getAttribute('title') ||
            (el.tagName === 'INPUT' ? el.value : '') ||
            el.textContent;

        label = clean(label, 80);

        if (!label) {
            // Icon-only button: fall back to the icon's name (e.g. "ti-trash" -> "trash").
            var icon = el.querySelector('[class*="ti-"]');
            if (icon) {
                var m = /(?:^|\s)ti-([a-z0-9-]+)/.exec(icon.className);
                if (m) label = 'icon: ' + m[1];
            }
        }
        return label;
    }

    function typeOf(el) {
        if (el.tagName === 'A') return 'link';
        var t = (el.getAttribute('type') || '').toLowerCase();
        if (t === 'submit' || (el.tagName === 'BUTTON' && !t && el.form)) return 'submit';
        if (el.tagName === 'BUTTON' || el.tagName === 'INPUT' || el.getAttribute('role') === 'button') return 'button';
        return 'other';
    }

    function hrefOf(el) {
        if (el.tagName !== 'A') return '';
        try {
            var u = new URL(el.href, window.location.href);
            // Same site: path only. Other site: host + path. Never the query string.
            return u.origin === window.location.origin ? u.pathname : u.host + u.pathname;
        } catch (e) {
            return '';
        }
    }

    function send(payload) {
        var body = new URLSearchParams(payload);
        body.append('_token', token);

        try {
            if (navigator.sendBeacon && navigator.sendBeacon(endpoint, body)) return;
        } catch (e) { /* fall through to fetch */ }

        try {
            fetch(endpoint, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                keepalive: true,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).catch(function () {});
        } catch (e) { /* tracking must never break the page */ }
    }

    document.addEventListener('click', function (e) {
        if (!e.isTrusted || !e.target || !e.target.closest) return; // real user clicks only

        var el = e.target.closest(SELECTOR);
        if (!el || el.closest('[data-audit-ignore]')) return;

        var label = labelOf(el);
        if (!label) return;

        var page = window.location.pathname;
        var key = label + '|' + page;
        var now = Date.now();
        if (key === lastKey && now - lastAt < 600) return; // double-click
        lastKey = key;
        lastAt = now;

        if (++sentThisMinute > 60) return; // runaway-click guard

        send({ label: label, type: typeOf(el), href: hrefOf(el), page: page });
    }, true);
})();
