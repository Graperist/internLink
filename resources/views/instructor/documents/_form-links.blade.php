{{-- One student's forms as links (each opens that form). Long lists fold after 4. --}}
@php
    $fold = $items->count() > 5;
    $groups = $fold ? [$items->take(4), $items->skip(4)] : [$items];
@endphp
@foreach ($groups as $g => $group)
    @if ($g === 1)<details class="il-form-more"><summary class="text-primary">+ {{ $group->count() }} more</summary>@endif
    <ul class="list-unstyled mb-0 il-form-links">
        @foreach ($group as $form)
            <li>
                <a href="{{ route('instructor.documents', array_filter(['student' => $student->id, 'form' => $form->requirement?->id])) }}" class="text-decoration-none">
                    @if ($kind === 'approved')
                        <i class="ti ti-circle-check text-success"></i>
                    @elseif (($form->state ?? null) === 'returned')
                        <i class="ti ti-arrow-back-up text-danger"></i>
                    @else
                        <i class="ti ti-clock text-secondary"></i>
                    @endif
                    <span class="text-body">{{ $form->requirement?->name ?? 'Form' }}</span>
                    @if ($kind === 'approved' && $form->date)
                        <span class="text-secondary" style="font-size: 0.72rem;">· {{ $form->date->format('M d') }}</span>
                    @elseif (($form->state ?? null) === 'returned')
                        <span class="text-danger" style="font-size: 0.72rem;">· returned{{ $form->date ? ' '.$form->date->format('M d') : '' }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
    @if ($g === 1)</details>@endif
@endforeach
