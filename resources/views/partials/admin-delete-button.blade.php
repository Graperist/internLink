{{--
    Trash icon for the Super Admin's deletions.
    $action  - the DELETE url
    $message - the confirmation question
    $confirmCode (optional) - a code the Super Admin must type to confirm (campus)
--}}
<form method="POST" action="{{ $action }}" class="d-inline" data-admin-delete
      data-message="{{ $message }}" @isset($confirmCode) data-confirm-code="{{ $confirmCode }}" @endisset>
    @csrf
    @method('DELETE')
    @isset($confirmCode)<input type="hidden" name="confirm_code" value="">@endisset
    <button type="submit" class="il-icon-btn danger" title="Delete"><i class="ti ti-trash"></i><span class="visually-hidden">Delete</span></button>
</form>
@once
    @push('scripts')
    <script>
        document.addEventListener('submit', function (e) {
            const form = e.target.closest('[data-admin-delete]');
            if (!form) return;
            const code = form.dataset.confirmCode;
            if (code) {
                const typed = window.prompt(form.dataset.message + '\n\nType ' + code + ' to confirm:');
                if (typed === null || typed.trim().toUpperCase() !== code.toUpperCase()) { e.preventDefault(); return; }
                form.querySelector('[name=confirm_code]').value = typed.trim();
            } else if (!window.confirm(form.dataset.message)) {
                e.preventDefault();
            }
        });
    </script>
    @endpush
@endonce
