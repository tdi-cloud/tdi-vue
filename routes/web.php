<?php

use App\Http\Controllers\ActionPlanController;
use App\Http\Controllers\ActionPlanMonitoringController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificateEmailController;
use App\Http\Controllers\CoverPageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeclarationController;
use App\Http\Controllers\DefaultProgramCoverController;
use App\Http\Controllers\EmailReminderController;
use App\Http\Controllers\EmployeeMapController;
use App\Http\Controllers\EmployeePhotoController;
use App\Http\Controllers\EmployeeProgressController;
use App\Http\Controllers\EnrolledProgramController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\EvaluationFormController;
use App\Http\Controllers\ForeignAgencyController;
use App\Http\Controllers\ForeignNominationController;
use App\Http\Controllers\ForeignNomineeAssessmentController;
use App\Http\Controllers\ForeignParticipantController;
use App\Http\Controllers\ForeignProgramController;
use App\Http\Controllers\ForeignSponsorConfigController;
use App\Http\Controllers\MyCertificateController;
use App\Http\Controllers\NhrdcMemberController;
use App\Http\Controllers\NhrdcSelfServiceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizingSponsorController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\ParticipantNotificationController;
use App\Http\Controllers\ProblemReportController;
use App\Http\Controllers\ProgramActivityController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ProvisionalEmployeeController;
use App\Http\Controllers\PublicFormAdminController;
use App\Http\Controllers\PublicFormController;
use App\Http\Controllers\PublicFormEditorController;
use App\Http\Controllers\PublicRegistrationController;
use App\Http\Controllers\RegionalReportController;
use App\Http\Controllers\RegistrationFormController;
use App\Http\Controllers\RegistrationFormEditorController;
use App\Http\Controllers\RegistrationReviewController;
use App\Http\Controllers\RequirementController;
use App\Http\Controllers\RequirementsTrackerController;
use App\Http\Controllers\ResourceSpeakerController;
use App\Http\Controllers\SiteImageController;
use App\Http\Controllers\SubmissionActivityController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\SupportingDocumentController;
use App\Http\Controllers\TerminalReportController;
use App\Http\Controllers\TesdaOrderController;
use App\Http\Controllers\TnaController;
use App\Http\Controllers\TPMRController;
use App\Http\Controllers\TutorialVideoController;
use App\Http\Controllers\UserManagementController;
use App\Mail\ReminderEmail;
use App\Models\ForeignNominee;
use App\Models\SiteImage;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome/index', [
        'enrolledPrograms' => auth()->check()
            ? EnrolledProgramController::forUser(auth()->user())
            : [],
        'tna' => TnaController::bannerData(auth()->user()),
        'supervisorTna' => TnaController::supervisorBannerData(auth()->user()),
        'nhrdcRating' => NhrdcSelfServiceController::bannerData(auth()->user()),
        'siteImages' => SiteImage::resolvedUrls(),
        'howToVideos' => TutorialVideoController::homepageData(auth()->user()),
    ]);
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'admin'])->name('dashboard');

