<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Form 17 - Internship Time Frame: the hours of one day from its morning
 * and afternoon time in/out. The lunch break between them is never
 * counted: 8:00-12:00 and 1:00-4:00 is 7 hours.
 */
class TimeFrame
{
    /**
     * @return array{hours: float, errors: array<string,string>}
     */
    public static function compute(?string $amIn, ?string $amOut, ?string $pmIn, ?string $pmOut): array
    {
        $errors = [];
        $minutes = 0;
        $pairs = ['am' => [$amIn, $amOut, 'morning'], 'pm' => [$pmIn, $pmOut, 'afternoon']];
        $ends = [];

        foreach ($pairs as $key => [$in, $out, $label]) {
            if (blank($in) && blank($out)) {
                continue;
            }
            if (blank($in) || blank($out)) {
                $errors[$key.'_'.(blank($in) ? 'in' : 'out')] = "Enter both the {$label} time in and time out.";
                continue;
            }
            $start = self::minutes($in);
            $end = self::minutes($out);
            if ($end <= $start) {
                $errors[$key.'_out'] = "The {$label} time out must be after the time in.";
                continue;
            }
            $minutes += $end - $start;
            $ends[$key] = [$start, $end];
        }

        if (isset($ends['am'], $ends['pm']) && $ends['pm'][0] < $ends['am'][1]) {
            $errors['pm_in'] = 'The afternoon time in must be after the morning time out.';
        }
        if ($errors === [] && $minutes === 0) {
            $errors['am_in'] = 'Enter at least the morning or the afternoon time in and out.';
        }

        return ['hours' => round($minutes / 60, 2), 'errors' => $errors];
    }

    /** "07:30" -> 450 */
    public static function minutes(string $time): int
    {
        $t = Carbon::parse($time);

        return $t->hour * 60 + $t->minute;
    }

    /** 7.5 -> "7.5", 8 -> "8" */
    public static function format(float|string|null $hours): string
    {
        return rtrim(rtrim(number_format((float) $hours, 2, '.', ''), '0'), '.');
    }

    /** "13:05:00" -> "1:05 PM" */
    public static function clock(?string $time): string
    {
        return filled($time) ? Carbon::parse($time)->format('g:i A') : '';
    }
}
