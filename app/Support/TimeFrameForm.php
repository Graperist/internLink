<?php

namespace App\Support;

use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\Internship;
use Illuminate\Http\Response;

/**
 * Form 17 – Internship Time Frame is not a submitted document: it is filled
 * from the days the HTE supervisor approved in Time Frame (DTR). This builds
 * the official printout straight from those days (whole form, or ?week=).
 */
class TimeFrameForm
{
    public const CODE = 'INTERNSHIP_TIME_FRAME';

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
        if ($download || ! $printable) {
            $html = str_replace('<body onload="window.print()">', '<body>', $html);
            $html = preg_replace('#<button class="no-print" onclick="window\.print\(\)">[^<]*</button>#', '', $html);
        }

        if ($download) {
            $name = trim(preg_replace('#[\\\\/:*?"<>|]+#', '-', 'Form 17 – Internship Time Frame'
                .TrainingWeeks::downloadSuffix($internship)
                .' - '.$internship->student->user->name));
            $script = view('student.documents._pdf-download', ['filename' => $name.'.pdf'])->render();
            $html = str_ireplace('</body>', $script.'</body>', $html);
        }

        return response($html);
    }
}
