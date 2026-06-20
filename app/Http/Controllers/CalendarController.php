<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(): Response
    {
        $events = Batch::with('program')
            ->get()
            ->map(function (Batch $batch) {
                // Kulay ng event base sa status ng batch
                $color = match (strtolower($batch->status)) {
                    'active'      => '#06b6d4', // cyan
                    'completed'   => '#10b981', // emerald
                    'upcoming'    => '#6366f1', // indigo
                    'rescheduled' => '#f59e0b', // amber
                    default       => '#6b7280', // gray (unknown status)
                };

                // String ang date columns mo, kaya i-parse natin nang safe
                $start = $this->parseDate($batch->date_start);
                $end   = $this->parseDate($batch->date_end);

                // Exclusive ang "end" sa FullCalendar all-day events,
                // kaya +1 day para makita ang event hanggang date_end mismo
                $endExclusive = $end?->copy()->addDay();

                return [
                    'id'    => $batch->id,
                    'title' => ($batch->program?->title ?? $batch->program_code)
                        . ' — Batch ' . $batch->batch,
                    'start' => $start?->toDateString(),
                    'end'   => $endExclusive?->toDateString(),
                    'allDay' => true,
                    'backgroundColor' => $color,
                    'borderColor'     => $color,
                    // Lahat ng detalye na ipapakita sa dialog pag-click
                    'extendedProps' => [
                        'program_id'    => $batch->program?->id,
                        'program_code'  => $batch->program_code,
                        'program_title' => $batch->program?->title,
                        'competency'    => $batch->program?->competency,
                        'category'      => $batch->program?->category,
                        'provider'      => $batch->program?->provider,
                        'batch'         => $batch->batch,
                        'status'        => $batch->status,
                        'modality'      => $batch->modality,
                        'venue'         => $batch->venue,
                        'date_start'    => $batch->date_start,
                        'date_end'      => $batch->date_end,
                        'time_start'    => $batch->time_start,
                        'time_end'      => $batch->time_end,
                        'days'          => $batch->days,
                        'hours'         => $batch->hours,
                        'participants'  => $batch->participants_count ?? $batch->participants()->count(),
                    ],
                ];
            })
            // Tanggalin ang mga batch na hindi ma-parse ang date para hindi sumabog ang calendar
            ->filter(fn ($event) => $event['start'] !== null)
            ->values();

        return Inertia::render('calendar/Calendar', [
            'events' => $events,
        ]);
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}