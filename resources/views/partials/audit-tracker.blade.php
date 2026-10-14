{{-- Invisible click tracker: reports button/link clicks to the audit trail. Draws nothing. --}}
@auth
    <script id="il-audit-tracker"
            src="{{ asset('js/audit-tracker.js') }}?v={{ filemtime(public_path('js/audit-tracker.js')) }}"
            data-endpoint="{{ route('audit.click') }}"
            data-token="{{ csrf_token() }}"
            defer></script>
@endauth
