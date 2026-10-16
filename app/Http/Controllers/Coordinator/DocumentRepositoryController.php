<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coordinator\StoreRepositoryFileRequest;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\Program;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentRepositoryController extends Controller
{
    /**
     * Figure 7 - Digital Repository: every document on file for the
     * campus, searchable/filterable, with a coordinator-side manual
     * upload since the Student portal's own upload flow doesn't exist yet.
     *
     * Known simplification: every row here is tied to a specific student's
     * internship (documents.internship_id is required). Your mockup shows
     * one row with no student ("MOA_BDO_Alaminos_2026.pdf") implying some
     * files are HTE-level, not student-level - that's a schema change
     * (nullable internship_id, or a separate hte-level attachment table)
     * that isn't in yet. For now, every upload needs a student attached.
     */
    public function index(Request $request): View
    {
        $campusId = auth()->user()->campus_id;

        // Like Document Review: the student list first; picking a student
        // opens their page with their passed files on the left and the
        // selected file on the right.
        if ($request->filled('student')) {
            return $this->studentPage($request, $campusId);
        }

        // One row per student (not per file). Each student carries only the
        // files they have passed: approved documents with a file on record,
        // for live requirements (no legacy forms / seminars).
        $query = Student::with(['user', 'program'])
            ->with(['documents' => fn ($q) => $q
                ->where('documents.status', 'approved')
                ->whereNotNull('documents.file_path')
                ->where('documents.file_path', '!=', '')
                ->whereHas('requirement', fn ($r) => $r->where('is_active', true))
                ->with(['requirement', 'uploader.role', 'attachments'])
            ])
            ->join('users', 'users.id', '=', 'students.user_id')
            ->where('students.campus_id', $campusId)
            ->select('students.*')
            ->orderBy('users.name');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.id_number', 'like', "%{$search}%")
                    ->orWhereHas('documents', fn ($d) => $d->where('documents.original_filename', 'like', "%{$search}%"));
            });
        }

        if ($requirementId = $request->query('document_requirement_id')) {
            $query->whereHas('documents', fn ($d) => $d
                ->where('documents.document_requirement_id', $requirementId)
                ->where('documents.status', 'approved'));
        }

        if ($programId = $request->query('program_id')) {
            $query->where('students.program_id', $programId);
        }

        $students = $query->paginate(15)->withQueryString();

        $requirements = DocumentRequirement::where('campus_id', $campusId)->where('is_active', true)->orderBy('name')->get();
        $programs = Program::where('campus_id', $campusId)->orderBy('name')->get();

        // How many live required forms each stage has, for the "x / y" counts.
        $stageTotals = $this->stageTotals($campusId);

        return view('coordinator.repository.index', compact('students', 'requirements', 'programs', 'stageTotals'));
    }

    /** One student's passed files, Document Review style. */
    private function studentPage(Request $request, int $campusId): View
    {
        $student = Student::with(['user', 'program'])
            ->where('campus_id', $campusId)
            ->findOrFail((int) $request->query('student'));

        // Previous / next follow the list order (by name, same program filter).
        $order = Student::join('users', 'users.id', '=', 'students.user_id')
            ->where('students.campus_id', $campusId)
            ->when($request->query('program_id'), fn ($q, $programId) => $q->where('students.program_id', $programId))
            ->orderBy('users.name')
            ->pluck('students.id')
            ->values();
        $index = $order->search($student->id);
        $position = ['index' => $index === false ? 1 : $index + 1, 'total' => max($order->count(), 1)];
        $prevId = $index !== false && $index > 0 ? $order[$index - 1] : null;
        $nextId = $index !== false && $index < $order->count() - 1 ? $order[$index + 1] : null;
        $prevStudent = $prevId ? Student::with('user')->find($prevId) : null;
        $nextStudent = $nextId ? Student::with('user')->find($nextId) : null;

        $files = $this->passedFiles($student);

        $selected = $files->firstWhere('key', (string) $request->query('file')) ?? $files->first();

        $stageTotals = $this->stageTotals($campusId);
        $passedByStage = $files->where('kind', 'form')->where('required', true)->countBy('stage');

        $jumpList = Student::with(['user', 'program'])
            ->join('users', 'users.id', '=', 'students.user_id')
            ->where('students.campus_id', $campusId)
            ->select('students.*')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->user->name, 'id_number' => $s->user->id_number.($s->program ? ' · '.$s->program->code : '')])
            ->values();

        return view('coordinator.repository.student', compact(
            'student', 'files', 'selected', 'prevStudent', 'nextStudent', 'position', 'stageTotals', 'passedByStage', 'jumpList'
        ));
    }

    /**
     * A student's passed files: approved forms that have a file on record
     * (live requirements only), each followed by the files attached to it
     * (Form 1's medical / psychological, Form 3's guardian ID). Ordered by
     * stage, then form number.
     */
    private function passedFiles(Student $student)
    {
        $stageRank = ['pre_deployment' => 1, 'active' => 2, 'post_deployment' => 3];

        $documents = Document::with(['requirement', 'uploader.role', 'attachments', 'reviews' => fn ($q) => $q->latest('reviewed_at')])
            ->whereHas('internship', fn ($q) => $q->where('student_id', $student->id))
            ->whereHas('requirement', fn ($q) => $q->where('is_active', true))
            ->where('status', 'approved')
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->get()
            ->sortBy(function ($document) use ($stageRank) {
                $number = preg_match('/^(\d+)/', (string) $document->requirement->form_number, $m) ? (int) $m[1] : PHP_INT_MAX;

                return [$stageRank[$document->requirement->stage] ?? 4, $number, (string) $document->requirement->form_number, $document->requirement->display_order];
            });

        $isPreviewable = fn ($name) => (bool) preg_match('/\.(pdf|png|jpe?g|gif|webp)$/i', (string) $name);
        $files = collect();
        foreach ($documents as $document) {
            $approvedAt = $document->reviews->firstWhere('decision', 'approved')?->reviewed_at ?? $document->completed_at ?? $document->submitted_at;
            $files->push([
                'key' => 'd'.$document->id,
                'kind' => 'form',
                'title' => $document->requirement->name,
                'stage' => $document->requirement->stage,
                'required' => (bool) $document->requirement->is_required,
                'filename' => $document->original_filename,
                'date' => $approvedAt,
                'uploader' => $document->uploader?->role?->name ?? 'Student',
                'previewable' => $isPreviewable($document->original_filename ?: $document->file_path),
                'view_url' => route('coordinator.repository.view', $document),
                'download_url' => route('coordinator.repository.download', $document),
            ]);
            foreach ($document->attachments->sortBy('kind') as $attachment) {
                $files->push([
                    'key' => 'a'.$attachment->id,
                    'kind' => 'attachment',
                    'title' => $attachment->label(),
                    'parent' => $document->requirement->name,
                    'stage' => $document->requirement->stage,
                    'required' => false,
                    'filename' => $attachment->original_filename,
                    'date' => $attachment->updated_at,
                    'uploader' => 'Student Intern',
                    'previewable' => $attachment->isPreviewable(),
                    'view_url' => route('coordinator.repository.attachment', $attachment),
                    'download_url' => route('coordinator.repository.attachment', ['attachment' => $attachment, 'download' => 1]),
                ]);
            }
        }

        return $files->values();
    }

    private function stageTotals(int $campusId)
    {
        return DocumentRequirement::where('campus_id', $campusId)
            ->where('is_active', true)
            ->where('is_required', true)
            ->whereIn('stage', ['pre_deployment', 'post_deployment'])
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');
    }

    /** Opens a passed file in the browser (the preview on the student page). */
    public function view(Document $document): StreamedResponse
    {
        abort_unless($document->internship->campus_id === auth()->user()->campus_id, 403);
        abort_if(blank($document->file_path), 404);
        abort_unless(Storage::exists($document->file_path), 404);

        return Storage::response($document->file_path, $document->original_filename, [], 'inline');
    }

    /** Opens (or with ?download=1 downloads) a file attached to a form. */
    public function attachment(Request $request, \App\Models\DocumentAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->document->internship->campus_id === auth()->user()->campus_id, 403);
        abort_unless(Storage::exists($attachment->file_path), 404);

        if ($request->boolean('download')) {
            return Storage::download($attachment->file_path, $attachment->original_filename);
        }

        return Storage::response($attachment->file_path, $attachment->original_filename, [], $attachment->isPreviewable() ? 'inline' : 'attachment');
    }

    public function create(): View
    {
        $campusId = auth()->user()->campus_id;

        $students = Student::with('user')->where('campus_id', $campusId)->get();
        $requirements = DocumentRequirement::where('campus_id', $campusId)->where('is_active', true)->orderBy('name')->get();

        return view('coordinator.repository.create', compact('students', 'requirements'));
    }

    public function store(StoreRepositoryFileRequest $request): RedirectResponse
    {
        $coordinator = auth()->user();

        $student = Student::findOrFail($request->validated('student_id'));

        $internship = $student->internships()
            ->whereIn('status', ['pre_deployment', 'deployed'])
            ->latest('id')
            ->first();

        abort_if(! $internship, 422, 'This student has no active internship record to attach a document to.');

        $file = $request->file('file');
        $path = $file->store("internship_documents/campus_{$coordinator->campus_id}/student_{$student->id}");

        Document::updateOrCreate(
            [
                'internship_id' => $internship->id,
                'document_requirement_id' => $request->validated('document_requirement_id'),
            ],
            [
                'uploaded_by_user_id' => $coordinator->id,
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'status' => 'submitted',
                'submitted_at' => now(),
            ]
        );

        return redirect()
            ->route('coordinator.repository')
            ->with('status', 'File uploaded to the repository.');
    }

    /**
     * One ZIP with every approved document file on record for a student -
     * the files they have passed - so the coordinator doesn't have to
     * download them one by one. Legacy/inactive requirements are left out.
     */
    public function downloadAllForStudent(Student $student)
    {
        $campusId = auth()->user()->campus_id;
        abort_unless($student->campus_id === $campusId, 403);

        $documents = Document::with('requirement')
            ->whereHas('internship', fn ($q) => $q->where('student_id', $student->id))
            ->whereHas('requirement', fn ($q) => $q->where('is_active', true))
            ->where('status', 'approved')
            ->whereNotNull('file_path')
            ->get()
            ->filter(fn ($document) => Storage::exists($document->file_path))
            ->sortBy(fn ($document) => $document->requirement->display_order);

        // Files attached to an approved form (Form 1's Medical Certificate
        // and Psychological Test Result) go in the same folder as the form.
        $attachments = \App\Models\DocumentAttachment::with('document.requirement')
            ->whereHas('document', fn ($q) => $q->where('status', 'approved')
                ->whereHas('internship', fn ($i) => $i->where('student_id', $student->id)))
            ->get()
            ->filter(fn ($attachment) => Storage::exists($attachment->file_path));

        if ($documents->isEmpty() && $attachments->isEmpty()) {
            return redirect()
                ->route('coordinator.repository')
                ->with('status', 'No approved files are on record for this student yet.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'docs_');
        $zip = new ZipArchive();
        abort_unless($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500, 'Could not create the ZIP file.');

        $used = [];
        foreach ($documents as $document) {
            $extension = pathinfo($document->original_filename ?: $document->file_path, PATHINFO_EXTENSION);
            $base = trim(str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '-', $document->requirement->name));
            $folder = match ($document->requirement->stage) {
                'pre_deployment' => 'Pre-Deployment',
                'post_deployment' => 'Post-Deployment',
                default => 'During Training',
            };
            $entry = $folder.'/'.$base.($extension ? '.'.$extension : '');

            // Keep names unique if two files would collide.
            $n = 2;
            while (isset($used[strtolower($entry)])) {
                $entry = $folder.'/'.$base.' ('.$n++.')'.($extension ? '.'.$extension : '');
            }
            $used[strtolower($entry)] = true;

            $zip->addFromString($entry, Storage::get($document->file_path)); // works on local disk and cloud buckets
        }
        foreach ($attachments as $attachment) {
            $extension = pathinfo($attachment->original_filename ?: $attachment->file_path, PATHINFO_EXTENSION);
            $base = trim(str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '-', $attachment->label()));
            $entry = 'Pre-Deployment/'.$base.($extension ? '.'.$extension : '');
            $n = 2;
            while (isset($used[strtolower($entry)])) {
                $entry = 'Pre-Deployment/'.$base.' ('.$n++.')'.($extension ? '.'.$extension : '');
            }
            $used[strtolower($entry)] = true;
            $zip->addFromString($entry, Storage::get($attachment->file_path));
        }
        $zip->close();

        $label = Str::slug(($student->user->id_number ?? 'student').'-'.($student->user->name ?? ''));

        return response()->download($tmp, $label.'-documents.zip')->deleteFileAfterSend(true);
    }

    public function download(Document $document): StreamedResponse
    {
        $campusId = auth()->user()->campus_id;

        abort_unless($document->internship->campus_id === $campusId, 403);
        abort_if(blank($document->file_path), 404, 'No file has been uploaded for this document yet.');
        abort_unless(Storage::exists($document->file_path), 404);

        return Storage::download($document->file_path, $document->original_filename);
    }
}
