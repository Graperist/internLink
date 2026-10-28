<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Academic terms used by the Partner Agency repository and the
 * FM-AA-INT-06 Masterlist of Partner Industries.
 *
 * The internship (OJT) is only in the 2nd Semester, so a term is an
 * academic year's 2nd Semester. An academic year "2026-2027" starts in
 * August 2026; its 2nd Semester runs Jan - May 2027.
 */
class Term
{
    /** The only semester with an internship. */
    public const SEMESTER = '2nd Semester';

    public const SEMESTERS = [self::SEMESTER];

    /** @return array{0:string,1:string} [academic year, semester] for a date (default: today) */
    public static function forDate(?CarbonInterface $date = null): array
    {
        $date = CarbonImmutable::instance($date ?? now());
        $month = (int) $date->format('n');
        $year = (int) $date->format('Y');

        // Aug - Dec: the 2nd Semester coming up in January; Jan - Jul: the one this year.
        $startYear = $month >= 8 ? $year : $year - 1;

        return [self::academicYear($startYear), self::SEMESTER];
    }

    public static function current(): array
    {
        return self::forDate();
    }

    public static function academicYear(int $startYear): string
    {
        return $startYear.'-'.($startYear + 1);
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
     * First and last day of a term. With the internship only in the 2nd
     * Semester, the term takes in the whole academic year (Aug 1 - Jul 31),
     * so an internship that starts early or runs late still counts.
     *
     * @return array{0:CarbonImmutable,1:CarbonImmutable}
     */
    public static function range(string $academicYear, string $semester = self::SEMESTER): array
    {
        $start = (int) substr($academicYear, 0, 4);

        return [CarbonImmutable::create($start, 8, 1), CarbonImmutable::create($start + 1, 7, 31)->endOfDay()];
    }

    /** @return array{0:string,1:string} the term just before the given one: last academic year's 2nd Semester */
    public static function previous(string $academicYear, string $semester = self::SEMESTER): array
    {
        $start = (int) substr($academicYear, 0, 4);

        return [self::academicYear($start - 1), self::SEMESTER];
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
