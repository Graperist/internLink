{{-- Shared confirm dialog for every approval step (see partials.approve-button). --}}
<div class="modal fade" id="approveStepModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="approveStepForm" action="">
                @csrf
                <input type="hidden" name="decision" id="approveStepDecision" value="" disabled>
                <input type="hidden" name="return_to" id="approveStepReturnTo" value="" disabled>
                <div class="modal-header">
                    <h2 class="modal-title h6 fw-bold" id="approveStepTitle">Approve</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary mb-0" id="approveStepMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="approveStepConfirm">Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    document.getElementById('approveStepModal').addEventListener('show.bs.modal', function (event) {
        const b = event.relatedTarget;
        const set = (id, value) => { const el = document.getElementById(id); el.value = value; el.disabled = value === ''; };
        document.getElementById('approveStepForm').action = b.getAttribute('data-action');
        document.getElementById('approveStepTitle').textContent = b.getAttribute('data-title');
        document.getElementById('approveStepMessage').textContent = b.getAttribute('data-message');
        document.getElementById('approveStepConfirm').textContent = b.getAttribute('data-confirm');
        set('approveStepDecision', b.getAttribute('data-decision') || '');
        set('approveStepReturnTo', b.getAttribute('data-return-to') || '');
    });
</script>
