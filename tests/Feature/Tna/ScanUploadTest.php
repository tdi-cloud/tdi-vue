<?php

use App\Models\Employee;
use App\Models\TnaAssessment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function scanTestEmployee(string $empcode, string $position): Employee
{
    $employee = new Employee;
    $employee->forceFill([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Dela Cruz',
        'FIRSTNAME' => 'Juan',
        'MI' => 'D',
        'POSITION' => $position,
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => 'NCR',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ])->save();

    return $employee;
}

function scanTestAssessment(User $subordinateUser, Employee $supervisorEmployee, ?string $position = 'Test Officer'): TnaAssessment
{
    return TnaAssessment::create([
        'user_id' => $subordinateUser->id,
        'position' => $position,
        'period' => config('tna.period'),
        'name' => 'Juan D. Dela Cruz',
        'supervisor_empcode' => $supervisorEmployee->EMPCODE,
        'supervisor_name' => 'Maria Supervisor',
        'signature' => null,
        'submitted_at' => now(),
    ]);
}

test('owner can upload a self-rating scan', function () {
    Storage::fake('local');

    $employee = scanTestEmployee('EMP-201', 'Test Officer 1');
    $supervisorEmployee = scanTestEmployee('EMP-202', 'Supervisor');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $assessment = scanTestAssessment($user, $supervisorEmployee);

    $file = UploadedFile::fake()->create('scan.pdf', 500, 'application/pdf');

    $response = $this->actingAs($user)
        ->post(route('tna.scans.upload', [$assessment, 'self']), ['file' => $file]);

    $response->assertSessionDoesntHaveErrors();
    $assessment->refresh();
    expect($assessment->self_rating_scan_path)->not->toBeNull();
    Storage::disk('local')->assertExists($assessment->self_rating_scan_path);
});

test('non-owner cannot upload a self-rating scan', function () {
    Storage::fake('local');

    $employee = scanTestEmployee('EMP-203', 'Test Officer 2');
    $supervisorEmployee = scanTestEmployee('EMP-204', 'Supervisor');
    $otherEmployee = scanTestEmployee('EMP-205', 'Someone Else');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $otherUser = User::factory()->create(['empcode' => $otherEmployee->EMPCODE]);
    $assessment = scanTestAssessment($user, $supervisorEmployee);

    $file = UploadedFile::fake()->create('scan.pdf', 500, 'application/pdf');

    $response = $this->actingAs($otherUser)
        ->post(route('tna.scans.upload', [$assessment, 'self']), ['file' => $file]);

    $response->assertForbidden();
});

test('assigned supervisor can upload a supervisory-rating scan', function () {
    Storage::fake('local');

    $employee = scanTestEmployee('EMP-206', 'Test Officer 3');
    $supervisorEmployee = scanTestEmployee('EMP-207', 'Supervisor');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $supervisorUser = User::factory()->create(['empcode' => $supervisorEmployee->EMPCODE]);
    $assessment = scanTestAssessment($user, $supervisorEmployee);

    $file = UploadedFile::fake()->create('scan.pdf', 500, 'application/pdf');

    $response = $this->actingAs($supervisorUser)
        ->post(route('tna.scans.upload', [$assessment, 'supervisory']), ['file' => $file]);

    $response->assertSessionDoesntHaveErrors();
    expect($assessment->fresh()->supervisor_rating_scan_path)->not->toBeNull();
});

test('rejects a non-pdf file', function () {
    Storage::fake('local');

    $employee = scanTestEmployee('EMP-208', 'Test Officer 4');
    $supervisorEmployee = scanTestEmployee('EMP-209', 'Supervisor');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $assessment = scanTestAssessment($user, $supervisorEmployee);

    $file = UploadedFile::fake()->create('scan.txt', 100, 'text/plain');

    $response = $this->actingAs($user)
        ->post(route('tna.scans.upload', [$assessment, 'self']), ['file' => $file]);

    $response->assertSessionHasErrors('file');
    expect($assessment->fresh()->self_rating_scan_path)->toBeNull();
});

test('rejects a pdf larger than 10MB', function () {
    Storage::fake('local');

    $employee = scanTestEmployee('EMP-210', 'Test Officer 5');
    $supervisorEmployee = scanTestEmployee('EMP-211', 'Supervisor');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $assessment = scanTestAssessment($user, $supervisorEmployee);

    $file = UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf');

    $response = $this->actingAs($user)
        ->post(route('tna.scans.upload', [$assessment, 'self']), ['file' => $file]);

    $response->assertSessionHasErrors('file');
});

test('replacing a scan overwrites the existing file at the same slot', function () {
    Storage::fake('local');

    $employee = scanTestEmployee('EMP-212', 'Test Officer 6');
    $supervisorEmployee = scanTestEmployee('EMP-213', 'Supervisor');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $assessment = scanTestAssessment($user, $supervisorEmployee);

    $first = UploadedFile::fake()->create('first.pdf', 200, 'application/pdf');
    $this->actingAs($user)->post(route('tna.scans.upload', [$assessment, 'self']), ['file' => $first]);
    $firstPath = $assessment->fresh()->self_rating_scan_path;
    Storage::disk('local')->assertExists($firstPath);

    $second = UploadedFile::fake()->create('second.pdf', 350, 'application/pdf');
    $response = $this->actingAs($user)->post(route('tna.scans.upload', [$assessment, 'self']), ['file' => $second]);
    $secondPath = $assessment->fresh()->self_rating_scan_path;

    // Parehong slot (fixed filename per type) — kaya iisa lang dapat sa path,
    // at hindi dapat nag-e-error kahit paulit-ulit na-o-overwrite.
    $response->assertSessionDoesntHaveErrors();
    expect($secondPath)->toBe($firstPath);
    Storage::disk('local')->assertExists($secondPath);
});

