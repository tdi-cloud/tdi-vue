<?php

use App\Models\ForeignSponsorConfig;
use App\Models\SiteImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function nominationFormConfig(): ForeignSponsorConfig
{
    return ForeignSponsorConfig::create([
        'organizing_sponsor' => 'Japan International Cooperation Agency',
        'slug' => 'jica',
        'form_title' => 'JICA 2026 Foreign Scholarship and Training Program',
        'is_active' => true,
    ]);
}

test('nomination form uses the default sponsor logo when no override exists', function () {
    $config = nominationFormConfig();

    $response = $this->get(route('nominate.show', $config->slug));

    $response->assertInertia(fn ($page) => $page
        ->component('ForeignPrograms/NominationForm')
        ->where('sponsorLogos.jica', config('site-images.sponsor_logo_jica.default'))
    );
});

test('nomination form reflects a superadmin-uploaded sponsor logo override', function () {
    Storage::fake('public');
    $config = nominationFormConfig();

    $path = UploadedFile::fake()->image('jica-logo.png')->store('site-images', 'public');
    SiteImage::create(['key' => 'sponsor_logo_jica', 'path' => $path]);

    $expectedUrl = Storage::disk('public')->url($path);

    $response = $this->get(route('nominate.show', $config->slug));

    $response->assertInertia(fn ($page) => $page
        ->component('ForeignPrograms/NominationForm')
        ->where('sponsorLogos.jica', $expectedUrl)
    );
});
