<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('auth:purge-expired-registrations')->everyFiveMinutes();
        $schedule->command('auth:purge-expired-password-recovery-otps')->everyFiveMinutes();
        $schedule->command('planora:notify-tasks-due-soon')->dailyAt('08:00')->withoutOverlapping();
        if (config('backups.schedule_enabled')) {
            $schedule->command('planora:backup full')->dailyAt(config('backups.schedule_at'))->withoutOverlapping();
            $schedule->command('planora:backups-prune')->dailyAt('03:15')->withoutOverlapping();
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
