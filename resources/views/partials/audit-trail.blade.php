{{--
    Audit Trail pop-up notepad (top of screen).

    Coordinator and instructor layouts only. Students and HTE representatives
    never get this: it renders nothing for them here, AND the /audit/feed
    endpoint refuses their requests on the server.
--}}
{{-- One @php block only: mixing inline @php(...) with @php/@endphp confuses Blade's block parser. --}}
@php
    $ilAuditUser = auth()->user();
    $ilAuditScope = match ($ilAuditUser?->role?->code) {
        'coordinator' => 'Everyone on your campus',
        'instructor' => 'You and your students',
        default => '',
    };
@endphp
@if ($ilAuditUser && ($ilAuditUser->isCoordinator() || $ilAuditUser->isInstructor()))

    <link rel="stylesheet" href="{{ asset('css/audit-trail.css') }}?v={{ filemtime(public_path('css/audit-trail.css')) }}">

    <div id="ilAudit" class="ila" data-audit-ignore
         data-feed="{{ route('audit.feed') }}" data-user="{{ $ilAuditUser->id }}">

        <button type="button" class="ila-toggle" aria-expanded="false" aria-controls="ilaPanel">
            <i class="ti ti-notes" aria-hidden="true"></i>
            <span>Audit Trail</span>
            <span class="ila-badge" hidden>0</span>
        </button>

        <section class="ila-panel" id="ilaPanel" role="dialog" aria-label="Audit trail" tabindex="-1" hidden>
            <div class="ila-spiral" aria-hidden="true"></div>

            <header class="ila-head">
                <div class="ila-title">
                    <strong>Audit Trail</strong>
                    <small>{{ $ilAuditScope }}</small>
                </div>
                <span class="ila-live" title="Refreshes automatically"><span class="ila-dot"></span> Live</span>
                <button type="button" class="ila-close" aria-label="Close audit trail"><i class="ti ti-x" aria-hidden="true"></i></button>
            </header>

            <div class="ila-filters" role="group" aria-label="Filter entries">
                <button type="button" class="ila-filter is-active" data-filter="all" aria-pressed="true">All</button>
                <button type="button" class="ila-filter" data-filter="click" aria-pressed="false">Clicks</button>
                <button type="button" class="ila-filter" data-filter="page" aria-pressed="false">Pages</button>
                <button type="button" class="ila-filter" data-filter="action" aria-pressed="false">Actions</button>
                <button type="button" class="ila-filter" data-filter="security" aria-pressed="false">Security</button>
            </div>

            <ol class="ila-list" aria-live="polite"></ol>
            <p class="ila-empty" hidden>Nothing recorded yet.</p>

            <footer class="ila-foot" hidden>
                <button type="button" class="ila-more" hidden>Load older entries</button>
                <span class="ila-status" role="status"></span>
            </footer>
        </section>
    </div>

    <script src="{{ asset('js/audit-trail.js') }}?v={{ filemtime(public_path('js/audit-trail.js')) }}" defer></script>
@endif
