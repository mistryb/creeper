<?php

use App\Auth\LoginCodes;
use App\Console\Commands\DispatchDueCreeps;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(DispatchDueCreeps::class)
    ->everyMinute()
    ->withoutOverlapping();

/*
 * Spent and expired codes are useless but not harmless: they are a growing
 * table of hashes. LoginCodes deletes each one on use or on a failed check,
 * so this only sweeps up codes nobody ever came back for.
 */
Schedule::call(fn (LoginCodes $codes) => $codes->purgeExpired())
    ->hourly()
    ->name('purge-expired-login-codes')
    ->withoutOverlapping();
