<?php

namespace App\Support;

use App\Models\Document;
use App\Models\DocumentRequirement;

/**
 * The six official PSU evaluation forms (2024 set) and where each one goes.
 *
 *   Form 13  Internship Evaluation Form ........ HTE ........ -> Instructor  (Evaluations sidebar)
 *   Form 21  Training Supervisor's Feedback .... HTE ........ -> Student     (My HTE Info sidebar)
 *   Form 20  Student-Intern's Feedback ......... Student .... -> HTE         (Evaluations Received sidebar)
 *   Form 23a Partner Agencies (Instructor) ..... Instructor . -> HTE         (Evaluations Received sidebar)
 *   Form 23c Partner Agencies (Student) ........ Student .... -> HTE         (Evaluations Received sidebar)
 *   Form 23b Partner Agencies (Self Ratee/HTE) . HTE ........ -> Coordinator (Documents sidebar)
 *
 * Every controller that sends, lists, or opens one of these forms reads this
 * map so the routing lives in exactly one place.
 */
class EvaluationRouting
{
    public const FORMS = [
        'EVALUATION_FORM' => [
            'form' => 'Form 13', 'title' => 'Internship Evaluation Form',
            'from' => 'hte', 'to' => 'instructor', 'inbox' => 'Evaluations',
            'view' => 'instructor.documents.printable-form13',
        ],
        'TRAINING_SUPERVISOR_FEEDBACK' => [
            'form' => 'Form 21', 'title' => "Training Supervisor's Feedback Form",
            'from' => 'hte', 'to' => 'student', 'inbox' => 'My HTE Info',
            'view' => 'instructor.documents.printable-training-supervisor-feedback',
        ],
        'STUDENT_FEEDBACK_FORM' => [
            'form' => 'Form 20', 'title' => "Student-Intern's Feedback Form",
            'from' => 'student', 'to' => 'hte', 'inbox' => 'Evaluations Received',
            'view' => 'instructor.documents.printable-student-feedback',
        ],
        'PARTNER_AGENCY_EVAL_INSTRUCTOR' => [
            'form' => 'Form 23a', 'title' => 'Evaluation Instrument of PSU Partner Agencies (Subject Instructor)',
            'from' => 'instructor', 'to' => 'hte', 'inbox' => 'Evaluations Received',
            'view' => 'instructor.documents.printable-partner-agency-eval-instructor',
        ],
        'STUDENT_HTE_EVALUATION' => [
            'form' => 'Form 23c', 'title' => 'Evaluation Instrument of PSU Partner Agencies (Student)',
            'from' => 'student', 'to' => 'hte', 'inbox' => 'Evaluations Received',
            'view' => 'instructor.documents.printable-student-hte-evaluation',
        ],
        'PARTNER_AGENCY_EVAL_SELF' => [
            'form' => 'Form 23b', 'title' => 'Evaluation Instrument of PSU Partner Agencies (Self Ratee)',
            'from' => 'hte', 'to' => 'coordinator', 'inbox' => 'Documents',
            'view' => 'instructor.documents.printable-partner-agency-eval-self',
        ],
    ];

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
     * Student-filled forms that go to the HTE rather than the Subject
     * Instructor, so they must stay out of the instructor's Document Review.
     *
     * @return string[]
     */
    public static function studentToHteCodes(): array
    {
        return array_values(array_intersect(self::codesFrom('student'), self::codesTo('hte')));
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
