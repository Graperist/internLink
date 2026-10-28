<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginSlide;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Super Admin: the photos on the login page (shared by every campus). One photo shows on its own;
 * two or more rotate. Until any are uploaded, the built-in campus photos show.
 */
class LoginPhotoController extends Controller
{
    public function index(): View
    {
        // Each built-in photo becomes its own row, so it can be replaced on its own.
        LoginSlide::listBuiltInsIfEmpty();

        return view('admin.login-photos', [
            'slides' => LoginSlide::orderBy('position')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $request->validate([
            'photos' => ['required', 'array', 'max:10'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:80'],
        ], [
            'photos.required' => 'Choose at least one photo.',
            'photos.*.image' => 'Each file must be a photo (JPG, PNG or WEBP).',
            'photos.*.mimes' => 'Each file must be a photo (JPG, PNG or WEBP).',
            'photos.*.max' => 'Each photo can be up to 5 MB.',
        ]);

        LoginSlide::listBuiltInsIfEmpty();   // adding never drops the built-in photos
        $position = (int) LoginSlide::max('position');
        $count = 0;
        foreach ($request->file('photos') as $file) {
            LoginSlide::create([
                'path' => $this->save($file),
                'caption' => $request->input('caption'),
                'position' => ++$position,
                'uploaded_by_user_id' => auth()->id(),
            ]);
            $count++;
        }

        AuditLogger::record('login_photos.uploaded', "Added {$count} login page ".Str::plural('photo', $count).'.', auth()->user(), $request);

        $message = $count.' '.Str::plural('photo', $count).' added to the login page.';

        if ($request->expectsJson()) {
            session()->flash('status', $message);   // shown after the page reloads
        }

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('status', $message);
    }

    /**
     * Replace one photo (a new picture, or the same one cropped to fit the
     * login page). Its place and caption stay; an uploaded file it replaces
     * is deleted, a built-in one is kept on disk.
     */
    public function replace(Request $request, LoginSlide $slide): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'photo.required' => 'Choose a photo.',
            'photo.image' => 'The file must be a photo (JPG, PNG or WEBP).',
            'photo.mimes' => 'The file must be a photo (JPG, PNG or WEBP).',
            'photo.max' => 'The photo can be up to 8 MB.',
        ]);

        $old = $slide->isBuiltIn() ? null : public_path($slide->path);
        $slide->update(['path' => $this->save($request->file('photo')), 'uploaded_by_user_id' => auth()->id()]);
        if ($old && is_file($old)) {
            @unlink($old);
        }

        AuditLogger::record('login_photos.replaced', 'Replaced a login page photo'.($slide->caption ? " ({$slide->caption})" : '').'.', auth()->user(), $request);

        $message = 'Photo '.($slide->caption ? '"'.$slide->caption.'" ' : '').'replaced.';

        if ($request->expectsJson()) {
            session()->flash('status', $message);   // shown after the page reloads
        }

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('status', $message);
    }

    /** Saves an uploaded photo under public/uploads/login-slides and returns its path. */
    private function save(\Illuminate\Http\UploadedFile $file): string
    {
        $dir = public_path(LoginSlide::DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = Str::random(24).'.'.strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $file->move($dir, $name);

        return LoginSlide::DIR.'/'.$name;
    }

    /** Change a photo's caption. */
    public function update(Request $request, LoginSlide $slide): RedirectResponse
    {
        $data = $request->validate(['caption' => ['nullable', 'string', 'max:80']]);
        $slide->update(['caption' => $data['caption'] ?? null]);

        return back()->with('status', 'Caption saved.');
    }

    /** Move a photo one place up or down. */
    public function move(Request $request, LoginSlide $slide): RedirectResponse
    {
        $slides = LoginSlide::orderBy('position')->orderBy('id')->get()->values();
        $i = $slides->search(fn ($s) => $s->id === $slide->id);
        $j = $request->input('direction') === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && isset($slides[$j])) {
            [$slides[$i], $slides[$j]] = [$slides[$j], $slides[$i]];
            $slides->each(fn ($s, $k) => $s->update(['position' => $k + 1]));
        }

        return back();
    }

    public function destroy(Request $request, LoginSlide $slide): RedirectResponse
    {
        $file = public_path($slide->path);
        if (str_starts_with($slide->path, LoginSlide::DIR.'/') && is_file($file)) {
            @unlink($file);
        }
        $slide->delete();

        AuditLogger::record('login_photos.removed', 'Removed a login page photo.', auth()->user(), $request);

        return back()->with('status', LoginSlide::count() ? 'Photo removed from the login page.' : 'Photo removed. With none left, the login page shows the built-in campus photos again.');
    }
}
