<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Academic terms (academic year + semester) used by partner agencies,
 * student enrollment, the Repository and the reports.
 *
 * Interns can be enrolled and partner agencies listed in the 1st or the
 * 2nd Semester. An academic year "2026-2027" starts in August 2026: its
 * 1st Semester runs Aug - Dec 2026, its 2nd Semester Jan - Jul 2027.
 */
class Term
{
    public const FIRST = '1st Semester';

    public const SECOND = '2nd Semester';

    /** Kept for older callers: the default semester. */
    public const SEMESTER = self::SECOND;

    public const SEMESTERS = [self::FIRST, self::SECOND];

    /** @return array{0:string,1:string} [academic year, semester] for a date (default: today) */
    public static function forDate(?CarbonInterface $date = null): array
    {
        $date = CarbonImmutable::instance($date ?? now());
        $month = (int) $date->format('n');
        $year = (int) $date->format('Y');

        // Aug - Dec: 1st Semester of the year starting now; Jan - Jul: 2nd Semester of last year's.
        return $month >= 8
            ? [self::academicYear($year), self::FIRST]
            : [self::academicYear($year - 1), self::SECOND];
    }

    public static function current(): array
    {
        return self::forDate();
    }

    public static function academicYear(int $startYear): string
    {
        return $startYear.'-'.($startYear + 1);
    }

    public static function isValidSemester(?string $value): bool
    {
        return in_array($value, self::SEMESTERS, true);
    }

    /**
     * Reads a typed academic year: "2026" (the first year), "2026-2027",
     * "2026 - 2027", "2026/2027" or "2026-27" all become "2026-2027".
     * Anything else is returned unchanged (and fails isValidAcademicYear).
     */
    public static function normalizeAcademicYear(?string $value): ?string
    {
        $value = trim((string) $value);
        if (preg_match('/^(\d{4})$/', $value, $m)) {
            return self::academicYear((int) $m[1]);
        }
        if (preg_match('/^(\d{4})\s*[-\/–]\s*(\d{2}|\d{4})$/u', $value, $m)) {
            $start = (int) $m[1];
            $end = strlen($m[2]) === 2 ? (int) (substr($m[1], 0, 2).$m[2]) : (int) $m[2];

            return $end === $start + 1 ? self::academicYear($start) : $value;
        }

        return $value === '' ? null : $value;
    }

    public static function isValidAcademicYear(?string $value): bool
    {
        return (bool) preg_match('/^(\d{4})-(\d{4})$/', (string) $value, $m) && (int) $m[2] === (int) $m[1] + 1;
    }

    /**
     * First and last day of a term: 1st Semester Aug 1 - Dec 31,
     * 2nd Semester Jan 1 - Jul 31.
     *
     * @return array{0:CarbonImmutable,1:CarbonImmutable}
     */
    public static function range(string $academicYear, string $semester = self::SECOND): array
    {
        $start = (int) substr($academicYear, 0, 4);

        return $semester === self::FIRST
            ? [CarbonImmutable::create($start, 8, 1), CarbonImmutable::create($start, 12, 31)->endOfDay()]
            : [CarbonImmutable::create($start + 1, 1, 1), CarbonImmutable::create($start + 1, 7, 31)->endOfDay()];
    }

    /**
     * First and last day of a whole academic year (Aug 1 - Jul 31).
     *
     * @return array{0:CarbonImmutable,1:CarbonImmutable}
     */
    public static function yearRange(string $academicYear): array
    {
        $start = (int) substr($academicYear, 0, 4);

        return [CarbonImmutable::create($start, 8, 1), CarbonImmutable::create($start + 1, 7, 31)->endOfDay()];
    }

    /** @return array{0:string,1:string} the term just before: 2nd Sem -> 1st Sem of the same year; 1st Sem -> last year's 2nd Sem */
    public static function previous(string $academicYear, string $semester = self::SECOND): array
    {
        if ($semester === self::SECOND) {
            return [$academicYear, self::FIRST];
        }
        $start = (int) substr($academicYear, 0, 4);

        return [self::academicYear($start - 1), self::SECOND];
    }

    /** Academic years offered in pickers: a few back, current, next. */
    public static function academicYearOptions(): array
    {
        $current = (int) substr(self::current()[0], 0, 4);

        return collect(range($current + 1, $current - 4))
            ->map(fn ($y) => self::academicYear($y))
            ->all();
    }
}