Route::middleware(['auth', 'verified', 'admin'])->group(function () {

    // PROGRAMS
    Route::get('/programs', [ProgramController::class, 'index'])->name('programs.index');
    Route::post('/programs', [ProgramController::class, 'store'])->name('programs.store');
    // Hidden na "daily monitoring" page (walang link sa navigation) — dapat
    // nasa itaas ng /programs/{program} para hindi ito ma-match bilang isang
    // route-model-bound program.
    Route::get('/programs/activity-log', [ProgramActivityController::class, 'index'])->name('programs.activity-log');
    // Same reason: dapat nasa itaas ng /programs/{program} para hindi ma-match
    // ang "default-cover" bilang isang route-model-bound program id.
    Route::post('/programs/default-cover', [DefaultProgramCoverController::class, 'upload'])->middleware('superadmin')->name('programs.default-cover.upload');
    Route::delete('/programs/default-cover', [DefaultProgramCoverController::class, 'destroy'])->middleware('superadmin')->name('programs.default-cover.destroy');
    Route::get('/programs/{program}', [ProgramController::class, 'show'])->name('programs.show');
    Route::delete('/programs/{program}', [ProgramController::class, 'destroy'])->name('programs.destroy');
    Route::get('/programs/{program}/edit', [ProgramController::class, 'edit'])->name('programs.edit');
    Route::put('/programs/{program}', [ProgramController::class, 'update'])->name('programs.update');
    Route::post('/programs/{program}/competencies', [ProgramController::class, 'storeCompetencies'])
        ->name('programs.competencies.store');
    Route::delete('/programs/{program}/competencies/{competency}', [ProgramController::class, 'destroyCompetency'])
        ->name('programs.competencies.destroy');

    // BATCHES
    Route::post('/batches', [BatchController::class, 'store'])->name('batches.store');
    Route::put('/batches/{batch}', [BatchController::class, 'update'])->name('batches.update');
    Route::delete('/batches/{batch}', [BatchController::class, 'destroy'])->name('batches.destroy');

    // PARTICIPANTS
    Route::get('/participants/search', [ParticipantController::class, 'search'])->middleware('search.suggest')->name('participants.search');
    Route::post('/participants', [ParticipantController::class, 'store'])->name('participants.store');
    Route::delete('/participants/{participant}', [ParticipantController::class, 'destroy'])->name('participants.destroy');
    Route::post('/participants/{participant}/attendance', [ParticipantController::class, 'updateAttendance'])
        ->name('participants.attendance');
    Route::delete('/participants/{participant}/justification', [ParticipantController::class, 'destroyJustification'])
        ->name('participants.justification.destroy');
    Route::post('/participants/bulk-store', [ParticipantController::class, 'bulkStore'])
        ->name('participants.bulk-store');
    Route::post('/participants/{participant}/apply-to-all', [ParticipantController::class, 'applyToAll'])->name('participants.applyToAll');
    Route::post('/participants/{participant}/reorder', [ParticipantController::class, 'reorder'])
        ->name('participants.reorder');

    // DASHBOARD
    Route::get('/dashboard/training-compliance', [DashboardController::class, 'trainingCompliance'])
        ->name('dashboard.training-compliance');
    Route::get('/dashboard/training-compliance/list', [DashboardController::class, 'trainingComplianceList'])
        ->name('dashboard.training-compliance.list');
    Route::get('/dashboard/supervisory-compliance', [DashboardController::class, 'supervisoryCompliance'])
        ->name('dashboard.supervisory-compliance');
    Route::get('/dashboard/supervisory-compliance-list', [DashboardController::class, 'supervisoryComplianceList'])
        ->name('dashboard.supervisory-compliance.list');

    Route::get('/dashboard/treap-compliance', [DashboardController::class, 'treapCompliance'])
        ->name('dashboard.treap-compliance');
    Route::get('/dashboard/treap-compliance-list', [DashboardController::class, 'treapComplianceList'])
        ->name('dashboard.treap-compliance.list');

    Route::get('/dashboard/reap-compliance', [DashboardController::class, 'reapCompliance'])->name('dashboard.reap-compliance');
    Route::get('/dashboard/reap-compliance-list', [DashboardController::class, 'reapComplianceList'])->name('dashboard.reap-compliance.list');

    Route::get('/dashboard/tdor-compliance', [DashboardController::class, 'tdorCompliance'])->name('dashboard.tdor-compliance');
    Route::get('/dashboard/tdor-compliance-list', [DashboardController::class, 'tdorComplianceList'])->name('dashboard.tdor-compliance.list');

    // NEW: Lookup endpoints for filter dropdowns
    Route::get('/dashboard/offices', [DashboardController::class, 'offices'])->name('dashboard.offices');
    Route::get('/dashboard/export-csv', [DashboardController::class, 'exportCsv'])->name('dashboard.export-csv');

    // CALENDAR
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');

    // HOW-TO VIDEOS — admins watch; uploading is superadmin only (see below)
    Route::get('/how-to-videos', [TutorialVideoController::class, 'index'])->name('how-to-videos.index');
    Route::get('/how-to-videos/{tutorialVideo}/documentation', [TutorialVideoController::class, 'documentation'])->name('how-to-videos.documentation');
    Route::post('/how-to-videos/{tutorialVideo}/view', [TutorialVideoController::class, 'recordView'])->name('how-to-videos.view');

    // PROGRAM REQUIREMENTS
    Route::post('programs/{program}/requirements', [RequirementController::class, 'store'])
        ->name('programs.requirements.store');
    Route::put('programs/{program}/requirements/{requirement}', [RequirementController::class, 'update'])
        ->name('programs.requirements.update');
    Route::delete('programs/{program}/requirements/{requirement}', [RequirementController::class, 'destroy'])
        ->name('programs.requirements.destroy');

    // REQUIREMENTS SUBMISSIONS
    // Hidden na "daily monitoring" page (walang link sa navigation) — dapat
    // nasa itaas ng /submissions/{submission} para hindi ito ma-match bilang
    // isang route-model-bound submission.
    Route::get('submissions/activity-log', [SubmissionActivityController::class, 'index'])
        ->name('submissions.activity-log');
    Route::get('submissions', [SubmissionController::class, 'index'])
        ->name('submissions.index');
    Route::post('submissions', [SubmissionController::class, 'store'])
        ->name('submissions.store');
    Route::patch('submissions/{submission}/review', [SubmissionController::class, 'review'])
        ->name('submissions.review');
    // Correct the submission date (e.g. to clear a wrong "Late" indication)
    Route::patch('submissions/{submission}/submitted-at', [SubmissionController::class, 'updateSubmittedAt'])
        ->name('submissions.submitted-at.update');
    // Approve every Pending submission at once — per program or across all
    // programs (global Submissions page). Superadmins only.
    Route::post('programs/{program}/submissions/approve-all', [SubmissionController::class, 'approveAllForProgram'])
        ->middleware('superadmin')
        ->name('programs.submissions.approve-all');
    Route::post('submissions/approve-all', [SubmissionController::class, 'approveAll'])
        ->middleware('superadmin')
        ->name('submissions.approve-all');
    Route::delete('submissions/{submission}', [SubmissionController::class, 'destroy'])
        ->name('submissions.destroy');

    // SUPPORTING DOCUMENTS
    Route::post('/programs/{program}/supporting-documents', [SupportingDocumentController::class, 'store'])
        ->name('programs.supporting-documents.store');
    Route::put('/programs/{program}/supporting-documents/{supportingDocument}', [SupportingDocumentController::class, 'update'])
        ->name('programs.supporting-documents.update');
    Route::delete('/programs/{program}/supporting-documents/{supportingDocument}', [SupportingDocumentController::class, 'destroy'])
        ->name('programs.supporting-documents.destroy');
    Route::get('/supporting-documents', [SupportingDocumentController::class, 'index'])
        ->name('supporting-documents.index');

    // RESOURCE SPEAKERS
    Route::post('/programs/{program}/resource-speakers', [ResourceSpeakerController::class, 'store'])
        ->name('programs.resource-speakers.store');
    Route::put('/programs/{program}/resource-speakers/{resourceSpeaker}', [ResourceSpeakerController::class, 'update'])
        ->name('programs.resource-speakers.update');
    Route::delete('/programs/{program}/resource-speakers/{resourceSpeaker}', [ResourceSpeakerController::class, 'destroy'])
        ->name('programs.resource-speakers.destroy');

    // EVALUATION (admin)
    Route::get('/batches/{batch}/evaluation', [EvaluationFormController::class, 'edit'])->name('batches.evaluation.edit');
    Route::post('/batches/{batch}/evaluation-form', [EvaluationFormController::class, 'store'])->name('batches.evaluation-form.store');
    Route::put('/evaluation-forms/{evaluationForm}', [EvaluationFormController::class, 'update'])->name('evaluation-forms.update');
    Route::delete('/evaluation-forms/{evaluationForm}', [EvaluationFormController::class, 'destroy'])->middleware('superadmin')->name('evaluation-forms.destroy');
    Route::post('/evaluation-forms/{evaluationForm}/background', [EvaluationFormController::class, 'uploadBackground'])->name('evaluation-forms.background.upload');
    Route::delete('/evaluation-forms/{evaluationForm}/background', [EvaluationFormController::class, 'destroyBackground'])->name('evaluation-forms.background.destroy');

    Route::put('/evaluation-sections/{evaluationSection}', [EvaluationFormController::class, 'updateSection'])->name('evaluation-sections.update');
    Route::post('/evaluation-sections/{evaluationSection}/move-up', [EvaluationFormController::class, 'moveSectionUp'])->name('evaluation-sections.move-up');
    Route::post('/evaluation-sections/{evaluationSection}/move-down', [EvaluationFormController::class, 'moveSectionDown'])->name('evaluation-sections.move-down');

    Route::post('/evaluation-sections/{evaluationSection}/questions', [EvaluationFormController::class, 'storeQuestion'])->name('evaluation-sections.questions.store');
    Route::put('/evaluation-questions/{evaluationQuestion}', [EvaluationFormController::class, 'updateQuestion'])->name('evaluation-questions.update');
    Route::delete('/evaluation-questions/{evaluationQuestion}', [EvaluationFormController::class, 'destroyQuestion'])->name('evaluation-questions.destroy');
    Route::post('/evaluation-questions/{evaluationQuestion}/move-up', [EvaluationFormController::class, 'moveQuestionUp'])->name('evaluation-questions.move-up');
    Route::post('/evaluation-questions/{evaluationQuestion}/move-down', [EvaluationFormController::class, 'moveQuestionDown'])->name('evaluation-questions.move-down');

    Route::post('/evaluation-forms/{evaluationForm}/facilitators', [EvaluationFormController::class, 'storeFacilitator'])->name('evaluation-forms.facilitators.store');
    Route::post('/evaluation-forms/{evaluationForm}/facilitators/bulk', [EvaluationFormController::class, 'bulkStoreFacilitators'])->name('evaluation-forms.facilitators.bulk-store');
    Route::put('/evaluation-facilitators/{evaluationFacilitator}', [EvaluationFormController::class, 'updateFacilitator'])->name('evaluation-facilitators.update');
    Route::delete('/evaluation-facilitators/{evaluationFacilitator}', [EvaluationFormController::class, 'destroyFacilitator'])->name('evaluation-facilitators.destroy');
    Route::post('/evaluation-facilitators/{evaluationFacilitator}/move-up', [EvaluationFormController::class, 'moveFacilitatorUp'])->name('evaluation-facilitators.move-up');
    Route::post('/evaluation-facilitators/{evaluationFacilitator}/move-down', [EvaluationFormController::class, 'moveFacilitatorDown'])->name('evaluation-facilitators.move-down');

    Route::get('/programs/{program}/evaluation-dashboard', [EvaluationFormController::class, 'dashboardData'])->name('programs.evaluation-dashboard');
    Route::get('/evaluation-forms/{evaluationForm}/responses', [EvaluationFormController::class, 'responses'])->name('evaluation-forms.responses');
    Route::delete('/evaluation-forms/{evaluationForm}/responses/{evaluationResponse}', [EvaluationFormController::class, 'destroyResponse'])->name('evaluation-forms.responses.destroy');

    // REGISTRATION FORM (admin) — the controllers limit changes to the form's owner, its editors or a superadmin
    // (setting a form up: the program's creator or a superadmin)
    Route::get('/batches/{batch}/registration', [RegistrationFormController::class, 'edit'])->name('batches.registration.edit');
    Route::post('/batches/{batch}/registration-form', [RegistrationFormController::class, 'store'])->name('batches.registration-form.store');
    Route::put('/registration-forms/{registrationForm}', [RegistrationFormController::class, 'update'])->name('registration-forms.update');
    Route::post('/registration-forms/{registrationForm}/background', [RegistrationFormController::class, 'uploadBackground'])->name('registration-forms.background.upload');
    Route::delete('/registration-forms/{registrationForm}/background', [RegistrationFormController::class, 'destroyBackground'])->name('registration-forms.background.destroy');
    Route::post('/registration-forms/{registrationForm}/questions', [RegistrationFormController::class, 'storeQuestion'])->name('registration-forms.questions.store');
    Route::put('/registration-questions/{registrationQuestion}', [RegistrationFormController::class, 'updateQuestion'])->name('registration-questions.update');
    Route::delete('/registration-questions/{registrationQuestion}', [RegistrationFormController::class, 'destroyQuestion'])->name('registration-questions.destroy');
    Route::post('/registration-questions/{registrationQuestion}/move-up', [RegistrationFormController::class, 'moveQuestionUp'])->name('registration-questions.move-up');
    Route::post('/registration-questions/{registrationQuestion}/move-down', [RegistrationFormController::class, 'moveQuestionDown'])->name('registration-questions.move-down');

    // Only the form's owner or a superadmin can add or remove editors
    Route::get('/registration-forms/{registrationForm}/editors/search', [RegistrationFormEditorController::class, 'search'])->middleware('search.suggest')
        ->name('registration-forms.editors.search');
    Route::post('/registration-forms/{registrationForm}/editors', [RegistrationFormEditorController::class, 'store'])->name('registration-forms.editors.store');
    Route::delete('/registration-form-editors/{registrationFormEditor}', [RegistrationFormEditorController::class, 'destroy'])->name('registration-form-editors.destroy');

    Route::post('/registration-forms/{registrationForm}/approve', [RegistrationReviewController::class, 'bulkApprove'])->name('registration-forms.approve');
    Route::post('/program-registrations/{programRegistration}/approve', [RegistrationReviewController::class, 'approve'])->name('program-registrations.approve');
    Route::post('/program-registrations/{programRegistration}/reject', [RegistrationReviewController::class, 'reject'])->name('program-registrations.reject');
    Route::put('/program-registrations/{programRegistration}', [RegistrationReviewController::class, 'update'])->name('program-registrations.update');
    Route::post('/program-registrations/{programRegistration}/resend-login', [RegistrationReviewController::class, 'resendLogin'])
        ->middleware('throttle:6,1,resend-login')
        ->name('program-registrations.resend-login');

    // PUBLIC FORMS (admin): the forms, their responses, question editor and settings
    Route::get('/public-forms', [PublicFormAdminController::class, 'index'])->name('public-forms.admin.index');
    Route::post('/public-forms', [PublicFormAdminController::class, 'store'])->name('public-forms.admin.store');
    Route::get('/public-forms/{slug}', [PublicFormAdminController::class, 'show'])->name('public-forms.admin.show');
    Route::put('/public-forms/{slug}', [PublicFormAdminController::class, 'update'])->name('public-forms.admin.update');
    Route::delete('/public-forms/{slug}', [PublicFormAdminController::class, 'destroy'])->name('public-forms.admin.destroy');
    Route::put('/public-forms/{slug}/details', [PublicFormAdminController::class, 'updateDetails'])->name('public-forms.admin.details.update');
    Route::get('/public-forms/{slug}/export', [PublicFormAdminController::class, 'export'])->name('public-forms.admin.export');
    Route::delete('/public-forms/{slug}/responses/{publicFormResponse}', [PublicFormAdminController::class, 'destroyResponse'])->name('public-forms.admin.responses.destroy');
    Route::post('/public-forms/{slug}/background', [PublicFormAdminController::class, 'uploadBackground'])->name('public-forms.admin.background.upload');
    Route::put('/public-forms/{slug}/background', [PublicFormAdminController::class, 'updateBackground'])->name('public-forms.admin.background.update');
    Route::delete('/public-forms/{slug}/background', [PublicFormAdminController::class, 'destroyBackground'])->name('public-forms.admin.background.destroy');
    Route::post('/public-forms/{slug}/header-image', [PublicFormAdminController::class, 'uploadHeaderImage'])->name('public-forms.admin.header-image.upload');
    Route::delete('/public-forms/{slug}/header-image', [PublicFormAdminController::class, 'destroyHeaderImage'])->name('public-forms.admin.header-image.destroy');

    // PUBLIC FORMS question editor: sections (steps) and their questions
    Route::post('/public-forms/{slug}/sections', [PublicFormEditorController::class, 'storeSection'])->name('public-forms.admin.sections.store');
    Route::put('/public-form-sections/{section}', [PublicFormEditorController::class, 'updateSection'])->name('public-form-sections.update');
    Route::delete('/public-form-sections/{section}', [PublicFormEditorController::class, 'destroySection'])->name('public-form-sections.destroy');
    Route::post('/public-form-sections/{section}/move', [PublicFormEditorController::class, 'moveSection'])->name('public-form-sections.move');
    Route::post('/public-forms/{slug}/questions', [PublicFormEditorController::class, 'storeQuestion'])->name('public-forms.admin.questions.store');
    Route::put('/public-form-questions/{question}', [PublicFormEditorController::class, 'updateQuestion'])->name('public-form-questions.update');
    Route::delete('/public-form-questions/{question}', [PublicFormEditorController::class, 'destroyQuestion'])->name('public-form-questions.destroy');
    Route::post('/public-form-questions/{question}/move', [PublicFormEditorController::class, 'moveQuestion'])->name('public-form-questions.move');

    // COVER PAGE
    Route::post('/programs/cover/upload', [CoverPageController::class, 'upload'])->name('programs.cover.upload');
    Route::delete('/programs/{program}/cover', [CoverPageController::class, 'destroy'])->name('programs.cover.destroy');

    // FOREIGN PROGRAMS
    // Restricted to FSTP unit members (see EnsureUserCanAccessForeignPrograms)
    Route::middleware('fstp')->group(function () {
        // FOREIGN DASHBOARD
        Route::get('foreign-programs/dashboard-data', [ForeignProgramController::class, 'dashboardData'])
            ->name('foreign-programs.dashboard-data');

        Route::get('foreign-programs/dashboard-nominees', [ForeignProgramController::class, 'dashboardNominees'])
            ->name('foreign-programs.dashboard-nominees');

        Route::get('/foreign-programs/by-sponsor', [ForeignProgramController::class, 'byOrganizingSponsor'])
            ->name('foreign-programs.by-sponsor')
            ->middleware('auth');

        // NOMINATION HISTORY — lahat ng nagpasa sa public nomination form, latest to oldest
        Route::get('/foreign-programs/nomination-history', [ForeignProgramController::class, 'nominationHistory'])
            ->name('foreign-programs.nomination-history');

        Route::get('/foreign-programs', [ForeignProgramController::class, 'index'])->name('foreign-programs.index');
        // Dapat nasa itaas ng /foreign-programs/{foreignProgram} para hindi ito
        // ma-match bilang isang route-model-bound program id.
        Route::delete('/foreign-programs/bulk-destroy', [ForeignProgramController::class, 'bulkDestroy'])->name('foreign-programs.bulk-destroy');
        Route::get('/foreign-programs/{foreignProgram}', [ForeignProgramController::class, 'show'])->name('foreign-programs.show');
        Route::post('/foreign-programs', [ForeignProgramController::class, 'store'])->name('foreign-programs.store');
        Route::put('/foreign-programs/{foreignProgram}', [ForeignProgramController::class, 'update'])->name('foreign-programs.update');
        Route::delete('/foreign-programs/{foreignProgram}', [ForeignProgramController::class, 'destroy'])->name('foreign-programs.destroy');

        // NOMINEE ASSESSMENT SHEET
        Route::get('/foreign-programs/{foreignProgram}/assessment', [ForeignNomineeAssessmentController::class, 'index'])
            ->name('foreign-programs.assessment');
        Route::get('/foreign-nominee-assessments/search-employee', [ForeignNomineeAssessmentController::class, 'searchEmployee'])->middleware('search.suggest')
            ->name('foreign-nominee-assessments.search-employee');
        Route::post('/foreign-nominees/{nominee}/assessment', [ForeignNomineeAssessmentController::class, 'save'])
            ->name('foreign-nominees.assessment.save');

        // Interview ratings — every NHRDC panel member submits their own rating per nominee
        Route::post('/foreign-nominees/{nominee}/interview-ratings', [ForeignNomineeAssessmentController::class, 'saveInterviewRating'])
            ->name('foreign-nominees.interview-ratings.save');
        Route::delete('/foreign-nominee-interview-ratings/{foreignNomineeInterviewRating}', [ForeignNomineeAssessmentController::class, 'destroyInterviewRating'])
            ->name('foreign-nominee-interview-ratings.destroy');

        // Per-NHRDC assessment sheet PDF + signed copy upload
        Route::get('/foreign-programs/{foreignProgram}/nhrdc/{nhrdcMember}/assessment-pdf', [ForeignNomineeAssessmentController::class, 'nhrdcPdf'])
            ->name('foreign-programs.nhrdc-assessment-pdf');
        Route::post('/foreign-programs/{foreignProgram}/nhrdc/{nhrdcMember}/signed-copy', [ForeignNomineeAssessmentController::class, 'uploadNhrdcSignedCopy'])
            ->name('foreign-programs.nhrdc-signed-copy.upload');
        Route::get('/foreign-programs/{foreignProgram}/nhrdc/{nhrdcMember}/signed-copy', [ForeignNomineeAssessmentController::class, 'downloadNhrdcSignedCopy'])
            ->name('foreign-programs.nhrdc-signed-copy.download');
        Route::delete('/foreign-programs/{foreignProgram}/nhrdc/{nhrdcMember}/signed-copy', [ForeignNomineeAssessmentController::class, 'destroyNhrdcSignedCopy'])
            ->name('foreign-programs.nhrdc-signed-copy.destroy');

        // NHRDC MEMBERS (roster of employees eligible to submit Interview ratings)
        Route::get('/nhrdc-members', [NhrdcMemberController::class, 'index'])->name('nhrdc-members.index');
        Route::post('/nhrdc-members', [NhrdcMemberController::class, 'store'])->name('nhrdc-members.store');
        Route::delete('/nhrdc-members/{nhrdcMember}', [NhrdcMemberController::class, 'destroy'])->name('nhrdc-members.destroy');
        Route::post('/nhrdc-members/{nhrdcMember}/move-up', [NhrdcMemberController::class, 'moveUp'])->name('nhrdc-members.move-up');
        Route::post('/nhrdc-members/{nhrdcMember}/move-down', [NhrdcMemberController::class, 'moveDown'])->name('nhrdc-members.move-down');
        // Representative authorized to rate nominees on the NHRDC member's behalf
        Route::put('/nhrdc-members/{nhrdcMember}/representative', [NhrdcMemberController::class, 'assignRepresentative'])->name('nhrdc-members.representative.assign');
        Route::delete('/nhrdc-members/{nhrdcMember}/representative', [NhrdcMemberController::class, 'removeRepresentative'])->name('nhrdc-members.representative.remove');

        // FOREIGN PARTICIPANTS
        Route::post('/foreign-programs/{foreignProgram}/participants', [ForeignParticipantController::class, 'store'])->name('foreign-participants.store');
        Route::put('/foreign-participants/{foreignParticipant}', [ForeignParticipantController::class, 'update'])->name('foreign-participants.update');
        Route::delete('/foreign-participants/{foreignParticipant}', [ForeignParticipantController::class, 'destroy'])->name('foreign-participants.destroy');
    });

    // REGIONAL REPORTS
    // TPMR
    Route::get('/tpmr', [RegionalReportController::class, 'index'])->name('tpmr.index');
    Route::post('/tpmr', [RegionalReportController::class, 'store'])->name('tpmr.store');
    Route::delete('/tpmr/{regionalReport}', [RegionalReportController::class, 'destroy'])->name('tpmr.destroy');

    // EMPLOYEES PROGRESS
    Route::get('/employees', [EmployeeProgressController::class, 'index'])->name('employees.index');
    Route::get('/employees/{empcode}/export', [EmployeeProgressController::class, 'exportCsv'])
        ->name('employees.export');
    Route::get('/employees/{empcode}/progress', [EmployeeProgressController::class, 'show'])->name('employees.progress');

    Route::get('/reports/tpmr', [TPMRController::class, 'generate'])->name('reports.tpmr');
    Route::get('/employees/search', [TPMRController::class, 'searchEmployees'])->middleware('search.suggest')->name('employees.search');

    // POST-TRAINING REQUIREMENTS TRACKER
    Route::get('/requirements-tracker', [RequirementsTrackerController::class, 'index'])->name('requirements-tracker.index');
    Route::get('/requirements-tracker/export', [RequirementsTrackerController::class, 'exportCsv'])
        ->name('requirements-tracker.export');
    Route::get('/requirements-tracker/search', [RequirementsTrackerController::class, 'search'])->middleware('search.suggest')
        ->name('requirements-tracker.search');

    // ACTION PLAN MONITORING — every REAP activity against its schedule; any admin reviews implementation reports
    Route::get('/action-plan-monitoring', [ActionPlanMonitoringController::class, 'index'])->name('action-plan-monitoring.index');
    Route::patch('/action-plan-monitoring/reports/{implementationReport}/review', [ActionPlanMonitoringController::class, 'review'])
        ->name('action-plan-monitoring.review');

    // TNA SUMMARY
    Route::get('/tna-summary', [TnaController::class, 'summaryIndex'])->name('tna-summary.index');
    Route::get('/tna-summary/dashboard-data', [TnaController::class, 'summaryDashboardData'])
        ->name('tna-summary.dashboard-data');

    // EMPLOYEES MAP
    Route::get('/employees-map', [EmployeeMapController::class, 'index'])->name('employees-map.index');
    Route::get('/employees-map/region', [EmployeeMapController::class, 'byRegion'])->name('employees-map.region');

    // DECLARATION OF COMPLETERS:
    Route::get('/declarations/signatories/search', [DeclarationController::class, 'searchSignatory'])->middleware('search.suggest')
        ->name('declarations.signatories.search');

    Route::get('/batches/{batch}/declaration', [DeclarationController::class, 'generate'])
        ->name('batches.declaration');

    // ORGANIZING SPONSORS (used only by Foreign Programs)
    Route::middleware('fstp')->group(function () {
        Route::get('/organizing-sponsors', [OrganizingSponsorController::class, 'index'])->name('organizing-sponsors.index');
        Route::post('/organizing-sponsors', [OrganizingSponsorController::class, 'store'])->name('organizing-sponsors.store');
        Route::put('/organizing-sponsors/{organizingSponsor}', [OrganizingSponsorController::class, 'update'])->name('organizing-sponsors.update');
        Route::delete('/organizing-sponsors/{organizingSponsor}', [OrganizingSponsorController::class, 'destroy'])->name('organizing-sponsors.destroy');
    });

    // EMAIL REMINDER
    Route::post('/email-reminder/send', [EmailReminderController::class, 'send'])
        ->name('email-reminder.send');

    // NOTIFY PARTICIPANTS — scoped so a batch from another program returns 404
    Route::post('/programs/{program}/batches/{batch}/notify', [ParticipantNotificationController::class, 'send'])
        ->scopeBindings()
        ->name('programs.notify-participants');

    // CERTIFICATES
    // ── ADMIN routes (inside auth + admin middleware group) ──
    Route::post('/certificates', [CertificateController::class, 'store'])
        ->name('certificates.store');

    Route::post('/certificates/bulk-store', [CertificateController::class, 'bulkStore'])
        ->name('certificates.bulk-store');

    Route::delete('/certificates/{certificate}', [CertificateController::class, 'destroy'])
        ->name('certificates.destroy');

    // EMAIL CERTIFICATES — scoped so a batch from another program returns 404
    Route::post('/programs/{program}/batches/{batch}/certificates/email', [CertificateEmailController::class, 'send'])
        ->scopeBindings()
        ->name('programs.email-certificates');

    // ATTENDANCE
    Route::get('/employees/search-signatory', [AttendanceController::class, 'searchSignatory'])->middleware('search.suggest')
        ->name('employees.search-signatory');

    Route::get('/attendance/generate', [AttendanceController::class, 'generate'])
        ->name('attendance.generate');

    Route::middleware('fstp')->group(function () {
        // FOREIGN AGENCIES
        Route::get('/foreign-agencies', [ForeignAgencyController::class, 'index'])->name('foreign-agencies.index');
        Route::post('/foreign-agencies', [ForeignAgencyController::class, 'store'])->name('foreign-agencies.store');
        Route::delete('/foreign-agencies/{foreignAgency}', [ForeignAgencyController::class, 'destroy'])->name('foreign-agencies.destroy');

        // FOREIGN NOMINATION
        Route::get('/foreign-sponsor-configs', [ForeignSponsorConfigController::class, 'index'])->name('foreign-sponsor-configs.index');
        Route::post('/foreign-sponsor-configs', [ForeignSponsorConfigController::class, 'store'])->name('foreign-sponsor-configs.store');
        Route::get('/foreign-sponsor-configs/{config}', [ForeignSponsorConfigController::class, 'show'])->name('foreign-sponsor-configs.show');
        Route::put('/foreign-sponsor-configs/{config}', [ForeignSponsorConfigController::class, 'update'])->name('foreign-sponsor-configs.update');

        // Requirements
        Route::post('/foreign-sponsor-configs/{config}/requirements',
            [ForeignSponsorConfigController::class, 'storeRequirement']
        )->name('foreign-sponsor-configs.requirements.store');

        Route::put('/foreign-nominee-requirements/{requirement}',
            [ForeignSponsorConfigController::class, 'updateRequirement']
        )->name('foreign-nominee-requirements.update');

        Route::delete('/foreign-nominee-requirements/{requirement}',
            [ForeignSponsorConfigController::class, 'destroyRequirement']
        )->name('foreign-nominee-requirements.destroy');

        // Admin-added nominee (participant)
        Route::post('/foreign-programs/{foreignProgram}/nominees',
            [ForeignSponsorConfigController::class, 'storeNominee']
        )->name('foreign-nominees.store');

        // Nominee Status Update
        Route::patch('/foreign-nominees/{nominee}/status',
            [ForeignSponsorConfigController::class, 'updateNomineeStatus']
        )->name('foreign-nominees.status');

        Route::delete('/foreign-nominees/{nominee}', function (ForeignNominee $nominee) {
            $nominee->delete();

            return back();
        })->name('foreign-nominees.destroy')->middleware('auth');

        // Submissions
        Route::post('/foreign-nominees/{nominee}/requirements/{requirement}/submission',
            [ForeignSponsorConfigController::class, 'storeSubmission']
        )->name('foreign-nominee-submissions.store');

        Route::post('/foreign-nominee-submissions/{submission}/replace',
            [ForeignSponsorConfigController::class, 'replaceSubmission']
        )->name('foreign-nominee-submissions.replace');

        Route::post('/foreign-nominees/{nominee}/accomplished-form/replace',
            [ForeignSponsorConfigController::class, 'replaceAccomplishedForm']
        )->name('foreign-nominees.accomplished-form.replace');
    });

    // TESDA ORDER
    Route::get('/programs/{program}/tesda-orders', [TesdaOrderController::class, 'index'])->name('tesda-orders.index');
    Route::post('/programs/{program}/tesda-orders', [TesdaOrderController::class, 'store'])->name('tesda-orders.store');
    Route::get('/tesda-orders/{tesdaOrder}/download', [TesdaOrderController::class, 'download'])->name('tesda-orders.download');
    Route::delete('/tesda-orders/{tesdaOrder}', [TesdaOrderController::class, 'destroy'])->name('tesda-orders.destroy');
    Route::get('/tesda-orders/search-signatory', [TesdaOrderController::class, 'searchSignatory'])->middleware('search.suggest')->name('tesda-orders.search-signatory');
    Route::get('/programs/{program}/tesda-orders/participants', [TesdaOrderController::class, 'participants'])
        ->name('tesda-orders.participants');

});

