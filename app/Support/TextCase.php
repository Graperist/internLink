<?php

namespace App\Support;

/**
 * Turns text typed in ALL CAPS into normal capitalization
 * ("BAYBAY NORTE SUAL PANGASINAN" -> "Baybay Norte Sual Pangasinan").
 * Text that already has lowercase letters is left exactly as typed.
 */
class TextCase
{
    /** Short words that stay as they are (Roman numerals, unit codes). */
    private const KEEP = ['II', 'III', 'IV', 'VI', 'VII', 'VIII', 'IX', 'XI', 'XII', 'PSU', 'LGU', 'NCR', 'CAR'];

    public static function fixCaps(?string $text): ?string
    {
        if ($text === null || ! preg_match('/\p{Lu}/u', $text) || preg_match('/\p{Ll}/u', $text)) {
            return $text;
        }

        $title = mb_convert_case(mb_strtolower($text), MB_CASE_TITLE, 'UTF-8');

        return preg_replace_callback('/\b[\p{L}]+\b/u', function ($m) {
            $upper = mb_strtoupper($m[0]);

            return in_array($upper, self::KEEP, true) ? $upper : $m[0];
        }, $title);
    }
}
