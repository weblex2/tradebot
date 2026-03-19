<?php
namespace App\Jobs;

use App\Models\Source;
use App\Services\BotLogger;
use App\Services\ScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScraperJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;
    public array $backoff = [60, 300, 600];

    public function __construct(private Source $source) {}

    public function handle(ScraperService $scraper): void
    {
        BotLogger::info('scraper', "Scraper started for {$this->source->name}", [], $this->source->id);
        $count = $scraper->scrape($this->source);
        BotLogger::info('scraper', "Scraper done: {$count} new articles for {$this->source->name}", ['articles_added' => $count], $this->source->id);
    }

    public function tags(): array
    {
        return ['scraper', 'source:' . $this->source->id];
    }
}
