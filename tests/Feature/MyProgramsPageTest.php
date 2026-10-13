<?php

use App\Models\Batch;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function myProgramsPageUser(string $empcode): User
{
    Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Dela Cruz',
        'FIRSTNAME' => 'Juan',
        'MI' => 'D',
        'POSITION' => 'Test Position',
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => 'NCR',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);

    return User::factory()->create(['empcode' => $empcode]);
}

function myProgramsPageEnroll(string $empcode, string $title): void
{
    $program = Program::create([
        'title' => $title,
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'TECHNICAL',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ]);
    $batch = Batch::create([
        'program_code' => $program->program_code,
        'batch' => 'Batch 1',
        'status' => 'Open',
        'modality' => 'Onsite',
        'date_start' => '2026-01-01',
        'date_end' => '2026-01-02',
        'time_start' => '08:00',
        'time_end' => '17:00',
        'days' => '2',
        'hours' => '16',
    ]);
    Participant::create([
        'sort_order' => 1,
        'batch_id' => $batch->id,
        'empcode' => $empcode,
        'attendance' => 'Pending',
        'hours' => 0,
        'added_by' => 'system',
    ]);
}

test('guests are redirected to login from the my programs page', function () {
    $this->get(route('programs.my-programs'))->assertRedirect(route('login'));
});

test('the my programs page lists only the signed-in user\'s enrolled programs', function () {
    $user = myProgramsPageUser('EMP-MYPROG-01');
    myProgramsPageUser('EMP-MYPROG-02');

    myProgramsPageEnroll('EMP-MYPROG-01', 'My Own Program');
    myProgramsPageEnroll('EMP-MYPROG-02', 'Someone Else Program');

    $this->actingAs($user)
        ->get(route('programs.my-programs'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MyPrograms/Index')
            ->has('programs', 1)
            ->where('programs.0.program_title', 'My Own Program')
        );
});

test('the my programs page renders an empty list for a user with no enrollments', function () {
    $user = myProgramsPageUser('EMP-MYPROG-03');

    $this->actingAs($user)
        ->get(route('programs.my-programs'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MyPrograms/Index')
            ->has('programs', 0)
        );
});
