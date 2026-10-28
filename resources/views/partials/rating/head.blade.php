{{--
    Evaluation forms: rating-scale look shared by every portal (styles, the
    scale legend and the sticky "answered" counter).
    $legend: ['5' => 'Extremely Satisfied', ...]; $count: number of rating questions.
--}}
@once
        <style>
            .rate-legend { display: flex; flex-wrap: wrap; gap: .4rem; }
            .rate-legend span { font-size: .76rem; padding: .2rem .6rem; border-radius: 999px; background: #eef2f8; color: #1c3f66; font-weight: 600; }
            .rate-progress { position: sticky; top: 0; z-index: 5; background: rgba(255,255,255,.96); backdrop-filter: blur(4px);
                border: 1px solid #e6eaf0; border-radius: 12px; padding: .6rem .9rem; margin-bottom: 1rem; }
            .rate-progress .progress { height: 6px; }
            .rate-group { display: flex; align-items: center; gap: .6rem; margin: 1.8rem 0 .8rem; }
            .rate-group h2 { margin: 0; font-size: 1rem; font-weight: 800; color: #1c3f66; }
            .rate-group .rate-group-line { flex: 1; height: 2px; background: linear-gradient(90deg, #f5b800, rgba(245,184,0,0)); border-radius: 2px; }
            .rate-q { border: 1px solid #e6eaf0; border-radius: 12px; padding: .9rem 1rem 1rem; background: #fff; transition: border-color .15s, box-shadow .15s; }
            .rate-q.is-answered { border-color: #cfe0f5; }
            .rate-q.is-missing { border-color: #e5a3a3; box-shadow: 0 0 0 3px rgba(214,69,69,.08); }
            .rate-q-text { display: flex; gap: .6rem; font-weight: 600; font-size: .92rem; margin-bottom: .75rem; color: #14213a; }
            .rate-q-no { flex: none; width: 26px; height: 26px; border-radius: 50%; background: #1c3f66; color: #fff; font-size: .75rem; display: grid; place-items: center; }
            .rate-q.is-answered .rate-q-no { background: #1f8a4c; }
            .rate-opts { display: grid; grid-template-columns: repeat(5, 1fr); gap: .45rem; }
            .rate-opt { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .1rem; min-height: 62px;
                padding: .45rem .3rem; border: 1.5px solid #d8dfea; border-radius: 10px; background: #fafbfd; cursor: pointer; text-align: center;
                transition: border-color .12s, background .12s, transform .12s; user-select: none; }
            .rate-opt:hover { border-color: #1c3f66; background: #f2f6fc; }
            .rate-opt .rate-num { font-size: 1.15rem; font-weight: 800; color: #1c3f66; line-height: 1; }
            .rate-opt .rate-txt { font-size: .68rem; color: #5d6b80; line-height: 1.15; }
            .btn-check:checked + .rate-opt { background: #1c3f66; border-color: #1c3f66; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(28,63,102,.2); }
            .btn-check:checked + .rate-opt .rate-num { color: #f5b800; }
            .btn-check:checked + .rate-opt .rate-txt { color: #fff; }
            .btn-check:focus-visible + .rate-opt { outline: 3px solid rgba(28,63,102,.35); outline-offset: 2px; }
            .rate-rows { display: grid; gap: .4rem; }
            .rate-row { display: flex; align-items: flex-start; gap: .7rem; padding: .6rem .75rem; border: 1.5px solid #d8dfea; border-radius: 10px;
                background: #fafbfd; cursor: pointer; font-size: .86rem; line-height: 1.35; transition: border-color .12s, background .12s; }
            .rate-row:hover { border-color: #1c3f66; background: #f2f6fc; }
            .rate-row .rate-num { flex: none; width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center;
                font-weight: 800; color: #1c3f66; background: #e8eef7; }
            .btn-check:checked + .rate-row { background: #1c3f66; border-color: #1c3f66; color: #fff; box-shadow: 0 4px 10px rgba(28,63,102,.2); }
            .btn-check:checked + .rate-row .rate-num { background: #f5b800; color: #1c3f66; }
            .btn-check:focus-visible + .rate-row { outline: 3px solid rgba(28,63,102,.35); outline-offset: 2px; }
            .rate-q-meta { font-weight: 500; color: #5d6b80; }
            .rate-remarks { margin-top: .8rem; padding-top: .75rem; border-top: 1px dashed #dde3ec; }
            .rate-remarks-label { display: flex; align-items: center; gap: .35rem; font-size: .8rem; font-weight: 700; color: #1c3f66; margin-bottom: .35rem; }
            .rate-remarks-label span { font-weight: 500; color: #8592a6; }
            .rate-remarks textarea { font-size: .88rem; border-radius: 10px; background: #fafbfd; resize: vertical; }
            .rate-section { border: 1px solid #e6eaf0; border-radius: 12px; padding: 1rem 1.1rem .4rem; background: #fff; }
            .rate-section .form-control, .rate-section .form-select { border-radius: 10px; }
            .rate-section textarea.form-control { background: #fafbfd; }
            .rate-checks { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: .45rem; margin-bottom: 1rem; }
            .rate-check { display: flex; align-items: center; gap: .6rem; padding: .6rem .75rem; border: 1.5px solid #d8dfea; border-radius: 10px;
                background: #fafbfd; cursor: pointer; font-size: .86rem; line-height: 1.3; transition: border-color .12s, background .12s; }
            .rate-check:hover { border-color: #1c3f66; background: #f2f6fc; }
            .rate-check .rate-tick { flex: none; width: 20px; height: 20px; border-radius: 6px; border: 1.5px solid #b9c3d2; display: grid; place-items: center; background: #fff; color: transparent; font-size: .8rem; }
            .btn-check:checked + .rate-check { border-color: #1c3f66; background: #eef3fa; font-weight: 600; }
            .btn-check:checked + .rate-check .rate-tick { background: #1c3f66; border-color: #1c3f66; color: #f5b800; }
            .btn-check:focus-visible + .rate-check { outline: 3px solid rgba(28,63,102,.35); outline-offset: 2px; }
            @media (max-width: 991.98px) { .rate-progress { top: 60px; } }
            @media (max-width: 575.98px) {
                .rate-opts { grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .3rem; }
                .rate-opt { min-height: 52px; }
                .rate-opt .rate-txt { font-size: .58rem; }
            }
        </style>
@endonce
        @if (collect($legend)->contains(fn ($text, $value) => (string) $text !== (string) $value))
        <div class="il-card mb-3">
            <div class="small fw-bold text-uppercase text-secondary mb-2" style="letter-spacing:.03em;">Rating scale</div>
            <div class="rate-legend">
                @foreach ($legend as $value => $text)
                    <span>{{ $value }}{{ (string) $value !== (string) $text ? ' · '.$text : '' }}</span>
                @endforeach
            </div>
        </div>
        @endif
        <div class="rate-progress" id="rateProgress">
            <div class="d-flex justify-content-between small mb-1">
                <span class="fw-semibold">Your answers</span>
                <span class="text-secondary"><span id="rateDone">0</span> of {{ $count }} answered</span>
            </div>
            <div class="progress"><div class="progress-bar bg-success" id="rateBar" style="width: 0%"></div></div>
        </div>
