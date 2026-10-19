{{--
    The one way an instructor approves a step of any form (Document Review and
    the full view): a button that opens the shared confirm dialog
    (partials.approve-modal). The wording per step lives here so every form
    reads the same.
    $step: print | signatures | signed | approve; $document; $name (form name);
    optional $returnTo ('viewer'), $class (button classes).
--}}
@php
    $steps = [
        'print' => [
            'route' => 'instructor.documents.print', 'icon' => 'ti-printer', 'label' => 'Approve for Printing',
            'title' => 'Approve for printing',
            'message' => 'This confirms the entered data looks correct. The student downloads and prints the form, has it signed by the signatories and brings it back to you. They can no longer edit it after this point.',
            'confirm' => 'Approve for printing',
        ],
        'signatures' => [
            'route' => 'instructor.documents.confirm-signatures', 'icon' => 'ti-signature', 'label' => 'Signatures confirmed — open upload',
            'title' => 'Confirm the signatures',
            'message' => 'Confirm the student brought back the printed form with all the signatures. This opens their upload of the signed copy.',
            'confirm' => 'Confirm and open upload',
        ],
        'signed' => [
            'route' => 'instructor.documents.complete', 'icon' => 'ti-circle-check', 'label' => 'Approve signed copy',
            'title' => 'Approve signed copy',
            'message' => 'Confirm the uploaded copy is complete and signed by all the signatories. This approves the form. To ask for a new copy, return it with remarks instead.',
            'confirm' => 'Approve',
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
