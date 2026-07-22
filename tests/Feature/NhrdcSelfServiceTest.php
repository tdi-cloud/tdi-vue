<?php

use App\Models\Employee;
use App\Models\ForeignNominee;
use App\Models\ForeignNomineeAssessment;
use App\Models\ForeignNomineeInterviewRating;
use App\Models\ForeignProgram;
use App\Models\NhrdcMember;
use App\Models\User;

function selfServiceEmployee(string $empcode, string $lastname, string $firstname, string $position = 'Test Position'): Employee
{
    return Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => $lastname,
        'FIRSTNAME' => $firstname,
        'MI' => 'R',
        'POSITION' => $position,
        'SG' => '15',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => 'CO',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);
}

function nhrdcSelfServiceUser(string $empcode, string $lastname, string $firstname, string $access = 'user'): User
{
    selfServiceEmployee($empcode, $lastname, $firstname, 'NHRDC Member');
    NhrdcMember::create(['empcode' => $empcode, 'sort_order' => NhrdcMember::nextSortOrder()]);

    return User::factory()->create(['empcode' => $empcode, 'access' => $access]);
}

function selfServiceNominee(): ForeignNominee
{
    $program = ForeignProgram::create([
        'program_title' => 'Self Service Test Program',
        'program_start' => now()->addMonth()->toDateString(),
        'program_end' => now()->addMonth()->addDays(5)->toDateString(),
        'slots' => 10,
        'modality' => 'in-person',
        'category' => 'Foreign',
        'organizing_sponsor' => 'JICA',
        'status' => 'for_dissemination',
    ]);

    return ForeignNominee::create([
        'foreign_program_id' => $program->id,
        'firstname' => 'Juan',
        'surname' => 'Dela Cruz',
        'sex' => 'male',
        'age' => 30,
        'position' => 'Test Position',
        'agency' => 'Test Agency',
        'status' => 'for_interview',
    ]);
}

function validSelfServiceScores(): array
{
    return [
        'communication_skills' => 5,
        'alertness' => 4,
        'judgement' => 5,
        'self_confidence' => 4,
        'emotional_stability' => 5,
        'appearance' => 5,
    ];
}

test('an NHRDC member can view the programs list', function () {
    $nhrdc = nhrdcSelfServiceUser('EMP-SS-01', 'Reyes', 'Carmela');
    selfServiceNominee();

    $response = $this->actingAs($nhrdc)->get(route('nhrdc.programs.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Nhrdc/Programs'));
});

test('a non-NHRDC authenticated user cannot access the NHRDC self-service area', function () {
    $employee = selfServiceEmployee('EMP-SS-02', 'Santos', 'Pedro');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);

    $this->actingAs($user)->get(route('nhrdc.programs.index'))->assertForbidden();
});

test('being an admin alone does not grant NHRDC self-service access', function () {
    $employee = selfServiceEmployee('EMP-SS-03', 'Cruz', 'Liza');
    $admin = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'admin']);

    $this->actingAs($admin)->get(route('nhrdc.programs.index'))->assertForbidden();
});

test('an NHRDC member sees the requirements total even if the admin never opened the assessment page', function () {
    $nhrdc = nhrdcSelfServiceUser('EMP-SS-13', 'Aquino', 'Ramon');
    $nominee = selfServiceNominee();

    expect(ForeignNomineeAssessment::where('foreign_nominee_id', $nominee->id)->exists())->toBeFalse();

    $response = $this->actingAs($nhrdc)->get(route('nhrdc.programs.show', $nominee->foreign_program_id));

    $response->assertInertia(fn ($page) => $page
        ->component('Nhrdc/Assessment')
        ->where('nominees.0.assessment.requirements_total', 70)
    );
});

test('an NHRDC member can view a program and only sees their own rating', function () {
    $nhrdc = nhrdcSelfServiceUser('EMP-SS-04', 'Garcia', 'Nora');
    $otherNhrdc = nhrdcSelfServiceUser('EMP-SS-05', 'Bautista', 'Carlos');
    $nominee = selfServiceNominee();

    ForeignNomineeInterviewRating::create([
        'foreign_nominee_id' => $nominee->id,
        'nhrdc_empcode' => $otherNhrdc->empcode,
        'nhrdc_name' => 'Carlos Bautista',
        'nhrdc_position' => 'Member',
        ...validSelfServiceScores(),
    ]);

    $response = $this->actingAs($nhrdc)->get(route('nhrdc.programs.show', $nominee->foreign_program_id));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Nhrdc/Assessment')
        ->where('nominees.0.my_rating', null)
    );
});

