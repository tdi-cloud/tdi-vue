<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $fillable = [
        'sort_order', 'program_code', 'title', 'description',
        'competency', 'modality', 'pax', 'category', 'type',
        'initiated', 'provider', 'cost', 'fund', 'origin', 'added_by',
    ];

    /**
     * Naka-off habang tumatakbo ang auto program_code assignment (sa loob ng
     * `created` hook sa ibaba) — para hindi ito ma-log bilang isang totoong
     * "updated" na aksyon ng user sa ProgramActivityLog.
     */
    private static bool $suppressActivityLog = false;

    protected static function booted(): void
    {
        // Auto-generate ng program_code kapag may bagong program
        static::created(function (Program $program) {
            static::$suppressActivityLog = true;
            $program->update([
                'program_code' => 'TDI-'.now()->year.'-'.str_pad($program->id, 4, '0', STR_PAD_LEFT),
            ]);
            static::$suppressActivityLog = false;

            ProgramActivityLog::create([
                'program_code' => $program->program_code,
                'title' => $program->title,
                'action' => 'created',
                'performed_by' => auth()->user()?->name,
            ]);
        });

        // "Daily monitoring" para sa mga tunay na edit (hindi kasama ang
        // auto program_code assignment pagkatapos gawin, na naka-suppress).
        static::updated(function (Program $program) {
            if (static::$suppressActivityLog) {
                return;
            }

            $changedFields = array_keys(collect($program->getChanges())->except('updated_at')->all());
            if (empty($changedFields)) {
                return;
            }

            ProgramActivityLog::create([
                'program_code' => $program->program_code,
                'title' => $program->title,
                'action' => 'updated',
                'performed_by' => auth()->user()?->name,
                'meta' => ['changed_fields' => $changedFields],
            ]);
        });

        // Kapag binura ang program, kasamang mabubura ang batches niya
        static::deleting(function (Program $program) {
            ProgramActivityLog::create([
                'program_code' => $program->program_code,
                'title' => $program->title,
                'action' => 'deleted',
                'performed_by' => auth()->user()?->name,
            ]);

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

    public function emailReminderLogs()
    {
        return $this->hasMany(EmailReminderLog::class)->latest();
    }
}
