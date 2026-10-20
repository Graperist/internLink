<?php

namespace App\Http\Controllers;

use App\Models\Dtr;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The photo a student took at the HTE for one time in / time out. Only the
 * student, the HTE supervisor of that deployment's HTE and the student's
 * instructors can open it.
 */
class DtrPhotoController extends Controller
{
    public function show(Dtr $dtr, string $stamp): StreamedResponse
    {
        abort_unless(array_key_exists($stamp, Dtr::PHOTO_STAMPS), 404);
        $photo = $dtr->photo($stamp);
        abort_unless($photo && Storage::exists($photo['path']), 404);

        $user = auth()->user();
        $deployment = $dtr->deployment()->with('internship')->first();
        $studentId = $deployment?->internship?->student_id;

        $allowed = ($user->student && $user->student->id === $studentId)
            || ($user->hteRepresentative && $deployment && (int) $user->hteRepresentative->hte_id === (int) $deployment->hte_id)
            || ($user->instructor && $studentId && $user->instructor->visibleStudents()->whereKey($studentId)->exists());
        abort_unless($allowed, 403);

        return Storage::response($photo['path'], null, ['Cache-Control' => 'private, max-age=86400']);
    }
}
