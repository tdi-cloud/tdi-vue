<?php

use App\Http\Controllers\BatchController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\RequirementController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\SupportingDocumentController;
use App\Http\Controllers\ResourceSpeakerController;
use App\Http\Controllers\CoverPageController;
use App\Http\Controllers\ForeignProgramController;
use App\Http\Controllers\ForeignParticipantController;
use App\Http\Controllers\RegionalReportController;
use App\Http\Controllers\EmployeeProgressController;
use App\Http\Controllers\EnrolledProgramController;
use App\Http\Controllers\DeclarationController;
use App\Http\Controllers\TPMRController;
use App\Http\Controllers\OrganizingSponsorController;
use App\Http\Controllers\EmailReminderController;
use App\Http\Controllers\CertificateController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;



Route::get('/', function () {
    return Inertia::render('Welcome/index', [
        'enrolledPrograms' => auth()->check()
            ? EnrolledProgramController::forUser(auth()->user())
            : [],
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified', 'admin'])->name('dashboard');


Route::middleware(['auth', 'verified', 'admin'])->group(function(){

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

    // NEW: Lookup endpoints for filter dropdowns
    Route::get('/dashboard/offices',     [DashboardController::class, 'offices'])->name('dashboard.offices');
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
    Route::get('/employees/{empcode}/progress', [EmployeeProgressController::class, 'show'])->name('employees.progress');
    

   
    Route::get('/reports/tpmr', [TPMRController::class, 'generate'])->name('reports.tpmr');
    Route::get('/employees/search', [TPMRController::class, 'searchEmployees'])->name('employees.search');
    


    // DECLARATION OF COMPLETERS:
    Route::get('/declarations/signatories/search', [DeclarationController::class, 'searchSignatory'])
        ->name('declarations.signatories.search');

    Route::get('/batches/{batch}/declaration', [DeclarationController::class, 'generate'])
        ->name('batches.declaration');


    // ORGANIZING SPONSORS
    Route::get('/organizing-sponsors',        [OrganizingSponsorController::class, 'index'])->name('organizing-sponsors.index');
    Route::post('/organizing-sponsors',       [OrganizingSponsorController::class, 'store'])->name('organizing-sponsors.store');
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
});



require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
