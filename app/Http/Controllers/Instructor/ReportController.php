<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\Document;
use App\Models\Hte;
use App\Models\HteListing;
use App\Models\Instructor;
use App\Models\Program;
use App\Models\User;
use App\Support\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Generate Reports (Subject Instructor) - three printable forms, filled in
     * from the system:
     *   FM-AA-INT-09  Masterlist of Student-Trainees (one page per company)
     *   FM-AA-INT-06  Masterlist of Partner Industries
     *   FM-AA-INT-05  Directory of Student-Interns
     *   FM-AA-INT-16  Internship Site Monitoring Report (one page per company)
     *   FM-AA-INT-24  Accreditation Instrument of PSU Partner Agencies as HTE
     *
     * Campus-wide reports (Evaluation Summary, HTE Directory, Documents
     * Compliance) live in the Coordinator portal.
     */
    public function index(): View
    {
        $instructor = auth()->user()->instructor;

        // Partner HTEs where this instructor's advisees are placed.
        $htes = $this->traineeDeployments($instructor)
            ->groupBy('hte_id')
            ->map(fn ($group) => $group->first()->hte)
            ->filter()
            ->sortBy('name')
            ->values();

        [$semester, $academicYear] = $this->currentTerm();

        // Form 24: any agency on the program's (coordinator: campus's) partner agency lists.
        $partnerAgencies = $this->partnerAgencies($instructor);

        // The coordinator: every program's reports, with the campus summary on top.
        $programs = $instructor->isCoordinator()
            ? Program::where('campus_id', $instructor->campus_id)->orderBy('code')->get()
            : collect();
        $campusSummary = $instructor->isCoordinator()
            ? \App\Http\Controllers\Coordinator\ReportController::campusSummary($instructor->campus_id)
            : null;

        return view('instructor.reports.index', compact('instructor', 'htes', 'semester', 'academicYear', 'partnerAgencies', 'programs', 'campusSummary'));
    }

    /**
     * FM-AA-INT-09 - one page per partner company: company details, then the
     * instructor's student-trainees placed there (name, year, course).
     */
    public function studentTrainees(Request $request): View
    {
        $data = $request->validate(['hte_id' => ['nullable', 'integer'], 'program_id' => ['nullable', 'integer']]);

        $instructor = auth()->user()->instructor;

        $deployments = $this->traineeDeployments($instructor, $this->chosenProgramId($request, $instructor));

        if (! empty($data['hte_id'])) {
            $deployments = $deployments->where('hte_id', (int) $data['hte_id']);
            abort_if($deployments->isEmpty(), 404);
        }

        $students = $deployments->map(fn ($d) => $d->internship->student)->unique('id');
        $profiles = $this->applicationProfiles($students);

        $companies = $deployments
            ->groupBy('hte_id')
            ->map(function ($group) use ($profiles) {
                $trainees = $group
                    ->map(fn ($d) => $d->internship->student)
                    ->unique('id')
                    ->sortBy(fn ($student) => $student->user->name, SORT_NATURAL | SORT_FLAG_CASE)
                    ->map(fn ($student) => [
                        'name' => $student->user->name,
                        'year' => $this->yearLevel($profiles[$student->id]['year_section'] ?? ''),
                        'course' => $profiles[$student->id]['course'] ?? ($student->program?->code ?? ''),
                    ])
                    ->values();

                return ['hte' => $group->first()->hte, 'trainees' => $trainees];
            })
            ->sortBy(fn ($company) => $company['hte']->name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return view('instructor.reports.student-trainees', array_merge($this->signatories($instructor), [
            'companies' => $companies,
        ]));
    }

    /**
     * FM-AA-INT-06 - Masterlist of Partner Industries, printed from the
     * Partner Agency repository for one academic year + semester. Only what
     * the official form asks for is printed: the agency name and the total
     * number of student-trainees. Address, contacts and MOA stay on screen.
     *
     * Instructor: their own program. Coordinator: one program, or all
     * programs of the campus (an agency several programs list appears once,
     * with their trainees added up).
     */
    public function partnerIndustries(Request $request): View
    {
        [$defaultYear, $defaultSemester] = Term::current();
        $request->merge(['academic_year' => Term::normalizeAcademicYear($request->query('academic_year'))]);
        $data = $request->validate([
            'semester' => ['nullable', 'in:'.implode(',', Term::SEMESTERS)],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'program_id' => ['nullable', 'integer'],
        ]);

        $instructor = auth()->user()->instructor;
        $academicYear = Term::isValidAcademicYear($data['academic_year'] ?? null) ? $data['academic_year'] : $defaultYear;
        $semester = $data['semester'] ?? $defaultSemester;

        $program = null;
        if (! $instructor->isCoordinator()) {
            $program = $instructor->program;
        } elseif (! empty($data['program_id'])) {
            $program = Program::where('campus_id', $instructor->campus_id)->findOrFail($data['program_id']);
        }

        $counts = HteController::internCounts($instructor->campus_id, $academicYear, $semester);

        $rows = HteListing::with('hte')
            ->where('campus_id', $instructor->campus_id)
            ->forTerm($academicYear, $semester)
            ->when($program, fn ($q) => $q->where('program_id', $program->id), fn ($q) => $q->when(! $instructor->isCoordinator(), fn ($q) => $q->whereRaw('1 = 0')))
            ->get()
            ->groupBy('hte_id')
            ->map(fn ($group) => [
                'name' => $group->first()->hte->name,
                'total' => $group->sum(fn ($listing) => $counts[$listing->program_id.'|'.$listing->hte_id] ?? 0),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return view('instructor.reports.partner-industries', array_merge($this->signatories($instructor), [
            'rows' => $rows,
            'semester' => $semester,
            'academicYear' => $academicYear,
            'collegeName' => $program?->courseTitle() ?? ($instructor->campus?->name ?? ''),
        ]));
    }

    /**
     * FM-AA-INT-05 - directory of the instructor's student-interns: name,
     * home address, email address and contact number. Address and contact
     * number come from the student's Application for On-the-Job Training.
     */
    public function directory(Request $request): View
    {
        $request->merge(['academic_year' => Term::normalizeAcademicYear($request->query('academic_year'))]);
        $data = $request->validate([
            'semester' => ['nullable', 'in:2nd Semester'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'program_id' => ['nullable', 'integer'],
        ]);

        $instructor = auth()->user()->instructor;
        [$defaultSemester, $defaultAcademicYear] = $this->currentTerm();
        $programId = $this->chosenProgramId($request, $instructor);
        $chosenProgram = $programId ? Program::find($programId) : null;

        $students = $instructor->visibleStudents()->when($programId, fn ($q) => $q->where('program_id', $programId))
            ->with(['user', 'program'])->get()
            ->sortBy(fn ($student) => $student->user->name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $profiles = $this->applicationProfiles($students);

        $rows = $students->map(fn ($student) => [
            'name' => $student->user->name,
            'address' => $profiles[$student->id]['home_address'] ?? '',
            'email' => $student->user->email,
            'contact' => $profiles[$student->id]['contact_number'] ?? '',
        ]);

        return view('instructor.reports.directory', array_merge($this->signatories($instructor), [
            'rows' => $rows,
            'semester' => $data['semester'] ?? $defaultSemester,
            'academicYear' => $data['academic_year'] ?? $defaultAcademicYear,
            'collegeName' => $chosenProgram?->name ?? ($instructor->isCoordinator() ? ($instructor->campus?->name ?? '') : ($instructor->program?->name ?? '')),
        ]));
    }

    /**
     * FM-AA-INT-16 - Internship Site Monitoring Report, one page per company
     * visited: the company, the date of the visit and the student-trainees
     * placed there are filled in; the feedback, problems and actions taken
     * are written in during the visit.
     */
    public function siteMonitoring(Request $request): View
    {
        $data = $request->validate([
            'hte_id' => ['nullable', 'integer'],
            'monitoring_date' => ['nullable', 'date'],
            'program_id' => ['nullable', 'integer'],
        ]);

        $instructor = auth()->user()->instructor;
        $deployments = $this->traineeDeployments($instructor, $this->chosenProgramId($request, $instructor));

        if (! empty($data['hte_id'])) {
            $deployments = $deployments->where('hte_id', (int) $data['hte_id']);
            abort_if($deployments->isEmpty(), 404);
        }

        $profiles = $this->applicationProfiles($deployments->map(fn ($d) => $d->internship->student)->unique('id'));

        $companies = $deployments
            ->groupBy('hte_id')
            ->map(fn ($group) => [
                'hte' => $group->first()->hte,
                'trainees' => $group->map(fn ($d) => $d->internship->student)->unique('id')
                    ->sortBy(fn ($student) => $student->user->name, SORT_NATURAL | SORT_FLAG_CASE)
                    ->map(fn ($student) => [
                        'name' => $student->user->name,
                        'course' => $profiles[$student->id]['course'] ?? ($student->program?->code ?? ''),
                        'year' => $this->yearLevel($profiles[$student->id]['year_section'] ?? ''),
                    ])->values(),
            ])
            ->sortBy(fn ($company) => $company['hte']->name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return view('instructor.reports.site-monitoring', array_merge($this->signatories($instructor), [
            'companies' => $companies,
            'monitoringDate' => ! empty($data['monitoring_date']) ? \Illuminate\Support\Carbon::parse($data['monitoring_date'])->format('F d, Y') : '',
        ]));
    }

    /**
     * FM-AA-INT-24 - Accreditation Instrument of PSU Partner Agencies as Host
     * Training Establishment: the eight criteria with Compliant /
     * Non-Compliant / Remarks to check on paper, for one partner agency (its
     * name printed on top) or blank.
     */
    public function accreditation(Request $request): View
    {
        $data = $request->validate(['hte_id' => ['nullable', 'integer']]);
        $instructor = auth()->user()->instructor;

        $hte = null;
        if (! empty($data['hte_id'])) {
            $hte = $this->partnerAgencies($instructor)->firstWhere('id', (int) $data['hte_id']);
            abort_if($hte === null, 404);
        }

        return view('instructor.reports.accreditation', array_merge($this->signatories($instructor), [
            'hte' => $hte,
            'criteria' => self::ACCREDITATION_CRITERIA,
        ]));
    }

    /** The criteria of FM-AA-INT-24, as printed on the official form. */
    public const ACCREDITATION_CRITERIA = [
        'A legitimate business entity, government agency, and accredited foreign partner recognized by the Philippine authorities.',
        'A duly signed Memorandum of Agreement is required for the partnership.',
        'In operation for a minimum of five (5) years.',
        'Willing and committed to provide the student-interns with high quality work-related experiences.',
        'Assure the safety of student-interns.',
        'Permit the Internship/Practicum Subject Instructor/ Campus Internship Coordinator/ Department Chairperson and/or Authorized Person to visit the host training site to meet directly with the Agency Supervisor and Student Intern (or other appropriate officials) to review the student-intern\'s progress.',
        'Presence of adequate resources and facilities to provide relevant learning opportunities for student-interns.',
        'Known to be a reputable Host Training Establishment with excellent performance and harmonious relationship with the interested parties.',
    ];

    /** Partner agencies on the program's lists (the coordinator: the whole campus's), any term. */
    private function partnerAgencies(Instructor $instructor): Collection
    {
        return Hte::where('campus_id', $instructor->campus_id)
            ->whereHas('listings', fn ($q) => $instructor->isCoordinator() ? $q : $q->where('program_id', $instructor->program_id))
            ->orderBy('name')
            ->get();
    }

    /**
     * Deployments of this instructor's advisees (same campus + program) that
     * have a partner HTE. Terminated placements are not counted.
     */
    private function traineeDeployments(Instructor $instructor, ?int $programId = null)
    {
        return Deployment::with(['hte', 'internship.student.user', 'internship.student.program'])
            ->whereNotNull('hte_id')
            ->whereIn('status', ['pending', 'active', 'completed'])
            ->whereHas('internship', fn ($q) => $instructor->limitInternships($q)->when($programId, fn ($q) => $q->where('program_id', $programId)))
            ->get();
    }

    /**
     * The coordinator can print any program's report: the program chosen on
     * the card (empty = all programs). An instructor's reports are always
     * their own program, so the choice is ignored for them.
     */
    private function chosenProgramId(Request $request, Instructor $instructor): ?int
    {
        if (! $instructor->isCoordinator() || ! $request->filled('program_id')) {
            return null;
        }

        return Program::where('campus_id', $instructor->campus_id)->findOrFail((int) $request->input('program_id'))->id;
    }

    /**
     * Course, year & section, home address and contact number as the student
     * entered them on the Application for On-the-Job Training, keyed by
     * student id.
     *
     * @param  Collection  $students
     * @return array<int,array<string,string>>
     */
    private function applicationProfiles(Collection $students): array
    {
        $documents = Document::with(['fieldValues', 'internship'])
            ->whereHas('requirement', fn ($q) => $q->where('code', 'APPLICATION_FOR_OJT'))
            ->whereHas('internship', fn ($q) => $q->whereIn('student_id', $students->pluck('id')))
            ->get();

        $profiles = [];

        foreach ($documents as $document) {
            $profiles[$document->internship->student_id] = [
                'course' => (string) $document->fieldValue('course'),
                'year_section' => (string) $document->fieldValue('year_section'),
                'home_address' => (string) ($document->fieldValue('home_address') ?: $document->fieldValue('current_address')),
                'contact_number' => (string) $document->fieldValue('contact_number'),
            ];
        }

        return $profiles;
    }

    /** "IV- A" / "4A" / "3rd" -> "4th Year" style label; falls back to the text as typed. */
    private function yearLevel(string $yearSection): string
    {
        $text = trim($yearSection);

        if ($text === '') {
            return '';
        }

        $romans = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, 'V' => 5];

        if (preg_match('/^\s*(IV|V|I{1,3})\b/i', $text, $m)) {
            $number = $romans[strtoupper($m[1])];
        } elseif (preg_match('/^\s*(\d)/', $text, $m)) {
            $number = (int) $m[1];
        } else {
            return $text;
        }

        $suffix = match ($number) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' };

        return $number.$suffix;
    }

    /**
     * Names printed in the signature blocks, all taken from records - the
     * instructor, the campus's OJT Coordinator, and the campus's CED.
     */
    private function signatories(Instructor $instructor): array
    {
        $campus = $instructor->campus;

        $coordinator = User::where('campus_id', $instructor->campus_id)
            ->whereHas('role', fn ($q) => $q->where('code', 'coordinator'))
            ->orderBy('id')
            ->first();

        $campusLabel = preg_replace(
            '/^Pangasinan State University\s*[-,:]\s*/i',
            '',
            trim((string) ($campus?->name ?? ''))
        );

        return [
            'campusLabel' => $campusLabel !== '' ? $campusLabel : 'Alaminos City Campus',
            'instructorName' => $instructor->user->name,
            'coordinatorName' => $coordinator?->name ?? '',
            'cedName' => trim((string) ($campus?->ced_name ?? '')),
            'deanName' => trim((string) ($campus?->college_dean_name ?? '')),
        ];
    }

    /**
     * Internships run in the 2nd semester only, so the semester is always
     * "2nd Semester". The academic year starts in August: Aug-Dec belongs to
     * the year that began that August, Jan-Jul to the year that began the
     * August before.
     */
    private function currentTerm(): array
    {
        $month = (int) now()->format('n');
        $year = (int) now()->format('Y');

        $startYear = $month >= 8 ? $year : $year - 1;

        return ['2nd Semester', $startYear.'-'.($startYear + 1)];
    }
}
