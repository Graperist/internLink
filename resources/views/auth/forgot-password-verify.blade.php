@extends('layouts.auth')

@section('title', 'Enter Code - PSU Intern-Link')

@section('content')
<div class="auth-card">

    <div class="auth-brand">
        <img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo">
        <div>
            <h1>PSU Intern-Link</h1>
            <p>Pangasinan State University</p>
        </div>
    </div>

    <p class="auth-subtitle">
        Enter the 6-digit code
        @if ($maskedEmail)
            we sent to <span style="color:var(--il-gold);">{{ $maskedEmail }}</span>.
        @else
            we emailed you.
        @endif
        <span style="display:block; font-weight:400; font-size:0.8rem; color:var(--il-text-muted); margin-top:.35rem;">
            It expires in 10 minutes. Check your spam folder if you don't see it.
        </span>
    </p>

    @if (session('status'))
        <div class="auth-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="auth-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $action ?? route('password.forgot.verify.check') }}" id="codeForm">
        @csrf
        <input type="hidden" name="code" id="code">

        <div class="code-boxes" id="codeBoxes">
            @for ($i = 0; $i < 6; $i++)
                <input type="text" inputmode="numeric" maxlength="1" pattern="[0-9]"
                       autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                       aria-label="Digit {{ $i + 1 }}" class="code-box">
            @endfor
        </div>

        <button type="submit" class="auth-submit mt-3">Verify</button>
    </form>

    <div class="d-flex justify-content-between align-items-center mt-3">
        <form method="POST" action="{{ $resendAction ?? route('password.forgot.resend') }}">
            @csrf
            <button type="submit" class="auth-linkbtn" id="resendBtn" disabled>Resend code</button>
            <span id="resendTimer" class="auth-link" style="display:none;"></span>
        </form>
        <a href="{{ $backUrl ?? route('login') }}" class="auth-link">{{ $backLabel ?? 'Back to sign in' }}</a>
    </div>

</div>

<style>
    .code-boxes { display:flex; gap:8px; justify-content:space-between; }
    .code-box {
        width:100%; min-width:0; aspect-ratio:1/1.15; text-align:center;
        font-size:1.5rem; font-weight:700; color:#fff;
        background:var(--il-navy-field); border:1px solid var(--il-navy-field-border);
        border-radius:10px; padding:0;
    }
    .code-box:focus {
        outline:none; border-color:var(--il-gold);
        box-shadow:0 0 0 3px rgba(240,180,41,.25);
    }
</style>

<script>
(function () {
    const boxes = Array.from(document.querySelectorAll('.code-box'));
    const hidden = document.getElementById('code');
    const form = document.getElementById('codeForm');

    const sync = () => { hidden.value = boxes.map(b => b.value).join(''); };

    boxes[0].focus();

    boxes.forEach((box, i) => {
        box.addEventListener('input', () => {
            box.value = box.value.replace(/\D/g, '').slice(-1);
            sync();
            if (box.value && i < boxes.length - 1) boxes[i + 1].focus();
        });
        box.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !box.value && i > 0) boxes[i - 1].focus();
            if (e.key === 'ArrowLeft' && i > 0) boxes[i - 1].focus();
            if (e.key === 'ArrowRight' && i < boxes.length - 1) boxes[i + 1].focus();
        });
        box.addEventListener('paste', (e) => {
            e.preventDefault();
            const digits = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
            digits.split('').forEach((d, k) => { boxes[k].value = d; });
            sync();
            boxes[Math.min(digits.length, 5)].focus();
        });
    });

    form.addEventListener('submit', (e) => {
        sync();
        if (hidden.value.length !== 6) { e.preventDefault(); boxes.find(b => !b.value).focus(); }
    });

    // Resend countdown
    let left = {{ (int) $cooldown }};
    const btn = document.getElementById('resendBtn');
    const timer = document.getElementById('resendTimer');
    const tick = () => {
        if (left > 0) {
            btn.style.display = 'none';
            timer.style.display = 'inline';
            timer.textContent = 'Resend code in ' + left + 's';
            left--;
            setTimeout(tick, 1000);
        } else {
            timer.style.display = 'none';
            btn.style.display = 'inline';
            btn.disabled = false;
        }
    };
    tick();
})();
</script>
@endsection
