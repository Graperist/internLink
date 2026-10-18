<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\Hte;
use App\Models\HteListing;
use App\Models\Instructor;
use App\Models\Program;
use App\Support\AuditLogger;
use App\Support\Term;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Partner Agencies - the repository of partner industries, per program and
 * per academic year + semester. This is the first step of the internship
 * process (PM-PSU-03-AA-06, Note 2): the subject instructor keeps the list
 * of partner agencies so students can choose one on their Application for
 * OJT, and so the program can contact them about taking interns.
 *
 *   - Instructors add / edit / remove entries for their own program.
 *   - The OJT Coordinator does the same for the program they teach, and can
 *     VIEW every program's list (campus-wide, like an admin).
 *   - The HTE itself (name, address, contacts, MOA, DTR location) is one
 *     shared campus record, so a company two programs work with isn't
 *     entered twice; a listing ties it to a program for one term.
 *   - Printing produces FM-AA-INT-06 with only what that form asks for
 *     (see ReportController@partnerIndustries).
 */
class HteController extends Controller
{
    public function index(Request $request): View
    {
        $instructor = auth()->user()->instructor;
        [$academicYear, $semester] = $this->term($request);
        $programId = $this->programFilter($request, $instructor);

        $listings = HteListing::with(['hte', 'program', 'addedBy'])
            ->where('campus_id', $instructor->campus_id)
            ->forTerm($academicYear, $semester)
            ->when($programId, fn ($q) => $q->where('program_id', $programId))
            ->when($search = $request->query('search'), fn ($q) => $q->whereHas('hte', fn ($h) => $h->where('name', 'like', "%{$search}%")))
            ->when($moa = $request->query('moa_status'), fn ($q) => $q->whereHas('hte', fn ($h) => $h->where('moa_status', $moa)))
            ->get()
            ->sortBy(fn ($l) => $l->hte->name.' '.$l->program->code, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $internCounts = self::internCounts($instructor->campus_id, $academicYear, $semester);
        foreach ($listings as $listing) {
            $listing->interns_count = $internCounts[$listing->program_id.'|'.$listing->hte_id] ?? 0;
        }

        // Are the existing slots enough? Compare the open slots on this
        // term's list with the students who still have no placement. If
        // they aren't, the program gathers more partner agencies and adds
        // them to this list.
        $isCurrentTerm = [$academicYear, $semester] === Term::current();
        $summary = [
            'agencies' => $listings->count(),
            'slots' => (int) $listings->sum('slots'),
            'placed' => (int) $listings->sum('interns_count'),
            'open' => (int) $listings->sum(fn ($l) => $l->slots ? max($l->slots - $l->interns_count, 0) : 0),
            'missing_slots' => $listings->whereNull('slots')->count(),
            'unplaced' => $isCurrentTerm
                ? $instructor->visibleStudents()
                    ->when($programId, fn ($q) => $q->where('program_id', $programId))
                    ->whereHas('currentInternship', fn ($q) => $q->where('status', 'pre_deployment'))
                    ->whereDoesntHave('currentInternship.deployment')
                    ->count()
                : null,
        ];

        [$prevYear, $prevSemester] = Term::previous($academicYear, $semester);
        $canCopy = $instructor->program_id !== null
            && HteListing::where('program_id', $instructor->program_id)->forTerm($prevYear, $prevSemester)->exists();

        $programs = $instructor->isCoordinator()
            ? Program::where('campus_id', $instructor->campus_id)->orderBy('code')->get()
            : collect();

        return view('instructor.htes.index', [
            'instructor' => $instructor,
            'listings' => $listings,
            'programs' => $programs,
            'programId' => $programId,
            'academicYear' => $academicYear,
            'semester' => $semester,
            'academicYears' => $this->academicYearOptions($academicYear),
            'semesters' => Term::SEMESTERS,
            'previousTerm' => "{$prevSemester}, AY {$prevYear}",
            'canCopy' => $canCopy,
            'summary' => $summary,
            'isCurrentTerm' => $isCurrentTerm,
        ]);
    }

    public function create(Request $request): View
    {
        $instructor = $this->instructorWithProgram();
        [$academicYear, $semester] = $this->term($request);

        // Agencies already on the campus record (entered by any program),
        // so an instructor can pick one instead of typing it again.
        $existing = Hte::where('campus_id', $instructor->campus_id)->orderBy('name')->get();

        return view('instructor.htes.form', [
            'listing' => new HteListing(['academic_year' => $academicYear, 'semester' => $semester]),
            'hte' => null,
            'existing' => $existing,
            'instructor' => $instructor,
            'academicYears' => $this->academicYearOptions($academicYear),
            'semesters' => Term::SEMESTERS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $instructor = $this->instructorWithProgram();

        $request->merge(['academic_year' => Term::normalizeAcademicYear($request->input('academic_year'))]);
        $data = $request->validate(array_merge([
            'existing_hte_id' => ['nullable', 'integer', Rule::exists('htes', 'id')->where('campus_id', $instructor->campus_id)],
            'academic_year' => ['required', 'string', function ($attr, $value, $fail) {
                if (! Term::isValidAcademicYear($value)) {
                    $fail('Type the academic year like 2026-2027 (or just 2026).');
                }
            }],
            'semester' => ['required', Rule::in(Term::SEMESTERS)],
        ], self::listingRules(), self::hteRules()));

        $listing = DB::transaction(function () use ($data, $instructor) {
            $details = collect($data)->only(array_keys(self::hteRules()))->all();

            if (! empty($data['existing_hte_id'])) {
                $hte = Hte::findOrFail($data['existing_hte_id']);
                $hte->update($details);
            } else {
                // Same name already on the campus? Reuse it instead of a duplicate.
                $hte = Hte::where('campus_id', $instructor->campus_id)->where('name', $details['name'])->first();
                if ($hte) {
                    $hte->update($details);
                } else {
                    $hte = Hte::create($details + ['campus_id' => $instructor->campus_id]);
                }
            }

            $duplicate = HteListing::where('program_id', $instructor->program_id)
                ->where('hte_id', $hte->id)
                ->forTerm($data['academic_year'], $data['semester'])
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'name' => "{$hte->name} is already on your {$data['semester']} {$data['academic_year']} list.",
                ]);
            }

            return HteListing::create([
                'campus_id' => $instructor->campus_id,
                'program_id' => $instructor->program_id,
                'hte_id' => $hte->id,
                'academic_year' => $data['academic_year'],
                'semester' => $data['semester'],
                'slots' => $data['slots'],
                'remarks' => $data['remarks'] ?? null,
                'added_by_user_id' => auth()->id(),
            ]);
        });

        $listing->load('hte', 'program');
        AuditLogger::record('hte_listing.created', "Added {$listing->hte->name} to the {$listing->program->code} partner list for {$listing->semester} {$listing->academic_year}.", auth()->user(), $request, $listing, $instructor->campus_id);

        return redirect()
            ->route('instructor.htes', ['academic_year' => $listing->academic_year, 'semester' => $listing->semester])
            ->with('status', "{$listing->hte->name} was added to your partner agencies.");
    }

