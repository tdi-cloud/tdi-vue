<?php

use App\Models\TutorialVideo;
use App\Models\TutorialVideoStep;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function tutorialUser(string $empcode, string $access): User
{
    return User::factory()->create(['empcode' => $empcode, 'access' => $access]);
}

function fakeVideo(string $name = 'create-program.mp4'): UploadedFile
{
    return UploadedFile::fake()->create($name, 2048, 'video/mp4');
}

test('admins and superadmins can watch the how-to videos', function (string $access, bool $canManage) {
    TutorialVideo::factory()->count(2)->create();

    $this->actingAs(tutorialUser('EMP-TV-01', $access))
        ->get(route('how-to-videos.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('HowToVideos/index')
            ->has('videos', 2)
            ->where('canManage', $canManage));
})->with([
    'admin' => ['admin', false],
    'superadmin' => ['superadmin', true],
]);

test('regular users cannot open the how-to videos page', function () {
    $this->actingAs(tutorialUser('EMP-TV-02', 'user'))
        ->get(route('how-to-videos.index'))
        ->assertForbidden();
});

test('guests are sent to the login page', function () {
    $this->get(route('how-to-videos.index'))->assertRedirect(route('login'));
});

test('the homepage lists the latest videos for admins only', function () {
    TutorialVideo::factory()->count(5)->create();

    $this->actingAs(tutorialUser('EMP-TV-03', 'admin'))
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->has('howToVideos', 4));

    $this->actingAs(tutorialUser('EMP-TV-04', 'user'))
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->has('howToVideos', 0));
});

test('guests do not get any videos on the homepage', function () {
    TutorialVideo::factory()->create();

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->has('howToVideos', 0));
});

