<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The academic year + semester filter shared by the list pages
 * (Students, Repository, Document Review, Time Frame, Evaluations,
 * Dashboard, ...). The current academic year and both semesters by
 * default; "all" (or the year box left empty) for every year.
 */
class TermFilter
{
    public function __construct(
        public readonly string $year,        // 'all' or "2026-2027"
        public readonly ?string $semester,   // null = both semesters
        public readonly string $currentYear,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $current = Term::current()[0];
        $semester = Term::isValidSemester($s = $request->query('semester')) ? $s : null;

        if (! $request->has('academic_year')) {
            return new self($current, $semester, $current);
        }
        $typed = (string) $request->query('academic_year');
        if ($typed === '' || $typed === 'all') {
            return new self('all', $semester, $current);
        }
        $year = Term::normalizeAcademicYear($typed);

        return new self(Term::isValidAcademicYear($year) ? $year : $current, $semester, $current);
    }

    /** Limit a students query (or a query joined to students) to the term. */
    public function applyToStudents($query, string $table = '')
    {
        $col = fn (string $c) => $table !== '' ? "{$table}.{$c}" : $c;

        return $query
            ->when($this->year !== 'all', fn ($q) => $q->where($col('academic_year'), $this->year))
            ->when($this->semester, fn ($q) => $q->where($col('semester'), $this->semester));
    }

    /** Limit a query of records that belong to a student (through $relation) to the term. */
    public function applyThrough($query, string $relation)
    {
        if (! $this->isFiltering()) {
            return $query;
        }

        return $query->whereHas($relation, fn ($q) => $this->applyToStudents($q));
    }

    public function isFiltering(): bool
    {
        return $this->year !== 'all' || $this->semester !== null;
    }

    public function isDefault(): bool
    {
        return $this->year === $this->currentYear && $this->semester === null;
    }

    /** e.g. "AY 2026-2027 (current) · 1st Semester" */
    public function label(): string
    {
        $year = $this->year === 'all' ? 'All academic years'
            : 'AY '.$this->year.($this->year === $this->currentYear ? ' (current)' : '');

        return $year.($this->semester ? ' · '.$this->semester : '');
    }

    /** Query parameters that keep this term in links (none for the default). */
    public function query(): array
    {
        return array_filter([
            'academic_year' => $this->year === $this->currentYear ? null : $this->year,
            'semester' => $this->semester,
        ]);
    }
}
