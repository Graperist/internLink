<?php

namespace App\Support;

use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\Internship;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Form 17 – Internship Time Frame is not a submitted document: it is filled
 * from the days the student entered in Time Frame (DTR). This builds
 * the official printout straight from those days (whole form, or ?week=).
 */
class TimeFrameForm
{
    public const CODE = 'INTERNSHIP_TIME_FRAME';

    /** Rows on one paper Form 17; more days continue on the next sheet. */
    public const ROWS = 28;

    /**
     * The days split into sheets of 28 rows (always at least one sheet),
     * keyed by sheet number starting at 1.
     */
    public static function sheets(Collection $entries): Collection
    {
        $chunks = $entries->values()->chunk(self::ROWS)->values();
        if ($chunks->isEmpty()) {
            $chunks = collect([collect()]);
        }

        return $chunks->mapWithKeys(fn ($chunk, $i) => [$i + 1 => $chunk->values()]);
    }

    /**
     * The sheets that have days, for "download by sheet" buttons:
     * [['number' => 1, 'first' => Carbon, 'last' => Carbon, 'count' => 28], ...]
     */
    public static function sheetList(iterable $days): array
    {
        $sorted = collect($days)->sortBy(fn ($d) => $d->log_date)->values();
        if ($sorted->isEmpty()) {
            return [];
        }

        return self::sheets($sorted)->map(fn ($chunk, $n) => [
            'number' => $n,
            'first' => $chunk->first()->log_date,
            'last' => $chunk->last()->log_date,
            'count' => $chunk->count(),
        ])->values()->all();
    }

    /** ?sheet=N, or ?period=sheet:N from the instructor's picker. */
    public static function requestedSheet(): ?int
    {
        $sheet = request()->query('sheet');
        $period = request()->query('period');
        if (is_string($period) && str_starts_with($period, 'sheet:')) {
            $sheet = substr($period, 6);
        }

        return is_scalar($sheet) && ctype_digit((string) $sheet) && (int) $sheet > 0 ? (int) $sheet : null;
    }

    /**
     * @param  bool  $printable  the student's copy opens the print dialog; the
     *                           instructor's is view-only (they only view and download)
     */
    public static function response(Internship $internship, bool $printable): Response
    {
        $internship->loadMissing('student.user', 'student.program', 'deployment.hte', 'campus');

        // An unsaved stand-in so the official template can be reused as is.
        $requirement = DocumentRequirement::where('campus_id', $internship->campus_id)->where('code', self::CODE)->first()
            ?? new DocumentRequirement(['code' => self::CODE, 'name' => 'Form 17 – Internship Time Frame']);
        $document = new Document(['internship_id' => $internship->id]);
        $document->setRelation('internship', $internship);
        $document->setRelation('requirement', $requirement);

        $html = view('instructor.documents.printable-internship-time-frame', ['document' => $document])->render();

        $download = request()->boolean('download');
        $preview = request()->boolean('preview');
        if (request()->boolean('view') && ! $download) {
            // Opened with "View": no print dialog, but it can still be printed from the page.
            $html = str_replace('<body onload="window.print()">', '<body>', $html);
        }
        if ($download || $preview || ! $printable) {
            $html = str_replace('<body onload="window.print()">', '<body>', $html);
            $html = preg_replace('#<button class="no-print" onclick="window\.print\(\)">[^<]*</button>#', '', $html);
        }

        if ($preview && ! $download) {
            // Shown inside the Time Frame page: the sheet on a white A4 page.
            $html = str_ireplace('</head>', '<style>html{background:#fff}body{width:8.27in;max-width:none;min-height:11.69in;padding:.5in;margin:0}.tf-page+.tf-page{margin-top:.5in;padding-top:.5in;border-top:2px dashed #b8c2cc}</style></head>', $html);
        }

        if ($download) {
            $name = trim(preg_replace('#[\\\\/:*?"<>|]+#', '-', 'Form 17 – Internship Time Frame'
                .TrainingWeeks::downloadSuffix($internship)
                .(self::requestedSheet() ? ' - Sheet '.self::requestedSheet() : '')
                .' - '.$internship->student->user->name));
            $script = view('student.documents._pdf-download', ['filename' => $name.'.pdf'])->render();
            $html = str_ireplace('</body>', $script.'</body>', $html);
        }

        return response($html);
    }
}
