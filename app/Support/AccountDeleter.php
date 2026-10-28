<?php

namespace App\Support;

use App\Models\Campus;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\Internship;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Super Admin deletions: a whole campus, or one account (coordinator,
 * instructor, HTE or student) with what belongs only to it.
 *
 *  - Student: their internships with every document, deployment, time
 *    frame day and evaluation, and the uploaded files.
 *  - Instructor / coordinator: the account. The reviews they made stay on
 *    the students' documents, without the reviewer.
 *  - HTE: the account. Evaluations they filed stay, without the account.
 *  - Campus: all of the above for every account on it, then the campus
 *    with its programs, partner agencies and forms.
 */
class AccountDeleter
{
    /** @var list<string> files to remove once the database work is done */
    private array $files = [];

    /** @var list<string> profile photos (public disk) */
    private array $publicFiles = [];

    public static function user(User $user): void
    {
        $deleter = new self;
        DB::transaction(fn () => $deleter->deleteUser($user));
        $deleter->removeFiles();
    }

    public static function campus(Campus $campus): void
    {
        $deleter = new self;
        DB::transaction(function () use ($deleter, $campus) {
            foreach (User::where('campus_id', $campus->id)->with('role')->get() as $user) {
                $deleter->deleteUser($user);
            }
            // Internships left without a student account (shouldn't happen, but
            // the campus's programs and forms can't go while any remain).
            foreach (Internship::where('campus_id', $campus->id)->get() as $internship) {
                $deleter->deleteInternship($internship);
            }
            $campus->delete();   // programs, partner agencies, forms, settings
        });
        $deleter->removeFiles();
    }

    private function deleteUser(User $user): void
    {
        $user->loadMissing('role', 'student.internships', 'instructor', 'hteRepresentative');

        if ($user->student) {
            foreach ($user->student->internships as $internship) {
                $this->deleteInternship($internship);
            }
        }

        if ($user->instructor) {
            DB::table('document_reviews')->where('instructor_id', $user->instructor->id)->update(['instructor_id' => null]);
        }

        if ($representative = $user->hteRepresentative) {
            DB::table('evaluations')->where('hte_representative_id', $representative->id)->update(['hte_representative_id' => null]);
            $representative->delete();
        }

        if ($user->avatar_path) {
            $this->publicFiles[] = $user->avatar_path;
        }

        $user->delete();   // its student / instructor record and notifications go with it
    }

    private function deleteInternship(Internship $internship): void
    {
        $documentIds = Document::where('internship_id', $internship->id)->pluck('id');
        $this->files = array_merge(
            $this->files,
            Document::whereIn('id', $documentIds)->whereNotNull('file_path')->pluck('file_path')->all(),
            DocumentAttachment::whereIn('document_id', $documentIds)->pluck('file_path')->all(),
        );

        $internship->delete();   // documents, reviews, deployment, time frame days, evaluations
    }

    private function removeFiles(): void
    {
        foreach ([[null, $this->files], ['public', $this->publicFiles]] as [$disk, $paths]) {
            foreach (array_filter(array_unique($paths)) as $path) {
                try {
                    Storage::disk($disk)->delete($path);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }
    }
}
