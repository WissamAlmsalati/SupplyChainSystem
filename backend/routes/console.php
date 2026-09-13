<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ponytail: expired OTP records (including pending cafe registrations) are safe
// to delete after a short buffer; this keeps the password_reset_otps table small.
Schedule::command('registration-otps:prune')->daily();
