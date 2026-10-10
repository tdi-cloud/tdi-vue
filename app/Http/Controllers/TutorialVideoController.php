<?php

namespace App\Http\Controllers;

use App\Models\TutorialVideo;
use App\Models\TutorialVideoStep;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TutorialVideoController extends Controller
{
    /** Largest accepted video, in kilobytes (500 MB). */
    public const MAX_VIDEO_KB = 512000;

    /**
     * Latest videos for the homepage section. Only admins can watch the videos,
     * so everyone else gets an empty list and the section stays hidden.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function homepageData(?User $user, int $limit = 4): array
    {
        if (! $user?->isAdmin()) {
            return [];
        }

        return TutorialVideo::query()
            ->withCount('steps')
            ->latest()
            ->limit($limit)
            ->get()
            ->map->toCard()
            ->all();
    }

    // GET /how-to-videos
    public function index(Request $request): Response
    {
        return Inertia::render('HowToVideos/index', [
            'videos' => TutorialVideo::query()->with('steps')->latest()->get()->map->toCard(),
            'canManage' => $request->user()->isSuperAdmin(),
            'maxVideoMb' => self::effectiveMaxVideoMb(),
        ]);
    }

    // GET /how-to-videos/{tutorialVideo}/documentation — shareable page with the written steps
    public function documentation(Request $request, TutorialVideo $tutorialVideo): Response
    {
        return Inertia::render('HowToVideos/Documentation', [
            'video' => $tutorialVideo->load('steps')->toCard(),
            'canManage' => $request->user()->isSuperAdmin(),
        ]);
    }

    /**
     * POST /how-to-videos/{tutorialVideo}/documentation
     *
     * Replaces the documentation with the submitted list of steps, in order.
     * Existing steps are matched by id; steps left out are deleted along with
     * their screenshots.
     */
    public function updateDocumentation(Request $request, TutorialVideo $tutorialVideo): RedirectResponse
    {
        $validated = $request->validate([
            'steps' => ['nullable', 'array', 'max:50'],
            'steps.*.id' => ['nullable', 'integer'],
            'steps.*.title' => ['required', 'string', 'max:150'],
            'steps.*.body' => ['nullable', 'string', 'max:3000'],
            'steps.*.image' => ['nullable', 'image', 'max:5120'],
            'steps.*.remove_image' => ['nullable', 'boolean'],
        ], [
            'steps.*.title.required' => 'Every step needs a title.',
            'steps.*.image.image' => 'Screenshots must be image files.',
            'steps.*.image.max' => 'Screenshots must be 5 MB or smaller.',
        ]);

        $existing = $tutorialVideo->steps()->get()->keyBy('id');
        $keptIds = [];

        DB::transaction(function () use ($request, $validated, $tutorialVideo, $existing, &$keptIds) {
            foreach ($validated['steps'] ?? [] as $index => $input) {
                $step = isset($input['id']) ? $existing->get((int) $input['id']) : null;
                $step ??= new TutorialVideoStep(['tutorial_video_id' => $tutorialVideo->id]);

                $step->fill([
                    'sort_order' => $index,
                    'title' => $input['title'],
                    'body' => $input['body'] ?? null,
                ]);

                $newImage = $request->file("steps.{$index}.image");
                if (($newImage || ! empty($input['remove_image'])) && $step->image_path) {
                    Storage::disk('public')->delete($step->image_path);
                    $step->image_path = null;
                }
                if ($newImage) {
                    $step->image_path = $newImage->store('tutorial-videos/steps', 'public');
                }

                $step->save();
                $keptIds[] = $step->id;
            }

            $removed = $existing->except($keptIds);
            Storage::disk('public')->delete($removed->pluck('image_path')->filter()->all());
            TutorialVideoStep::whereKey($removed->modelKeys())->delete();
        });

        return back()->with('success', 'Documentation saved.');
    }

    /**
     * Largest video that can actually be uploaded: the app limit, capped by the
     * server's upload_max_filesize and post_max_size settings in php.ini.
     */
    public static function effectiveMaxVideoMb(): int
    {
        $serverLimitKb = intdiv((int) UploadedFile::getMaxFilesize(), 1024);

        return intdiv(min(self::MAX_VIDEO_KB, $serverLimitKb ?: self::MAX_VIDEO_KB), 1024);
    }

    // POST /how-to-videos
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'video' => ['required', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:'.self::MAX_VIDEO_KB],
            'thumbnail' => ['nullable', 'image', 'max:5120'],
        ]);

        TutorialVideo::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'video_path' => $request->file('video')->store('tutorial-videos', 'public'),
            'thumbnail_path' => $request->file('thumbnail')?->store('tutorial-videos/thumbnails', 'public'),
            'uploaded_by' => $request->user()->empcode,
        ]);

        return back()->with('success', 'Video uploaded.');
    }

    // POST /how-to-videos/{tutorialVideo} (POST so files can be sent with the edit)
    public function update(Request $request, TutorialVideo $tutorialVideo): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:'.self::MAX_VIDEO_KB],
            'thumbnail' => ['nullable', 'image', 'max:5120'],
        ]);

        $attributes = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
        ];

        if ($request->hasFile('video')) {
            Storage::disk('public')->delete($tutorialVideo->video_path);
            $attributes['video_path'] = $request->file('video')->store('tutorial-videos', 'public');
        }

        if ($request->hasFile('thumbnail')) {
            if ($tutorialVideo->thumbnail_path) {
                Storage::disk('public')->delete($tutorialVideo->thumbnail_path);
            }
            $attributes['thumbnail_path'] = $request->file('thumbnail')->store('tutorial-videos/thumbnails', 'public');
        }

        $tutorialVideo->update($attributes);

        return back()->with('success', 'Video updated.');
    }

    // DELETE /how-to-videos/{tutorialVideo}
    public function destroy(TutorialVideo $tutorialVideo): RedirectResponse
    {
        Storage::disk('public')->delete(array_filter([
            $tutorialVideo->video_path,
            $tutorialVideo->thumbnail_path,
            ...$tutorialVideo->steps()->pluck('image_path')->all(),
        ]));
        $tutorialVideo->delete();

        return back()->with('success', 'Video deleted.');
    }
}
