{{--
    Lock / unlock a received evaluation. Until it is locked the student or the
    HTE can still edit it (up to the deadline); the instructor locks it, e.g.
    once graded, and can unlock it for a correction.
--}}
@php
    $locked = \App\Support\EvaluationEditWindow::isLockedByInstructor($doc);
    $closed = $locked || \App\Support\EvaluationEditWindow::deadlinePassed($doc);
    $until = \App\Support\EvaluationEditWindow::editableUntil($doc);
@endphp
@if ($canLock)
    <form method="POST" action="{{ route($locked ? 'instructor.evaluations.unlock' : 'instructor.evaluations.lock', $doc) }}" class="d-inline"
          @if ($locked) onsubmit="return confirm('Unlock this evaluation so it can be edited again?')" @endif>
        @csrf
        <button type="submit" class="ev-act {{ $locked ? 'ev-locked' : '' }}"
                title="{{ $locked ? 'Locked — click to unlock for a correction' : ($closed ? 'Editing closed '.$until?->format('M j') : 'Can be edited until '.$until?->format('M j').' — click to lock now') }}">
            <i class="ti {{ $locked ? 'ti-lock' : 'ti-lock-open' }}"></i><span class="visually-hidden">{{ $locked ? 'Unlock' : 'Lock' }}</span>
        </button>
    </form>
@elseif ($locked)
    <span class="ev-act ev-locked" title="Locked by the program's instructor"><i class="ti ti-lock"></i></span>
@endif
