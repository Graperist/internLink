<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\Coordinator\DocumentController;
use App\Http\Controllers\Coordinator\DocumentRepositoryController;
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

Route::middleware(['auth', \App\Http\Middleware\EnsureCampusIsActive::class, EnsurePasswordIsChanged::class])->group(function () {
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
    //  - feed:  read-only JSON of the audit trail. OJT Coordinator only -
    //           everyone else is refused here, not just hidden in the UI.
    Route::post('/audit/click', [AuditTrailController::class, 'click'])
        ->middleware('throttle:120,1')
        ->name('audit.click');
    Route::get('/audit/feed', [AuditTrailController::class, 'feed'])
        ->middleware(['role:coordinator', 'throttle:120,1'])
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
        // Audit Trail (sidebar): who did what, when.
        Route::get('/audit-trail', [AuditTrailController::class, 'index'])->middleware('role:coordinator')->name('audit-trail');

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
        Route::put('/evaluations/edit-days', [EvaluationController::class, 'editDays'])->name('evaluations.edit-days');
        Route::post('/evaluations/{document}/lock', [EvaluationController::class, 'lock'])->whereNumber('document')->name('evaluations.lock');
        Route::post('/evaluations/{document}/unlock', [EvaluationController::class, 'unlock'])->whereNumber('document')->name('evaluations.unlock');

        Route::get('/reports', [InstructorReportController::class, 'index'])->name('reports');
        Route::get('/reports/student-trainees', [InstructorReportController::class, 'studentTrainees'])->name('reports.student-trainees');
        Route::get('/reports/partner-industries', [InstructorReportController::class, 'partnerIndustries'])->name('reports.partner-industries');
        Route::get('/reports/directory', [InstructorReportController::class, 'directory'])->name('reports.directory');
        Route::get('/reports/site-monitoring', [InstructorReportController::class, 'siteMonitoring'])->name('reports.site-monitoring');
        Route::get('/reports/accreditation', [InstructorReportController::class, 'accreditation'])->name('reports.accreditation');
        // The Notifications page was removed; old links go to the dashboard.
        Route::redirect('/notifications', '/instructor/dashboard')->name('notifications');

        // The Form 19 deadline of the instructor's own program, set in Document Review.
        Route::put('/documents/form19-deadline', [\App\Http\Controllers\Instructor\Form19DeadlineController::class, 'update'])->name('documents.deadline');
        Route::put('/documents/students/{student}/form19-due', [\App\Http\Controllers\Instructor\Form19DeadlineController::class, 'week'])->name('documents.week-due');

        Route::get('/profile', [InstructorProfileController::class, 'index'])->name('profile');
        Route::put('/profile/account', [InstructorProfileController::class, 'updateAccount'])->name('profile.account');
        Route::put('/profile/photo', [InstructorProfileController::class, 'updatePhoto'])->name('profile.photo');
        Route::delete('/profile/photo', [InstructorProfileController::class, 'removePhoto'])->name('profile.photo.remove');
    });

    // Back to the Super Admin portal from a campus they opened.
    Route::post('/admin/leave-campus', [\App\Http\Controllers\Admin\ManageCampusController::class, 'leave'])->name('admin.campuses.leave');
    // While managing a campus: delete one of its instructor, HTE or student accounts.
    Route::delete('/admin/campus-accounts/{user}', [\App\Http\Controllers\Admin\DeleteController::class, 'campusAccount'])
        ->middleware(\App\Http\Middleware\EnsureManagingSuperAdmin::class)->name('admin.campus-accounts.destroy');

    // Super Admin (university-wide): campuses, their OJT Coordinator accounts
    // and the login page photos.
    Route::middleware('role:super_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/campuses', [\App\Http\Controllers\Admin\CampusController::class, 'index'])->name('campuses.index');
        Route::get('/campuses/create', [\App\Http\Controllers\Admin\CampusController::class, 'create'])->name('campuses.create');
        Route::post('/campuses', [\App\Http\Controllers\Admin\CampusController::class, 'store'])->name('campuses.store');
        Route::get('/campuses/{campus}/edit', [\App\Http\Controllers\Admin\CampusController::class, 'edit'])->name('campuses.edit');
        Route::put('/campuses/{campus}', [\App\Http\Controllers\Admin\CampusController::class, 'update'])->name('campuses.update');
        // Campuses are archived, never deleted.
        Route::post('/campuses/{campus}/archive', [\App\Http\Controllers\Admin\CampusController::class, 'archive'])->name('campuses.archive');
        Route::post('/campuses/{campus}/restore', [\App\Http\Controllers\Admin\CampusController::class, 'restore'])->name('campuses.restore');

        Route::get('/coordinators', [\App\Http\Controllers\Admin\CoordinatorController::class, 'index'])->name('coordinators.index');
        Route::get('/coordinators/create', [\App\Http\Controllers\Admin\CoordinatorController::class, 'create'])->name('coordinators.create');
        Route::post('/coordinators', [\App\Http\Controllers\Admin\CoordinatorController::class, 'store'])->name('coordinators.store');
        Route::post('/coordinators/{user}/reset-password', [\App\Http\Controllers\Admin\CoordinatorController::class, 'resetPassword'])->name('coordinators.reset-password');
        Route::post('/coordinators/{user}/status', [\App\Http\Controllers\Admin\CoordinatorController::class, 'toggleStatus'])->name('coordinators.status');
        Route::delete('/coordinators/{user}', [\App\Http\Controllers\Admin\DeleteController::class, 'coordinator'])->name('coordinators.destroy');

        Route::get('/login-photos', [\App\Http\Controllers\Admin\LoginPhotoController::class, 'index'])->name('login-photos');
        Route::get('/login-photos/preview', [LoginController::class, 'preview'])->name('login-photos.preview');
        Route::post('/login-photos', [\App\Http\Controllers\Admin\LoginPhotoController::class, 'store'])->name('login-photos.store');
        Route::put('/login-photos/{slide}', [\App\Http\Controllers\Admin\LoginPhotoController::class, 'update'])->name('login-photos.update');
        Route::post('/login-photos/{slide}/replace', [\App\Http\Controllers\Admin\LoginPhotoController::class, 'replace'])->name('login-photos.replace');
        Route::post('/login-photos/{slide}/move', [\App\Http\Controllers\Admin\LoginPhotoController::class, 'move'])->name('login-photos.move');
        Route::delete('/login-photos/{slide}', [\App\Http\Controllers\Admin\LoginPhotoController::class, 'destroy'])->name('login-photos.destroy');

        // Open a campus as its OJT Coordinator (every coordinator page for that campus).
        Route::post('/campuses/{campus}/manage', [\App\Http\Controllers\Admin\ManageCampusController::class, 'start'])->name('campuses.manage');

        // Audit trail of every campus.
        Route::get('/audit-trail', [AuditTrailController::class, 'index'])->name('audit-trail');

        Route::get('/account', [\App\Http\Controllers\Admin\AccountController::class, 'edit'])->name('account');
        Route::put('/account', [\App\Http\Controllers\Admin\AccountController::class, 'update'])->name('account.update');
    });

    // Coordinator-only campus administration, on top of the shared portal.
    Route::middleware(['role:coordinator', EnsureInstructorProfile::class])->prefix('coordinator')->name('coordinator.')->group(function () {
        // Settings: one list page (like an app's Settings), with each setting on its own page.
        Route::view('/settings', 'coordinator.settings')->name('settings');
        // Login page photos are the Super Admin's now.
        Route::redirect('/login-photos', '/coordinator/settings');

        // Old coordinator pages that are now shared modules - kept as
        // redirects so bookmarks and saved notification links still work.
        Route::redirect('/dashboard', '/instructor/dashboard')->name('dashboard');
        Route::redirect('/students', '/instructor/advisees')->name('students.index');
        Route::redirect('/deployments', '/instructor/advisees')->name('deployments.index');
        Route::redirect('/monitoring', '/instructor/monitoring')->name('monitoring');
        Route::redirect('/notifications', '/instructor/dashboard')->name('notifications');
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

        // Repository - every intern's document status and passed files
        Route::get('/documents', [DocumentController::class, 'index'])->name('documents');
        Route::get('/documents/students/{student}', [DocumentReviewController::class, 'tracking'])->name('documents.student');

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

        // Campus Exports is part of Generate Reports now; the intern roster CSV stays.
        Route::get('/reports', [ReportController::class, 'index'])->name('reports');
        Route::get('/reports/export/roster', [ReportController::class, 'exportInternRoster'])->name('reports.export-roster');
    });

    // Figure 19 - HTE Portal

    Route::middleware('role:hte_representative')->prefix('hte')->name('hte.')->group(function () {
        // HTE accounts are for evaluating the interns only: Interns and Evaluations.
        // The dashboard, Timeframe and profile pages were removed; old links go to Evaluations.
        Route::redirect('/dashboard', '/hte/evaluations')->name('dashboard');

        Route::get('/students', [HtePortalController::class, 'assignedStudents'])->name('students');
        Route::redirect('/students/{deployment}/dtr-history', '/hte/students')->name('students.dtr-history');

        Route::redirect('/dtr', '/hte/evaluations')->name('dtr');

        Route::get('/evaluations', [HtePortalController::class, 'evaluations'])->name('evaluations');
        // View a submitted evaluation (Forms 13, 21, 23b) in its official layout.
        Route::get('/evaluations/{document}/view', [HtePortalController::class, 'viewEvaluation'])->whereNumber('document')->name('evaluations.view');

        // Evaluations of the agency go to the Subject Instructor now, not the HTE.
        Route::redirect('/evaluations/received', '/hte/evaluations')->name('evaluations.received');
        Route::get('/evaluations/self', [HteFormController::class, 'createSelf'])->name('self-evaluation.create');
        Route::post('/evaluations/self', [HteFormController::class, 'storeSelf'])->name('self-evaluation.store');

        Route::get('/forms/{requirement}/{deployment}', [HteFormController::class, 'create'])->name('forms.create');
        Route::post('/forms/{requirement}/{deployment}', [HteFormController::class, 'store'])->name('forms.store');

        Route::redirect('/profile', '/hte/evaluations')->name('profile');

        Route::redirect('/notifications', '/hte/evaluations')->name('notifications');
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
        // Form 19: each week is submitted (and reviewed) on its own; {packet} is the week number.
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
        // View a submitted evaluation of their agency (Forms 23c, 20).
        Route::get('/evaluations/{document}/view', [\App\Http\Controllers\Student\EvaluationController::class, 'show'])->whereNumber('document')->name('evaluations.view');
        Route::get('/progress', [ProgressController::class, 'index'])->name('progress');
        Route::get('/announcements', [StudentNotificationController::class, 'index'])->name('announcements');
        Route::post('/announcements/updates/read-all', [StudentNotificationController::class, 'readAll'])->name('announcements.read-all');
        // Old link (saved in notifications) - same page.
        Route::get('/notifications', [StudentNotificationController::class, 'index'])->name('notifications');

        Route::get('/profile', [StudentProfileController::class, 'index'])->name('profile');
        Route::put('/profile/account', [StudentProfileController::class, 'updateAccount'])->name('profile.account');
        Route::put('/profile/photo', [StudentProfileController::class, 'updatePhoto'])->name('profile.photo');
        Route::delete('/profile/photo', [StudentProfileController::class, 'removePhoto'])->name('profile.photo.remove');
    });
});
