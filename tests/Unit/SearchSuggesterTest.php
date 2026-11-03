<?php

use App\Support\SearchSuggester;

$terms = ['Leadership Program', 'Supervisory Development Course', 'Juan Dela Cruz', 'NCR', 'CARAGA'];

test('an exact match needs no suggestion', function () use ($terms) {
    expect(SearchSuggester::suggest('leadership program', $terms))->toBeNull()
        ->and(SearchSuggester::suggest('LEADERSHIP', $terms))->toBeNull();
});

test('a typo is corrected to the closest known word', function () use ($terms) {
    expect(SearchSuggester::suggest('progarm', $terms))->toBe('program')
        ->and(SearchSuggester::suggest('Supervsory', $terms))->toBe('supervisory');
});

test('every misspelled word in a multi-word query is corrected', function () use ($terms) {
    expect(SearchSuggester::suggest('leadrship progarm', $terms))->toBe('leadership program')
        ->and(SearchSuggester::suggest('juan dela cuz', $terms))->toBe('juan dela cruz');
});

test('there is no suggestion when nothing is close enough', function () use ($terms) {
    expect(SearchSuggester::suggest('xylophone', $terms))->toBeNull()
        ->and(SearchSuggester::suggest('zzz', $terms))->toBeNull();
});

test('short words, numbers and empty input are left alone', function () use ($terms) {
    expect(SearchSuggester::suggest('', $terms))->toBeNull()
        ->and(SearchSuggester::suggest('   ', $terms))->toBeNull()
        ->and(SearchSuggester::suggest('nc', $terms))->toBeNull()
        ->and(SearchSuggester::suggest('2026', $terms))->toBeNull()
        ->and(SearchSuggester::suggest('progarm', []))->toBeNull();
});

test('two swapped letters count as one typo', function () use ($terms) {
    expect(SearchSuggester::suggest('curz', $terms))->toBe('cruz')
        ->and(SearchSuggester::suggest('JaunDela', ['Juandela']))->toBe('juandela');
});
