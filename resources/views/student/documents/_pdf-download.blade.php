{{--
    Appended to a form's official printable page when the student downloads
    it: turns the page into an A4 PDF in the browser and saves it, so the
    file matches the printout exactly. The PDF margins and the page width
    come from the template's own @page rule, as when printing.
--}}
<div id="pdfStatus" class="no-print" data-html2canvas-ignore="true"
     style="position:fixed;top:12px;left:50%;transform:translateX(-50%);z-index:9999;background:#1c3f66;color:#fff;font:600 13px Arial,sans-serif;padding:10px 18px;border-radius:8px;box-shadow:0 4px 14px rgba(0,0,0,.2);max-width:90vw;text-align:center;">
    Preparing your PDF&hellip;
</div>
<script src="{{ asset('vendor/html2pdf/html2pdf.bundle.min.js') }}"></script>
<script>
window.addEventListener('load', function () {
    var status = document.getElementById('pdfStatus');
    var filename = @json($filename);
    var A4_WIDTH = 210;

    // Converts a CSS length ("14mm", "0.5in", "12px") to millimetres.
    function toMm(value) {
        if (!value) return 0;
        var probe = document.createElement('div');
        probe.style.cssText = 'position:absolute;visibility:hidden;width:' + value;
        document.body.appendChild(probe);
        var px = probe.getBoundingClientRect().width;
        probe.remove();
        return px * 25.4 / 96;
    }

    // The template's @page margins (top, right, bottom, left), as when printing.
    function pageMargins() {
        var m = { top: 10, right: 10, bottom: 10, left: 10 };
        Array.prototype.forEach.call(document.styleSheets, function (sheet) {
            var rules;
            try { rules = sheet.cssRules; } catch (e) { return; }
            Array.prototype.forEach.call(rules || [], function (rule) {
                if (rule.type === 6 /* CSSRule.PAGE_RULE */ && rule.style) {
                    if (rule.style.marginTop) m.top = toMm(rule.style.marginTop);
                    if (rule.style.marginRight) m.right = toMm(rule.style.marginRight);
                    if (rule.style.marginBottom) m.bottom = toMm(rule.style.marginBottom);
                    if (rule.style.marginLeft) m.left = toMm(rule.style.marginLeft);
                }
            });
        });
        return m;
    }

    var m = pageMargins();
    var contentWidth = A4_WIDTH - m.left - m.right;

    // Lay the page out at the printable width, pinned to the left edge
    // (html2canvas mis-measures a centred page). The PDF adds the margins.
    document.documentElement.style.margin = '0';
    document.body.style.margin = '0';
    document.body.style.padding = '0';
    document.body.style.maxWidth = 'none';
    document.body.style.width = contentWidth + 'mm';
    var widthPx = Math.round(contentWidth * 96 / 25.4);

    html2pdf().set({
        margin: [m.top, m.left, m.bottom, m.right],
        filename: filename,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff', windowWidth: widthPx, scrollX: 0, scrollY: 0, x: 0, y: 0 },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak: { mode: ['css', 'legacy'], avoid: ['tr', 'li', '.sig', '.sign', '.signature', '.approval-grid'] }
    }).from(document.body).outputPdf('blob').then(function (blob) {
        // Saved through a named link so the file keeps the form's name.
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.setAttribute('data-html2canvas-ignore', 'true');
        document.body.appendChild(link);
        link.click();
        setTimeout(function () { URL.revokeObjectURL(link.href); link.remove(); }, 10000);
        status.textContent = 'Downloaded "' + filename + '". You can close this tab.';
        status.style.background = '#1f7a3f';
    }).catch(function () {
        status.textContent = 'Could not create the PDF. Use your browser\'s Print > Save as PDF instead.';
        status.style.background = '#b42318';
    });
});
</script>
