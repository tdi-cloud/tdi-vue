<?php

use App\Models\Employee;
use App\Models\ForeignNominee;
use App\Models\ForeignNomineeInterviewRating;
use App\Models\ForeignProgram;
use App\Models\NhrdcMember;
use App\Models\User;

function representativeTestEmployee(string $empcode, string $lastname, string $firstname = 'Test'): Employee
{
    return Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => $lastname,
        'FIRSTNAME' => $firstname,
        'MI' => 'R',
        'POSITION' => 'Test Position',
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

function representativeTestFstpAdmin(): User
{
    representativeTestEmployee('EMP-REP-ADMIN', 'Admin', 'Ana');

    return User::factory()->create(['empcode' => 'EMP-REP-ADMIN', 'access' => 'admin', 'is_fstp_member' => true]);
}

/**
 * @return array{0: NhrdcMember, 1: User}
 */
function representativeTestNhrdc(string $empcode, string $lastname): array
{
    representativeTestEmployee($empcode, $lastname, 'Nhrdc');
    $member = NhrdcMember::create(['empcode' => $empcode, 'sort_order' => NhrdcMember::nextSortOrder()]);

    return [$member, User::factory()->create(['empcode' => $empcode, 'access' => 'user'])];
}

function representativeTestUser(string $empcode, string $lastname): User
{
    representativeTestEmployee($empcode, $lastname, 'Rep');

    return User::factory()->create(['empcode' => $empcode, 'access' => 'user']);
}

function representativeTestNominee(): ForeignNominee
{
    $program = ForeignProgram::create([
        'program_title' => 'Representative Test Program',
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

function representativeTestScores(int $score = 4): array
{
    return [
        'communication_skills' => $score,
        'alertness' => $score,
        'judgement' => $score,
        'self_confidence' => $score,
        'emotional_stability' => $score,
        'appearance' => $score,
    ];
}

// ── FSTP unit: assigning / removing a representative ─────────────────────────

test('the FSTP unit can assign a representative to an NHRDC member', function () {
    $admin = representativeTestFstpAdmin();
    [$member] = representativeTestNhrdc('EMP-REP-N1', 'Reyes');
    $representative = representativeTestUser('EMP-REP-R1', 'Santos');

    $response = $this->actingAs($admin)->putJson(route('nhrdc-members.representative.assign', $member), [
        'empcode' => $representative->empcode,
    ]);

    $response->assertOk();
    expect($response->json('0.representative.empcode'))->toBe($representative->empcode);
    expect($member->fresh()->representative_empcode)->toBe($representative->empcode);
});

test('the FSTP unit can remove a representative', function () {
    $admin = representativeTestFstpAdmin();
    [$member] = representativeTestNhrdc('EMP-REP-N2', 'Reyes');
    $representative = representativeTestUser('EMP-REP-R2', 'Santos');
    $member->update(['representative_empcode' => $representative->empcode]);

    $this->actingAs($admin)->deleteJson(route('nhrdc-members.representative.remove', $member))
        ->assertOk()
        ->assertJsonPath('0.representative', null);

    expect($member->fresh()->representative_empcode)->toBeNull();
});

test('a representative must have a user account', function () {
    $admin = representativeTestFstpAdmin();
    [$member] = representativeTestNhrdc('EMP-REP-N3', 'Reyes');
    $withoutAccount = representativeTestEmployee('EMP-REP-NOACCT', 'Lopez');

    $this->actingAs($admin)->putJson(route('nhrdc-members.representative.assign', $member), [
        'empcode' => $withoutAccount->EMPCODE,
    ])->assertJsonValidationErrors('empcode');
});

test('an NHRDC member cannot be assigned as a representative', function () {
    $admin = representativeTestFstpAdmin();
    [$member] = representativeTestNhrdc('EMP-REP-N4', 'Reyes');
    [, $otherNhrdcUser] = representativeTestNhrdc('EMP-REP-N5', 'Garcia');

    $this->actingAs($admin)->putJson(route('nhrdc-members.representative.assign', $member), [
        'empcode' => $otherNhrdcUser->empcode,
    ])->assertJsonValidationErrors('empcode');

    $this->actingAs($admin)->putJson(route('nhrdc-members.representative.assign', $member), [
        'empcode' => $member->empcode,
    ])->assertJsonValidationErrors('empcode');
});

test('one person can represent only one NHRDC member', function () {
    $admin = representativeTestFstpAdmin();
    [$first] = representativeTestNhrdc('EMP-REP-N6', 'Reyes');
    [$second] = representativeTestNhrdc('EMP-REP-N7', 'Garcia');
    $representative = representativeTestUser('EMP-REP-R3', 'Santos');
    $first->update(['representative_empcode' => $representative->empcode]);

    $this->actingAs($admin)->putJson(route('nhrdc-members.representative.assign', $second), [
        'empcode' => $representative->empcode,
    ])->assertJsonValidationErrors('empcode');
});

test('an assigned representative cannot be added to the NHRDC roster', function () {
    $admin = representativeTestFstpAdmin();
    [$member] = representativeTestNhrdc('EMP-REP-N8', 'Reyes');
    $representative = representativeTestUser('EMP-REP-R4', 'Santos');
    $member->update(['representative_empcode' => $representative->empcode]);

    $this->actingAs($admin)->postJson(route('nhrdc-members.store'), [
        'empcode' => $representative->empcode,
    ])->assertJsonValidationErrors('empcode');
});

test('non-FSTP users cannot assign a representative', function () {
    [$member, $nhrdcUser] = representativeTestNhrdc('EMP-REP-N9', 'Reyes');
    $representative = representativeTestUser('EMP-REP-R5', 'Santos');

    $this->actingAs($nhrdcUser)->putJson(route('nhrdc-members.representative.assign', $member), [
        'empcode' => $representative->empcode,
    ])->assertForbidden();
});

// ── Representative: rating on the NHRDC member's behalf ──────────────────────

test('a representative can open the NHRDC rating area and sees who they represent', function () {
    [$member] = representativeTestNhrdc('EMP-REP-N10', 'Reyes');
    $representative = representativeTestUser('EMP-REP-R6', 'Santos');
    $member->update(['representative_empcode' => $representative->empcode]);
    $nominee = representativeTestNominee();

    $this->actingAs($representative)->get(route('nhrdc.programs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Nhrdc/Programs')
            ->where('representing.name', $member->employee->name)
        );

    $this->actingAs($representative)->get(route('nhrdc.programs.show', $nominee->foreign_program_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('representing.name', $member->employee->name));
});

test('a representative rating is saved under the NHRDC member and marked as entered by the representative', function () {
    [$member] = representativeTestNhrdc('EMP-REP-N11', 'Reyes');
    $representative = representativeTestUser('EMP-REP-R7', 'Santos');
    $member->update(['representative_empcode' => $representative->empcode]);
    $nominee = representativeTestNominee();

    $this->actingAs($representative)
        ->postJson(route('nhrdc.ratings.save', $nominee), representativeTestScores())
        ->assertOk();

    $rating = ForeignNomineeInterviewRating::where('foreign_nominee_id', $nominee->id)->sole();
    expect($rating->nhrdc_empcode)->toBe($member->empcode);
    expect($rating->nhrdc_name)->toBe($member->employee->name);
    expect($rating->rated_by)->toBe($representative->id);
    expect($rating->rated_by_name)->toBe(Employee::where('EMPCODE', $representative->empcode)->first()->name);
    expect($rating->rated_by_role)->toBe(ForeignNomineeInterviewRating::RATED_BY_REPRESENTATIVE);
});

test('the NHRDC member and their representative share one rating and the latest entry shows who rated', function () {
    [$member, $nhrdcUser] = representativeTestNhrdc('EMP-REP-N12', 'Reyes');
    $representative = representativeTestUser('EMP-REP-R8', 'Santos');
    $member->update(['representative_empcode' => $representative->empcode]);
    $nominee = representativeTestNominee();

    $this->actingAs($representative)->postJson(route('nhrdc.ratings.save', $nominee), representativeTestScores(3))->assertOk();
    $this->actingAs($nhrdcUser)->postJson(route('nhrdc.ratings.save', $nominee), representativeTestScores(5))->assertOk();

    $rating = ForeignNomineeInterviewRating::where('foreign_nominee_id', $nominee->id)->sole();
    expect($rating->total)->toEqual(30.0);
    expect($rating->rated_by)->toBe($nhrdcUser->id);
    expect($rating->rated_by_role)->toBe(ForeignNomineeInterviewRating::RATED_BY_NHRDC);
});

test('a representative can generate the NHRDC member\'s assessment sheet PDF', function () {
    [$member] = representativeTestNhrdc('EMP-REP-N13', 'Reyes');
    $representative = representativeTestUser('EMP-REP-R9', 'Santos');
    $member->update(['representative_empcode' => $representative->empcode]);
    $nominee = representativeTestNominee();

    $this->actingAs($representative)->get(route('nhrdc.programs.assessment-pdf', $nominee->foreign_program_id))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('a representative cannot upload or manage the signed assessment sheet', function () {
    [$member] = representativeTestNhrdc('EMP-REP-N17', 'Reyes');
    $representative = representativeTestUser('EMP-REP-R12', 'Santos');
    $member->update(['representative_empcode' => $representative->empcode]);
    $nominee = representativeTestNominee();

    $this->actingAs($representative)->postJson(route('nhrdc.signed-copy.upload', $nominee->foreign_program_id))->assertForbidden();
    $this->actingAs($representative)->deleteJson(route('nhrdc.signed-copy.destroy', $nominee->foreign_program_id))->assertForbidden();
});

test('a removed representative loses access to the NHRDC rating area', function () {
    [$member] = representativeTestNhrdc('EMP-REP-N14', 'Reyes');
    $representative = representativeTestUser('EMP-REP-R10', 'Santos');
    $member->update(['representative_empcode' => $representative->empcode]);
    $member->update(['representative_empcode' => null]);

    $this->actingAs($representative)->get(route('nhrdc.programs.index'))->assertForbidden();
});

test('a representative sees the rating summary banner on the homepage', function () {
    [$member] = representativeTestNhrdc('EMP-REP-N15', 'Reyes');
    $representative = representativeTestUser('EMP-REP-R11', 'Santos');
    $member->update(['representative_empcode' => $representative->empcode]);
    representativeTestNominee();

    $this->actingAs($representative)->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('nhrdcRating.pending', 1)
            ->where('nhrdcRating.representing', $member->employee->name)
        );
});

test('a rating entered by the FSTP unit is marked as such', function () {
    $admin = representativeTestFstpAdmin();
    [$member] = representativeTestNhrdc('EMP-REP-N16', 'Reyes');
    $nominee = representativeTestNominee();

    $this->actingAs($admin)->postJson(route('foreign-nominees.interview-ratings.save', $nominee), [
        'nhrdc_empcode' => $member->empcode,
        ...representativeTestScores(),
    ])->assertOk();

    $rating = ForeignNomineeInterviewRating::where('foreign_nominee_id', $nominee->id)->sole();
    expect($rating->rated_by_role)->toBe(ForeignNomineeInterviewRating::RATED_BY_FSTP);
    expect($rating->rated_by_name)->toBe($admin->name);
});