    public function edit(HteListing $listing): View
    {
        $instructor = auth()->user()->instructor;
        $this->authorizeManage($listing, $instructor);
        $listing->load('hte', 'program');

        return view('instructor.htes.form', [
            'listing' => $listing,
            'hte' => $listing->hte,
            'existing' => collect(),
            'instructor' => $instructor,
            'academicYears' => [$listing->academic_year],
            'semesters' => [$listing->semester],
        ]);
    }

    public function update(Request $request, HteListing $listing): RedirectResponse
    {
        $instructor = auth()->user()->instructor;
        $this->authorizeManage($listing, $instructor);

        $data = $request->validate(array_merge(self::listingRules(), self::hteRules()));

        DB::transaction(function () use ($data, $listing) {
            $listing->hte->update(collect($data)->only(array_keys(self::hteRules()))->all());
            $listing->update(['slots' => $data['slots'], 'remarks' => $data['remarks'] ?? null]);
        });

        AuditLogger::record('hte_listing.updated', "Updated partner agency {$listing->hte->name}.", auth()->user(), $request, $listing, $listing->campus_id);

        return redirect()
            ->route('instructor.htes', ['academic_year' => $listing->academic_year, 'semester' => $listing->semester])
            ->with('status', "{$listing->hte->name} was updated.");
    }

    /** Remove from this program's list for this term. The HTE record stays. */
    public function destroy(Request $request, HteListing $listing): RedirectResponse
    {
        $instructor = auth()->user()->instructor;
        $this->authorizeManage($listing, $instructor);
        $listing->load('hte');

        $placed = self::internCounts($listing->campus_id, $listing->academic_year, $listing->semester)[$listing->program_id.'|'.$listing->hte_id] ?? 0;
        if ($placed > 0) {
            return back()->withErrors(['listing' => "{$listing->hte->name} already has {$placed} of your interns this term, so it stays on the list."]);
        }

        $listing->delete();

        AuditLogger::record('hte_listing.deleted', "Removed {$listing->hte->name} from the partner list for {$listing->semester} {$listing->academic_year}.", auth()->user(), $request, null, $listing->campus_id);

        return back()->with('status', "{$listing->hte->name} was removed from this term's list.");
    }

    /** Start a new term from last term's list (own program). */
    public function copyPrevious(Request $request): RedirectResponse
    {
        $instructor = $this->instructorWithProgram();
        $request->merge(['academic_year' => Term::normalizeAcademicYear($request->input('academic_year'))]);
        $data = $request->validate([
            'academic_year' => ['required', 'string'],
            'semester' => ['required', Rule::in(Term::SEMESTERS)],
        ]);
        abort_unless(Term::isValidAcademicYear($data['academic_year']), 422);

        [$prevYear, $prevSemester] = Term::previous($data['academic_year'], $data['semester']);

        $already = HteListing::where('program_id', $instructor->program_id)
            ->forTerm($data['academic_year'], $data['semester'])->pluck('hte_id');

        $copied = 0;
        HteListing::where('program_id', $instructor->program_id)
            ->forTerm($prevYear, $prevSemester)
            ->whereNotIn('hte_id', $already)
            ->get()
            ->each(function ($old) use ($data, &$copied) {
                HteListing::create([
                    'campus_id' => $old->campus_id,
                    'program_id' => $old->program_id,
                    'hte_id' => $old->hte_id,
                    'academic_year' => $data['academic_year'],
                    'semester' => $data['semester'],
                    'slots' => $old->slots,
                    'remarks' => $old->remarks,
                    'added_by_user_id' => auth()->id(),
                ]);
                $copied++;
            });

        return redirect()
            ->route('instructor.htes', $data)
            ->with('status', $copied
                ? "Copied {$copied} partner agenc".($copied === 1 ? 'y' : 'ies')." from {$prevSemester} {$prevYear}. Review their details before contacting them."
                : 'Nothing new to copy from last term.');
    }

