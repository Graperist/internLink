{{--
    Document Review: the official printable form with what the student filled
    in, shown as an A4 page fitted to the card (view only).
--}}
<div class="il-print-preview mb-2">
    <iframe src="{{ route('instructor.documents.printable', ['document' => $document, 'preview' => 1]) }}"
            title="{{ $document->requirement->name }} preview" scrolling="no" tabindex="-1" loading="lazy"></iframe>
</div>

@once
    <style>
        .il-print-preview { position: relative; overflow: hidden; background: #e9edf2; border-radius: 8px; padding: 12px; height: 400px; }
        .il-print-preview iframe { display: block; width: 794px; height: 1123px; border: 0; background: #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,.15); transform-origin: top left; }
    </style>
    <script>
        // Fits each A4 preview to its card width and to the form's full height.
        (function () {
            function fit(box) {
                var frame = box.querySelector('iframe');
                if (!frame || !box.clientWidth) return;
                var scale = Math.min(1, (box.clientWidth - 24) / 794);
                var h = 1123;
                try { h = Math.max(1123, frame.contentDocument.documentElement.scrollHeight); } catch (e) {}
                frame.style.height = h + 'px';
                frame.style.transform = 'scale(' + scale + ')';
                box.style.height = (h * scale + 24) + 'px';
            }
            function fitAll() { document.querySelectorAll('.il-print-preview').forEach(fit); }
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.il-print-preview iframe').forEach(function (f) {
                    f.addEventListener('load', function () { fit(f.parentElement); });
                });
                fitAll();
            });
            window.addEventListener('resize', fitAll);
            // Previews inside collapsed sections are measured when they open.
            document.addEventListener('shown.bs.collapse', fitAll);
        })();
    </script>
@endonce
