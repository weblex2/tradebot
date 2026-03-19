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
        $schedule->command('scraper:run')->everyFifteenMinutes()->withoutOverlapping();

        // Analysis cycle: every 15 minutes, offset by 5 min after scraper
        $schedule->command('trade:analyze')->cron('5,20,35,50 * * * *')->withoutOverlapping();

        // Prune old articles: weekly
        $schedule->job(new PruneOldArticlesJob())->weekly()->sundays()->at('02:00');

        // Prune old bot logs: weekly (keep 30 days)
        $schedule->call(fn() => \App\Models\BotLog::where('created_at', '<', now()->subDays(30))->delete())
            ->weekly()->sundays()->at('03:00');
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
