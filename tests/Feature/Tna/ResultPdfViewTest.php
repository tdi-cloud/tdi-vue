<?php

function resultPdfViewHtml(array $extra = []): string
{
    $assessment = (object) [
        'name' => 'Juan D. Dela Cruz',
        'position' => 'Administrative Aide I',
        'office' => 'ODDG',
        'division' => 'AI/TDI',
        'supervisor_name' => 'Maria Supervisor',
        'supervisor_position' => 'HRMO',
    ];

    $units = collect([
        [
            'unit' => 'Work effectively in vocational education and training',
            'rows' => collect([
                [
                    'element' => 'Work within the vocational education and training policy framework',
                    'type' => 'core',
                    'crit_self' => 3, 'crit_sup' => 3,
                    'comp_self' => 3, 'comp_sup' => 3,
                    'freq_self' => 2, 'freq_sup' => 2,
                    'score' => 18.0,
                ],
                [
                    'element' => "Work within the training organization's quality framework",
                    'type' => 'core',
                    'crit_self' => 3, 'crit_sup' => 2,
                    'comp_self' => 3, 'comp_sup' => 2,
                    'freq_self' => 2, 'freq_sup' => 2,
                    'score' => 11.5,
                ],
            ]),
        ],
    ]);

    $form = ['name' => 'Maria Supervisor', 'office' => 'Central Office'];
    $priority = collect();

    return view('pdf.tna-result', array_merge([
        'a' => $assessment,
        'form' => $form,
        'units' => $units,
        'priority' => $priority,
    ], $extra))->render();
}

test('tna result pdf view shows the noted by office from office/division', function () {
    $html = resultPdfViewHtml(['notedOffice' => 'ODDG-PO']);

    expect($html)->toContain('HRMO, ODDG-PO');
    expect($html)->not->toContain('HRMO, Central Office');
});

test('tna result pdf view falls back to the stored office when no office/division is given', function () {
    $html = resultPdfViewHtml();

    expect($html)->toContain('HRMO, Central Office');
});

test('tna result pdf view merges the unit of competency cell into one row per unit', function () {
    $html = resultPdfViewHtml();

    // Yung unit name ay dapat isang beses lang lumabas (tunay na merged
    // na isang <td>, hindi na inuulit sa bawat element row).
    expect(substr_count($html, 'Work effectively in vocational education and training'))->toBe(1);

    // Dapat gamit na ang nested-table technique (parehong approach ng
    // supervisory-rating.blade.php), hindi na yung lumang blank+no-border hack.
    expect($html)->toContain('class="nest"');
    expect($html)->toContain('table class="inner"');
    expect($html)->not->toContain('unit-cont');
});

test('tna result pdf view still renders all element rows for the unit', function () {
    $html = resultPdfViewHtml();

    expect($html)->toContain('Work within the vocational education and training policy framework');
    expect($html)->toContain('Work within the training organization');
});