// SUPERADMIN ONLY
Route::middleware(['auth', 'verified', 'superadmin'])->group(function () {

    // USER MANAGEMENT
    Route::get('/user-management', [UserManagementController::class, 'index'])->name('user-management.index');
    Route::put('/user-management/{user}', [UserManagementController::class, 'update'])->name('user-management.update');
    Route::post('/user-management/{user}/avatar', [UserManagementController::class, 'updateAvatar'])->name('user-management.avatar.update');
    Route::delete('/user-management/{user}/avatar', [UserManagementController::class, 'destroyAvatar'])->name('user-management.avatar.destroy');

    // EMPLOYEE PICTURES (upload, change, remove from the Employees page), keyed by EMPCODE
    Route::post('/employees/{empcode}/photo', [EmployeePhotoController::class, 'update'])->name('employees.photo.update');
    Route::delete('/employees/{empcode}/photo', [EmployeePhotoController::class, 'destroy'])->name('employees.photo.destroy');

    // HOMEPAGE IMAGES
    Route::get('/site-images', [SiteImageController::class, 'index'])->name('site-images.index');
    Route::post('/site-images/{key}', [SiteImageController::class, 'update'])->name('site-images.update');
    Route::delete('/site-images/{key}', [SiteImageController::class, 'destroy'])->name('site-images.destroy');

    // HOW-TO VIDEOS (upload, edit, delete)
    Route::post('/how-to-videos', [TutorialVideoController::class, 'store'])->name('how-to-videos.store');
    Route::post('/how-to-videos/{tutorialVideo}', [TutorialVideoController::class, 'update'])->name('how-to-videos.update');
    Route::delete('/how-to-videos/{tutorialVideo}', [TutorialVideoController::class, 'destroy'])->name('how-to-videos.destroy');
    Route::post('/how-to-videos/{tutorialVideo}/documentation', [TutorialVideoController::class, 'updateDocumentation'])->name('how-to-videos.documentation.update');

    // PROBLEM REPORTS
    Route::get('/problem-reports', [ProblemReportController::class, 'index'])->name('problem-reports.index');
    Route::put('/problem-reports/{problemReport}', [ProblemReportController::class, 'updateStatus'])->name('problem-reports.update-status');

    // EMPLOYEES FROM REGISTRATION (holding list until HR's monthly list includes them)
    Route::get('/employees-from-registration', [ProvisionalEmployeeController::class, 'index'])->name('employees-from-registration.index');
    Route::post('/employees-from-registration/sync', [ProvisionalEmployeeController::class, 'sync'])->name('employees-from-registration.sync');
    Route::put('/employees-from-registration/{provisionalEmployee}', [ProvisionalEmployeeController::class, 'update'])->name('employees-from-registration.update');
});