test('an NHRDC member can save their own interview rating', function () {
    $nhrdc = nhrdcSelfServiceUser('EMP-SS-06', 'Torres', 'Isabel');
    $nominee = selfServiceNominee();

    $response = $this->actingAs($nhrdc)->post(
        route('nhrdc.ratings.save', $nominee),
        validSelfServiceScores(),
    );

    $response->assertOk();

    $rating = ForeignNomineeInterviewRating::where('foreign_nominee_id', $nominee->id)->first();

    expect($rating)->not->toBeNull();
    expect($rating->nhrdc_empcode)->toBe($nhrdc->empcode);
    expect($rating->total)->toBe((float) (5 + 4 + 5 + 4 + 5 + 5));
    expect($rating->rated_by)->toBe($nhrdc->id);
});

test('an NHRDC member cannot submit a rating under a different empcode', function () {
    $nhrdc = nhrdcSelfServiceUser('EMP-SS-07', 'Villanueva', 'Marco');
    $otherNhrdc = nhrdcSelfServiceUser('EMP-SS-08', 'Fernandez', 'Miguel');
    $nominee = selfServiceNominee();

    $scores = validSelfServiceScores();
    $scores['nhrdc_empcode'] = $otherNhrdc->empcode; // attempted spoof, should be ignored

    $this->actingAs($nhrdc)->post(route('nhrdc.ratings.save', $nominee), $scores);

    $rating = ForeignNomineeInterviewRating::where('foreign_nominee_id', $nominee->id)->first();

    expect($rating->nhrdc_empcode)->toBe($nhrdc->empcode);
    expect(ForeignNomineeInterviewRating::where('nhrdc_empcode', $otherNhrdc->empcode)->exists())->toBeFalse();
});

test('saving a rating twice updates it instead of creating a duplicate', function () {
    $nhrdc = nhrdcSelfServiceUser('EMP-SS-09', 'Ramos', 'Elena');
    $nominee = selfServiceNominee();

    $this->actingAs($nhrdc)->post(route('nhrdc.ratings.save', $nominee), validSelfServiceScores());

    $updated = validSelfServiceScores();
    $updated['communication_skills'] = 2;
    $this->actingAs($nhrdc)->post(route('nhrdc.ratings.save', $nominee), $updated);

    expect(ForeignNomineeInterviewRating::where('foreign_nominee_id', $nominee->id)->count())->toBe(1);
    expect(ForeignNomineeInterviewRating::where('foreign_nominee_id', $nominee->id)->first()->communication_skills)->toBe('2.00');
});

test('interview scores above the criterion maximum are rejected', function () {
    $nhrdc = nhrdcSelfServiceUser('EMP-SS-10', 'Lopez', 'Paolo');
    $nominee = selfServiceNominee();

    $scores = validSelfServiceScores();
    $scores['appearance'] = 6; // max is 5

    $response = $this->actingAs($nhrdc)->post(route('nhrdc.ratings.save', $nominee), $scores);

    $response->assertSessionHasErrors('appearance');
});

test('a non-NHRDC user cannot save an interview rating', function () {
    $employee = selfServiceEmployee('EMP-SS-11', 'Manalo', 'Beatriz');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);
    $nominee = selfServiceNominee();

    $this->actingAs($user)
        ->post(route('nhrdc.ratings.save', $nominee), validSelfServiceScores())
        ->assertForbidden();
});

test('an NHRDC member can generate their own assessment sheet PDF', function () {
    $nhrdc = nhrdcSelfServiceUser('EMP-SS-12', 'Domingo', 'Rafael');
    $nominee = selfServiceNominee();

    $response = $this->actingAs($nhrdc)->get(route('nhrdc.programs.assessment-pdf', $nominee->foreign_program_id));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

test('an NHRDC member sees a pending rating summary on the homepage', function () {
    $nhrdc = nhrdcSelfServiceUser('EMP-SS-14', 'Alonzo', 'Grace');
    selfServiceNominee();
    selfServiceNominee();

    $response = $this->actingAs($nhrdc)->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('nhrdcRating.pending', 2)
        ->where('nhrdcRating.rated', 0)
        ->where('nhrdcRating.total', 2)
    );
});

test('the homepage rating summary reflects nominees the NHRDC member already rated', function () {
    $nhrdc = nhrdcSelfServiceUser('EMP-SS-15', 'Bernardo', 'Hector');
    $nominee = selfServiceNominee();

    $this->actingAs($nhrdc)->post(route('nhrdc.ratings.save', $nominee), validSelfServiceScores());

    $response = $this->actingAs($nhrdc)->get(route('home'));

    $response->assertInertia(fn ($page) => $page
        ->where('nhrdcRating.pending', 0)
        ->where('nhrdcRating.rated', 1)
        ->where('nhrdcRating.total', 1)
    );
});

test('a non-NHRDC user does not see the NHRDC rating summary on the homepage', function () {
    $employee = selfServiceEmployee('EMP-SS-16', 'Cortez', 'Ivy');
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);
    selfServiceNominee();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertInertia(fn ($page) => $page->where('nhrdcRating', null));
});
