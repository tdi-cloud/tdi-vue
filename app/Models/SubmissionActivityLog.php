<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * "Daily monitoring" na audit trail para sa Requirements Submissions — isang
 * row kada encode/update/review/delete, ginagamit ng hidden na
 * `/submissions/activity-log` na page (walang link sa navigation, admin lang).
 */
class SubmissionActivityLog extends Model
{
    protected $fillable = [
        'submission_id', 'participant_name', 'requirement_name', 'program_code',
        'batch_label', 'action', 'status', 'performed_by', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
