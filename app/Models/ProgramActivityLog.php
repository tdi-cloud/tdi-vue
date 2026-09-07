<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * "Daily monitoring" na audit trail para sa Programs module — isang row
 * kada create/update/delete, ginagamit ng hidden na
 * `/programs/activity-log` na page (walang link sa navigation, admin lang).
 */
class ProgramActivityLog extends Model
{
    protected $fillable = [
        'program_code', 'title', 'action', 'performed_by', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
