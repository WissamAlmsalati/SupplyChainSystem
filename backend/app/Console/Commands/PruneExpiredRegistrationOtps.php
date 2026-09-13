<?php

namespace App\Console\Commands;

use App\Models\PasswordResetOtp;
use Illuminate\Console\Command;

class PruneExpiredRegistrationOtps extends Command
{
    protected $signature = 'registration-otps:prune {--hours=24 : Delete OTP records expired more than this many hours ago}';

    protected $description = 'Delete expired password-reset and pending-registration OTP records';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');

        $deleted = PasswordResetOtp::where('expires_at', '<', now()->subHours($hours))->delete();

        $this->info("Deleted {$deleted} expired OTP record(s).");

        return self::SUCCESS;
    }
}
