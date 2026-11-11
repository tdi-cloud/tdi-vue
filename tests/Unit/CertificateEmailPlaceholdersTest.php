<?php

use App\Support\CertificateEmailPlaceholders;
use App\Support\CertificateEmailTemplates;

/**
 * @return array<string, string>
 */
function placeholderTestValues(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Juan',
        'full_name' => 'Juan D. Cruz',
        'program_title' => 'Leadership Bootcamp',
        'batch' => 'Batch 1',
        'schedule' => 'March 3–5, 2026',
        'certificate_type' => 'Completion',
        'certificate_number' => 'TDI-2026-CERT-0001',
    ], $overrides);
}

test('every known placeholder is replaced in plain text', function () {
    $text = '{first_name}|{full_name}|{program_title}|{batch}|{schedule}|{certificate_type}|{certificate_number}';

    expect(CertificateEmailPlaceholders::replaceInText($text, placeholderTestValues()))
        ->toBe('Juan|Juan D. Cruz|Leadership Bootcamp|Batch 1|March 3–5, 2026|Completion|TDI-2026-CERT-0001');
});

test('unknown placeholders are left as written', function () {
    expect(CertificateEmailPlaceholders::replaceInText('Hi {first_name}, see {venue} and {FIRST_NAME}.', placeholderTestValues()))
        ->toBe('Hi Juan, see {venue} and {FIRST_NAME}.');
});

test('values are escaped in HTML but kept as plain text in the subject', function () {
    $values = placeholderTestValues(['first_name' => '<script>alert("x")</script> & Co']);

    expect(CertificateEmailPlaceholders::replaceInHtml('<p>Dear <strong>{first_name}</strong>,</p>', $values))
        ->toBe('<p>Dear <strong>&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; Co</strong>,</p>')
        ->and(CertificateEmailPlaceholders::replaceInText('For {first_name}', $values))
        ->toBe('For <script>alert("x")</script> & Co');
});

test('a value that contains a placeholder is not expanded again', function () {
    expect(CertificateEmailPlaceholders::replaceInText('{first_name} / {batch}', placeholderTestValues(['first_name' => '{batch}'])))
        ->toBe('{batch} / Batch 1');
});

test('extra values that are not placeholders are ignored', function () {
    expect(CertificateEmailPlaceholders::replaceInText('{secret}', placeholderTestValues(['secret' => 'leaked'])))
        ->toBe('{secret}');
});

test('every certificate type has a template that uses the placeholders', function () {
    $templates = CertificateEmailTemplates::all();

    expect(array_keys($templates))->toBe(['Participation', 'Completion', 'Appearance', 'Appreciation', 'Recognition', 'Achievement']);

    foreach ($templates as $type => $template) {
        expect($template['subject'])->toContain('{program_title}')
            ->and($template['body'])
            ->toStartWith('<p>Dear {first_name},</p>')
            ->toContain('{program_title}', '{batch}', '{schedule}', '{certificate_number}', 'My Certificates', "Certificate of {$type}")
            ->and($template['emoji'])->toBe(CertificateEmailTemplates::emoji($type));
    }
});
