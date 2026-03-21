<?php

namespace App\Console\Commands;

use App\Models\Discussion;
use App\Services\DiscussionService;
use Illuminate\Console\Command;

class DiscussionsDiscussCommand extends Command
{
    protected $signature   = 'discussions:discuss {--id= : Only advance a specific discussion}';
    protected $description = 'Advance all pending/discussing items by one round';

    public function handle(DiscussionService $service): int
    {
        $query = Discussion::whereIn('status', ['pending', 'discussing'])
                           ->where('round', '<', $service->maxRounds());

        if ($id = $this->option('id')) {
            $query->where('id', $id);
        }

        $discussions = $query->orderBy('priority', 'desc')->orderBy('created_at')->get();

        if ($discussions->isEmpty()) {
            $this->info('No active discussions to advance.');
            return self::SUCCESS;
        }

        $this->info("Advancing {$discussions->count()} discussion(s)...");

        foreach ($discussions as $d) {
            $nextRound = $d->round + 1;
            $this->line("  [{$d->id}] {$d->title} → round {$nextRound}/{$service->maxRounds()}");
            $service->runDiscussionRound($d);
            $d->refresh();
            $this->line("       Status: {$d->status}");
        }

        $agreed   = Discussion::where('status', 'agreed')->count();
        $rejected = Discussion::where('status', 'rejected')->count();
        $this->info("Done. Total agreed: {$agreed}, rejected: {$rejected}");

        return self::SUCCESS;
    }
}
