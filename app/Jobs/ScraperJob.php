<?php
namespace App\Jobs;

use App\Models\Source;
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
        Log::info('ScraperJob: starting', ['source_id' => $this->source->id, 'name' => $this->source->name]);
        $count = $scraper->scrape($this->source);
        Log::info('ScraperJob: done', ['source_id' => $this->source->id, 'articles_added' => $count]);
    }

    public function tags(): array
    {
        return ['scraper', 'source:' . $this->source->id];
    }
}
