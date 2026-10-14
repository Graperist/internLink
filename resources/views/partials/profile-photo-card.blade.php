{{--
    Shared "Profile Photo" card for every portal's My Profile page.
    Include with: @include('partials.profile-photo-card', ['prefix' => 'student'])
    $prefix must match the route group name (student|instructor|coordinator|hte),
    since profile.photo / profile.photo.remove are namespaced per portal.

    Selecting a file opens a crop modal with a circular guide - the user
    drags/zooms to position their photo, and only what's inside that circle
    is what actually gets uploaded (a square JPEG cropped from that area),
    not the whole original photo auto-cropped by CSS.
--}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">

<div class="il-card">
    <h2 class="h6 fw-bold mb-3">Profile Photo</h2>

    <div class="d-flex align-items-center gap-3 mb-3">
        <div id="{{ $prefix }}AvatarInitials" class="il-avatar {{ auth()->user()->avatar_url ? 'd-none' : '' }}" style="width:64px;height:64px;font-size:1.25rem;">
            {{ collect(explode(' ', auth()->user()->name))->map(fn($p) => $p[0] ?? '')->join('') }}
        </div>
        <img
            id="{{ $prefix }}AvatarPreview"
            src="{{ auth()->user()->avatar_url }}"
            alt="{{ auth()->user()->name }}"
            class="rounded-circle {{ auth()->user()->avatar_url ? '' : 'd-none' }}"
            style="width:64px;height:64px;object-fit:cover;"
        >

        <div>
            <p class="small text-secondary mb-0">JPG, PNG, or WEBP. Max 2MB.</p>
            <p class="small text-secondary mb-0">You'll be able to drag and zoom to fit the circle before saving.</p>
        </div>
    </div>

    <form method="POST" action="{{ route($prefix.'.profile.photo') }}" enctype="multipart/form-data" class="mb-2" id="{{ $prefix }}AvatarForm">
        @csrf
        @method('PUT')
        <input type="file" name="photo" id="{{ $prefix }}AvatarFileInput" class="form-control form-control-sm mb-2" accept="image/png,image/jpeg,image/webp">
        <button type="submit" class="btn btn-sm btn-primary" id="{{ $prefix }}AvatarUploadButton" disabled>Upload photo</button>
    </form>

    @if (auth()->user()->avatar_url)
        <form method="POST" action="{{ route($prefix.'.profile.photo.remove') }}" onsubmit="return confirm('Remove your profile photo?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-secondary">Remove photo</button>
        </form>
    @endif
</div>

<div class="modal fade" id="{{ $prefix }}AvatarCropModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adjust your photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div style="max-height: 380px; overflow: hidden;">
                    <img id="{{ $prefix }}AvatarCropImage" style="max-width: 100%; display: block;">
                </div>
                <p class="small text-secondary mt-2 mb-0">
                    Drag to reposition, scroll or pinch to zoom. Only what's inside the circle will be saved.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="{{ $prefix }}AvatarCropConfirm">Use this photo</button>
            </div>
        </div>
    </div>
</div>

<style>
    #{{ $prefix }}AvatarCropModal .cropper-view-box,
    #{{ $prefix }}AvatarCropModal .cropper-face {
        border-radius: 50%;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script>
(function () {
    const prefix = @json($prefix);
    const fileInput = document.getElementById(prefix + 'AvatarFileInput');
    const uploadButton = document.getElementById(prefix + 'AvatarUploadButton');
    const cropImage = document.getElementById(prefix + 'AvatarCropImage');
    const confirmButton = document.getElementById(prefix + 'AvatarCropConfirm');
    const modalEl = document.getElementById(prefix + 'AvatarCropModal');
    const preview = document.getElementById(prefix + 'AvatarPreview');
    const initials = document.getElementById(prefix + 'AvatarInitials');

    let cropper = null;
    let modal = null;
    let confirmed = false;
    let pendingFileName = 'profile-photo.jpg';

    function getModal() {
        modal = modal || new bootstrap.Modal(modalEl);
        return modal;
    }

    fileInput.addEventListener('change', function () {
        const file = fileInput.files && fileInput.files[0];
        if (!file) {
            return;
        }

        confirmed = false;
        pendingFileName = file.name.replace(/\.[^.]+$/, '') + '.jpg';

        const reader = new FileReader();
        reader.onload = function (e) {
            cropImage.src = e.target.result;
            getModal().show();
        };
        reader.readAsDataURL(file);
    });

    modalEl.addEventListener('shown.bs.modal', function () {
        if (cropper) {
            cropper.destroy();
        }
        cropper = new Cropper(cropImage, {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 1,
            cropBoxMovable: false,
            cropBoxResizable: false,
            background: false,
            guides: false,
            center: false,
            highlight: false,
        });
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        if (!confirmed) {
            // Cancelled without confirming a crop - clear the file input so
            // an un-cropped photo can't accidentally get uploaded.
            fileInput.value = '';
        }
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
    });

    confirmButton.addEventListener('click', function () {
        if (!cropper) {
            return;
        }

        cropper.getCroppedCanvas({ width: 480, height: 480, imageSmoothingQuality: 'high' }).toBlob(function (blob) {
            const croppedFile = new File([blob], pendingFileName, { type: 'image/jpeg' });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(croppedFile);
            fileInput.files = dataTransfer.files;

            const url = URL.createObjectURL(blob);
            preview.src = url;
            preview.classList.remove('d-none');
            initials.classList.add('d-none');

            uploadButton.disabled = false;
            confirmed = true;
            getModal().hide();
        }, 'image/jpeg', 0.92);
    });
})();
</script>
