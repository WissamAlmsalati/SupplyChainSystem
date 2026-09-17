<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ponytail: expired OTP records (including pending customer registrations) are safe
// to delete after a short buffer; this keeps the password_reset_otps table small.
Schedule::command('registration-otps:prune')->daily();

// Expired bearer tokens are useless rows; drop them a day after expiry.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
