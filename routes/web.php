<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CoverPageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeclarationController;
use App\Http\Controllers\EmailReminderController;
use App\Http\Controllers\EmployeeProgressController;
use App\Http\Controllers\EnrolledProgramController;
use App\Http\Controllers\ForeignAgencyController;
use App\Http\Controllers\ForeignNominationController;
use App\Http\Controllers\ForeignParticipantController;
use App\Http\Controllers\ForeignProgramController;
use App\Http\Controllers\ForeignSponsorConfigController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizingSponsorController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\RegionalReportController;
use App\Http\Controllers\RequirementController;
use App\Http\Controllers\RequirementsTrackerController;
use App\Http\Controllers\ResourceSpeakerController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\SupportingDocumentController;
use App\Http\Controllers\TesdaOrderController;
use App\Http\Controllers\TnaController;
use App\Http\Controllers\TPMRController;
use App\Models\ForeignNominee;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome/index', [
        'enrolledPrograms' => auth()->check()
            ? EnrolledProgramController::forUser(auth()->user())
            : [],
        'tna' => TnaController::bannerData(auth()->user()),
        'supervisorTna' => TnaController::supervisorBannerData(auth()->user()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified', 'admin'])->name('dashboard');

Route::middleware(['auth', 'verified', 'admin'])->group(function () {

    // PROGRAMS
    Route::get('/programs', [ProgramController::class, 'index'])->name('programs.index');
    Route::post('/programs', [ProgramController::class, 'store'])->name('programs.store');
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
    Route::get('/participants/search', [ParticipantController::class, 'search'])->name('participants.search');
    Route::post('/participants', [ParticipantController::class, 'store'])->name('participants.store');
    Route::delete('/participants/{participant}', [ParticipantController::class, 'destroy'])->name('participants.destroy');
    Route::post('/participants/{participant}/attendance', [ParticipantController::class, 'updateAttendance'])
        ->name('participants.attendance');
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

    // NEW: Lookup endpoints for filter dropdowns
    Route::get('/dashboard/offices', [DashboardController::class, 'offices'])->name('dashboard.offices');
    Route::get('/dashboard/batch-years', [DashboardController::class, 'batchYears'])->name('dashboard.batch-years');

    // CALENDAR
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');

    // PROGRAM REQUIREMENTS
    Route::post('programs/{program}/requirements', [RequirementController::class, 'store'])
        ->name('programs.requirements.store');
    Route::delete('programs/{program}/requirements/{requirement}', [RequirementController::class, 'destroy'])
        ->name('programs.requirements.destroy');

    // REQUIREMENTS SUBMISSIONS
    Route::post('submissions', [SubmissionController::class, 'store'])
        ->name('submissions.store');
    Route::patch('submissions/{submission}/review', [SubmissionController::class, 'review'])
        ->name('submissions.review');
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

    // COVER PAGE
    Route::post('/programs/cover/upload', [CoverPageController::class, 'upload'])->name('programs.cover.upload');
    Route::delete('/programs/{program}/cover', [CoverPageController::class, 'destroy'])->name('programs.cover.destroy');

    // FOREIGN PROGRAMS

    // FOREIGN DASHBOARD
    Route::get('foreign-programs/dashboard-data', [ForeignProgramController::class, 'dashboardData'])
        ->name('foreign-programs.dashboard-data');

    Route::get('/foreign-programs/by-sponsor', [ForeignProgramController::class, 'byOrganizingSponsor'])
        ->name('foreign-programs.by-sponsor')
        ->middleware('auth');

    Route::get('/foreign-programs', [ForeignProgramController::class, 'index'])->name('foreign-programs.index');
    Route::get('/foreign-programs/{foreignProgram}', [ForeignProgramController::class, 'show'])->name('foreign-programs.show');
    Route::post('/foreign-programs', [ForeignProgramController::class, 'store'])->name('foreign-programs.store');
    Route::put('/foreign-programs/{foreignProgram}', [ForeignProgramController::class, 'update'])->name('foreign-programs.update');
    Route::delete('/foreign-programs/{foreignProgram}', [ForeignProgramController::class, 'destroy'])->name('foreign-programs.destroy');

    // FOREIGN PARTICIPANTS
    Route::post('/foreign-programs/{foreignProgram}/participants', [ForeignParticipantController::class, 'store'])->name('foreign-participants.store');
    Route::put('/foreign-participants/{foreignParticipant}', [ForeignParticipantController::class, 'update'])->name('foreign-participants.update');
    Route::delete('/foreign-participants/{foreignParticipant}', [ForeignParticipantController::class, 'destroy'])->name('foreign-participants.destroy');

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
    Route::get('/employees/search', [TPMRController::class, 'searchEmployees'])->name('employees.search');

    // POST-TRAINING REQUIREMENTS TRACKER
    Route::get('/requirements-tracker', [RequirementsTrackerController::class, 'index'])->name('requirements-tracker.index');
    Route::get('/requirements-tracker/export', [RequirementsTrackerController::class, 'exportCsv'])
        ->name('requirements-tracker.export');

    // DECLARATION OF COMPLETERS:
    Route::get('/declarations/signatories/search', [DeclarationController::class, 'searchSignatory'])
        ->name('declarations.signatories.search');

    Route::get('/batches/{batch}/declaration', [DeclarationController::class, 'generate'])
        ->name('batches.declaration');

    // ORGANIZING SPONSORS
    Route::get('/organizing-sponsors', [OrganizingSponsorController::class, 'index'])->name('organizing-sponsors.index');
    Route::post('/organizing-sponsors', [OrganizingSponsorController::class, 'store'])->name('organizing-sponsors.store');
    Route::delete('/organizing-sponsors/{organizingSponsor}', [OrganizingSponsorController::class, 'destroy'])->name('organizing-sponsors.destroy');

    // EMAIL REMINDER
    Route::post('/email-reminder/send', [EmailReminderController::class, 'send'])
        ->name('email-reminder.send');

    // CERTIFICATES
    // ── ADMIN routes (inside auth + admin middleware group) ──
    Route::post('/certificates', [CertificateController::class, 'store'])
        ->name('certificates.store');

    Route::delete('/certificates/{certificate}', [CertificateController::class, 'destroy'])
        ->name('certificates.destroy');

    // ATTENDANCE
    Route::get('/employees/search-signatory', [AttendanceController::class, 'searchSignatory'])
        ->name('employees.search-signatory');

    Route::get('/attendance/generate', [AttendanceController::class, 'generate'])
        ->name('attendance.generate');

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

    // Nominee Status Update
    Route::patch('/foreign-nominees/{nominee}/status',
        [ForeignSponsorConfigController::class, 'updateNomineeStatus']
    )->name('foreign-nominees.status');

    Route::delete('/foreign-nominees/{nominee}', function (ForeignNominee $nominee) {
        $nominee->delete();

        return back();
    })->name('foreign-nominees.destroy')->middleware('auth');

    // TESDA ORDER
    Route::get('/programs/{program}/tesda-orders', [TesdaOrderController::class, 'index'])->name('tesda-orders.index');
    Route::post('/programs/{program}/tesda-orders', [TesdaOrderController::class, 'store'])->name('tesda-orders.store');
    Route::get('/tesda-orders/{tesdaOrder}/download', [TesdaOrderController::class, 'download'])->name('tesda-orders.download');
    Route::delete('/tesda-orders/{tesdaOrder}', [TesdaOrderController::class, 'destroy'])->name('tesda-orders.destroy');
    Route::get('/tesda-orders/search-signatory', [TesdaOrderController::class, 'searchSignatory'])->name('tesda-orders.search-signatory');
    Route::get('/programs/{program}/tesda-orders/participants', [TesdaOrderController::class, 'participants'])
        ->name('tesda-orders.participants');

});

// REGULAR USER

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/my-programs/{batch}', [EnrolledProgramController::class, 'show'])
        ->name('programs.my-progress');

    Route::post('/my-programs/{batch}/requirements/{requirement}/submit', [EnrolledProgramController::class, 'submitRequirement'])
        ->name('programs.my-progress.submit');

    Route::delete('/my-programs/{batch}/requirements/{requirement}/submission', [EnrolledProgramController::class, 'destroySubmission'])
        ->name('programs.my-progress.destroy');

    // CERTIFICATES
    Route::post('/my-programs/{batch}/certificates', [CertificateController::class, 'uploadByUser'])
        ->name('certificates.upload-by-user');

    Route::delete('/my-programs/{batch}/certificates/{certificate}', [CertificateController::class, 'destroyByUser'])
        ->name('certificates.destroy-by-user');

    // NOTIFICATIONS
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');

    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read');

    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('notifications.read-all');

    // SELF RATING

    Route::get('/tna/self-rating', [TnaController::class, 'selfRating'])
        ->name('tna.self-rating');

    Route::post('/tna/self-rating', [TnaController::class, 'store'])
        ->name('tna.self-rating.store');

    Route::get('/tna/supervisor-search', [TnaController::class, 'searchSupervisor'])
        ->name('tna.supervisor.search');

    Route::get('/tna/fasd-search', [TnaController::class, 'searchFasd'])
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
Route::post('/nominate/{slug}', [ForeignNominationController::class, 'submit'])->name('nominate.submit');
Route::get('/nominate/{slug}/success', [ForeignNominationController::class, 'success'])->name('nominate.success');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
