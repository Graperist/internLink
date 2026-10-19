{{--
    Every printable form is A4: included in each printable page's <head>.
    - Printing always uses A4 portrait (the form's own @page margins stay).
    - On screen the form is shown as an A4 sheet (210 x 297 mm) with those
      same margins, so what you see is what prints.
    - A form wider than the A4 printable area is scaled down to fit.
    Not used while a PDF is being downloaded (?download=1): that script lays
    the page out on A4 itself.
--}}
<style>
    @page { size: A4 portrait; }
    @media print {
        html, body { width: auto; }
    }
</style>
@unless (request()->boolean('download'))
<style>
    @media screen {
        html.a4-sheet { background: #e9ecef; }
        html.a4-sheet body {
            box-sizing: border-box;
            width: 210mm;
            min-height: 297mm;
            max-width: none;
            margin: 18px auto;
            padding: var(--a4-top, 12mm) var(--a4-right, 12mm) var(--a4-bottom, 12mm) var(--a4-left, 12mm);
            background: #fff;
            box-shadow: 0 2px 14px rgba(0, 0, 0, .18);
        }
    }
    @media screen and (max-width: 220mm) {
        html.a4-sheet body { margin: 0 auto; box-shadow: none; }
    }
</style>
<script>
(function () {
    var root = document.documentElement;
    root.classList.add('a4-sheet');

    function toMm(value) {
        var probe = document.createElement('div');
        probe.style.cssText = 'position:absolute;visibility:hidden;width:' + value;
        document.body.appendChild(probe);
        var mm = probe.getBoundingClientRect().width * 25.4 / 96;
        probe.remove();
        return mm;
    }

    // The form's own @page margins become the sheet's padding.
    function applyMargins() {
        Array.prototype.forEach.call(document.styleSheets, function (sheet) {
            var rules;
            try { rules = sheet.cssRules; } catch (e) { return; }
            Array.prototype.forEach.call(rules || [], function (rule) {
                if (rule.type !== 6 /* CSSRule.PAGE_RULE */ || !rule.style) return;
                [['marginTop', 'top'], ['marginRight', 'right'], ['marginBottom', 'bottom'], ['marginLeft', 'left']].forEach(function (pair) {
                    if (rule.style[pair[0]]) root.style.setProperty('--a4-' + pair[1], toMm(rule.style[pair[0]]).toFixed(2) + 'mm');
                });
            });
        });
    }

    // Shrinks content that is wider than the A4 printable width.
    var wrapper = null;
    function fit() {
        var body = document.body;
        if (!wrapper) {
            wrapper = document.createElement('div');
            wrapper.className = 'a4-fit';
            while (body.firstChild) wrapper.appendChild(body.firstChild);
            body.appendChild(wrapper);
        }
        wrapper.style.zoom = '';
        var style = getComputedStyle(body);
        var available = body.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
        var needed = wrapper.scrollWidth;
        if (available > 0 && needed > available + 1) {
            wrapper.style.zoom = (available / needed).toFixed(4);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        applyMargins();
        fit();
    });
    window.addEventListener('load', fit);
})();
</script>
@endunless
