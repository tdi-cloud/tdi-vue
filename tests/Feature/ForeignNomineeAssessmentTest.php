<?php

use App\Models\Employee;
use App\Models\ForeignNominee;
use App\Models\ForeignNomineeAssessment;
use App\Models\ForeignNomineeInterviewRating;
use App\Models\ForeignProgram;
use App\Models\NhrdcMember;
use App\Models\User;

function assessmentTestAdmin(string $empcode): User
{
    Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Admin',
        'FIRSTNAME' => 'Ana',
        'MI' => 'D',
        'POSITION' => 'HRMO',
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'F',
        'REGION' => 'CO',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);

    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin']);
}

function assessmentTestNominee(): ForeignNominee
{
    $program = ForeignProgram::create([
        'program_title' => 'Assessment Test Program',
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

function nhrdcTestEmployee(string $empcode, string $lastname, string $firstname): Employee
{
    return Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'NHRDC Office',
        'LASTNAME' => $lastname,
        'FIRSTNAME' => $firstname,
        'MI' => 'R',
        'POSITION' => 'NHRDC Member',
        'SG' => '15',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'M',
        'REGION' => 'CO',
        'OFFICE' => 'NHRDC Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);
}

function nhrdcRosterMember(string $empcode, string $lastname, string $firstname): Employee
{
    $employee = nhrdcTestEmployee($empcode, $lastname, $firstname);
    NhrdcMember::create(['empcode' => $employee->EMPCODE, 'sort_order' => NhrdcMember::nextSortOrder()]);

    return $employee;
}

function validAssessmentScores(): array
{
    // Each value must be one of the fixed rubric points for its criterion —
    // see ForeignNomineeAssessment::REQUIREMENT_OPTIONS.
    return [
        'need_for_training' => 17,
        'relevance_to_duties' => 28,
        'meets_donor_requirements' => 8,
        'completion_of_documents' => 6,
    ];
}

function validInterviewScores(): array
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

test('admin can view the nominee assessment sheet for a program', function () {
    $admin = assessmentTestAdmin('EMP-ASSESS-01');
    $nominee = assessmentTestNominee();

    $response = $this->actingAs($admin)->get(route('foreign-programs.assessment', $nominee->foreign_program_id));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('ForeignPrograms/Assessment')
        ->has('nominees', 1)
        ->where('nominees.0.id', $nominee->id)
    );
});

test('viewing the assessment sheet auto-creates a full-marks requirements default for unassessed nominees', function () {
    $admin = assessmentTestAdmin('EMP-ASSESS-15');
    $nominee = assessmentTestNominee();

    expect(ForeignNomineeAssessment::where('foreign_nominee_id', $nominee->id)->exists())->toBeFalse();

    $response = $this->actingAs($admin)->get(route('foreign-programs.assessment', $nominee->foreign_program_id));

    $response->assertInertia(fn ($page) => $page
        ->component('ForeignPrograms/Assessment')
        ->where('nominees.0.assessment.requirements_total', 70)
    );

    $assessment = ForeignNomineeAssessment::where('foreign_nominee_id', $nominee->id)->first();
    expect($assessment)->not->toBeNull();
    expect($assessment->requirements_total)->toBe(70.0);
});

test('admin can save the requirements section and the total is computed correctly', function () {
    $admin = assessmentTestAdmin('EMP-ASSESS-02');
    $nominee = assessmentTestNominee();

    $response = $this->actingAs($admin)->post(
        route('foreign-nominees.assessment.save', $nominee),
        validAssessmentScores(),
    );

    $response->assertOk();

    $assessment = ForeignNomineeAssessment::where('foreign_nominee_id', $nominee->id)->first();

    expect($assessment)->not->toBeNull();
    expect($assessment->requirements_total)->toBe((float) (17 + 28 + 8 + 6));
    expect($assessment->assessed_by)->toBe($admin->id);
    expect($assessment->assessed_at)->not->toBeNull();
});

test('saving the requirements section twice updates the existing record instead of creating a duplicate', function () {
    $admin = assessmentTestAdmin('EMP-ASSESS-03');
    $nominee = assessmentTestNominee();

    $this->actingAs($admin)->post(route('foreign-nominees.assessment.save', $nominee), validAssessmentScores());

    $updated = validAssessmentScores();
    $updated['need_for_training'] = 10;

    $this->actingAs($admin)->post(route('foreign-nominees.assessment.save', $nominee), $updated);

    expect(ForeignNomineeAssessment::where('foreign_nominee_id', $nominee->id)->count())->toBe(1);
    expect(ForeignNomineeAssessment::where('foreign_nominee_id', $nominee->id)->first()->need_for_training)->toBe('10.00');
});

test('requirements scores outside the fixed rubric options are rejected', function () {
    $admin = assessmentTestAdmin('EMP-ASSESS-04');
    $nominee = assessmentTestNominee();

    $scores = validAssessmentScores();
    $scores['need_for_training'] = 21; // not one of the rubric's fixed point values (20, 17, 15, 10)

    $response = $this->actingAs($admin)->post(route('foreign-nominees.assessment.save', $nominee), $scores);

    $response->assertSessionHasErrors('need_for_training');
    expect(ForeignNomineeAssessment::where('foreign_nominee_id', $nominee->id)->exists())->toBeFalse();
});

test('an NHRDC member can submit their own interview rating for a nominee', function () {
    $admin = assessmentTestAdmin('EMP-ASSESS-06');
    $nominee = assessmentTestNominee();
    $nhrdc = nhrdcRosterMember('EMP-NHRDC-01', 'Santos', 'Pedro');

    $response = $this->actingAs($admin)->post(
        route('foreign-nominees.interview-ratings.save', $nominee),
        ['nhrdc_empcode' => $nhrdc->EMPCODE, ...validInterviewScores()],
    );

    $response->assertOk();

    $rating = ForeignNomineeInterviewRating::where('foreign_nominee_id', $nominee->id)->first();

    expect($rating)->not->toBeNull();
    expect($rating->nhrdc_empcode)->toBe('EMP-NHRDC-01');
    expect($rating->nhrdc_name)->toBe($nhrdc->name);
    // First member added to the roster is always the Chairperson.
    expect($rating->nhrdc_position)->toBe('Chairperson, HRDC');
    expect($rating->total)->toBe((float) (5 + 4 + 5 + 4 + 5 + 5));
    expect($rating->rated_by)->toBe($admin->id);
});

test('multiple NHRDC members can each rate the same nominee independently', function () {
    $admin = assessmentTestAdmin('EMP-ASSESS-07');
    $nominee = assessmentTestNominee();
    $chair = nhrdcRosterMember('EMP-NHRDC-02', 'Cruz', 'Liza');
    $viceChair = nhrdcRosterMember('EMP-NHRDC-03', 'Reyes', 'Tomas');

    $this->actingAs($admin)->post(
        route('foreign-nominees.interview-ratings.save', $nominee),
        ['nhrdc_empcode' => $chair->EMPCODE, ...validInterviewScores()],
    );

    $lowerScores = validInterviewScores();
    $lowerScores['appearance'] = 2;
    $this->actingAs($admin)->post(
        route('foreign-nominees.interview-ratings.save', $nominee),
        ['nhrdc_empcode' => $viceChair->EMPCODE, ...$lowerScores],
    );

    $ratings = ForeignNomineeInterviewRating::where('foreign_nominee_id', $nominee->id)->get();

    expect($ratings)->toHaveCount(2);
    expect($ratings->firstWhere('nhrdc_empcode', $chair->EMPCODE)->nhrdc_position)->toBe('Chairperson, HRDC');
    expect($ratings->firstWhere('nhrdc_empcode', $viceChair->EMPCODE)->nhrdc_position)->toBe('Vice Chairperson, HRDC');
});

test('saving a rating twice for the same NHRDC member updates it instead of creating a duplicate', function () {
    $admin = assessmentTestAdmin('EMP-ASSESS-08');
    $nominee = assessmentTestNominee();
    $nhrdc = nhrdcRosterMember('EMP-NHRDC-04', 'Garcia', 'Nora');

    $this->actingAs($admin)->post(
        route('foreign-nominees.interview-ratings.save', $nominee),
        ['nhrdc_empcode' => $nhrdc->EMPCODE, ...validInterviewScores()],
    );

    $updated = validInterviewScores();
    $updated['communication_skills'] = 2;
    $this->actingAs($admin)->post(
        route('foreign-nominees.interview-ratings.save', $nominee),
        ['nhrdc_empcode' => $nhrdc->EMPCODE, ...$updated],
    );

    $ratings = ForeignNomineeInterviewRating::where('foreign_nominee_id', $nominee->id)->get();

    expect($ratings)->toHaveCount(1);
    expect($ratings->first()->communication_skills)->toBe('2.00');
});

test('only employees on the NHRDC roster can submit an interview rating', function () {
    $admin = assessmentTestAdmin('EMP-ASSESS-09');
    $nominee = assessmentTestNominee();
    // Employee exists but was never added to the NHRDC roster.
    $notOnRoster = nhrdcTestEmployee('EMP-NHRDC-05', 'Bautista', 'Carlos');

    $response = $this->actingAs($admin)->post(
        route('foreign-nominees.interview-ratings.save', $nominee),
        ['nhrdc_empcode' => $notOnRoster->EMPCODE, ...validInterviewScores()],
    );

    $response->assertSessionHasErrors('nhrdc_empcode');
});

test('interview scores above the criterion maximum are rejected', function () {
    $admin = assessmentTestAdmin('EMP-ASSESS-10');
    $nominee = assessmentTestNominee();
    $nhrdc = nhrdcRosterMember('EMP-NHRDC-06', 'Ramos', 'Elena');

    $scores = validInterviewScores();
    $scores['appearance'] = 6; // max is 5

    $response = $this->actingAs($admin)->post(
        route('foreign-nominees.interview-ratings.save', $nominee),
        ['nhrdc_empcode' => $nhrdc->EMPCODE, ...$scores],
    );

    $response->assertSessionHasErrors('appearance');
});

test('admin can remove an NHRDC member\'s interview rating', function () {
    $admin = assessmentTestAdmin('EMP-ASSESS-11');
    $nominee = assessmentTestNominee();
    $nhrdc = nhrdcRosterMember('EMP-NHRDC-07', 'Villanueva', 'Marco');

    $this->actingAs($admin)->post(
        route('foreign-nominees.interview-ratings.save', $nominee),
        ['nhrdc_empcode' => $nhrdc->EMPCODE, ...validInterviewScores()],
    );
    $rating = ForeignNomineeInterviewRating::where('foreign_nominee_id', $nominee->id)->first();

    $response = $this->actingAs($admin)->delete(route('foreign-nominee-interview-ratings.destroy', $rating));

    $response->assertOk();
    expect(ForeignNomineeInterviewRating::find($rating->id))->toBeNull();
});

test('the assessment sheet includes each nominee\'s requirements score and interview ratings', function () {
    $admin = assessmentTestAdmin('EMP-ASSESS-12');
    $nominee = assessmentTestNominee();
    $nhrdc = nhrdcRosterMember('EMP-NHRDC-08', 'Torres', 'Isabel');

    $this->actingAs($admin)->post(route('foreign-nominees.assessment.save', $nominee), validAssessmentScores());
    $this->actingAs($admin)->post(
        route('foreign-nominees.interview-ratings.save', $nominee),
        ['nhrdc_empcode' => $nhrdc->EMPCODE, ...validInterviewScores()],
    );

    $response = $this->actingAs($admin)->get(route('foreign-programs.assessment', $nominee->foreign_program_id));

    $response->assertInertia(fn ($page) => $page
        ->component('ForeignPrograms/Assessment')
        ->where('nominees.0.assessment.requirements_total', 17 + 28 + 8 + 6)
        ->has('nominees.0.interview_ratings', 1)
        ->where('nominees.0.interview_ratings.0.nhrdc_name', $nhrdc->name)
    );
});

test('admin can search employees for the NHRDC roster picker', function () {
    $admin = assessmentTestAdmin('EMP-ASSESS-13');
    nhrdcTestEmployee('EMP-NHRDC-09', 'Fernandez', 'Miguel');

    $response = $this->actingAs($admin)->getJson(
        route('foreign-nominee-assessments.search-employee', ['q' => 'Fernandez'])
    );

    $response->assertOk();
    $response->assertJsonFragment(['empcode' => 'EMP-NHRDC-09', 'position' => 'NHRDC Member']);
});

test('non-admin users cannot view or save a nominee assessment', function () {
    $employee = Employee::forceCreate([
        'EMPCODE' => 'EMP-ASSESS-14',
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Reyes',
        'FIRSTNAME' => 'Maria',
        'MI' => 'D',
        'POSITION' => 'Test Position',
        'SG' => '10',
        'PLANTILLA STATUS' => 'Permanent',
        'SEX' => 'F',
        'REGION' => 'NCR',
        'OFFICE' => 'Test Office',
        'LOCATION' => 'Main',
        'SECTION' => 'Test Section',
        'UNIT' => 'Test Unit',
    ]);
    $user = User::factory()->create(['empcode' => $employee->EMPCODE, 'access' => 'user']);
    $nominee = assessmentTestNominee();

    $this->actingAs($user)
        ->get(route('foreign-programs.assessment', $nominee->foreign_program_id))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('foreign-nominees.assessment.save', $nominee), validAssessmentScores())
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('foreign-nominees.interview-ratings.save', $nominee), validInterviewScores())
        ->assertForbidden();
});
