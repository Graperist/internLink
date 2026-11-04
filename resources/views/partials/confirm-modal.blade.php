{{--
    One in-page window for every "Are you sure?" instead of the browser's own
    pop-up (which shows the site address and can't be styled).

    Works with the existing markup unchanged: any form with
    onsubmit="return confirm('…')" or button/link with onclick="return confirm('…')"
    opens this window with the same question; OK continues, Cancel stops.
    Scripts can also use ilConfirm(message), ilAlert(message) and
    ilPrompt(message, {placeholder}) - each returns a Promise.
--}}
<div class="modal fade" id="ilConfirmModal" tabindex="-1" aria-labelledby="ilConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title h6 fw-bold d-flex align-items-center gap-2" id="ilConfirmTitle">
                    <span class="il-confirm-icon"><i class="ti ti-help-circle"></i></span>
                    <span id="ilConfirmHeading">Please confirm</span>
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="ilConfirmMessage" style="white-space: pre-line;"></p>
                <input type="text" class="form-control mt-3 d-none" id="ilConfirmInput" autocomplete="off">
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" id="ilConfirmCancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="ilConfirmOk">OK</button>
            </div>
        </div>
    </div>
</div>
<style>
    .il-confirm-icon { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; background:#eef2f7; color:#1c3f66; font-size:1.1rem; }
    .il-confirm-icon.danger { background:#fdecec; color:#c0392b; }
</style>
<script>
(function () {
    if (window.ilConfirm) return;
    var el = document.getElementById('ilConfirmModal');
    var nativeConfirm = window.confirm.bind(window);
    var queue = Promise.resolve();

    // Shows the window and resolves with: true/false (confirm), the typed text or null (prompt).
    function open(kind, message, options) {
        options = options || {};
        if (!window.bootstrap || !el) {   // no Bootstrap: fall back to the browser's own
            return Promise.resolve(kind === 'prompt' ? window.prompt(message) : kind === 'alert' ? (window.alert(message), true) : nativeConfirm(message));
        }
        var run = function () {
            return new Promise(function (resolve) {
                var modal = bootstrap.Modal.getOrCreateInstance(el);
                var msg = String(message || '');
                var danger = /\b(delete|remove|archive|reset|unlock|cannot be undone|deactivat)/i.test(msg);
                var input = document.getElementById('ilConfirmInput');
                var ok = document.getElementById('ilConfirmOk');
                var cancel = document.getElementById('ilConfirmCancel');
                var icon = el.querySelector('.il-confirm-icon');

                document.getElementById('ilConfirmMessage').textContent = msg;
                document.getElementById('ilConfirmHeading').textContent = options.title || (kind === 'alert' ? 'Notice' : 'Please confirm');
                icon.classList.toggle('danger', danger && kind !== 'alert');
                icon.innerHTML = kind === 'alert' ? '<i class="ti ti-info-circle"></i>' : (danger ? '<i class="ti ti-alert-triangle"></i>' : '<i class="ti ti-help-circle"></i>');
                ok.className = 'btn ' + (danger && kind !== 'alert' ? 'btn-danger' : 'btn-primary');
                ok.textContent = options.okText || 'OK';
                cancel.classList.toggle('d-none', kind === 'alert');
                input.classList.toggle('d-none', kind !== 'prompt');
                input.value = '';
                input.placeholder = options.placeholder || '';

                var answer = kind === 'prompt' ? null : false;
                var finish = function () {
                    el.removeEventListener('hidden.bs.modal', finish);
                    ok.onclick = null; input.onkeydown = null;
                    resolve(kind === 'alert' ? true : answer);
                };
                ok.onclick = function () { answer = kind === 'prompt' ? input.value : true; modal.hide(); };
                input.onkeydown = function (e) { if (e.key === 'Enter') { e.preventDefault(); ok.click(); } };
                el.addEventListener('hidden.bs.modal', finish);
                el.addEventListener('shown.bs.modal', function focus() {
                    el.removeEventListener('shown.bs.modal', focus);
                    (kind === 'prompt' ? input : ok).focus();
                });
                modal.show();
            });
        };
        var p = queue.then(run);
        queue = p.catch(function () {});
        return p;
    }
    window.ilConfirm = function (m, o) { return open('confirm', m, o); };
    window.ilAlert = function (m, o) { return open('alert', m, o); };
    window.ilPrompt = function (m, o) { return open('prompt', m, o); };

    // The question an inline handler would ask, without asking it (or null when it wouldn't).
    function questionOf(target, handler, event) {
        var fn = target[handler];
        if (typeof fn !== 'function') return null;
        var asked = null;
        window.confirm = function (m) { asked = String(m); return false; };
        try { fn.call(target, event); } catch (e) {} finally { window.confirm = nativeConfirm; }
        return asked;
    }
    // Let the confirmed action run through its own handler once, answering "yes".
    function allowOnce() {
        window.confirm = function () { return true; };
        setTimeout(function () { window.confirm = nativeConfirm; }, 0);
    }

    document.addEventListener('click', function (e) {
        var target = e.target.closest && e.target.closest('[onclick*="confirm("]');
        if (!target) return;
        if (target.dataset.ilConfirmed === '1') { delete target.dataset.ilConfirmed; allowOnce(); return; }
        var question = questionOf(target, 'onclick', e);
        if (question === null) return;   // it doesn't ask this time
        e.preventDefault(); e.stopImmediatePropagation();
        ilConfirm(question).then(function (yes) {
            if (!yes) return;
            target.dataset.ilConfirmed = '1';
            target.click();
        });
    }, true);

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.matches || !form.matches('form[onsubmit*="confirm("]')) return;
        if (form.dataset.ilConfirmed === '1') { delete form.dataset.ilConfirmed; allowOnce(); return; }
        var question = questionOf(form, 'onsubmit', e);
        if (question === null) return;
        e.preventDefault(); e.stopImmediatePropagation();
        var submitter = e.submitter || null;
        ilConfirm(question).then(function (yes) {
            if (!yes) return;
            form.dataset.ilConfirmed = '1';
            if (form.requestSubmit) { form.requestSubmit(submitter); } else { form.submit(); }
        });
    }, true);
})();
</script>
