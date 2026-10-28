@extends('layouts.admin')

@section('title', 'Login Photos - PSU Intern-Link')

@section('content')
@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
@endpush
<style>
    .lp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 1rem; }
    .lp-card { border: 1px solid #e6eaf0; border-radius: 12px; background: #fff; }
    /* Same shape as the photo area of the login page, so the card shows what will be seen there. */
    .lp-photo { aspect-ratio: {{ \App\Models\LoginSlide::ASPECT_W }} / {{ \App\Models\LoginSlide::ASPECT_H }}; background: #e9edf2 center / cover no-repeat; position: relative; border-radius: 11px 11px 0 0; }
    .lp-order { position: absolute; top: .5rem; left: .5rem; background: rgba(11,47,134,.85); color: #fff; font-weight: 700; font-size: .75rem; border-radius: 999px; padding: .15rem .55rem; }
    .lp-body { display: flex; align-items: center; gap: .5rem; padding: .55rem .6rem .55rem .85rem; min-height: 48px; }
    .lp-caption { font-weight: 600; font-size: .9rem; color: #14213a; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .lp-more { width: 34px; height: 34px; border-radius: 50%; border: 0; background: transparent; color: #5d6b80; display: grid; place-items: center; font-size: 1.15rem; }
    .lp-more:hover, .lp-more[aria-expanded=true] { background: #eef1f6; color: #14213a; }
    .lp-menu .dropdown-item { display: flex; align-items: center; gap: .55rem; font-size: .88rem; padding: .45rem .9rem; }
    .lp-add { border: 2px dashed #c7d0dc; border-radius: 12px; background: #fafbfd; color: #1c3f66; display: grid; place-content: center; justify-items: center; gap: .35rem;
        aspect-ratio: {{ \App\Models\LoginSlide::ASPECT_W }} / {{ \App\Models\LoginSlide::ASPECT_H }}; font-weight: 700; cursor: pointer; width: 100%; }
    .lp-add:hover { border-color: #1c3f66; background: #f1f5fb; }
    .lp-add i { font-size: 1.8rem; }
    .lp-add span { font-weight: 400; font-size: .78rem; color: #5d6b80; }
    .lp-crop-stage { height: min(60vh, 520px); background: #1b2333; border-radius: 10px; overflow: hidden; }
    .lp-crop-stage img { display: block; max-width: 100%; }
</style>

<div class="il-page-header d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div>
        <h1 class="il-page-title">Login page photos</h1>
        <p class="text-secondary small mb-0">These change every 5 seconds on the sign-in page. Use <i class="ti ti-dots"></i> on a photo to replace, crop, rename, move or remove it.</p>
    </div>
    <a href="{{ route('admin.login-photos.preview') }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="ti ti-external-link"></i> View login page</a>
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div id="lpAlert" class="alert alert-danger d-none"></div>

<form id="lpAddForm" method="POST" action="{{ route('admin.login-photos.store') }}" class="d-none">@csrf</form>
<input type="file" id="photos" accept=".jpg,.jpeg,.png,.webp" multiple class="d-none">

<div class="il-card">
    <div class="lp-grid">
        @foreach ($slides as $i => $slide)
            @php $name = $slide->caption ?: 'Photo '.($i + 1); @endphp
            <div class="lp-card">
                <div class="lp-photo" style="background-image:url('{{ $slide->url() }}')"><span class="lp-order">{{ $i + 1 }}</span></div>
                <div class="lp-body">
                    <span class="lp-caption" title="{{ $slide->caption }}">{!! $slide->caption ? e($slide->caption) : '<span class="text-secondary fw-normal">No caption</span>' !!}</span>
                    <div class="dropdown">
                        <button type="button" class="lp-more" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Options for {{ $name }}"><i class="ti ti-dots"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end lp-menu shadow-sm">
                            <li><button type="button" class="dropdown-item" data-replace="{{ route('admin.login-photos.replace', $slide) }}" data-name="{{ $name }}"><i class="ti ti-replace"></i> Replace</button></li>
                            <li><button type="button" class="dropdown-item" data-adjust="{{ route('admin.login-photos.replace', $slide) }}" data-src="{{ $slide->url() }}" data-name="{{ $name }}"><i class="ti ti-crop"></i> Adjust fit</button></li>
                            <li><button type="button" class="dropdown-item" data-rename="{{ route('admin.login-photos.update', $slide) }}" data-caption="{{ $slide->caption }}"><i class="ti ti-pencil"></i> Edit caption</button></li>
                            @unless ($loop->first)
                                <li><button type="submit" class="dropdown-item" form="lpUp{{ $slide->id }}"><i class="ti ti-arrow-left"></i> Move earlier</button></li>
                            @endunless
                            @unless ($loop->last)
                                <li><button type="submit" class="dropdown-item" form="lpDown{{ $slide->id }}"><i class="ti ti-arrow-right"></i> Move later</button></li>
                            @endunless
                            <li><hr class="dropdown-divider"></li>
                            <li><button type="submit" class="dropdown-item text-danger" form="lpDel{{ $slide->id }}"><i class="ti ti-trash"></i> Remove</button></li>
                        </ul>
                    </div>
                </div>
                <form id="lpUp{{ $slide->id }}" method="POST" action="{{ route('admin.login-photos.move', $slide) }}" class="d-none">@csrf<input type="hidden" name="direction" value="up"></form>
                <form id="lpDown{{ $slide->id }}" method="POST" action="{{ route('admin.login-photos.move', $slide) }}" class="d-none">@csrf<input type="hidden" name="direction" value="down"></form>
                <form id="lpDel{{ $slide->id }}" method="POST" action="{{ route('admin.login-photos.destroy', $slide) }}" class="d-none" onsubmit="return confirm('Remove this photo from the login page?')">@csrf @method('DELETE')</form>
            </div>
        @endforeach
        <button type="button" class="lp-add" id="lpAddBtn"><i class="ti ti-photo-plus"></i> Add photo<span>JPG, PNG or WEBP, up to 5 MB</span></button>
    </div>
</div>

<input type="file" id="lpReplaceFile" accept=".jpg,.jpeg,.png,.webp" class="d-none">

{{-- Edit a caption. --}}
<div class="modal fade" id="lpCaptionModal" tabindex="-1" aria-labelledby="lpCaptionTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" id="lpCaptionForm">
            @csrf @method('PUT')
            <div class="modal-header"><h2 class="modal-title h6 fw-bold" id="lpCaptionTitle">Edit caption</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <input type="text" name="caption" id="lpCaptionInput" class="form-control" maxlength="80" placeholder="e.g. San Carlos Campus" aria-label="Caption">
                <div class="small text-secondary mt-1">Shown on the photo on the login page. Leave blank for none.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

{{-- Fit a photo to the login page: the frame has the same shape as the login page's photo area. --}}
<div class="modal fade" id="lpCropModal" tabindex="-1" aria-labelledby="lpCropTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h6 fw-bold mb-0" id="lpCropTitle">Fit to the login page</h2>
                    <div class="small text-secondary" id="lpCropHint">Drag the photo or the frame. What is inside the frame is what shows on the login page.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="lp-crop-stage"><img id="lpCropImg" alt="Photo to fit"></div>
                <div class="d-flex gap-2 mt-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-crop="zoom-in"><i class="ti ti-zoom-in"></i> Zoom in</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-crop="zoom-out"><i class="ti ti-zoom-out"></i> Zoom out</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-crop="reset"><i class="ti ti-refresh"></i> Reset</button>
                    <span class="small text-secondary align-self-center ms-auto" id="lpCropCount"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="lpCropWhole" title="Upload the photo as it is; the login page trims its edges to fill the space">Use the whole photo</button>
                <button type="button" class="btn btn-primary" id="lpCropUse"><i class="ti ti-check"></i> Fit and save</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script>
(() => {
    const RATIO = {{ \App\Models\LoginSlide::ASPECT_W }} / {{ \App\Models\LoginSlide::ASPECT_H }};
    const OUT_W = 1600, OUT_H = Math.round(1600 / RATIO);
    const token = document.querySelector('#lpAddForm input[name=_token]').value;
    const modalEl = document.getElementById('lpCropModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const img = document.getElementById('lpCropImg');
    const alertBox = document.getElementById('lpAlert');
    let cropper = null, job = null;

    const fail = msg => { alertBox.textContent = msg; alertBox.classList.remove('d-none'); window.scrollTo({ top: 0, behavior: 'smooth' }); };

    // Opens the crop window for one photo; resolves with a Blob/File, or null when cancelled.
    function crop(src, title, { allowWhole = true, count = '' } = {}) {
        return new Promise(resolve => {
            job = { resolve, src };
            document.getElementById('lpCropTitle').textContent = title;
            document.getElementById('lpCropCount').textContent = count;
            document.getElementById('lpCropWhole').classList.toggle('d-none', !allowWhole);
            img.src = typeof src === 'string' ? src : URL.createObjectURL(src);
            modal.show();
        });
    }
    modalEl.addEventListener('shown.bs.modal', () => {
        cropper?.destroy();
        cropper = new Cropper(img, { aspectRatio: RATIO, viewMode: 1, autoCropArea: 1, dragMode: 'move', background: false });
    });
    modalEl.addEventListener('hidden.bs.modal', () => {
        cropper?.destroy(); cropper = null;
        if (job) { const j = job; job = null; j.resolve(null); }
    });
    modalEl.querySelectorAll('[data-crop]').forEach(b => b.addEventListener('click', () => {
        if (!cropper) return;
        ({ 'zoom-in': () => cropper.zoom(0.1), 'zoom-out': () => cropper.zoom(-0.1), reset: () => cropper.reset() })[b.dataset.crop]();
    }));
    const finish = value => { const j = job; job = null; modal.hide(); j?.resolve(value); };
    document.getElementById('lpCropWhole').addEventListener('click', () => finish(job.src));
    document.getElementById('lpCropUse').addEventListener('click', () => {
        const canvas = cropper.getCroppedCanvas({ width: OUT_W, height: OUT_H, imageSmoothingQuality: 'high', fillColor: '#0b2f86' });
        canvas.toBlob(blob => finish(new File([blob], 'login-photo.jpg', { type: 'image/jpeg' })), 'image/jpeg', 0.88);
    });
    // The hidden event fires before the next photo opens; wait for it before showing the window again.
    const closed = () => new Promise(r => modalEl.classList.contains('show') ? modalEl.addEventListener('hidden.bs.modal', r, { once: true }) : r());

    async function send(url, data) {
        data.append('_token', token);
        const res = await fetch(url, { method: 'POST', body: data, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (res.ok) { location.reload(); return; }
        const body = await res.json().catch(() => ({}));
        fail(body.errors ? Object.values(body.errors)[0][0] : (body.message || 'The photo could not be saved. Try again.'));
    }

    // Add photo: pick one or more, fit each, then upload them together.
    const adder = document.getElementById('photos');
    document.getElementById('lpAddBtn').addEventListener('click', () => { adder.value = ''; adder.click(); });
    adder.addEventListener('change', async () => {
        const files = [...adder.files];
        if (!files.length) return;
        const data = new FormData();
        for (const [i, f] of files.entries()) {
            await closed();
            const out = await crop(f, 'Fit to the login page', { count: files.length > 1 ? `Photo ${i + 1} of ${files.length}` : '' });
            if (!out) return;
            data.append('photos[]', out, out.name || f.name);
        }
        send(document.getElementById('lpAddForm').action, data);
    });

    // Edit caption.
    const capModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('lpCaptionModal'));
    document.querySelectorAll('[data-rename]').forEach(b => b.addEventListener('click', () => {
        document.getElementById('lpCaptionForm').action = b.dataset.rename;
        document.getElementById('lpCaptionInput').value = b.dataset.caption || '';
        capModal.show();
    }));
    document.getElementById('lpCaptionModal').addEventListener('shown.bs.modal', () => document.getElementById('lpCaptionInput').focus());

    // Replace one photo with a new picture.
    const picker = document.getElementById('lpReplaceFile');
    let target = null;
    document.querySelectorAll('[data-replace]').forEach(b => b.addEventListener('click', () => { target = b; picker.value = ''; picker.click(); }));
    picker.addEventListener('change', async () => {
        const f = picker.files[0];
        if (!f || !target) return;
        const out = await crop(f, 'Replace "' + target.dataset.name + '"');
        if (!out) return;
        const data = new FormData(); data.append('photo', out, out.name || f.name);
        send(target.dataset.replace, data);
    });

    // Re-fit a photo that is already on the login page.
    document.querySelectorAll('[data-adjust]').forEach(b => b.addEventListener('click', async () => {
        const out = await crop(b.dataset.src, 'Adjust fit: ' + b.dataset.name, { allowWhole: false });
        if (!out) return;
        const data = new FormData(); data.append('photo', out);
        send(b.dataset.adjust, data);
    }));
})();
</script>
@endpush