// NHRDC SELF-SERVICE (committee members rate nominee interviews themselves —
// gated by NHRDC roster membership, not admin access)
Route::middleware(['auth', 'verified', 'nhrdc'])->prefix('nhrdc')->name('nhrdc.')->group(function () {
    Route::get('/programs', [NhrdcSelfServiceController::class, 'index'])->name('programs.index');
    Route::get('/programs/{foreignProgram}', [NhrdcSelfServiceController::class, 'show'])->name('programs.show');
    Route::get('/programs/{foreignProgram}/assessment-pdf', [NhrdcSelfServiceController::class, 'pdf'])->name('programs.assessment-pdf');
    Route::post('/programs/{foreignProgram}/signed-copy', [NhrdcSelfServiceController::class, 'uploadSignedCopy'])->name('signed-copy.upload');
    Route::get('/programs/{foreignProgram}/signed-copy', [NhrdcSelfServiceController::class, 'downloadSignedCopy'])->name('signed-copy.download');
    Route::delete('/programs/{foreignProgram}/signed-copy', [NhrdcSelfServiceController::class, 'destroySignedCopy'])->name('signed-copy.destroy');
    Route::post('/nominees/{nominee}/rating', [NhrdcSelfServiceController::class, 'saveRating'])->name('ratings.save');
});

