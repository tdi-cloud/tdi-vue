<?php

use App\Models\Batch;
use App\Models\DefaultProgramCover;
use App\Models\Employee;
use App\Models\Participant;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function defaultCoverTestAdmin(string $empcode): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => 'admin']);
}

function defaultCoverTestParticipant(string $empcode, bool $withOwnCover = false): Participant
{
    $program = Program::create([
        'title' => 'Default Cover Test Program',
        'modality' => 'Onsite',
        'pax' => '20',
        'category' => 'Regional',
        'type' => 'TECHNICAL',
        'initiated' => 'NTTA',
        'cost' => '0',
        'fund' => 'Test',
        'origin' => 'Local',
    ]);

    if ($withOwnCover) {
        $program->coverPage()->create(['image' => 'cover_pages/own-cover.jpg']);
    }

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

    Employee::forceCreate([
        'EMPCODE' => $empcode,
        'OFFICE/DIVISION' => 'Test Office',
        'LASTNAME' => 'Santos',
        'FIRSTNAME' => 'Maria',
        'MI' => 'C',
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

    return Participant::create([
        'sort_order' => 1,
        'batch_id' => $batch->id,
        'empcode' => $empcode,
        'attendance' => 'Pending',
        'hours' => 0,
        'added_by' => 'system',
    ]);
}

test('an admin can upload a default program cover image', function () {
    Storage::fake('public');
    $admin = defaultCoverTestAdmin('EMP-DCOVER-ADMIN-01');

    $response = $this->actingAs($admin)->post(route('programs.default-cover.upload'), [
        'image' => UploadedFile::fake()->image('default.jpg'),
    ]);

    $response->assertSessionDoesntHaveErrors();
    $cover = DefaultProgramCover::first();
    expect($cover)->not->toBeNull();
    Storage::disk('public')->assertExists($cover->image);
});

test('uploading a new default cover replaces the old file', function () {
    Storage::fake('public');
    $admin = defaultCoverTestAdmin('EMP-DCOVER-ADMIN-02');

    $this->actingAs($admin)->post(route('programs.default-cover.upload'), [
        'image' => UploadedFile::fake()->image('first.jpg'),
    ]);
    $oldPath = DefaultProgramCover::first()->image;

    $this->actingAs($admin)->post(route('programs.default-cover.upload'), [
        'image' => UploadedFile::fake()->image('second.jpg'),
    ]);

    expect(DefaultProgramCover::count())->toBe(1);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists(DefaultProgramCover::first()->image);
});

test('an admin can remove the default program cover', function () {
    Storage::fake('public');
    $admin = defaultCoverTestAdmin('EMP-DCOVER-ADMIN-03');

    $this->actingAs($admin)->post(route('programs.default-cover.upload'), [
        'image' => UploadedFile::fake()->image('default.jpg'),
    ]);
    $path = DefaultProgramCover::first()->image;

    $response = $this->actingAs($admin)->delete(route('programs.default-cover.destroy'));

    $response->assertSessionDoesntHaveErrors();
    expect(DefaultProgramCover::count())->toBe(0);
    Storage::disk('public')->assertMissing($path);
});

test('a program without its own cover page falls back to the default cover on the enrolled program page', function () {
    Storage::fake('public');
    $admin = defaultCoverTestAdmin('EMP-DCOVER-ADMIN-04');
    $this->actingAs($admin)->post(route('programs.default-cover.upload'), [
        'image' => UploadedFile::fake()->image('default.jpg'),
    ]);
    $defaultPath = DefaultProgramCover::first()->image;

    $user = User::factory()->create(['empcode' => 'EMP-DCOVER-USER-01']);
    $participant = defaultCoverTestParticipant($user->empcode, withOwnCover: false);

    $response = $this->actingAs($user)->get(route('programs.my-progress', $participant->batch_id));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('MyPrograms/Show')
        ->where('program.cover_image', '/storage/'.$defaultPath)
    );
});

test('a program with its own cover page ignores the default cover', function () {
    Storage::fake('public');
    $admin = defaultCoverTestAdmin('EMP-DCOVER-ADMIN-05');
    $this->actingAs($admin)->post(route('programs.default-cover.upload'), [
        'image' => UploadedFile::fake()->image('default.jpg'),
    ]);

    $user = User::factory()->create(['empcode' => 'EMP-DCOVER-USER-02']);
    $participant = defaultCoverTestParticipant($user->empcode, withOwnCover: true);

    $response = $this->actingAs($user)->get(route('programs.my-progress', $participant->batch_id));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('MyPrograms/Show')
        ->where('program.cover_image', '/storage/cover_pages/own-cover.jpg')
    );
});

test('with no default cover set, a program without its own cover has a null cover image', function () {
    $user = User::factory()->create(['empcode' => 'EMP-DCOVER-USER-03']);
    $participant = defaultCoverTestParticipant($user->empcode, withOwnCover: false);

    $response = $this->actingAs($user)->get(route('programs.my-progress', $participant->batch_id));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('MyPrograms/Show')
        ->where('program.cover_image', null)
    );
});
