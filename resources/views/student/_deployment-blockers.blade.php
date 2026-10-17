{{--
    Why the student's deployment hasn't started yet (shown on My HTE Info
    and the Daily Time Record while the deployment is pending).
--}}
@php
    $outstanding = $internship ? \App\Support\PreDeployment::outstanding($internship) : collect();
@endphp
<div class="il-card mb-3" id="deploymentBlockers">
    <h2 class="h6 fw-bold mb-2"><i class="ti ti-route"></i> Before your OJT can start</h2>
    @if ($outstanding->isEmpty())
        <div class="d-flex align-items-start gap-2 small">
            <i class="ti ti-circle-check-filled text-success fs-5"></i>
            <div>
                <strong>All your pre-deployment requirements are approved.</strong>
                Your subject instructor now sets your internship start and end dates (Start deployment).
                Your Daily Time Record opens as soon as they do &mdash; you'll get a notification.
            </div>
        </div>
    @else
        <p class="small text-secondary mb-2">
            Your deployment starts once every pre-deployment requirement is <strong>approved</strong>
            and your subject instructor sets your start date. Still to go ({{ $outstanding->count() }}):
        </p>
        <div class="list-group list-group-flush small">
            @foreach ($outstanding as $item)
                <a href="{{ route('student.documents', ['form' => $item['id']]) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2 px-0">
                    <i class="ti {{ $item['waiting_on'] === 'instructor' ? 'ti-clock text-warning' : 'ti-alert-circle text-danger' }} fs-5"></i>
                    <span class="flex-grow-1">
                        <span class="fw-semibold d-block">{{ $item['name'] }}</span>
                        <span class="text-secondary">{{ $item['status'] }}</span>
                    </span>
                    <i class="ti ti-chevron-right text-secondary"></i>
                </a>
            @endforeach
        </div>
    @endif
</div>