// REGULAR USER

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/my-programs', [EnrolledProgramController::class, 'index'])
        ->name('programs.my-programs');

    Route::get('/my-programs/{batch}', [EnrolledProgramController::class, 'show'])
        ->name('programs.my-progress');

    // MY CERTIFICATES — every certificate of the signed-in employee in one gallery
    Route::get('/my-certificates', [MyCertificateController::class, 'index'])
        ->name('certificates.mine');

    Route::post('/my-programs/{batch}/requirements/{requirement}/submit', [EnrolledProgramController::class, 'submitRequirement'])
        ->name('programs.my-progress.submit');

    Route::delete('/my-programs/{batch}/requirements/{requirement}/submission', [EnrolledProgramController::class, 'destroySubmission'])
        ->name('programs.my-progress.destroy');

    // TREAP / REAP REPORT FORM — fills out the form, then generates and submits the PDF
    Route::get('/my-programs/{batch}/requirements/{requirement}/terminal-report', [TerminalReportController::class, 'edit'])
        ->name('programs.terminal-report.edit');

    Route::put('/my-programs/{batch}/requirements/{requirement}/terminal-report', [TerminalReportController::class, 'update'])
        ->name('programs.terminal-report.update');

    Route::get('/my-programs/{batch}/requirements/{requirement}/terminal-report/generated', [TerminalReportController::class, 'generated'])
        ->name('programs.terminal-report.generated');

    // ACTION PLAN — add activities and submit an implementation report per activity
    Route::post('/my-programs/{batch}/action-plan/activities', [ActionPlanController::class, 'storeActivity'])
        ->name('programs.action-plan.activities.store');

    Route::delete('/my-programs/{batch}/action-plan/activities/{activity}', [ActionPlanController::class, 'destroyActivity'])
        ->name('programs.action-plan.activities.destroy');

    Route::post('/my-programs/{batch}/action-plan/activities/{activity}/implementation-report', [ActionPlanController::class, 'submitReport'])
        ->name('programs.action-plan.report.submit');

    // ABSENCE JUSTIFICATION
    Route::post('/my-programs/{batch}/documents', [EnrolledProgramController::class, 'submitOtherDocument'])
        ->name('programs.my-progress.documents.submit');

    Route::delete('/my-programs/{batch}/documents/{submission}', [EnrolledProgramController::class, 'destroyOtherDocument'])
        ->name('programs.my-progress.documents.destroy');

    Route::post('/my-programs/{batch}/absent-justification', [EnrolledProgramController::class, 'submitJustification'])
        ->name('programs.my-progress.justification.submit');

    Route::delete('/my-programs/{batch}/absent-justification', [EnrolledProgramController::class, 'destroyJustification'])
        ->name('programs.my-progress.justification.destroy');

    // CERTIFICATES
    Route::post('/my-programs/{batch}/certificates', [CertificateController::class, 'uploadByUser'])
        ->name('certificates.upload-by-user');

    Route::post('/my-programs/{batch}/certificates/bulk', [CertificateController::class, 'bulkUploadByUser'])
        ->name('certificates.bulk-upload-by-user');

    Route::delete('/my-programs/{batch}/certificates/{certificate}', [CertificateController::class, 'destroyByUser'])
        ->name('certificates.destroy-by-user');

    // NOTIFICATIONS
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');

    // Dedicated notifications page (the route above is the JSON feed for the bell dropdown)
    Route::get('/notifications/all', [NotificationController::class, 'page'])
        ->name('notifications.page');

    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read');

    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('notifications.read-all');

    // PROBLEM REPORTS
    Route::post('/problem-reports', [ProblemReportController::class, 'store'])
        ->name('problem-reports.store');
    // The reporter's own reports + conversations; replies are open to the reporter and super admins.
    Route::get('/problem-reports/mine', [ProblemReportController::class, 'mine'])
        ->name('problem-reports.mine');
    Route::post('/problem-reports/{problemReport}/replies', [ProblemReportController::class, 'reply'])
        ->name('problem-reports.replies.store');

    // SELF RATING

    Route::get('/tna/self-rating', [TnaController::class, 'selfRating'])
        ->name('tna.self-rating');

    Route::post('/tna/self-rating', [TnaController::class, 'store'])
        ->name('tna.self-rating.store');

    Route::get('/tna/supervisor-search', [TnaController::class, 'searchSupervisor'])->middleware('search.suggest')
        ->name('tna.supervisor.search');

    Route::get('/tna/fasd-search', [TnaController::class, 'searchFasd'])->middleware('search.suggest')
        ->name('tna.fasd.search');

    Route::get('/tna/assessments/{assessment}/pdf', [TnaController::class, 'pdf'])
        ->name('tna.self-rating.pdf');

    Route::delete('/tna/assessments/{assessment}', [TnaController::class, 'destroy'])
        ->name('tna.self-rating.destroy');

    Route::patch('/tna/assessments/{assessment}/supervisor', [TnaController::class, 'updateSupervisor'])
        ->name('tna.self-rating.supervisor');

    // SUPERVISORY
    Route::get('/tna/supervisory', [TnaController::class, 'supervisoryIndex'])
        ->name('tna.supervisory.index');

    Route::get('/tna/supervisory/{assessment}', [TnaController::class, 'supervisoryShow'])
        ->name('tna.supervisory.show');

    Route::post('/tna/supervisory/{assessment}', [TnaController::class, 'supervisoryStore'])
        ->name('tna.supervisory.store');

    Route::get('/tna/supervisory/{assessment}/pdf', [TnaController::class, 'supervisoryPdf'])
        ->name('tna.supervisory.pdf');

    Route::delete('/tna/supervisory/{assessment}/rating', [TnaController::class, 'supervisoryRedo'])
        ->name('tna.supervisory.redo');

    Route::get('/tna/supervisory/{assessment}/fasd', [TnaController::class, 'editFasd'])
        ->name('tna.supervisory.fasd.edit');

    Route::patch('/tna/supervisory/{assessment}/fasd', [TnaController::class, 'updateFasd'])
        ->name('tna.supervisory.fasd.update');

    // SIGNED COPIES (karagdagang attachment lang, hindi ang auto-generated PDF)
    Route::post('/tna/assessments/{assessment}/scans/{type}', [TnaController::class, 'uploadScan'])
        ->whereIn('type', array_keys(TnaController::SCAN_TYPES))
        ->name('tna.scans.upload');

    Route::get('/tna/assessments/{assessment}/scans/{type}', [TnaController::class, 'downloadScan'])
        ->whereIn('type', array_keys(TnaController::SCAN_TYPES))
        ->name('tna.scans.download');

    Route::delete('/tna/assessments/{assessment}/scans/{type}', [TnaController::class, 'deleteScan'])
        ->whereIn('type', array_keys(TnaController::SCAN_TYPES))
        ->name('tna.scans.destroy');

    // TNA RESULT
    Route::get('/tna/results/{assessment}', [TnaController::class, 'resultShow'])->name('tna.result.show');
    Route::get('/tna/results/{assessment}/pdf', [TnaController::class, 'resultPdf'])->name('tna.result.pdf');

});

