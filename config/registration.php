<?php

// Settings for the public program registration form and the accounts
// created when a registration is approved.
return [

    // How many days the emailed temporary password works. After that the
    // person uses "Forgot password", or the organizer resends login details.
    'temp_password_days' => (int) env('REGISTRATION_TEMP_PASSWORD_DAYS', 7),

];
