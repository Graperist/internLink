<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\Coordinator\DocumentController;
use App\Http\Controllers\Coordinator\DocumentRepositoryController;
use App\Http\Controllers\Hte\NotificationController as HteNotificationController;
use App\Http\Controllers\Hte\PortalController as HtePortalController;
use App\Http\Controllers\Hte\FormController as HteFormController;
use App\Http\Controllers\Coordinator\HteAccountController;
use App\Http\Controllers\Coordinator\InstructorController;
use App\Http\Controllers\Coordinator\ProgramController;
use App\Http\Controllers\Coordinator\ReportController;
use App\Http\Controllers\Instructor\AdviseeController;
use App\Http\Controllers\Instructor\InternAccountController;
use App\Http\Controllers\Instructor\MonitoringController;
use App\Http\Controllers\Instructor\DashboardController as InstructorDashboardController;
use App\Http\Controllers\Instructor\DocumentReviewController;
use App\Http\Controllers\Instructor\FormController as InstructorFormController;
use App\Http\Controllers\Instructor\EvaluationController;
use App\Http\Controllers\Instructor\ReportController as InstructorReportController;
use App\Http\Controllers\Instructor\NotificationController as InstructorNotificationController;
use App\Http\Controllers\Instructor\ProfileController as InstructorProfileController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\DocumentController as StudentDocumentController;
use App\Http\Controllers\Student\DtrController;
use App\Http\Controllers\Student\HteInfoController;
use App\Http\Controllers\Student\NotificationController as StudentNotificationController;
use App\Http\Controllers\Student\ProfileController as StudentProfileController;
use App\Http\Controllers\Student\ProgressController;
use App\Http\Middleware\EnsureInstructorProfile;
use App\Http\Middleware\EnsurePasswordIsChanged;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');

    // Forgot password: ID -> emailed 6-digit code -> new password.
    Route::get('/forgot-password', [ForgotPasswordController::class, 'showRequest'])->name('password.forgot');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendCode'])
        ->middleware('throttle:5,1')->name('password.forgot.send');
    Route::get('/forgot-password/verify', [ForgotPasswordController::class, 'showVerify'])->name('password.forgot.verify');
    Route::post('/forgot-password/verify', [ForgotPasswordController::class, 'verify'])
        ->middleware('throttle:10,1')->name('password.forgot.verify.check');
    Route::post('/forgot-password/resend', [ForgotPasswordController::class, 'resend'])
        ->middleware('throttle:5,1')->name('password.forgot.resend');
    Route::get('/forgot-password/reset', [ForgotPasswordController::class, 'showReset'])->name('password.forgot.reset');
    Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'reset'])
        ->middleware('throttle:10,1')->name('password.forgot.reset.save');
});

