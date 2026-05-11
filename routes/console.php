<?php
declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(\Illuminate\Foundation\Inspiring::quote());
})->purpose('Display an inspiring quote');

// 🔥 FAANG STANDARD: Production Safe Scheduling
Schedule::command('app:sync-bayut-full')
    ->twiceDaily(2, 14) // Raat 2 AM aur Dopahar 2 PM chalega
    ->withoutOverlapping() // CRITICAL: Agar purana sync chal raha hai, toh naya start nahi hoga
    ->onOneServer() // Agar multiple servers use kar rahe ho, toh sirf ek server fetch karega
    ->appendOutputTo(storage_path('logs/bayut-sync.log')); // Logs maintain karna zaroori hai

    Schedule::command('app:cleanup-expired-invitations')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/quota-cleanup.log'));