# Evaluation form routing — change notes

## Routing (single source: app/Support/EvaluationRouting.php)
| Form | Filled by | Delivered to |
|---|---|---|
| 13  Internship Evaluation Form            | HTE        | Instructor  > Evaluations (View Form 13) |
| 21  Training Supervisor's Feedback        | HTE        | Student     > My HTE Info |
| 20  Student-Intern's Feedback             | Student    | HTE         > Evaluations Received |
| 23a Partner Agencies (Subject Instructor) | Instructor | HTE         > Evaluations Received |
| 23c Partner Agencies (Student)            | Student    | HTE         > Evaluations Received |
| 23b Partner Agencies (Self Ratee / HTE)   | HTE        | Coordinator > Documents > "HTE Evaluation Forms" panel |

## After applying
    php artisan db:seed --class=OfficialOjtFormsSeeder   # updates reviewer_role to the recipients (existing DBs)
    php artisan test tests/Feature/EvaluationRoutingTest.php

## Bugs fixed on the way
* DocumentFormRules: "numeric|min:0|max:100" was passed as one rule -> 500 on Form 13 (ojt_grade) submit.
* printable-form13.blade.php: doubled backslashes in class references -> parse error when opened.

## Files
Changed: Coordinator/DocumentController, Coordinator/DocumentViewController, Hte/FormController,
Hte/ReceivedEvaluationController, Instructor/{Dashboard,DocumentReview,Evaluation}Controller,
Student/{Document,HteInfo}Controller, Support/DocumentFormRules, OfficialOjtFormsSeeder, routes/web.php,
views: coordinator/documents/index, hte/evaluations, hte/evaluations-received, instructor/evaluations/index,
student/documents, student/hte, instructor/documents/printable-{form13,ojt-form,student-feedback,student-hte-evaluation}.
New: app/Support/EvaluationRouting.php, tests/Feature/EvaluationRoutingTest.php.
