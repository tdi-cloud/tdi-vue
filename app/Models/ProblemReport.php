<?php

namespace App\Models;

use Database\Factories\ProblemReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProblemReport extends Model
{
    /** @use HasFactory<ProblemReportFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'user_id',
        'description',
        'page_url',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The conversation under the report, oldest message first.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ProblemReportReply::class)->oldest()->oldest('id');
    }

    /**
     * Super admins can reply to every report; anyone else only to their own.
     */
    public function canBeRepliedToBy(User $user): bool
    {
        return $user->isSuperAdmin() || $this->user()->is($user);
    }
}
