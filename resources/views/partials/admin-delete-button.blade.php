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
        // Asked in the in-page window (partials.confirm-modal), not the browser's pop-up.
        document.addEventListener('submit', function (e) {
            const form = e.target.closest('[data-admin-delete]');
            if (!form) return;
            if (form.dataset.confirmed === '1') { delete form.dataset.confirmed; return; }
            e.preventDefault();
            const code = form.dataset.confirmCode;
            const go = () => { form.dataset.confirmed = '1'; form.requestSubmit ? form.requestSubmit() : form.submit(); };
            if (code) {
                ilPrompt(form.dataset.message + '\n\nType ' + code + ' to confirm:', { placeholder: code, okText: 'Delete' }).then((typed) => {
                    if (typed === null || typed.trim().toUpperCase() !== code.toUpperCase()) return;
                    form.querySelector('[name=confirm_code]').value = typed.trim();
                    go();
                });
            } else {
                ilConfirm(form.dataset.message, { okText: 'Delete' }).then((yes) => { if (yes) go(); });
            }
        });
    </script>
    @endpush
@endonce
