<?php
namespace App\Jobs;

use App\Models\Article;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PruneOldArticlesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 300;

    public function handle(): void
    {
        $cutoff  = now()->subDays(90);
        $deleted = Article::where('created_at', '<', $cutoff)->delete();
        Log::info('PruneOldArticlesJob: pruned old articles', ['deleted' => $deleted, 'cutoff' => $cutoff->toDateString()]);
    }
}
