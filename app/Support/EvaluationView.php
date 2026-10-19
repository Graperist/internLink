<?php

namespace App\Support;

use App\Models\Document;
use Illuminate\Http\Response;

/**
 * Opens a submitted evaluation form in its official layout for reading,
 * or (?download=1) saves it as a PDF in the browser.
 */
class EvaluationView
{
    public static function response(Document $document, ?string $backUrl = null): Response
    {
        $document->load('fieldValues', 'entries.values', 'requirement.fieldDefinitions', 'internship.student.user', 'internship.deployment.hte', 'hte', 'uploader');
        $code = $document->requirement->code;

        $html = view(EvaluationRouting::view($code), [
            'document' => $document,
            'autoPrint' => false,
            'backUrl' => request()->boolean('download') ? null : $backUrl,   // no Back link inside the PDF
        ])->render();
        // View and download only - no print dialog.
        $html = preg_replace('#<button class="no-print" onclick="window\.print\(\)">[^<]*</button>#', '', $html);

        if (request()->boolean('download')) {
            $about = EvaluationRouting::isPerAgency($code)
                ? ($document->hte?->name ?? $document->internship?->deployment?->hte?->name ?? '')
                : ($document->internship?->student?->user?->name ?? '');
            $name = trim(preg_replace('#[\\\\/:*?"<>|]+#', '-', EvaluationRouting::label($code).' - '.$about));
            $script = view('student.documents._pdf-download', ['filename' => $name.'.pdf'])->render();
            $html = str_ireplace('</body>', $script.'</body>', $html);
        }

        return response($html);
    }
}
