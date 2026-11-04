@extends('layouts.student')

@section('title', 'Edit Week '.$document->packet_number.' - PSU Intern-Link')

@section('content')
    <div class="il-page-header">
        <div>
            <h1 class="il-page-title">Edit Week {{ $document->packet_number }}</h1>
            <p class="text-secondary mb-0">
                {{ $entries->count() }} {{ \Illuminate\Support\Str::plural('day', $entries->count()) }}@if ($due) · Due {{ $due->format('M d, g:i A') }}@endif
            </p>
        </div>
        <a href="{{ route('student.documents.log', $requirement) }}" class="il-btn-secondary text-decoration-none">
            <i class="ti ti-arrow-left"></i> Back to Weekly Report
        </a>
    </div>

    <div class="il-card" style="max-width: 1000px;">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach (collect($errors->all())->unique() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('student.documents.log.week.update', [$requirement, $document->packet_number]) }}">
            @csrf
            @method('PUT')

            @foreach ($entries as $entry)
                <div class="border rounded-3 p-3 mb-3">
                    <div class="fw-bold mb-2"><i class="ti ti-calendar"></i> {{ $entry->entry_date->format('l, M d, Y') }}</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="il-form-label" for="tasks-{{ $entry->id }}">Tasks Accomplished</label>
                            <textarea class="form-control @error('days.'.$entry->id.'.tasks_accomplished') is-invalid @enderror" id="tasks-{{ $entry->id }}"
                                      name="days[{{ $entry->id }}][tasks_accomplished]" rows="5" required>{{ old('days.'.$entry->id.'.tasks_accomplished', $entry->value('tasks_accomplished')) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="il-form-label" for="learned-{{ $entry->id }}">Knowledge, Skills, Values Learned</label>
                            <textarea class="form-control @error('days.'.$entry->id.'.knowledge_skills_learned') is-invalid @enderror" id="learned-{{ $entry->id }}"
                                      name="days[{{ $entry->id }}][knowledge_skills_learned]" rows="5" required>{{ old('days.'.$entry->id.'.knowledge_skills_learned', $entry->value('knowledge_skills_learned')) }}</textarea>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="d-flex gap-2">
                <button type="submit" class="il-btn-primary"><i class="ti ti-device-floppy"></i> Save Week {{ $document->packet_number }}</button>
                <a href="{{ route('student.documents.log', $requirement) }}" class="il-btn-secondary text-decoration-none">Cancel</a>
            </div>
        </form>
    </div>
@endsection
