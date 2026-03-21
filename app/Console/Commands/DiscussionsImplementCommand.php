<?php

namespace App\Console\Commands;

use App\Models\Discussion;
use App\Services\DiscussionService;
use Illuminate\Console\Command;

class DiscussionsImplementCommand extends Command
{
    protected $signature   = 'discussions:implement {--id= : Only implement a specific discussion}';
    protected $description = 'Implement all agreed discussions';

    public function handle(DiscussionService $service): int
    {
        $query = Discussion::where('status', 'agreed');

        if ($id = $this->option('id')) {
            $query->where('id', $id);
        }

        $discussions = $query->orderBy('priority', 'desc')->orderBy('updated_at')->get();

        if ($discussions->isEmpty()) {
            $this->info('No agreed discussions to implement.');
            return self::SUCCESS;
        }

        $this->info("Implementing {$discussions->count()} discussion(s)...");

        $ok   = 0;
        $fail = 0;

        foreach ($discussions as $d) {
            $this->line("  [{$d->id}] {$d->title}");
            $success = $service->implementChanges($d);

            if ($success) {
                $ok++;
                $this->line("       ✓ Finished");
            } else {
                $fail++;
                $this->line("       ✗ Failed — check logs");
            }
        }

        $this->info("Done. {$ok} implemented, {$fail} failed.");
        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
