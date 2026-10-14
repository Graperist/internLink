@extends('layouts.student')

@section('title', 'Certificate of Units Earned for Practicum - PSU Intern-Link')

@section('content')
<div class="il-page-header">
    <h1 class="il-page-title">Certificate of Units Earned for Practicum</h1>
    <a href="{{ route('student.documents') }}" class="il-btn-secondary text-decoration-none">
        <i class="ti ti-arrow-left"></i> Back to My Documents
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

    @if ($document && $document->status === 'returned' && $document->latestReview?->remarks)
        <div class="alert alert-danger">
            <strong>Returned for correction:</strong> {{ $document->latestReview->remarks }}
        </div>
    @endif

    @php
        /*
         * A brand-new document has no Document model yet, so $document is null.
         * Always get the student from the authenticated account, and use $document
         * only for previously saved field values.
         */
        $student = auth()->user()->student;
        $internship = $student?->currentInternship;
        $programName = $student?->program?->name ?? '';

        $existing = function (string $key) use ($document) {
            if ($document) {
                return old($key, $document->fieldValue($key) ?? '');
            }

            return old($key, '');
        };

        $decodeRows = function (string $value, int $count, array $defaults) {
            $decoded = json_decode($value, true);

            if (!is_array($decoded)) {
                $decoded = [];
            }

            $rows = [];

            for ($i = 0; $i < $count; $i++) {
                $row = is_array($decoded[$i] ?? null) ? $decoded[$i] : [];
                $rows[] = array_merge($defaults, $row);
            }

            return $rows;
        };

        $emptyCourseRow = [
            'semester1' => '',
            'units1' => '',
            'semester2' => '',
            'units2' => '',
            'summer' => '',
            'summer_units' => '',
            'total' => '',
        ];

        $firstYearValue = $existing('first_year_courses');
        $secondYearValue = $existing('second_year_courses');
        $thirdYearValue = $existing('third_year_courses');
        $fourthYearValue = $existing('fourth_year_courses');

        $firstYearDecoded = json_decode($firstYearValue, true);
        $secondYearDecoded = json_decode($secondYearValue, true);
        $thirdYearDecoded = json_decode($thirdYearValue, true);
        $fourthYearDecoded = json_decode($fourthYearValue, true);

        $firstYearCount = max(7, is_array($firstYearDecoded) ? count($firstYearDecoded) : 0);
        $secondYearCount = max(7, is_array($secondYearDecoded) ? count($secondYearDecoded) : 0);
        $thirdYearCount = max(7, is_array($thirdYearDecoded) ? count($thirdYearDecoded) : 0);
        $fourthYearCount = max(7, is_array($fourthYearDecoded) ? count($fourthYearDecoded) : 0);

        $firstYearRows = $decodeRows($firstYearValue, $firstYearCount, $emptyCourseRow);
        $secondYearRows = $decodeRows($secondYearValue, $secondYearCount, $emptyCourseRow);
        $thirdYearRows = $decodeRows($thirdYearValue, $thirdYearCount, $emptyCourseRow);
        $fourthYearRows = $decodeRows($fourthYearValue, $fourthYearCount, $emptyCourseRow);

        $emptyFailedRow = [
            'code' => '',
            'title' => '',
            'units' => '',
        ];

        $failedRows = $decodeRows($existing('failed_courses'), 6, $emptyFailedRow);

        $fullName = trim($student?->user?->name ?? '');
        $nameParts = preg_split('/\s+/', $fullName, -1, PREG_SPLIT_NO_EMPTY);

        $surname = $existing('surname');
        $givenName = $existing('given_name');
        $middleName = $existing('middle_name');

        if ($surname === '' && count($nameParts) >= 2) {
            $surname = array_pop($nameParts);
            $givenName = $givenName !== '' ? $givenName : ($nameParts[0] ?? '');
            $remaining = array_slice($nameParts, 1);
            $middleName = $middleName !== '' ? $middleName : implode(' ', $remaining);
        }

        $course = $existing('course') ?: $programName;
        $major = $existing('major');
        $recordTotal = $existing('record_total');
    @endphp

    <p class="text-secondary small">
        Enter your academic record exactly as reflected in your school records. The form follows the official
        Certificate of Units Earned for Practicum/Internship template. The certification and Registrar signature
        are completed during document processing.
    </p>

    <form method="POST"
          action="{{ route('student.documents.fill.store', $requirement) }}"
          id="certificateUnitsForm">
        @csrf

        <h2 class="h6 fw-bold mt-4 mb-3">Student Information</h2>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="il-form-label" for="surname">Last Name</label>
                <input type="text" class="form-control" id="surname" name="surname" value="{{ $surname }}" required>
            </div>

            <div class="col-md-4">
                <label class="il-form-label" for="given_name">First Name</label>
                <input type="text" class="form-control" id="given_name" name="given_name" value="{{ $givenName }}" required>
            </div>

            <div class="col-md-4">
                <label class="il-form-label" for="middle_name">Middle Name</label>
                <input type="text" class="form-control" id="middle_name" name="middle_name" value="{{ $middleName }}">
            </div>

            <div class="col-md-6">
                <label class="il-form-label" for="course">Course</label>
                <input type="text" class="form-control" id="course" name="course" value="{{ $course }}" required>
            </div>

            <div class="col-md-6">
                <label class="il-form-label" for="major">Major</label>
                <input type="text" class="form-control" id="major" name="major" value="{{ $major }}">
            </div>
        </div>

        @php
            $yearSections = [
                ['key' => 'first_year', 'label' => 'FIRST YEAR', 'rows' => $firstYearRows],
                ['key' => 'second_year', 'label' => 'SECOND YEAR', 'rows' => $secondYearRows],
                ['key' => 'third_year', 'label' => 'THIRD YEAR', 'rows' => $thirdYearRows],
                ['key' => 'fourth_year', 'label' => 'FOURTH YEAR', 'rows' => $fourthYearRows],
            ];
        @endphp

        @foreach ($yearSections as $section)
            <div class="mt-4">
                <h2 class="h6 fw-bold mb-2">Courses Earned — {{ $section['label'] }}</h2>

                <div class="table-responsive border rounded">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th rowspan="2" style="width:19%;">YEAR LEVEL</th>
                                <th colspan="2" class="text-center">1<sup>st</sup> SEMESTER</th>
                                <th colspan="2" class="text-center">2<sup>nd</sup> SEMESTER</th>
                                <th colspan="2" class="text-center">SUMMER</th>
                                <th rowspan="2" style="width:9%;">TOTAL</th>
                            </tr>
                            <tr>
                                <th>COURSE</th>
                                <th>UNITS</th>
                                <th>COURSE</th>
                                <th>UNITS</th>
                                <th>COURSE</th>
                                <th>UNITS</th>
                            </tr>
                        </thead>

                        <tbody data-year-body="{{ $section['key'] }}">
                            @foreach ($section['rows'] as $rowIndex => $row)
                                <tr>
                                    @if ($rowIndex === 0)
                                        <td rowspan="{{ count($section['rows']) }}" class="fw-semibold text-center align-middle year-label-cell">
                                            {{ $section['label'] }}
                                        </td>
                                    @endif

                                    <td>
                                        <input
                                            type="text"
                                            class="form-control form-control-sm"
                                            data-year="{{ $section['key'] }}"
                                            data-row="{{ $rowIndex }}"
                                            data-field="semester1"
                                            value="{{ $row['semester1'] }}"
                                        >
                                    </td>

                                    <td>
                                        <input
                                            type="text"
                                            class="form-control form-control-sm"
                                            data-year="{{ $section['key'] }}"
                                            data-row="{{ $rowIndex }}"
                                            data-field="units1"
                                            value="{{ $row['units1'] }}"
                                        >
                                    </td>

                                    <td>
                                        <input
                                            type="text"
                                            class="form-control form-control-sm"
                                            data-year="{{ $section['key'] }}"
                                            data-row="{{ $rowIndex }}"
                                            data-field="semester2"
                                            value="{{ $row['semester2'] }}"
                                        >
                                    </td>

                                    <td>
                                        <input
                                            type="text"
                                            class="form-control form-control-sm"
                                            data-year="{{ $section['key'] }}"
                                            data-row="{{ $rowIndex }}"
                                            data-field="units2"
                                            value="{{ $row['units2'] }}"
                                        >
                                    </td>

                                    <td>
                                        <input
                                            type="text"
                                            class="form-control form-control-sm"
                                            data-year="{{ $section['key'] }}"
                                            data-row="{{ $rowIndex }}"
                                            data-field="summer"
                                            value="{{ $row['summer'] }}"
                                        >
                                    </td>

                                    <td>
                                        <input
                                            type="text"
                                            class="form-control form-control-sm"
                                            data-year="{{ $section['key'] }}"
                                            data-row="{{ $rowIndex }}"
                                            data-field="summer_units"
                                            value="{{ $row['summer_units'] }}"
                                        >
                                    </td>

                                    <td>
                                        <input
                                            type="text"
                                            class="form-control form-control-sm"
                                            data-year="{{ $section['key'] }}"
                                            data-row="{{ $rowIndex }}"
                                            data-field="total"
                                            value="{{ $row['total'] }}"
                                        >
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="button"
                        class="btn btn-outline-primary btn-sm mt-2 add-course-row"
                        data-year="{{ $section['key'] }}">
                    <i class="ti ti-plus"></i> Add another course
                </button>

                <input type="hidden" name="{{ $section['key'] }}_courses" id="{{ $section['key'] }}_courses">
            </div>
        @endforeach

        <div class="mt-4">
            <h2 class="h6 fw-bold mb-2">TOTAL</h2>
            <input
                type="text"
                class="form-control"
                name="record_total"
                value="{{ $recordTotal }}"
            >
        </div>

        <div class="mt-4">
            <h2 class="h6 fw-bold mb-2">Courses Failed to Take as of the Present</h2>

            <div class="table-responsive border rounded">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:25%;">COURSE CODE</th>
                            <th>COURSE TITLE</th>
                            <th style="width:20%;">UNITS</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($failedRows as $rowIndex => $row)
                            <tr>
                                <td>
                                    <input
                                        type="text"
                                        class="form-control form-control-sm"
                                        data-failed-row="{{ $rowIndex }}"
                                        data-field="code"
                                        value="{{ $row['code'] }}"
                                    >
                                </td>

                                <td>
                                    <input
                                        type="text"
                                        class="form-control form-control-sm"
                                        data-failed-row="{{ $rowIndex }}"
                                        data-field="title"
                                        value="{{ $row['title'] }}"
                                    >
                                </td>

                                <td>
                                    <input
                                        type="text"
                                        class="form-control form-control-sm"
                                        data-failed-row="{{ $rowIndex }}"
                                        data-field="units"
                                        value="{{ $row['units'] }}"
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <input type="hidden" name="failed_courses" id="failed_courses">
        </div>

        <div class="alert alert-secondary small mt-4">
            <strong>Certification:</strong> The official form's “CERTIFIED TRUE AND CORRECT” section is reserved
            for the Campus Registrar and is not entered by the student.
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="il-btn-primary">Submit for review</button>
            <a href="{{ route('student.documents') }}" class="il-btn-secondary text-decoration-none">Cancel</a>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('certificateUnitsForm');
        const yearKeys = ['first_year', 'second_year', 'third_year', 'fourth_year'];

        function courseCell(field, value, yearKey, rowIndex) {
            const td = document.createElement('td');
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control form-control-sm';
            input.dataset.year = yearKey;
            input.dataset.row = rowIndex;
            input.dataset.field = field;
            input.value = value || '';
            td.appendChild(input);
            return td;
        }

        function updateYearLabelRowspan(yearKey) {
            const tbody = document.querySelector('[data-year-body="' + yearKey + '"]');
            if (!tbody) return;

            const labelCell = tbody.querySelector('.year-label-cell');
            if (labelCell) {
                labelCell.rowSpan = tbody.querySelectorAll('tr').length;
            }
        }

        function addCourseRow(yearKey) {
            const tbody = document.querySelector('[data-year-body="' + yearKey + '"]');
            if (!tbody) return;

            const rows = tbody.querySelectorAll('tr');
            const rowIndex = rows.length;
            const tr = document.createElement('tr');

            tr.appendChild(courseCell('semester1', '', yearKey, rowIndex));
            tr.appendChild(courseCell('units1', '', yearKey, rowIndex));
            tr.appendChild(courseCell('semester2', '', yearKey, rowIndex));
            tr.appendChild(courseCell('units2', '', yearKey, rowIndex));
            tr.appendChild(courseCell('summer', '', yearKey, rowIndex));
            tr.appendChild(courseCell('summer_units', '', yearKey, rowIndex));
            tr.appendChild(courseCell('total', '', yearKey, rowIndex));

            tbody.appendChild(tr);
            updateYearLabelRowspan(yearKey);
        }

        document.querySelectorAll('.add-course-row').forEach(function (button) {
            button.addEventListener('click', function () {
                addCourseRow(button.dataset.year);
            });
        });

        function serializeYear(yearKey) {
            const rows = [];
            const inputs = document.querySelectorAll('[data-year="' + yearKey + '"]');
            const rowIndexes = {};

            inputs.forEach(function (input) {
                const index = input.dataset.row;
                if (!rowIndexes[index]) {
                    rowIndexes[index] = {};
                }
                rowIndexes[index][input.dataset.field] = input.value.trim();
            });

            Object.keys(rowIndexes)
                .sort(function (a, b) { return Number(a) - Number(b); })
                .forEach(function (index) {
                    rows.push(rowIndexes[index]);
                });

            document.getElementById(yearKey + '_courses').value = JSON.stringify(rows);
        }

        function serializeFailedCourses() {
            const failedRows = [];
            const failedIndexes = {};

            document.querySelectorAll('[data-failed-row]').forEach(function (input) {
                const index = input.dataset.failedRow;
                if (!failedIndexes[index]) {
                    failedIndexes[index] = {};
                }
                failedIndexes[index][input.dataset.field] = input.value.trim();
            });

            Object.keys(failedIndexes)
                .sort(function (a, b) { return Number(a) - Number(b); })
                .forEach(function (index) {
                    failedRows.push(failedIndexes[index]);
                });

            document.getElementById('failed_courses').value = JSON.stringify(failedRows);
        }

        form.addEventListener('submit', function () {
            yearKeys.forEach(serializeYear);
            serializeFailedCourses();
        });

        yearKeys.forEach(updateYearLabelRowspan);
    });
</script>
@endsection
