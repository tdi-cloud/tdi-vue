<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $fillable = [
        'sort_order', 'program_code', 'title', 'description',
        'competency', 'modality', 'pax', 'category', 'type',
        'initiated', 'provider', 'cost', 'fund', 'origin',
    ];

    protected static function booted(): void
    {
        // Auto-generate ng program_code kapag may bagong program
        static::created(function (Program $program) {
            $program->update([
                'program_code' => 'TDI-' . now()->year . '-' . str_pad($program->id, 4, '0', STR_PAD_LEFT),
        ]);
        });

        // Kapag binura ang program, kasamang mabubura ang batches niya
        static::deleting(function (Program $program) {
            $program->batches->each->delete();
        });
    }

    public function batches()
    {
        return $this->hasMany(Batch::class, 'program_code', 'program_code');
    }

    public function competencies()
    {
        return $this->hasMany(ProgramCompetency::class);
    }

    public function supportingDocuments()
    {
        return $this->hasMany(ProgramSupportingDocument::class)->latest('date_issued');
    }

    public function resourceSpeakers()
    {
        return $this->hasMany(ResourceSpeaker::class)->latest('date_engaged');
    }
    public function coverPage()
    {
        return $this->hasOne(CoverPage::class);
    }

    public function tesdaOrders()
    {
        return $this->hasMany(TesdaOrder::class)->latest();
    }
}