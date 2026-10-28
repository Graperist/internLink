{{--
    Form 17 – Internship Time Frame card, shared by the Time Frame (DTR) page
    and My Documents: the printable form previewed one sheet at a time, and
    View / Download that ask for the whole time frame, a sheet or a week.
    Needs $days (the student's Dtr rows) and $internship.
--}}
            <div class="il-card">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <h2 class="h6 fw-bold mb-1">Form 17 – Internship Time Frame</h2>
                        <p class="small text-secondary mb-0">This is how your printed Form 17 looks. It fills in as you add days &mdash; there is nothing to submit.</p>
                    </div>
                    @if ($days->isNotEmpty())
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#tfOutputModal" data-tf-mode="view"><i class="ti ti-eye"></i> View</button>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#tfOutputModal" data-tf-mode="download"><i class="ti ti-download"></i> Download Form 17</button>
                        </div>
                    @endif
                </div>

                @include('partials.form17-sheets', ['days' => $days, 'previewUrl' => fn ($sheet) => route('student.dtr.form', array_filter(['preview' => 1, 'sheet' => $sheet]))])

                @if ($days->isNotEmpty())
                    @php
                        $tfSheets = \App\Support\TimeFrameForm::sheetList($days);
                        $tfWeeks = \App\Support\TrainingWeeks::list($days->pluck('log_date'), \App\Support\TrainingWeeks::anchorForInternship($internship));
                    @endphp
                    {{-- Print / Download ask which part of Form 17: the whole form, one sheet or one week. --}}
                    <div class="modal fade" id="tfOutputModal" tabindex="-1" aria-labelledby="tfOutputTitle" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <form class="modal-content" method="GET" action="{{ route('student.dtr.form') }}" target="_blank" id="tfOutputForm">
                                <input type="hidden" name="download" value="1" id="tfOutputDownload">
                                <input type="hidden" name="view" value="1" id="tfOutputView" disabled>
                                <div class="modal-header">
                                    <h2 class="modal-title h6 fw-bold" id="tfOutputTitle">Download Form 17</h2>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <label for="tfOutputPeriod" class="il-form-label">Which part of your time frame?</label>
                                    <select name="period" id="tfOutputPeriod" class="form-select">
                                        <option value="">Whole time frame &ndash; all {{ $days->count() }} {{ \Illuminate\Support\Str::plural('day', $days->count()) }}{{ count($tfSheets) > 1 ? ' ('.count($tfSheets).' sheets)' : '' }}</option>
                                        @if (count($tfSheets) > 1)
                                            <optgroup label="One sheet ({{ \App\Support\TimeFrameForm::ROWS }} days each)">
                                                @foreach ($tfSheets as $sheet)
                                                    <option value="sheet:{{ $sheet['number'] }}">Sheet {{ $sheet['number'] }} &ndash; {{ $sheet['first']->format('M d') }} to {{ $sheet['last']->format('M d, Y') }} ({{ $sheet['count'] }} {{ \Illuminate\Support\Str::plural('day', $sheet['count']) }})</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                        <optgroup label="One week">
                                            @foreach ($tfWeeks as $week)
                                                <option value="week:{{ $week['start']->toDateString() }}">Week {{ $week['number'] }} &ndash; {{ $week['start']->format('M d') }} to {{ $week['end']->format('M d, Y') }} ({{ $week['count'] }} {{ \Illuminate\Support\Str::plural('day', $week['count']) }})</option>
                                            @endforeach
                                        </optgroup>
                                    </select>
                                    @if (count($tfSheets) > 1)
                                        <div class="small text-secondary mt-2">
                                            <i class="ti ti-info-circle"></i> Form 17 has {{ \App\Support\TimeFrameForm::ROWS }} rows, so your days continue on
                                            {{ count($tfSheets) }} sheets. Each sheet has its own total and signatures.
                                        </div>
                                    @endif
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary" id="tfOutputSubmit"><i class="ti ti-download"></i> Download</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <script>
                        // The same dialog views or downloads, depending on the button that opened it.
                        (function () {
                            var modal = document.getElementById('tfOutputModal');
                            if (!modal) return;
                            var title = document.getElementById('tfOutputTitle'), submit = document.getElementById('tfOutputSubmit'),
                                download = document.getElementById('tfOutputDownload'), view = document.getElementById('tfOutputView'), form = document.getElementById('tfOutputForm');
                            modal.addEventListener('show.bs.modal', function (e) {
                                var viewing = e.relatedTarget && e.relatedTarget.dataset.tfMode === 'view';
                                title.textContent = viewing ? 'View Form 17' : 'Download Form 17';
                                submit.innerHTML = viewing ? '<i class="ti ti-eye"></i> View' : '<i class="ti ti-download"></i> Download';
                                download.disabled = viewing;   // viewing opens the form in a new tab
                                view.disabled = !viewing;
                            });
                            form.addEventListener('submit', function () {
                                setTimeout(function () { bootstrap.Modal.getInstance(modal)?.hide(); }, 0);
                            });
                        })();
                    </script>
                @endif
            </div>
