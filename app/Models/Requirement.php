<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Requirement extends Model
{
    protected $fillable = [
        'batch_id',
        'title',
        'name',
        'due_date',
        'is_required',
        'note',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'due_date'    => 'date:Y-m-d',
    ];

    /**
     * Master list ng requirement types.
     * unit: 'days' o 'months', value: ilan ang idadagdag sa batch date_end.
     */
    public const TYPES = [
        'TREAP' => [
            'name'  => 'Terminal Report',
            'unit'  => 'days',
            'value' => 5,
        ],
        'REAP' => [
            'name'  => 'Terminal Report and Re-entry Action Plan',
            'unit'  => 'days',
            'value' => 15,
        ],
        'TDOR' => [
            'name'  => 'Training Development Outcome Report',
            'unit'  => 'months',
            'value' => 6,
        ],
        'Feedback Report' => [
            'name'  => 'Feedback Report',
            'unit'  => 'days',
            'value' => 5, // ⚠️ PALITAN kung iba ang tamang bilang ng days
        ],
        'Benchmarking Report' => [
            'name'  => 'Benchmarking Report',
            'unit'  => 'days',
            'value' => 5,
        ],
        'After Activity Report' => [
            'name'  => 'After Activity Report',
            'unit'  => 'days',
            'value' => 5,
        ],
    ];

    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    /**
     * Buong pangalan ng requirement base sa title.
     */
    public static function nameFor(string $title): string
    {
        return self::TYPES[$title]['name'] ?? $title;
    }

    /**
     * Auto-compute ng due date mula sa date_end ng batch.
     * Hal: TREAP = date_end + 5 days, TDOR = date_end + 6 months.
     */
    public static function dueDateFor(string $title, string $batchDateEnd): string
    {
        $date = Carbon::parse($batchDateEnd);
        $type = self::TYPES[$title] ?? null;

        if ($type) {
            if ($type['unit'] === 'months') {
                $date->addMonths($type['value']);

                if ($date->isWeekend()) {
                    $date->nextWeekday();
                }
            } else {
                // Laktawan ang Saturday at Sunday sa pagbilang
                $date->addWeekdays($type['value']);
            }
        }

        return $date->toDateString();
    }
}