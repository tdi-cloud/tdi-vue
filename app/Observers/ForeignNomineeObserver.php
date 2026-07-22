<?php

namespace App\Observers;

use App\Models\ForeignNominee;

class ForeignNomineeObserver
{
    /**
     * A program waiting for nominees moves to "waiting for result" as soon as
     * it receives its first nominee.
     */
    public function created(ForeignNominee $foreignNominee): void
    {
        $program = $foreignNominee->program;

        if ($program && $program->status === 'waiting_for_nominees') {
            $program->update(['status' => 'waiting_for_result']);
        }
    }

    /**
     * If deleting a nominee leaves the program with none left, revert it back
     * to "waiting for nominees" — undoing the automatic move made above.
     */
    public function deleted(ForeignNominee $foreignNominee): void
    {
        $program = $foreignNominee->program;

        if ($program && $program->status === 'waiting_for_result' && $program->nominees()->doesntExist()) {
            $program->update(['status' => 'waiting_for_nominees']);
        }
    }
}
