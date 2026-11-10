<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class AbsentJustification extends Model
{
    protected $fillable = [
        'participant_id',
        'file_path',
        'original_name',
        'file_size',
        'uploaded_by_empcode',
        'uploaded_by_name',
    ];

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }

    /**
     * Build the attributes that describe a freshly uploaded memo: where it is stored,
     * what the file was called, how big it is, and who uploaded it.
     *
     * @return array{file_path: string, original_name: string, file_size: int, uploaded_by_empcode: ?string, uploaded_by_name: ?string}
     */
    public static function attributesForUpload(UploadedFile $file, ?User $uploader): array
    {
        return [
            'file_path' => $file->store('justifications', 'public'),
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'uploaded_by_empcode' => $uploader?->empcode,
            'uploaded_by_name' => $uploader?->name,
        ];
    }
}