test('both subordinate and supervisor can view an uploaded scan inline, but a stranger cannot', function () {
    Storage::fake('local');

    $employee = scanTestEmployee('EMP-214', 'Test Officer 7');
    $supervisorEmployee = scanTestEmployee('EMP-215', 'Supervisor');
    $strangerEmployee = scanTestEmployee('EMP-216', 'Someone Else');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $supervisorUser = User::factory()->create(['empcode' => $supervisorEmployee->EMPCODE]);
    $strangerUser = User::factory()->create(['empcode' => $strangerEmployee->EMPCODE]);
    $assessment = scanTestAssessment($user, $supervisorEmployee);

    $file = UploadedFile::fake()->create('scan.pdf', 500, 'application/pdf');
    $this->actingAs($user)->post(route('tna.scans.upload', [$assessment, 'self']), ['file' => $file]);

    $ownerResponse = $this->actingAs($user)
        ->get(route('tna.scans.download', [$assessment, 'self']));
    $ownerResponse->assertOk();
    expect($ownerResponse->headers->get('Content-Disposition'))->toContain('inline');

    $this->actingAs($supervisorUser)
        ->get(route('tna.scans.download', [$assessment, 'self']))
        ->assertOk();

    $this->actingAs($strangerUser)
        ->get(route('tna.scans.download', [$assessment, 'self']))
        ->assertForbidden();
});

test('downloading a scan that was never uploaded returns 404', function () {
    Storage::fake('local');

    $employee = scanTestEmployee('EMP-217', 'Test Officer 8');
    $supervisorEmployee = scanTestEmployee('EMP-218', 'Supervisor');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $assessment = scanTestAssessment($user, $supervisorEmployee);

    $this->actingAs($user)
        ->get(route('tna.scans.download', [$assessment, 'self']))
        ->assertNotFound();
});

test('an unknown scan type returns 404', function () {
    Storage::fake('local');

    $employee = scanTestEmployee('EMP-219', 'Test Officer 9');
    $supervisorEmployee = scanTestEmployee('EMP-220', 'Supervisor');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $assessment = scanTestAssessment($user, $supervisorEmployee);

    $file = UploadedFile::fake()->create('scan.pdf', 500, 'application/pdf');

    $this->actingAs($user)
        ->post(route('tna.scans.upload', [$assessment, 'not-a-real-type']), ['file' => $file])
        ->assertNotFound();
});

test('uploader can delete their own uploaded scan', function () {
    Storage::fake('local');

    $employee = scanTestEmployee('EMP-221', 'Test Officer 10');
    $supervisorEmployee = scanTestEmployee('EMP-222', 'Supervisor');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $assessment = scanTestAssessment($user, $supervisorEmployee);

    $file = UploadedFile::fake()->create('scan.pdf', 500, 'application/pdf');
    $this->actingAs($user)->post(route('tna.scans.upload', [$assessment, 'self']), ['file' => $file]);
    $path = $assessment->fresh()->self_rating_scan_path;
    Storage::disk('local')->assertExists($path);

    $response = $this->actingAs($user)->delete(route('tna.scans.destroy', [$assessment, 'self']));

    $response->assertSessionDoesntHaveErrors();
    expect($assessment->fresh()->self_rating_scan_path)->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

test('non-uploader cannot delete a scan', function () {
    Storage::fake('local');

    $employee = scanTestEmployee('EMP-223', 'Test Officer 11');
    $supervisorEmployee = scanTestEmployee('EMP-224', 'Supervisor');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $supervisorUser = User::factory()->create(['empcode' => $supervisorEmployee->EMPCODE]);
    $assessment = scanTestAssessment($user, $supervisorEmployee);

    $file = UploadedFile::fake()->create('scan.pdf', 500, 'application/pdf');
    $this->actingAs($user)->post(route('tna.scans.upload', [$assessment, 'self']), ['file' => $file]);

    $response = $this->actingAs($supervisorUser)->delete(route('tna.scans.destroy', [$assessment, 'self']));

    $response->assertForbidden();
    expect($assessment->fresh()->self_rating_scan_path)->not->toBeNull();
});

test('deleting a scan that was never uploaded returns 404', function () {
    Storage::fake('local');

    $employee = scanTestEmployee('EMP-225', 'Test Officer 12');
    $supervisorEmployee = scanTestEmployee('EMP-226', 'Supervisor');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE]);
    $assessment = scanTestAssessment($user, $supervisorEmployee);

    $this->actingAs($user)
        ->delete(route('tna.scans.destroy', [$assessment, 'self']))
        ->assertNotFound();
});
