<?php

use App\Http\Controllers\TnaController;

function bandForScore(float $score): array
{
    $method = new ReflectionMethod(TnaController::class, 'bandFor');
    $method->setAccessible(true);

    return $method->invoke(new TnaController, $score);
}

test('bandFor covers decimal scores that fall between integer band boundaries', function () {
    expect(bandForScore(20.2)['label'])->toBe('Moderately Competent');
    expect(bandForScore(4.5)['label'])->toBe('Not Competent');
    expect(bandForScore(12.5)['label'])->toBe('Slightly Competent');
    expect(bandForScore(28.5)['label'])->toBe('Competent');
});

test('bandFor resolves exact integer boundaries correctly', function () {
    expect(bandForScore(0)['label'])->toBe('Not Competent');
    expect(bandForScore(4)['label'])->toBe('Not Competent');
    expect(bandForScore(5)['label'])->toBe('Slightly Competent');
    expect(bandForScore(12)['label'])->toBe('Slightly Competent');
    expect(bandForScore(13)['label'])->toBe('Moderately Competent');
    expect(bandForScore(20)['label'])->toBe('Moderately Competent');
    expect(bandForScore(21)['label'])->toBe('Competent');
    expect(bandForScore(28)['label'])->toBe('Competent');
    expect(bandForScore(29)['label'])->toBe('Highly Competent');
    expect(bandForScore(36)['label'])->toBe('Highly Competent');
});

test('bandFor flags needs_training correctly per band', function () {
    expect(bandForScore(4.5)['needs_training'])->toBeTrue();
    expect(bandForScore(20.2)['needs_training'])->toBeTrue();
    expect(bandForScore(21)['needs_training'])->toBeFalse();
    expect(bandForScore(36)['needs_training'])->toBeFalse();
});
