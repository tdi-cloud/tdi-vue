<?php

namespace App\Models;

use Database\Factories\ProblemReportReplyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in a problem report's conversation, written by a super admin
 * or by the person who reported the problem.
 */
class ProblemReportReply extends Model
{
    /** @use HasFactory<ProblemReportReplyFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'body',
    ];

    public function problemReport(): BelongsTo
    {
        return $this->belongsTo(ProblemReport::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
