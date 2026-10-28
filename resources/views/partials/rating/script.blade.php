{{-- Rating questions: answered counter, and a jump to the first blank one on submit. --}}
<script>
// Rating questions: count the answers, mark each answered card, and on
// submit scroll to the first question left blank.
(function () {
    var cards = Array.prototype.slice.call(document.querySelectorAll('[data-rate-q]'));
    if (!cards.length) return;
    var done = document.getElementById('rateDone'), bar = document.getElementById('rateBar');
    function answered(card) { return !!card.querySelector('input:checked'); }
    function update() {
        var n = cards.filter(answered).length;
        cards.forEach(function (c) { var a = answered(c); c.classList.toggle('is-answered', a); if (a) c.classList.remove('is-missing'); });
        if (done) done.textContent = n;
        if (bar) bar.style.width = Math.round(n / cards.length * 100) + '%';
    }
    cards.forEach(function (c) { c.addEventListener('change', update); });
    var form = cards[0].closest('form');
    if (form) {
        form.addEventListener('invalid', function (e) {
            var card = e.target.closest && e.target.closest('[data-rate-q]');
            if (!card) return;
            e.preventDefault();
            var first = cards.filter(function (c) { return !answered(c) && c.querySelector('[required]'); })[0];
            cards.forEach(function (c) { if (!answered(c) && c.querySelector('[required]')) c.classList.add('is-missing'); });
            if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, true);
    }
    update();
})();
</script>