// PUBLIC USER

Route::get('/nominate/{slug}', [ForeignNominationController::class, 'show'])->name('nominate.show');
Route::get('/nominate/{slug}/check-email', [ForeignNominationController::class, 'checkEmail'])->name('nominate.check-email');
Route::post('/nominate/{slug}', [ForeignNominationController::class, 'submit'])->name('nominate.submit');
Route::get('/nominate/{slug}/success', [ForeignNominationController::class, 'success'])->name('nominate.success');

Route::get('/evaluate/{slug}', [EvaluationController::class, 'show'])->name('evaluate.show');
Route::post('/evaluate/{slug}', [EvaluationController::class, 'submit'])->name('evaluate.submit');
Route::get('/evaluate/{slug}/success', [EvaluationController::class, 'success'])->name('evaluate.success');

Route::get('/join/{slug}', [PublicRegistrationController::class, 'show'])->name('join.show');
// Each throttle has its own prefix so searching doesn't use up the submit allowance
// (offices often share one IP, so the limits are generous).
Route::get('/join/{slug}/search', [PublicRegistrationController::class, 'search'])->middleware(['throttle:60,1,join-search', 'search.suggest'])->name('join.search');
Route::post('/join/{slug}', [PublicRegistrationController::class, 'submit'])->middleware('throttle:20,1,join-submit')->name('join.submit');
Route::get('/join/{slug}/success', [PublicRegistrationController::class, 'success'])->name('join.success');

// Public forms (e.g. the Learning and Development Survey): no login, employees find their name first
Route::get('/forms', [PublicFormController::class, 'index'])->name('public-forms.index');
Route::get('/forms/{slug}', [PublicFormController::class, 'show'])->name('public-forms.show');
Route::get('/forms/{slug}/search', [PublicFormController::class, 'search'])->middleware(['throttle:60,1,public-form-search', 'search.suggest'])->name('public-forms.search');
Route::post('/forms/{slug}', [PublicFormController::class, 'submit'])->middleware('throttle:20,1,public-form-submit')->name('public-forms.submit');
Route::get('/forms/{slug}/success', [PublicFormController::class, 'success'])->name('public-forms.success');

// LOCAL ONLY: email template preview
if (app()->environment('local')) {
    Route::get('/mail-preview/reminder', fn () => new ReminderEmail(
        emailSubject: 'Reminder: Submit your requirements',
        body: '<p>Good day! This is a reminder to submit your pending requirements.</p>',
        signature: 'TESDA Development Institute',
        trainingProgram: 'Emergency First Aid with AED Training',
        dueDate: 'October 5, 2026',
        status: 'Pending',
    ))->name('mail-preview.reminder');
}

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
