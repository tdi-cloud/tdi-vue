<?php

namespace App\Mail;

use App\Models\ForeignNominee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewNomineeNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ForeignNominee $nominee) {}

    public function build()
    {
        $program = $this->nominee->program;

        // Buong absolute URL papunta sa program show page (may list ng nominees)
        $programUrl = $program
            ? route('foreign-programs.show', $program->id)
            : null;

        return $this->subject('New Nomination Submitted — ' . ($program->program_title ?? 'Foreign Program'))
            ->view('emails.new-nominee')
            ->with([
                'nominee'    => $this->nominee,
                'program'    => $program,
                'config'     => $this->nominee->sponsorConfig,
                'programUrl' => $programUrl,
            ]);
    }
}