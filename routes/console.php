<?php

use Illuminate\\Foundation\\Inspiring;
use Illuminate\\Support\\Facades\\Artisan;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mobile-otp:prune', function () {
    $retentionDays = 7;
    $cutoff = now()->subDays($retentionDays);

    $deleted = DB::table('mobile_otp_challenges')
        ->where(function ($query) use ($cutoff) {
            $query->where('expires_at', '<', $cutoff)
                ->orWhere('consumed_at', '<', $cutoff);
        })
        ->delete();

    $this->info("Deleted {$deleted} old mobile OTP challenge(s).");
})->purpose('Delete expired and consumed mobile OTP challenges after seven days');

Schedule::command('mobile-otp:prune')->daily();