test('a superadmin can upload a video with a thumbnail', function () {
    Storage::fake('public');

    $this->actingAs(tutorialUser('EMP-TV-05', 'superadmin'))
        ->post(route('how-to-videos.store'), [
            'title' => 'How to create a program',
            'description' => 'Walkthrough of the Create New Program form.',
            'video' => fakeVideo(),
            'thumbnail' => UploadedFile::fake()->image('cover.jpg'),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $video = TutorialVideo::sole();
    expect($video->title)->toBe('How to create a program')
        ->and($video->uploaded_by)->toBe('EMP-TV-05');
    Storage::disk('public')->assertExists([$video->video_path, $video->thumbnail_path]);
});

test('only superadmins can upload, edit or delete videos', function (string $access) {
    $user = tutorialUser('EMP-TV-06', $access);
    $video = TutorialVideo::factory()->create();

    $this->actingAs($user)->post(route('how-to-videos.store'), ['title' => 'X', 'video' => fakeVideo()])->assertForbidden();
    $this->actingAs($user)->post(route('how-to-videos.update', $video), ['title' => 'X'])->assertForbidden();
    $this->actingAs($user)->delete(route('how-to-videos.destroy', $video))->assertForbidden();
})->with(['admin', 'user']);

test('uploads that are not videos are rejected', function () {
    Storage::fake('public');

    $this->actingAs(tutorialUser('EMP-TV-07', 'superadmin'))
        ->post(route('how-to-videos.store'), [
            'title' => 'Not a video',
            'video' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('video');

    expect(TutorialVideo::count())->toBe(0);
});

test('a 228 MB screen recording is accepted', function () {
    Storage::fake('public');

    $this->actingAs(tutorialUser('EMP-TV-12', 'superadmin'))
        ->post(route('how-to-videos.store'), [
            'title' => 'How to create a program',
            'video' => UploadedFile::fake()->create('createProgram.mp4', 233849, 'video/mp4'),
        ])
        ->assertSessionHasNoErrors();

    expect(TutorialVideo::count())->toBe(1);
});

test('videos larger than 500 MB are rejected', function () {
    Storage::fake('public');

    $this->actingAs(tutorialUser('EMP-TV-10', 'superadmin'))
        ->post(route('how-to-videos.store'), [
            'title' => 'Too big',
            'video' => UploadedFile::fake()->create('long-demo.mp4', 512001, 'video/mp4'),
        ])
        ->assertSessionHasErrors('video');

    expect(TutorialVideo::count())->toBe(0);
});

test('the page never advertises more than the 500 MB limit', function () {
    $this->actingAs(tutorialUser('EMP-TV-11', 'superadmin'))
        ->get(route('how-to-videos.index'))
        ->assertInertia(fn (Assert $page) => $page->where('maxVideoMb', fn ($mb) => $mb > 0 && $mb <= 500));
});

test('a superadmin can edit a video and replace its file', function () {
    Storage::fake('public');
    $oldPath = fakeVideo('old.mp4')->store('tutorial-videos', 'public');
    $video = TutorialVideo::factory()->create(['video_path' => $oldPath]);

    $this->actingAs(tutorialUser('EMP-TV-08', 'superadmin'))
        ->post(route('how-to-videos.update', $video), [
            'title' => 'Updated title',
            'description' => null,
            'video' => fakeVideo('new.mp4'),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $video->refresh();
    expect($video->title)->toBe('Updated title');
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($video->video_path);
});

test('deleting a video removes its files', function () {
    Storage::fake('public');
    $path = fakeVideo()->store('tutorial-videos', 'public');
    $thumb = UploadedFile::fake()->image('cover.jpg')->store('tutorial-videos/thumbnails', 'public');
    $video = TutorialVideo::factory()->create(['video_path' => $path, 'thumbnail_path' => $thumb]);

    $this->actingAs(tutorialUser('EMP-TV-09', 'superadmin'))
        ->delete(route('how-to-videos.destroy', $video))
        ->assertRedirect();

    expect(TutorialVideo::count())->toBe(0);
    Storage::disk('public')->assertMissing([$path, $thumb]);
});

test('admins can open the shareable documentation page with its steps in order', function () {
    $video = TutorialVideo::factory()->create(['title' => 'How to create a program']);
    TutorialVideoStep::factory()->for($video)->create(['sort_order' => 1, 'title' => 'Click Create Program']);
    TutorialVideoStep::factory()->for($video)->create(['sort_order' => 0, 'title' => 'Open Programs in the sidebar']);

    $this->actingAs(tutorialUser('EMP-TV-20', 'admin'))
        ->get(route('how-to-videos.documentation', $video))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('HowToVideos/Documentation')
            ->where('video.title', 'How to create a program')
            ->where('video.steps.0.title', 'Open Programs in the sidebar')
            ->where('video.steps.1.title', 'Click Create Program')
            ->where('canManage', false));
});

test('regular users cannot open documentation pages', function () {
    $video = TutorialVideo::factory()->create();

    $this->actingAs(tutorialUser('EMP-TV-21', 'user'))
        ->get(route('how-to-videos.documentation', $video))
        ->assertForbidden();
});

test('the video library includes each video\'s steps and a documentation link', function () {
    $video = TutorialVideo::factory()->create();
    TutorialVideoStep::factory()->count(2)->for($video)->create();

    $this->actingAs(tutorialUser('EMP-TV-22', 'admin'))
        ->get(route('how-to-videos.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('videos.0.steps_count', 2)
            ->has('videos.0.steps', 2)
            ->where('videos.0.documentation_url', route('how-to-videos.documentation', $video)));
});

test('a superadmin can add, update, reorder and remove documentation steps', function () {
    Storage::fake('public');
    $video = TutorialVideo::factory()->create();
    $oldShot = UploadedFile::fake()->image('old.png')->store('tutorial-videos/steps', 'public');
    $keep = TutorialVideoStep::factory()->for($video)->create(['sort_order' => 0, 'title' => 'Old title', 'image_path' => $oldShot]);
    $drop = TutorialVideoStep::factory()->for($video)->create(['sort_order' => 1, 'image_path' => UploadedFile::fake()->image('drop.png')->store('tutorial-videos/steps', 'public')]);

    $this->actingAs(tutorialUser('EMP-TV-23', 'superadmin'))
        ->post(route('how-to-videos.documentation.update', $video), [
            'steps' => [
                ['title' => 'Open Programs in the sidebar', 'body' => 'Under Management.', 'image' => UploadedFile::fake()->image('sidebar.png')],
                ['id' => $keep->id, 'title' => 'Click Create Program', 'body' => null, 'image' => UploadedFile::fake()->image('new.png')],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $steps = $video->steps()->get();
    expect($steps->pluck('title')->all())->toBe(['Open Programs in the sidebar', 'Click Create Program'])
        ->and($steps[1]->id)->toBe($keep->id)
        ->and(TutorialVideoStep::find($drop->id))->toBeNull();
    Storage::disk('public')->assertMissing([$oldShot, $drop->image_path]);
    Storage::disk('public')->assertExists([$steps[0]->image_path, $steps[1]->image_path]);
});

test('a superadmin can remove a step screenshot without replacing it', function () {
    Storage::fake('public');
    $video = TutorialVideo::factory()->create();
    $shot = UploadedFile::fake()->image('shot.png')->store('tutorial-videos/steps', 'public');
    $step = TutorialVideoStep::factory()->for($video)->create(['image_path' => $shot]);

    $this->actingAs(tutorialUser('EMP-TV-24', 'superadmin'))
        ->post(route('how-to-videos.documentation.update', $video), [
            'steps' => [['id' => $step->id, 'title' => $step->title, 'remove_image' => true]],
        ])
        ->assertSessionHasNoErrors();

    expect($step->fresh()->image_path)->toBeNull();
    Storage::disk('public')->assertMissing($shot);
});

test('every documentation step needs a title', function () {
    $video = TutorialVideo::factory()->create();

    $this->actingAs(tutorialUser('EMP-TV-25', 'superadmin'))
        ->post(route('how-to-videos.documentation.update', $video), ['steps' => [['title' => '', 'body' => 'Text']]])
        ->assertSessionHasErrors('steps.0.title');

    expect($video->steps()->count())->toBe(0);
});

test('admins cannot edit documentation', function () {
    $video = TutorialVideo::factory()->create();

    $this->actingAs(tutorialUser('EMP-TV-26', 'admin'))
        ->post(route('how-to-videos.documentation.update', $video), ['steps' => [['title' => 'X']]])
        ->assertForbidden();
});

test('deleting a video also removes its documentation screenshots', function () {
    Storage::fake('public');
    $video = TutorialVideo::factory()->create();
    $shot = UploadedFile::fake()->image('shot.png')->store('tutorial-videos/steps', 'public');
    TutorialVideoStep::factory()->for($video)->create(['image_path' => $shot]);

    $this->actingAs(tutorialUser('EMP-TV-27', 'superadmin'))
        ->delete(route('how-to-videos.destroy', $video))
        ->assertRedirect();

    expect(TutorialVideoStep::count())->toBe(0);
    Storage::disk('public')->assertMissing($shot);
});
