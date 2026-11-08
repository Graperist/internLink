{{--
    1st or 2nd Semester picker.

    @include('partials.semester-select', [
        'name' => 'semester', 'id' => 'f_sem', 'value' => '2nd Semester',
        'small' => false, 'autoSubmit' => false, 'allowAll' => false, 'disabled' => false,
    ])
--}}
<select name="{{ $name ?? 'semester' }}" id="{{ $id ?? 'semester' }}"
        class="form-select {{ ($small ?? false) ? 'form-select-sm' : '' }} @error($name ?? 'semester') is-invalid @enderror"
        @if ($autoSubmit ?? false) onchange="this.form.submit()" @endif
        {{ ($disabled ?? false) ? 'disabled' : '' }} aria-label="Semester">
    @if ($allowAll ?? false)
        <option value="{{ $allValue ?? '' }}" @selected(($value ?? '') === '' || ($value ?? '') === 'all')>All semesters</option>
    @endif
    @foreach (\App\Support\Term::SEMESTERS as $sem)
        <option value="{{ $sem }}" @selected(($value ?? '') === $sem)>{{ $sem }}</option>
    @endforeach
</select>
@error($name ?? 'semester')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
