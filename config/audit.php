<?php

/*
|--------------------------------------------------------------------------
| Audit trail
|--------------------------------------------------------------------------
| Human-readable wording for the audit pop-up. Keys are route names.
| Anything not listed here still gets logged - it just falls back to a
| label generated from the route name.
*/

return [

    // Routes that are never logged (the audit endpoints themselves, and the
    // login/logout POSTs which LoginController logs explicitly with more
    // detail).
    'ignore_routes' => ['audit.feed', 'audit.click', 'login.attempt', 'logout'],

    'role_labels' => [
        'coordinator' => 'Coordinator',
        'instructor' => 'Instructor',
        'hte_representative' => 'HTE',
        'student' => 'Student',
    ],

    // GET requests (a page was opened / a file was downloaded)
    'pages' => [
        'password.change' => 'Opened the change-password page',

        'coordinator.dashboard' => 'Opened the dashboard',
        'coordinator.students.index' => 'Opened the Intern List',
        'coordinator.students.create' => 'Opened the Add Intern form',
        'coordinator.students.import' => 'Opened the bulk intern import',
        'coordinator.students.import.template' => 'Downloaded the intern import template',
        'coordinator.students.documents' => "Opened a student's document trail",
        'coordinator.instructors.index' => 'Opened Manage Instructors',
        'coordinator.instructors.create' => 'Opened the Add Instructor form',
        'coordinator.instructors.edit' => 'Opened the Edit Instructor form',
        'coordinator.hte-accounts.index' => 'Opened HTE Accounts',
        'coordinator.hte-accounts.create' => 'Opened the Create HTE Account form',
        'coordinator.deployments.index' => 'Opened Deployment Management',
        'coordinator.monitoring' => 'Opened Monitoring',
        'coordinator.documents' => 'Opened Document Tracking',
        'coordinator.documents.export' => 'Exported the document tracking list',
        'coordinator.documents.printable' => 'Opened a printable form',
        'coordinator.documents.view' => 'Viewed a submitted document',
        'coordinator.documents.download' => 'Downloaded a submitted document',
        'coordinator.repository' => 'Opened the Digital Repository',
        'coordinator.repository.create' => 'Opened the repository upload form',
        'coordinator.repository.download' => 'Downloaded a repository file',
        'coordinator.htes' => 'Opened Partner HTEs',
        'coordinator.htes.create' => 'Opened the Add HTE form',
        'coordinator.htes.edit' => 'Opened the Edit HTE form',
        'coordinator.reports' => 'Opened Reports',
        'coordinator.reports.export-roster' => 'Exported the intern roster',
        'coordinator.reports.export-hte' => 'Exported the HTE partnership report',
        'coordinator.reports.export-approved-documents' => 'Downloaded all approved documents',
        'coordinator.notifications' => 'Opened Notifications',
        'coordinator.profile' => 'Opened My Profile',

        'instructor.dashboard' => 'Opened the dashboard',
        'instructor.advisees' => 'Opened My Students',
        'instructor.advisees.export' => 'Exported the advisee list',
        'instructor.documents' => 'Opened Document Review',
        'instructor.documents.printable' => 'Opened a printable form',
        'instructor.documents.view' => 'Viewed a submitted document',
        'instructor.documents.download' => 'Downloaded a submitted document',
        'instructor.evaluations' => 'Opened Evaluations',
        'instructor.reports' => 'Opened Reports',
        'instructor.reports.student-trainees' => 'Generated the Masterlist of Student-Trainees',
        'instructor.reports.partner-industries' => 'Generated the Masterlist of Partner Industries',
        'instructor.reports.directory' => 'Generated the Directory of Student-Interns',
        'instructor.reports.export-evaluations' => 'Exported the evaluation summary',
        'instructor.reports.export-htes' => 'Exported the HTE directory',
        'instructor.reports.export-compliance' => 'Exported the document compliance report',
        'instructor.notifications' => 'Opened Notifications',
        'instructor.profile' => 'Opened My Profile',

        'hte.dashboard' => 'Opened the dashboard',
        'hte.students' => 'Opened Assigned Students',
        'hte.students.dtr-history' => "Opened a student's DTR history",
        'hte.dtr' => 'Opened DTR Verification',
        'hte.evaluations' => 'Opened Evaluations',
        'hte.notifications' => 'Opened Notifications',
        'hte.profile' => 'Opened My Profile',

        'student.dashboard' => 'Opened the dashboard',
        'student.dtr' => 'Opened the Daily Time Record',
        'student.documents' => 'Opened My Documents',
        'student.documents.fill' => 'Opened a form to fill in',
        'notifications.open' => 'Opened a notification',
        'student.documents.log' => 'Opened a log sheet',
        'student.documents.log.entry.create' => 'Opened the new log entry form',
        'student.documents.download' => 'Downloaded a document',
        'student.hte' => 'Opened My HTE Info',
        'student.progress' => 'Opened My Progress',
        'student.notifications' => 'Opened Notifications',
        'student.profile' => 'Opened My Profile',
    ],

    // POST / PUT / PATCH / DELETE requests (something was changed)
    'actions' => [
        'password.update' => 'Changed account password',

        'coordinator.students.store' => 'Added an intern',
        'coordinator.students.import.store' => 'Imported interns from a file',
        'coordinator.students.reset-password' => "Reset an intern's password",
        'coordinator.instructors.store' => 'Created an instructor account',
        'coordinator.instructors.update' => 'Updated an instructor',
        'coordinator.instructors.reset-password' => "Reset an instructor's password",
        'coordinator.hte-accounts.store' => 'Created an HTE account',
        'coordinator.hte-accounts.reset-password' => "Reset an HTE account's password",
        'coordinator.hte-accounts.destroy' => 'Deleted an HTE account',
        'coordinator.deployments.store' => 'Deployed a student to an HTE',
        'coordinator.repository.store' => 'Uploaded a file to the repository',
        'coordinator.htes.store' => 'Added a partner HTE',
        'coordinator.htes.update' => 'Updated a partner HTE',
        'coordinator.profile.account' => 'Updated account details',
        'coordinator.profile.photo' => 'Changed profile photo',
        'coordinator.profile.photo.remove' => 'Removed profile photo',

        'instructor.documents.review' => 'Reviewed a document (approve / return)',
        'instructor.documents.print' => 'Printed a document for signing',
        'instructor.documents.complete' => 'Marked a document as completed',
        'instructor.evaluations.rate' => 'Rated an intern',
        'instructor.profile.account' => 'Updated account details',
        'instructor.profile.photo' => 'Changed profile photo',
        'instructor.profile.photo.remove' => 'Removed profile photo',

        'hte.dtr.verify' => 'Verified a DTR entry',
        'hte.dtr.flag' => 'Flagged a DTR entry',
        'hte.evaluations.submit' => 'Submitted an intern evaluation',
        'hte.profile.company' => 'Updated the company profile',
        'hte.profile.account' => 'Updated account details',
        'hte.profile.photo' => 'Changed profile photo',
        'hte.profile.photo.remove' => 'Removed profile photo',

        'student.dtr.clock-in' => 'Clocked in',
        'student.dtr.clock-out' => 'Clocked out',
        'student.dtr.resolve' => 'Responded to a flagged DTR entry',
        'student.documents.store' => 'Uploaded a document',
        'student.documents.upload-signed' => 'Uploaded a signed document',
        'student.documents.fill.store' => 'Saved form fields',
        'student.documents.log.entry.store' => 'Added a log entry',
        'student.documents.log.entry.destroy' => 'Deleted a log entry',
        'student.documents.log.week.destroy' => 'Deleted a weekly report',
        'student.documents.log.submit' => 'Submitted a log for review',
        'student.profile.account' => 'Updated account details',
        'student.profile.photo' => 'Changed profile photo',
        'student.profile.photo.remove' => 'Removed profile photo',
    ],
];
