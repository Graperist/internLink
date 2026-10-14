/*
 * Audit Trail pop-up notepad.
 *
 * Pulls entries from /audit/feed (already scoped to what this viewer may see)
 * and draws them. Everything from the server is inserted with textContent -
 * never innerHTML - because descriptions can contain text that originated in
 * a user's browser.
 */
(function () {
    'use strict';

    var root = document.getElementById('ilAudit');
    if (!root) return;

    var feedUrl = root.getAttribute('data-feed');
    var storeKey = 'ila:lastSeen:' + root.getAttribute('data-user');

    var toggle = root.querySelector('.ila-toggle');
    var panel = root.querySelector('.ila-panel');
    var closeBtn = root.querySelector('.ila-close');
    var list = root.querySelector('.ila-list');
    var empty = root.querySelector('.ila-empty');
    var badge = root.querySelector('.ila-badge');
    var moreBtn = root.querySelector('.ila-more');
    var statusEl = root.querySelector('.ila-status');
    var foot = root.querySelector('.ila-foot');
    var filterBtns = root.querySelectorAll('.ila-filter');

    var ICONS = { click: 'ti-pointer', page: 'ti-file-text', action: 'ti-bolt', security: 'ti-shield-lock' };
    var POLL_MS = 15000;

    var items = [];        // newest first
    var known = {};        // id -> true (dedupe)
    var newIds = {};       // ids that arrived after first paint (flash highlight)
    var hasMore = false;
    var filter = 'all';
    var stopped = false;
    var lastSeen = readStore();
    var initialised = false;

    /* ---------- storage (best effort) ---------- */
    function readStore() {
        try { return parseInt(sessionStorage.getItem(storeKey), 10) || 0; } catch (e) { return 0; }
    }
    function writeStore(v) {
        try { sessionStorage.setItem(storeKey, String(v)); } catch (e) { /* private mode etc. */ }
    }

    /* ---------- helpers ---------- */
    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text != null) n.textContent = text;
        return n;
    }

    function ago(iso) {
        var s = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000));
        if (s < 10) return 'just now';
        if (s < 60) return s + 's ago';
        var m = Math.floor(s / 60);
        if (m < 60) return m + ' min ago';
        var h = Math.floor(m / 60);
        if (h < 24) return h + ' hr ago';
        var d = Math.floor(h / 24);
        return d < 7 ? d + ' d ago' : new Date(iso).toLocaleDateString();
    }

    // The footer only exists to hold "Load older" and error messages - hide it when it has neither.
    function syncFoot() { foot.hidden = moreBtn.hidden && !statusEl.textContent; }
    function setStatus(msg) { statusEl.textContent = msg || ''; syncFoot(); }
    function isOpen() { return !panel.hidden; }
    function latestId() { return items.length ? items[0].id : 0; }
    function oldestId() { return items.length ? items[items.length - 1].id : 0; }

    /* ---------- network ---------- */
    function load(params, done) {
        var qs = Object.keys(params).map(function (k) { return k + '=' + encodeURIComponent(params[k]); }).join('&');

        fetch(feedUrl + (qs ? '?' + qs : ''), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            if (r.status === 401 || r.status === 403 || r.status === 419) {
                stopped = true; // signed out / not allowed - stop hammering the server
                setStatus('Session ended - reload the page.');
                throw new Error('stopped');
            }
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        }).then(function (data) {
            setStatus('');
            done(data);
        }).catch(function (e) {
            if (e.message !== 'stopped') setStatus("Couldn't refresh - will retry.");
        });
    }

    function ingest(incoming, where) {
        var fresh = incoming.filter(function (i) { return !known[i.id]; });
        fresh.forEach(function (i) {
            known[i.id] = true;
            if (initialised) newIds[i.id] = true;
        });
        items = where === 'top' ? fresh.concat(items) : items.concat(fresh);
    }

    function poll() {
        if (stopped || document.hidden || !initialised) return;
        load({ after_id: latestId() }, function (data) {
            if (data.items.length) {
                ingest(data.items, 'top');
                afterChange();
            }
        });
    }

    /* ---------- rendering ---------- */
    function unreadCount() {
        return items.filter(function (i) { return i.id > lastSeen && !i.self; }).length;
    }

    function updateBadge() {
        var n = isOpen() ? 0 : unreadCount();
        badge.hidden = n === 0;
        badge.textContent = n > 99 ? '99+' : String(n);
    }

    function markSeen() {
        lastSeen = Math.max(lastSeen, latestId());
        writeStore(lastSeen);
        updateBadge();
    }

    function render() {
        var visible = items.filter(function (i) { return filter === 'all' || i.kind === filter; });

        list.textContent = '';
        visible.forEach(function (i) {
            var li = el('li', 'ila-item ila-k-' + i.kind + (i.alert ? ' is-alert' : '') + (newIds[i.id] ? ' is-new' : ''));

            var ico = el('span', 'ila-ico');
            ico.setAttribute('aria-hidden', 'true');
            ico.appendChild(el('i', 'ti ' + (ICONS[i.kind] || 'ti-point')));
            li.appendChild(ico);

            var meta = el('div', 'ila-meta');
            meta.appendChild(el('span', 'ila-who' + (i.self ? ' is-you' : ''), i.self ? 'You' : i.user));
            if (i.role) meta.appendChild(el('span', 'ila-role', i.role));
            var time = el('time', 'ila-time', ago(i.at));
            time.setAttribute('datetime', i.at);
            time.setAttribute('data-at', i.at);
            time.title = new Date(i.at).toLocaleString();
            meta.appendChild(time);
            li.appendChild(meta);

            li.appendChild(el('div', 'ila-desc', i.description));
            if (i.ip) li.appendChild(el('div', 'ila-ip', 'IP ' + i.ip));

            list.appendChild(li);
        });

        if (isOpen()) newIds = {}; // keep the highlight until it's actually been seen

        var none = visible.length === 0;
        empty.hidden = !none;
        empty.textContent = items.length === 0
            ? 'Nothing recorded yet.'
            : 'No ' + filter + ' entries in what is loaded so far.';
        moreBtn.hidden = !hasMore;
        syncFoot();
    }

    function afterChange() {
        render();
        if (isOpen()) markSeen(); else updateBadge();
    }

    /* ---------- open / close ---------- */
    function open() {
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        markSeen();
        render(); // refresh relative times
        panel.focus({ preventScroll: true });
        poll();
    }

    function close() {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        updateBadge();
        toggle.focus({ preventScroll: true });
    }

    toggle.addEventListener('click', function () { isOpen() ? close() : open(); });
    closeBtn.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && isOpen()) close(); });

    filterBtns.forEach(function (b) {
        b.addEventListener('click', function () {
            filter = b.getAttribute('data-filter');
            filterBtns.forEach(function (x) {
                var on = x === b;
                x.classList.toggle('is-active', on);
                x.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            render();
        });
    });

    moreBtn.addEventListener('click', function () {
        moreBtn.disabled = true;
        load({ before_id: oldestId() }, function (data) {
            ingest(data.items, 'bottom');
            hasMore = data.has_more;
            moreBtn.disabled = false;
            render();
        });
        // re-enable if the request failed
        setTimeout(function () { moreBtn.disabled = false; }, 8000);
    });

    /* ---------- boot ---------- */
    load({}, function (data) {
        ingest(data.items, 'bottom');
        hasMore = data.has_more;
        initialised = true;

        // First ever load in this tab: don't greet the user with a badge for old history.
        if (!lastSeen) { lastSeen = latestId(); writeStore(lastSeen); }

        afterChange();
    });

    setInterval(poll, POLL_MS);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });

    // keep "2 min ago" labels honest while the panel is open
    setInterval(function () {
        if (!isOpen()) return;
        list.querySelectorAll('time[data-at]').forEach(function (t) {
            t.textContent = ago(t.getAttribute('data-at'));
        });
    }, 30000);
})();