    // ------------------------------------------------------------------

    /**
     * Interns per program + HTE in a term: deployments whose start date (or,
     * before the OJT has started, the date the placement was made) falls in
     * the term. Keyed "programId|hteId".
     *
     * @return array<string,int>
     */
    public static function internCounts(int $campusId, string $academicYear, string $semester): array
    {
        [$from, $to] = Term::range($academicYear, $semester);

        return Deployment::query()
            ->join('internships', 'internships.id', '=', 'deployments.internship_id')
            ->where('internships.campus_id', $campusId)
            ->whereIn('deployments.status', ['pending', 'active', 'completed'])
            ->where(fn ($q) => $q
                ->whereBetween('deployments.start_date', [$from->toDateString(), $to->toDateString()])
                ->orWhere(fn ($q) => $q->whereNull('deployments.start_date')
                    ->whereBetween('deployments.created_at', [$from, $to])))
            ->selectRaw('internships.program_id, deployments.hte_id, count(distinct internships.student_id) as total')
            ->groupBy('internships.program_id', 'deployments.hte_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->program_id.'|'.$row->hte_id => (int) $row->total])
            ->all();
    }

    /** Full partner details - every field the program needs to contact them. */
    public static function hteRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'representative_position' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'moa_status' => ['required', Rule::in(['pending', 'active', 'expired'])],
        ];
    }

    private static function listingRules(): array
    {
        return [
            // Number of interns the HTE agreed to take this term.
            'slots' => ['required', 'integer', 'min:1', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array{0:string,1:string} */
    private function term(Request $request): array
    {
        [$defaultYear, $defaultSemester] = Term::current();
        $year = Term::normalizeAcademicYear($request->query('academic_year', $request->input('academic_year')));
        $semester = $request->query('semester', $request->input('semester'));

        return [
            Term::isValidAcademicYear($year) ? $year : $defaultYear,
            in_array($semester, Term::SEMESTERS, true) ? $semester : $defaultSemester,
        ];
    }

    /** Instructor: always their own program. Coordinator: any program, or all. */
    private function programFilter(Request $request, Instructor $instructor): ?int
    {
        if (! $instructor->isCoordinator()) {
            return $instructor->program_id ?? 0;
        }

        $value = $request->query('program_id');
        if ($value === null) {
            return null; // all programs by default for the coordinator
        }

        return Program::where('campus_id', $instructor->campus_id)->whereKey($value)->exists() ? (int) $value : null;
    }

    private function academicYearOptions(string $selected): array
    {
        return collect(Term::academicYearOptions())->push($selected)->unique()->sortDesc()->values()->all();
    }

    private function authorizeManage(HteListing $listing, ?Instructor $instructor): void
    {
        abort_unless($instructor && $instructor->canManageListing($listing), 403);
    }

    private function instructorWithProgram(): Instructor
    {
        $instructor = auth()->user()->instructor;
        abort_unless($instructor, 403);
        abort_if($instructor->program_id === null, 403, 'Set the program you teach in My Profile before adding partner agencies.');

        return $instructor;
    }

    /**
     * Slots per HTE on a program's list for a term: what the HTE gave and
     * how many of the program's interns are already placed there.
     *
     * @return array<int,array{slots:?int,taken:int,full:bool}> keyed by hte_id
     */
    public static function availability(int $campusId, int $programId, string $academicYear, string $semester): array
    {
        $counts = self::internCounts($campusId, $academicYear, $semester);

        return HteListing::where('program_id', $programId)
            ->forTerm($academicYear, $semester)
            ->get()
            ->mapWithKeys(function ($listing) use ($counts) {
                $taken = $counts[$listing->program_id.'|'.$listing->hte_id] ?? 0;

                return [$listing->hte_id => [
                    'slots' => $listing->slots,
                    'taken' => $taken,
                    'full' => $listing->slots !== null && $taken >= $listing->slots,
                ]];
            })
            ->all();
    }

    /** @return Collection<int,Hte> HTEs a program lists for a term (used by student forms) */
    public static function listedHtes(int $programId, string $academicYear, string $semester): Collection
    {
        return Hte::whereHas('listings', fn ($q) => $q->where('program_id', $programId)->forTerm($academicYear, $semester))
            ->orderBy('name')
            ->get();
    }
}
