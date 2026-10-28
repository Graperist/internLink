<?php

namespace App\Support;

use App\Models\Document;
use App\Models\DocumentRequirement;

/**
 * The six official PSU evaluation forms (2024 set) and where each one goes.
 *
 * Like the PSU faculty evaluation, whoever is rated never sees the rating:
 * every evaluation goes to the Subject Instructor (Evaluations sidebar).
 *
 *   Form 13  Internship Evaluation Form ........ HTE ........ about the intern ... -> Instructor
 *   Form 21  Training Supervisor's Feedback .... HTE ........ about the intern ... -> Instructor
 *   Form 20  Student-Intern's Feedback ......... Student .... about the agency ... -> Instructor
 *   Form 23c Partner Agencies (Student) ........ Student .... about the agency ... -> Instructor
 *   Form 23a Partner Agencies (Instructor) ..... Instructor . about the agency ... kept by the instructor
 *   Form 23b Partner Agencies (Self Ratee/HTE) . HTE ........ about itself ....... -> Instructor
 *
 * 23a and 23b are one per agency ('per' => 'agency'); the others are one per
 * intern's internship. Each opens once the deployment is completed.
 *
 * Every controller that sends, lists, or opens one of these forms reads this
 * map so the routing lives in exactly one place.
 */
class EvaluationRouting
{
    public const FORMS = [
        'EVALUATION_FORM' => [
            'form' => 'Form 13', 'title' => 'Internship Evaluation Form',
            'from' => 'hte', 'to' => 'instructor', 'inbox' => 'Evaluations', 'per' => 'intern',
            'view' => 'instructor.documents.printable-form13',
        ],
        'TRAINING_SUPERVISOR_FEEDBACK' => [
            'form' => 'Form 21', 'title' => "Training Supervisor's Feedback Form",
            'from' => 'hte', 'to' => 'instructor', 'inbox' => 'Evaluations', 'per' => 'intern',
            'view' => 'instructor.documents.printable-training-supervisor-feedback',
        ],
        'STUDENT_HTE_EVALUATION' => [
            'form' => 'Form 23c', 'title' => 'Evaluation Instrument of PSU Partner Agencies (Student)',
            'from' => 'student', 'to' => 'instructor', 'inbox' => 'Evaluations', 'per' => 'intern',
            'view' => 'instructor.documents.printable-student-hte-evaluation',
        ],
        'STUDENT_FEEDBACK_FORM' => [
            'form' => 'Form 20', 'title' => "Student-Intern's Feedback Form",
            'from' => 'student', 'to' => 'instructor', 'inbox' => 'Evaluations', 'per' => 'intern',
            'view' => 'instructor.documents.printable-student-feedback',
        ],
        'PARTNER_AGENCY_EVAL_INSTRUCTOR' => [
            'form' => 'Form 23a', 'title' => 'Evaluation Instrument of PSU Partner Agencies (Subject Instructor)',
            'from' => 'instructor', 'to' => 'instructor', 'inbox' => 'Evaluations', 'per' => 'agency',
            'view' => 'instructor.documents.printable-partner-agency-eval-instructor',
        ],
        'PARTNER_AGENCY_EVAL_SELF' => [
            'form' => 'Form 23b', 'title' => 'Evaluation Instrument of PSU Partner Agencies (Self Ratee)',
            'from' => 'hte', 'to' => 'instructor', 'inbox' => 'Evaluations', 'per' => 'agency',
            'view' => 'instructor.documents.printable-partner-agency-eval-self',
        ],
    ];

    /** Forms the HTE fills about each intern (Forms 13, 21). */
    public const HTE_INTERN_CODES = ['EVALUATION_FORM', 'TRAINING_SUPERVISOR_FEEDBACK'];

    /** Forms the student fills about their agency (Forms 23c, 20). */
    public const STUDENT_CODES = ['STUDENT_HTE_EVALUATION', 'STUDENT_FEEDBACK_FORM'];

    /** Statuses that mean "the sender has submitted it". */
    public const SUBMITTED = ['submitted', 'for_review', 'approved', 'printed', 'completed'];

    /** @return string[] requirement codes delivered TO the given role. */
    public static function codesTo(string $role): array
    {
        return array_keys(array_filter(self::FORMS, fn ($f) => $f['to'] === $role));
    }

    /** @return string[] requirement codes filled BY the given role. */
    public static function codesFrom(string $role): array
    {
        return array_keys(array_filter(self::FORMS, fn ($f) => $f['from'] === $role));
    }

    /**
     * The student's evaluations (Forms 20, 23c). They are filled on the
     * student's Evaluate Agency page and read by the instructor under
     * Evaluations, so they stay out of My Documents and Document Review.
     *
     * @return string[]
     */
    public static function studentEvaluationCodes(): array
    {
        return self::codesFrom('student');
    }

    /** @deprecated the student's evaluations no longer go to the HTE; same as studentEvaluationCodes(). */
    public static function studentToHteCodes(): array
    {
        return self::studentEvaluationCodes();
    }

    public static function isPerAgency(string $code): bool
    {
        return (self::FORMS[$code]['per'] ?? null) === 'agency';
    }

    public static function view(string $code): string
    {
        return self::FORMS[$code]['view'];
    }

    public static function label(string $code): string
    {
        $f = self::FORMS[$code] ?? null;

        return $f ? $f['form'].' – '.$f['title'] : $code;
    }

    public static function isSubmitted(?Document $document): bool
    {
        return $document !== null && in_array($document->status, self::SUBMITTED, true);
    }

    /** Requirement ids (by code) for one campus, active forms only. */
    public static function requirementIds(int $campusId, ?array $codes = null)
    {
        return DocumentRequirement::where('campus_id', $campusId)
            ->where('is_active', true)
            ->whereIn('code', $codes ?? array_keys(self::FORMS))
            ->pluck('id', 'code');
    }
}
