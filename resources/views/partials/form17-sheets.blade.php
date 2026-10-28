{{--
    Form 17 preview: the printable Time Frame shown one A4 sheet at a time
    (arrows and dots when there is more than one sheet), opening on the latest.
    Used by the student's Time Frame / My Documents and the instructor's
    Time Frame monitoring.
    $days: the intern's Dtr rows; $previewUrl: fn (?int $sheet) => preview link.
--}}
                @php
                    // One sheet at a time; it opens on the latest sheet (the one being filled).
                    $previewSheets = max(1, count(\App\Support\TimeFrameForm::sheetList($days)));
                @endphp
                <div class="tf-carousel" id="tfCarousel" data-count="{{ $previewSheets }}">
                    @if ($previewSheets > 1)
                        <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary px-2" data-tf-prev title="Previous sheet" aria-label="Previous sheet"><i class="ti ti-chevron-left"></i></button>
                            <span class="small fw-semibold" style="min-width: 7rem; text-align: center;" data-tf-label>Sheet {{ $previewSheets }} of {{ $previewSheets }}</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary px-2" data-tf-next title="Next sheet" aria-label="Next sheet"><i class="ti ti-chevron-right"></i></button>
                        </div>
                    @endif
                    <div class="tf-preview" id="tfPreview">
                        @for ($n = 1; $n <= $previewSheets; $n++)
                            <iframe data-src="{{ $previewUrl($previewSheets > 1 ? $n : null) }}"
                                    title="Form 17 preview, sheet {{ $n }}" scrolling="no" tabindex="-1" @if ($n !== $previewSheets) hidden @endif></iframe>
                        @endfor
                    </div>
                    @if ($previewSheets > 1)
                        <div class="d-flex justify-content-center gap-1 mt-2" data-tf-dots>
                            @for ($n = 1; $n <= $previewSheets; $n++)
                                <button type="button" class="tf-dot {{ $n === $previewSheets ? 'is-active' : '' }}" data-tf-go="{{ $n }}" aria-label="Sheet {{ $n }}"></button>
                            @endfor
                        </div>
                    @endif
                </div>
    <style>
        .tf-preview { position: relative; overflow: hidden; background: #e9edf2; border-radius: 8px; padding: 12px; }
        .tf-preview iframe { display: block; width: 794px; height: 1123px; border: 0; background: #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,.15); transform-origin: top left; }
        .tf-preview iframe[hidden] { display: none; }
        .tf-dot { width: 8px; height: 8px; border-radius: 50%; border: 0; padding: 0; background: #c3ccd6; }
        .tf-dot.is-active { background: #1c3f66; width: 20px; border-radius: 4px; }
    </style>
    <script>
        // Form 17 preview: one A4 sheet at a time, fitted to the card width.
        (function () {
            var root = document.getElementById('tfCarousel');
            if (!root) return;
            var box = document.getElementById('tfPreview');
            var frames = box.querySelectorAll('iframe');
            var count = frames.length, current = count;
            var label = root.querySelector('[data-tf-label]');
            var dots = root.querySelectorAll('[data-tf-go]');
            var prev = root.querySelector('[data-tf-prev]'), next = root.querySelector('[data-tf-next]');

            function fit() {
                var frame = frames[current - 1];
                var scale = Math.min(1, (box.clientWidth - 24) / 794);
                var h = 1123;
                try { h = Math.max(1123, frame.contentDocument.documentElement.scrollHeight); } catch (e) {}
                frame.style.height = h + 'px';
                frame.style.transform = 'scale(' + scale + ')';
                box.style.height = (h * scale + 24) + 'px';
            }
            function show(n) {
                current = Math.min(count, Math.max(1, n));
                frames.forEach(function (f, i) {
                    var on = i === current - 1;
                    f.hidden = !on;
                    if (on && !f.getAttribute('src')) f.setAttribute('src', f.dataset.src);   // load when first shown
                });
                dots.forEach(function (d) { d.classList.toggle('is-active', +d.dataset.tfGo === current); });
                if (label) label.textContent = 'Sheet ' + current + ' of ' + count;
                if (prev) prev.disabled = current === 1;
                if (next) next.disabled = current === count;
                fit();
            }

            frames.forEach(function (f) { f.addEventListener('load', fit); });
            if (prev) prev.addEventListener('click', function () { show(current - 1); });
            if (next) next.addEventListener('click', function () { show(current + 1); });
            dots.forEach(function (d) { d.addEventListener('click', function () { show(+d.dataset.tfGo); }); });
            window.addEventListener('resize', fit);
            show(current);
        })();
    </script>
