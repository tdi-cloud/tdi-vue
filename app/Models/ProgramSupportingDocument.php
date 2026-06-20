<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramSupportingDocument extends Model
{
    protected $table = 'program_supporting_documents';

    protected $fillable = [
        'program_id',
        'program_code',
        'document_type',
        'subject',
        'document_series',
        'origin',
        'document_number',
        'date_issued',
        'link',
    ];

    protected $casts = [
        'date_issued' => 'date',
        'document_series' => 'integer',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}