Route::middleware(['auth', EnsurePasswordIsChanged::class])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Forced/self-service password change - reachable by any authenticated
    // role, deliberately outside the role-specific prefixed groups below.
    Route::get('/password/change', [PasswordChangeController::class, 'show'])->name('password.change');
    Route::put('/password/change', [PasswordChangeController::class, 'update'])->name('password.update');
    Route::get('/password/change/verify', [PasswordChangeController::class, 'showVerify'])->name('password.change.verify');
    Route::post('/password/change/verify', [PasswordChangeController::class, 'verify'])
        ->middleware('throttle:10,1')->name('password.change.verify.check');
    Route::post('/password/change/resend', [PasswordChangeController::class, 'resend'])
        ->middleware('throttle:5,1')->name('password.change.resend');

    // Audit trail.
    //  - click: any signed-in user's browser reports button/link clicks here.
    //  - feed:  read-only JSON for the audit pop-up. Coordinator and
    //           instructor only - students and HTE reps are refused here,
    //           not just hidden in the UI.
    Route::post('/audit/click', [AuditTrailController::class, 'click'])
        ->middleware('throttle:120,1')
        ->name('audit.click');
    Route::get('/audit/feed', [AuditTrailController::class, 'feed'])
        ->middleware(['role:coordinator,instructor', 'throttle:120,1'])
        ->name('audit.feed');

    // Clicking any notification (every portal) opens the page it is about.
    Route::get('/notifications/{notification}/open', \App\Http\Controllers\NotificationOpenController::class)->name('notifications.open');

    // ------------------------------------------------------------------
    // Coordinator + Subject Instructor - ONE shared portal.
    // The OJT Coordinator is also a program instructor, so both roles use
    // the same modules below. The difference is scope (see Instructor
    // model): an instructor sees their own program; the coordinator sees
    // the whole campus. Acting on a student stays limited to one's own
    // program for both. URL prefix and route names stay "instructor" so
    // links already saved in notifications keep working.
    // ------------------------------------------------------------------
    Route::middleware(['role:instructor,coordinator', EnsureInstructorProfile::class])->prefix('instructor')->name('instructor.')->group(function () {
        Route::get('/dashboard', [InstructorDashboardController::class, 'index'])->name('dashboard');

        // Announcements (e.g. Pre-/Post-OJT Seminar schedule) -> student notifications
        Route::get('/announcements', [\App\Http\Controllers\Instructor\AnnouncementController::class, 'index'])->name('announcements');
        Route::post('/announcements', [\App\Http\Controllers\Instructor\AnnouncementController::class, 'store'])->name('announcements.store');

        Route::get('/advisees', [AdviseeController::class, 'index'])->name('advisees');
        Route::get('/advisees/export', [AdviseeController::class, 'export'])->name('advisees.export');
        Route::post('/advisees/{deployment}/activate', [AdviseeController::class, 'activate'])->name('advisees.activate');
        Route::post('/advisees/{deployment}/confirm-completion', [AdviseeController::class, 'confirmCompletion'])->name('advisees.confirm-completion');

        // Intern accounts - added by the subject instructor, own program only.
        Route::get('/interns/create', [InternAccountController::class, 'create'])->name('interns.create');
        Route::post('/interns', [InternAccountController::class, 'store'])->name('interns.store');
        Route::get('/interns/import', [InternAccountController::class, 'importForm'])->name('interns.import');
        Route::post('/interns/import', [InternAccountController::class, 'import'])->name('interns.import.store');
        Route::get('/interns/import/template', [InternAccountController::class, 'template'])->name('interns.template');
        Route::post('/interns/{student}/reset-password', [InternAccountController::class, 'resetPassword'])->name('interns.reset-password');

        Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring');
        Route::get('/monitoring/{student}/form-17', [MonitoringController::class, 'form'])->name('monitoring.form');

        Route::get('/documents', [DocumentReviewController::class, 'index'])->name('documents');
        Route::post('/documents/remind', [DocumentReviewController::class, 'remind'])->name('documents.remind');
        // Full view of one submitted form with the Return / Approve panel.
        Route::get('/documents/{document}/open', [DocumentReviewController::class, 'show'])->name('documents.show');
        Route::post('/documents/{document}/review', [DocumentReviewController::class, 'review'])->name('documents.review');
        Route::post('/documents/{document}/print', [DocumentReviewController::class, 'printForSigning'])->name('documents.print');
        Route::get('/documents/{document}/printable', [DocumentReviewController::class, 'printable'])->name('documents.printable');
        Route::post('/documents/{document}/confirm-signatures', [DocumentReviewController::class, 'confirmSignatures'])->name('documents.confirm-signatures');
        Route::post('/documents/{document}/complete', [DocumentReviewController::class, 'markCompleted'])->name('documents.complete');
        Route::get('/documents/{document}/view', [DocumentReviewController::class, 'view'])->name('documents.view');
        Route::get('/documents/{document}/download', [DocumentReviewController::class, 'download'])->name('documents.download');
        Route::get('/documents/attachments/{attachment}', [DocumentReviewController::class, 'attachment'])->name('documents.attachment');

        // Form 23a: evaluate a partner agency (one per agency).
        Route::get('/evaluations/agencies/{hte}', [InstructorFormController::class, 'create'])->name('agency-evaluations.create');
        Route::post('/evaluations/agencies/{hte}', [InstructorFormController::class, 'store'])->name('agency-evaluations.store');

        // Partner Agencies - repository of partner industries per program
        // and term (step 1 of the internship process). Entries are kept by
        // each program's instructor; the coordinator views all programs.
        Route::get('/partner-agencies', [\App\Http\Controllers\Instructor\HteController::class, 'index'])->name('htes');
        Route::get('/partner-agencies/create', [\App\Http\Controllers\Instructor\HteController::class, 'create'])->name('htes.create');
        Route::post('/partner-agencies', [\App\Http\Controllers\Instructor\HteController::class, 'store'])->name('htes.store');
        Route::post('/partner-agencies/copy-previous', [\App\Http\Controllers\Instructor\HteController::class, 'copyPrevious'])->name('htes.copy');
        Route::get('/partner-agencies/{listing}/edit', [\App\Http\Controllers\Instructor\HteController::class, 'edit'])->name('htes.edit');
        Route::put('/partner-agencies/{listing}', [\App\Http\Controllers\Instructor\HteController::class, 'update'])->name('htes.update');
        Route::delete('/partner-agencies/{listing}', [\App\Http\Controllers\Instructor\HteController::class, 'destroy'])->name('htes.destroy');

        Route::get('/evaluations', [EvaluationController::class, 'index'])->name('evaluations');
        Route::get('/evaluations/{document}', [EvaluationController::class, 'show'])->whereNumber('document')->name('evaluations.show');

        Route::get('/reports', [InstructorReportController::class, 'index'])->name('reports');
        Route::get('/reports/student-trainees', [InstructorReportController::class, 'studentTrainees'])->name('reports.student-trainees');
        Route::get('/reports/partner-industries', [InstructorReportController::class, 'partnerIndustries'])->name('reports.partner-industries');
        Route::get('/reports/directory', [InstructorReportController::class, 'directory'])->name('reports.directory');
        Route::get('/notifications', [InstructorNotificationController::class, 'index'])->name('notifications');

        Route::get('/profile', [InstructorProfileController::class, 'index'])->name('profile');
        Route::put('/profile/account', [InstructorProfileController::class, 'updateAccount'])->name('profile.account');
        Route::put('/profile/photo', [InstructorProfileController::class, 'updatePhoto'])->name('profile.photo');
        Route::delete('/profile/photo', [InstructorProfileController::class, 'removePhoto'])->name('profile.photo.remove');
    });

    // Coordinator-only campus administration, on top of the shared portal.
    Route::middleware(['role:coordinator', EnsureInstructorProfile::class])->prefix('coordinator')->name('coordinator.')->group(function () {
        // Old coordinator pages that are now shared modules - kept as
        // redirects so bookmarks and saved notification links still work.
        Route::redirect('/dashboard', '/instructor/dashboard')->name('dashboard');
        Route::redirect('/students', '/instructor/advisees')->name('students.index');
        Route::redirect('/deployments', '/instructor/advisees')->name('deployments.index');
        Route::redirect('/monitoring', '/instructor/monitoring')->name('monitoring');
        Route::redirect('/notifications', '/instructor/notifications')->name('notifications');
        Route::redirect('/profile', '/instructor/profile')->name('profile');

        // Programs - courses and majors offered for OJT on this campus
        Route::get('/programs', [ProgramController::class, 'index'])->name('programs.index');
        Route::get('/programs/create', [ProgramController::class, 'create'])->name('programs.create');
        Route::post('/programs', [ProgramController::class, 'store'])->name('programs.store');
        Route::get('/programs/{program}/edit', [ProgramController::class, 'edit'])->name('programs.edit');
        Route::put('/programs/{program}', [ProgramController::class, 'update'])->name('programs.update');
        Route::delete('/programs/{program}', [ProgramController::class, 'destroy'])->name('programs.destroy');

        // Campus Executive Director / College Dean names used by printable forms
        Route::get('/campus-settings', [\App\Http\Controllers\Coordinator\CampusSettingsController::class, 'edit'])->name('campus-settings.edit');
        Route::put('/campus-settings', [\App\Http\Controllers\Coordinator\CampusSettingsController::class, 'update'])->name('campus-settings.update');

        // Manage Instructors - create/reassign/reset Subject Instructor accounts
        Route::get('/instructors', [InstructorController::class, 'index'])->name('instructors.index');
        Route::get('/instructors/create', [InstructorController::class, 'create'])->name('instructors.create');
        Route::post('/instructors', [InstructorController::class, 'store'])->name('instructors.store');
        Route::get('/instructors/{instructor}/edit', [InstructorController::class, 'edit'])->name('instructors.edit');
        Route::put('/instructors/{instructor}', [InstructorController::class, 'update'])->name('instructors.update');
        Route::post('/instructors/{instructor}/reset-password', [InstructorController::class, 'resetPassword'])->name('instructors.reset-password');

        // HTE Accounts - create/reset the single representative account per HTE
        Route::get('/hte-accounts', [HteAccountController::class, 'index'])->name('hte-accounts.index');
        Route::get('/hte-accounts/create', [HteAccountController::class, 'create'])->name('hte-accounts.create');
        Route::post('/hte-accounts', [HteAccountController::class, 'store'])->name('hte-accounts.store');
        Route::post('/hte-accounts/{representative}/reset-password', [HteAccountController::class, 'resetPassword'])->name('hte-accounts.reset-password');
        Route::delete('/hte-accounts/{representative}', [HteAccountController::class, 'destroy'])->name('hte-accounts.destroy');

        // Document Tracking - campus-wide compliance overview
        Route::get('/documents', [DocumentController::class, 'index'])->name('documents');
        Route::get('/documents/export', [DocumentController::class, 'export'])->name('documents.export');

        // Digital Repository
        Route::get('/repository', [DocumentRepositoryController::class, 'index'])->name('repository');
        Route::get('/repository/upload', [DocumentRepositoryController::class, 'create'])->name('repository.create');
        Route::post('/repository', [DocumentRepositoryController::class, 'store'])->name('repository.store');
        Route::get('/repository/students/{student}/download-all', [DocumentRepositoryController::class, 'downloadAllForStudent'])->name('repository.student.download-all');
        Route::get('/repository/{document}/download', [DocumentRepositoryController::class, 'download'])->name('repository.download');
        Route::get('/repository/{document}/view', [DocumentRepositoryController::class, 'view'])->name('repository.view');
        Route::get('/repository/attachments/{attachment}', [DocumentRepositoryController::class, 'attachment'])->name('repository.attachment');

        // Partner HTEs now live in the shared Partner Agencies module.
        Route::redirect('/htes', '/instructor/partner-agencies')->name('htes');

        // Campus reports (CSV / file exports)
        Route::get('/reports', [ReportController::class, 'index'])->name('reports');
        Route::get('/reports/export/roster', [ReportController::class, 'exportInternRoster'])->name('reports.export-roster');
        Route::get('/reports/export/htes', [ReportController::class, 'exportHtePartnership'])->name('reports.export-hte');
        Route::get('/reports/export/approved-documents', [ReportController::class, 'downloadApprovedDocuments'])->name('reports.export-approved-documents');
        Route::get('/reports/export/evaluations', [ReportController::class, 'exportEvaluationSummary'])->name('reports.export-evaluations');
        Route::get('/reports/export/compliance', [ReportController::class, 'exportDocumentsCompliance'])->name('reports.export-compliance');
    });

    // Figure 19 - HTE Portal
    Route::middleware('role:hte_representative')->prefix('hte')->name('hte.')->group(function () {
        Route::get('/dashboard', [HtePortalController::class, 'dashboard'])->name('dashboard');

        Route::get('/students', [HtePortalController::class, 'assignedStudents'])->name('students');
        Route::get('/students/{deployment}/dtr-history', [HtePortalController::class, 'studentDtrHistory'])->name('students.dtr-history');

        Route::get('/dtr', [HtePortalController::class, 'dtrVerification'])->name('dtr');
        Route::post('/dtr/{dtr}/verify', [HtePortalController::class, 'verifyDtr'])->name('dtr.verify');
        Route::post('/dtr/{dtr}/flag', [HtePortalController::class, 'flagDtr'])->name('dtr.flag');

        Route::get('/evaluations', [HtePortalController::class, 'evaluations'])->name('evaluations');

        // Evaluations of the agency go to the Subject Instructor now, not the HTE.
        Route::redirect('/evaluations/received', '/hte/evaluations')->name('evaluations.received');
        Route::get('/evaluations/self', [HteFormController::class, 'createSelf'])->name('self-evaluation.create');
        Route::post('/evaluations/self', [HteFormController::class, 'storeSelf'])->name('self-evaluation.store');

        Route::get('/forms/{requirement}/{deployment}', [HteFormController::class, 'create'])->name('forms.create');
        Route::post('/forms/{requirement}/{deployment}', [HteFormController::class, 'store'])->name('forms.store');

        Route::get('/profile', [HtePortalController::class, 'profile'])->name('profile');
        Route::put('/profile/company', [HtePortalController::class, 'updateCompanyProfile'])->name('profile.company');
        Route::put('/profile/account', [HtePortalController::class, 'updateAccount'])->name('profile.account');
        Route::put('/profile/photo', [HtePortalController::class, 'updatePhoto'])->name('profile.photo');
        Route::delete('/profile/photo', [HtePortalController::class, 'removePhoto'])->name('profile.photo.remove');

        Route::get('/notifications', [HteNotificationController::class, 'index'])->name('notifications');
    });

    // Figures 20-25 - Student Portal
    Route::middleware('role:student')->prefix('student')->name('student.')->group(function () {
        Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');

        Route::get('/dtr', [DtrController::class, 'index'])->name('dtr');
        // Form 17 - Internship Time Frame (the DTR): typed AM/PM time in/out per date.
        Route::post('/dtr', [DtrController::class, 'store'])->name('dtr.store');
        Route::delete('/dtr/{dtr}', [DtrController::class, 'destroy'])->name('dtr.destroy');
        // Form 17 printout made from the approved days (?download=1, ?week=).
        Route::get('/dtr/form-17', [DtrController::class, 'form'])->name('dtr.form');

        Route::get('/documents', [StudentDocumentController::class, 'index'])->name('documents');
        Route::get('/documents/{requirement}/fill', [StudentDocumentController::class, 'create'])->name('documents.fill');
        Route::post('/documents/{requirement}/fill', [StudentDocumentController::class, 'storeFields'])->name('documents.fill.store');
        Route::get('/documents/{requirement}/log', [StudentDocumentController::class, 'showLog'])->name('documents.log');
        Route::get('/documents/{requirement}/log/entry', [StudentDocumentController::class, 'createEntry'])->name('documents.log.entry.create');
        Route::post('/documents/{requirement}/log/entry', [StudentDocumentController::class, 'storeEntry'])->name('documents.log.entry.store');
        Route::delete('/documents/{requirement}/log/entry/{entry}', [StudentDocumentController::class, 'destroyEntry'])->whereNumber('entry')->name('documents.log.entry.destroy');
        Route::delete('/documents/{requirement}/log/week/{weekStart}', [StudentDocumentController::class, 'destroyWeek'])->name('documents.log.week.destroy');
        Route::post('/documents/{requirement}/log/submit', [StudentDocumentController::class, 'submitLog'])->name('documents.log.submit');
        // Form 19: each four-week packet is submitted on its own.
        Route::post('/documents/{requirement}/log/packet/{packet}/submit', [StudentDocumentController::class, 'submitLog'])->whereNumber('packet')->name('documents.log.packet.submit');
        Route::post('/documents/{document}/upload-signed', [StudentDocumentController::class, 'uploadSigned'])->name('documents.upload-signed');
        Route::post('/documents', [StudentDocumentController::class, 'store'])->name('documents.store');
        Route::post('/documents/{requirement}/moa', [StudentDocumentController::class, 'uploadMoa'])->name('documents.moa');
        Route::get('/documents/{document}/download', [StudentDocumentController::class, 'download'])->name('documents.download');
        Route::get('/documents/{document}/view', [StudentDocumentController::class, 'view'])->name('documents.view');
        Route::get('/documents/{document}/print', [StudentDocumentController::class, 'printable'])->name('documents.printable');
        Route::get('/documents/attachments/{attachment}', [StudentDocumentController::class, 'attachment'])->name('documents.attachment');


        Route::get('/hte', [HteInfoController::class, 'index'])->name('hte');
        // Evaluate Agency: the student's evaluations of their agency (Forms 23c, 20) - to the instructor only.
        Route::get('/evaluations', [\App\Http\Controllers\Student\EvaluationController::class, 'index'])->name('evaluations');
        Route::get('/progress', [ProgressController::class, 'index'])->name('progress');
        Route::get('/notifications', [StudentNotificationController::class, 'index'])->name('notifications');

        Route::get('/profile', [StudentProfileController::class, 'index'])->name('profile');
        Route::put('/profile/account', [StudentProfileController::class, 'updateAccount'])->name('profile.account');
        Route::put('/profile/photo', [StudentProfileController::class, 'updatePhoto'])->name('profile.photo');
        Route::delete('/profile/photo', [StudentProfileController::class, 'removePhoto'])->name('profile.photo.remove');
    });
});
