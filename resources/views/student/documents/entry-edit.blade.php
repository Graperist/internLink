@extends('layouts.student')

@section('title', 'Edit Training Day - PSU Intern-Link')

@section('content')
    <div class="il-page-header">
        <div>
            <h1 class="il-page-title">Edit Training Day</h1>
            <p class="text-secondary mb-0">Week {{ $entry->document->packet_number }} of your Form 19 · {{ $entry->entry_date->format('l, M d, Y') }}</p>
        </div>
        <a href="{{ route('student.documents.log', $requirement) }}" class="il-btn-secondary text-decoration-none">
            <i class="ti ti-arrow-left"></i> Back to Weekly Report
        </a>
    </div>

    <div class="il-card">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('student.documents.log.entry.update', [$requirement, $entry->id]) }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="il-form-label">Training Date</label>
                <input type="text" class="form-control" value="{{ $entry->entry_date->format('M d, Y') }}" disabled>
                <div class="form-text">The date can't be changed. For a different day, delete this one and add the right day.</div>
            </div>

            <div class="mb-3">
                <label class="il-form-label" for="tasks_accomplished">Tasks Accomplished</label>
                <textarea class="form-control" id="tasks_accomplished" name="tasks_accomplished" rows="7" required>{{ old('tasks_accomplished', $entry->value('tasks_accomplished')) }}</textarea>
            </div>

            <div class="mb-3">
                <label class="il-form-label" for="knowledge_skills_learned">Knowledge, Skills, Values Learned</label>
                <textarea class="form-control" id="knowledge_skills_learned" name="knowledge_skills_learned" rows="7" required>{{ old('knowledge_skills_learned', $entry->value('knowledge_skills_learned')) }}</textarea>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="il-btn-primary"><i class="ti ti-device-floppy"></i> Save Changes</button>
                <a href="{{ route('student.documents.log', $requirement) }}" class="il-btn-secondary text-decoration-none">Cancel</a>
            </div>
        </form>
    </div>
@endsection
