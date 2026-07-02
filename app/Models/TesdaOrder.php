<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TesdaOrder extends Model
{
    protected $fillable = [
        'program_id', 'subject', 'date_issued', 'effectivity', 'supersedes',
        'series_year', 'total_pages', 'body', 'include_participants',
        'include_batch_data', 'closure', 'signatory_empcode',
        'signatory_name', 'signatory_position', 'pdf_path', 'generated_by',
    ];

    protected $casts = [
        'include_participants' => 'boolean',
        'include_batch_data'   => 'boolean',
        'date_issued'          => 'date',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}