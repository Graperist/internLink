{{--
    The one way an instructor approves a step of any form (Document Review and
    the full view): a button that opens the shared confirm dialog
    (partials.approve-modal). The wording per step lives here so every form
    reads the same.
    $step: print | signed | approve; $document; $name (form name);
    optional $returnTo ('viewer'), $class (button classes).
--}}
@php
    $steps = [
        'print' => [
            'route' => 'instructor.documents.print', 'icon' => 'ti-printer', 'label' => 'Approve for Printing',
            'title' => 'Approve for printing',
            'message' => 'This confirms the entered data looks correct. The student downloads and prints the form, has it signed by the signatories and uploads the signed copy for you to confirm. They can no longer edit it after this point.',
            'confirm' => 'Approve for printing',
        ],
        'signed' => [
            'route' => 'instructor.documents.complete', 'icon' => 'ti-signature', 'label' => 'Signatures confirmed — Approve',
            'title' => 'Confirm the signatures and approve',
            'message' => 'Check the uploaded copy: it is complete and signed by all the signatories. Confirming approves the form. To ask for a new copy, return it with remarks instead.',
            'confirm' => 'Confirm and approve',
        ],
        'approve' => [
            'route' => 'instructor.documents.review', 'icon' => 'ti-circle-check', 'label' => 'Approve',
            'title' => 'Approve',
            'message' => 'Confirm the submitted document is complete and correct. This approves the form. To ask for a correction, return it with remarks instead.',
            'confirm' => 'Approve', 'decision' => 'approved',
        ],
    ];
    $s = $steps[$step];
@endphp
<button type="button" class="{{ $class ?? 'btn btn-sm btn-primary' }}" data-bs-toggle="modal" data-bs-target="#approveStepModal"
        data-action="{{ route($s['route'], $document) }}"
        data-title="{{ $s['title'] }}{{ filled($name ?? null) ? ' — '.$name : '' }}"
        data-message="{{ $s['message'] }}"
        data-confirm="{{ $s['confirm'] }}"
        data-decision="{{ $s['decision'] ?? '' }}"
        data-return-to="{{ $returnTo ?? '' }}">
    <i class="ti {{ $s['icon'] }}"></i> {{ $s['label'] }}
</button>
