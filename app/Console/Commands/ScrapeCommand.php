<?php
namespace App\Console\Commands;

use App\Jobs\ScraperJob;
use App\Models\Source;
use App\Services\BotLogger;
use Illuminate\Console\Command;

class ScrapeCommand extends Command
{
    protected $signature   = 'scraper:run {--source= : Scrape a specific source ID}';
    protected $description = 'Scrape news sources for crypto signals';

    public function handle(): int
    {
        $sourceId = $this->option('source');

        if ($sourceId) {
            $source = Source::find($sourceId);
            if (!$source) {
                $this->error("Source #{$sourceId} not found.");
                return self::FAILURE;
            }
            $this->info("Dispatching scraper for: {$source->name}");
            ScraperJob::dispatch($source);
            return self::SUCCESS;
        }

        $sources = Source::where('is_active', true)->get();

        if ($sources->isEmpty()) {
            $this->warn('No active sources found.');
            return self::SUCCESS;
        }

        $due = $sources->filter(fn($s) => $s->isDueForScrape());

        if ($due->isEmpty()) {
            return self::SUCCESS;
        }

        BotLogger::info('scraper', "Scraper gestartet: {$due->count()} Sources fällig");

        foreach ($due as $source) {
            ScraperJob::dispatch($source);
        }

        return self::SUCCESS;
    }
}
