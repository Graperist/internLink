<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\Document;
use App\Models\Hte;
use App\Models\Instructor;
use App\Models\User;
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

        return view('instructor.reports.index', compact('instructor', 'htes', 'semester', 'academicYear'));
    }

    /**
     * FM-AA-INT-09 - one page per partner company: company details, then the
     * instructor's student-trainees placed there (name, year, course).
     */
    public function studentTrainees(Request $request): View
    {
        $data = $request->validate(['hte_id' => ['nullable', 'integer']]);

        $instructor = auth()->user()->instructor;

        $deployments = $this->traineeDeployments($instructor);

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
     * FM-AA-INT-06 - one row per partner industry, with the number of this
     * instructor's student-trainees placed there.
     */
    public function partnerIndustries(Request $request): View
    {
        $data = $request->validate([
            'semester' => ['nullable', 'in:2nd Semester'],
            'academic_year' => ['nullable', 'string', 'max:20'],
        ]);

        $instructor = auth()->user()->instructor;
        [$defaultSemester, $defaultAcademicYear] = $this->currentTerm();

        $rows = $this->traineeDeployments($instructor)
            ->groupBy('hte_id')
            ->map(fn ($group) => [
                'name' => $group->first()->hte->name,
                'total' => $group->pluck('internship.student_id')->unique()->count(),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return view('instructor.reports.partner-industries', array_merge($this->signatories($instructor), [
            'rows' => $rows,
            'semester' => $data['semester'] ?? $defaultSemester,
            'academicYear' => $data['academic_year'] ?? $defaultAcademicYear,
            'collegeName' => $instructor->isCoordinator() ? ($instructor->campus?->name ?? '') : ($instructor->program?->name ?? ''),
        ]));
    }

    /**
     * FM-AA-INT-05 - directory of the instructor's student-interns: name,
     * home address, email address and contact number. Address and contact
     * number come from the student's Application for On-the-Job Training.
     */
    public function directory(Request $request): View
    {
        $data = $request->validate([
            'semester' => ['nullable', 'in:2nd Semester'],
            'academic_year' => ['nullable', 'string', 'max:20'],
        ]);

        $instructor = auth()->user()->instructor;
        [$defaultSemester, $defaultAcademicYear] = $this->currentTerm();

        $students = $instructor->visibleStudents()->with(['user', 'program'])->get()
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
            'collegeName' => $instructor->isCoordinator() ? ($instructor->campus?->name ?? '') : ($instructor->program?->name ?? ''),
        ]));
    }

    /**
     * Deployments of this instructor's advisees (same campus + program) that
     * have a partner HTE. Terminated placements are not counted.
     */
    private function traineeDeployments(Instructor $instructor)
    {
        return Deployment::with(['hte', 'internship.student.user', 'internship.student.program'])
            ->whereNotNull('hte_id')
            ->whereIn('status', ['pending', 'active', 'completed'])
            ->whereHas('internship', fn ($q) => $instructor->limitInternships($q))
            ->get();
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
