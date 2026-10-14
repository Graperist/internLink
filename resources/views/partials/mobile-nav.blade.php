{{-- Phone/tablet menu: top bar with a hamburger that slides the sidebar in. Hidden on desktop. --}}
<div class="il-mobile-bar">
    <button type="button" class="il-mobile-toggle" id="ilMobileToggle" aria-label="Open menu" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>
    <img src="{{ asset('images/psu-logo.png') }}" alt="PSU">
    <strong>PSU Intern-Link</strong>
</div>
<div class="il-sidebar-backdrop" id="ilSidebarBackdrop"></div>
<script>
    (function () {
        var shell = document.querySelector('.il-shell');
        var toggle = document.getElementById('ilMobileToggle');
        var backdrop = document.getElementById('ilSidebarBackdrop');
        if (!shell || !toggle) return;

        function setOpen(open) {
            shell.classList.toggle('il-sidebar-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        toggle.addEventListener('click', function () { setOpen(!shell.classList.contains('il-sidebar-open')); });
        backdrop.addEventListener('click', function () { setOpen(false); });
        document.querySelectorAll('.il-sidebar a').forEach(function (a) {
            a.addEventListener('click', function () { setOpen(false); });
        });
    })();
</script>
