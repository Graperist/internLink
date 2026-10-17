<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Academic terms used by the Partner Agency repository and the
 * FM-AA-INT-06 Masterlist of Partner Industries.
 *
 * An academic year "2026-2027" starts in August 2026:
 *   1st Semester  Aug - Dec 2026
 *   2nd Semester  Jan - May 2027
 *   Midyear       Jun - Jul 2027
 */
class Term
{
    public const SEMESTERS = ['1st Semester', '2nd Semester', 'Midyear'];

    /** @return array{0:string,1:string} [academic year, semester] for a date (default: today) */
    public static function forDate(?CarbonInterface $date = null): array
    {
        $date = CarbonImmutable::instance($date ?? now());
        $month = (int) $date->format('n');
        $year = (int) $date->format('Y');

        $startYear = $month >= 8 ? $year : $year - 1;
        $semester = match (true) {
            $month >= 8 => '1st Semester',
            $month <= 5 => '2nd Semester',
            default => 'Midyear',
        };

        return [self::academicYear($startYear), $semester];
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

    /** @return array{0:CarbonImmutable,1:CarbonImmutable} first and last day of a term */
    public static function range(string $academicYear, string $semester): array
    {
        $start = (int) substr($academicYear, 0, 4);

        return match ($semester) {
            '1st Semester' => [CarbonImmutable::create($start, 8, 1), CarbonImmutable::create($start, 12, 31)->endOfDay()],
            '2nd Semester' => [CarbonImmutable::create($start + 1, 1, 1), CarbonImmutable::create($start + 1, 5, 31)->endOfDay()],
            default => [CarbonImmutable::create($start + 1, 6, 1), CarbonImmutable::create($start + 1, 7, 31)->endOfDay()],
        };
    }

    /** @return array{0:string,1:string} the term just before the given one */
    public static function previous(string $academicYear, string $semester): array
    {
        $start = (int) substr($academicYear, 0, 4);

        return match ($semester) {
            '2nd Semester' => [$academicYear, '1st Semester'],
            'Midyear' => [$academicYear, '2nd Semester'],
            default => [self::academicYear($start - 1), 'Midyear'],
        };
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
