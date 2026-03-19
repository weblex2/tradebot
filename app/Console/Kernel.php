<?php

namespace App\Console;

use App\Jobs\PruneOldArticlesJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Scraper: runs every minute, ScraperService filters by refresh_minutes internally
        $schedule->command('scraper:run')->everyMinute()->withoutOverlapping();

        // Analysis cycle: every 30 minutes
        $schedule->command('trade:analyze')->everyThirtyMinutes()->withoutOverlapping();

        // Prune old articles: weekly
        $schedule->job(new PruneOldArticlesJob())->weekly()->sundays()->at('02:00');
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
