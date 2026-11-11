<?php

namespace App\Support;

use App\Models\Certificate;

/**
 * Ready-made subject and message for each certificate type, used to pre-fill the
 * Email Certificates window. The admin may edit the text before sending; the
 * {placeholders} are filled in per participant by CertificateEmailPlaceholders.
 */
final class CertificateEmailTemplates
{
    /**
     * Sentence shared by every template: where the certificate is.
     */
    private const ATTACHMENT_NOTE = 'is attached to this email as a PDF. It is also saved in <strong>My Certificates</strong> in the TDI system, where you can view or download it anytime.';

    /**
     * Every template with its hero emoji, keyed by certificate type in Certificate::TYPES order.
     *
     * @return array<string, array{subject: string, body: string, emoji: string}>
     */
    public static function all(): array
    {
        return collect(Certificate::TYPES)
            ->mapWithKeys(fn (string $type): array => [$type => [...self::for($type), 'emoji' => self::emoji($type)]])
            ->all();
    }

    /**
     * The template for one certificate type; unknown types fall back to Participation.
     *
     * @return array{subject: string, body: string}
     */
    public static function for(string $type): array
    {
        [$subject, $opening, $closing] = match ($type) {
            'Completion' => [
                'Congratulations! Your Certificate of Completion for {program_title}',
                'Congratulations on successfully completing <strong>{program_title}</strong> ({batch}), held on {schedule}! Your dedication throughout the program is truly commendable.',
                'We wish you continued success as you put your new learning into practice.',
            ],
            'Appearance' => [
                'Your Certificate of Appearance – {program_title}',
                'This confirms your appearance at <strong>{program_title}</strong> ({batch}), held on {schedule}. Thank you for being with us.',
                'Please keep it for your records.',
            ],
            'Appreciation' => [
                'Thank you! Your Certificate of Appreciation – {program_title}',
                'Thank you for your valuable contribution to <strong>{program_title}</strong> ({batch}), held on {schedule}. Your time, expertise and support made a real difference to the program and its participants.',
                'We truly appreciate your support and hope to work with you again.',
            ],
            'Recognition' => [
                'Congratulations! Your Certificate of Recognition – {program_title}',
                'Congratulations! We are pleased to recognize your outstanding involvement in <strong>{program_title}</strong> ({batch}), held on {schedule}. Your commitment set a high standard for everyone in the program.',
                'Keep up the excellent work!',
            ],
            'Achievement' => [
                'Congratulations on your achievement – {program_title}',
                'Congratulations on your achievement in <strong>{program_title}</strong> ({batch}), held on {schedule}! Your effort and excellence have earned this well-deserved distinction.',
                'We are proud of what you have accomplished and wish you even greater success ahead.',
            ],
            default => [
                'Your Certificate of Participation – {program_title}',
                'Thank you for participating in <strong>{program_title}</strong> ({batch}), held on {schedule}. Your active involvement helped make the program a meaningful learning experience.',
                'We look forward to seeing you in future learning and development programs.',
            ],
        };

        $label = self::label(in_array($type, Certificate::TYPES, true) ? $type : 'Participation');

        return [
            'subject' => $subject,
            'body' => implode('', [
                '<p>Dear {first_name},</p>',
                "<p>{$opening}</p>",
                "<p>Your {$label} (<strong>{certificate_number}</strong>) ".self::ATTACHMENT_NOTE.'</p>',
                "<p>{$closing}</p>",
            ]),
        ];
    }

    /**
     * Full certificate name, e.g. "Certificate of Completion".
     */
    public static function label(string $type): string
    {
        return "Certificate of {$type}";
    }

    /**
     * Emoji shown in the email's hero for each type.
     */
    public static function emoji(string $type): string
    {
        return match ($type) {
            'Completion' => '🎓',
            'Appearance' => '📜',
            'Appreciation' => '🙏',
            'Recognition' => '🌟',
            'Achievement' => '🏅',
            default => '🤝',
        };
    }
}
