<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\ErrorFix;
use App\Services\BotLogger;
use App\Services\ErrorFixService;
use Illuminate\Console\Command;

class ErrorAutoFixCommand extends Command
{
    protected $signature   = 'errors:auto-fix';
    protected $description = 'Scan Laravel logs for new errors, analyze with Claude, and apply safe fixes.';

    public function handle(ErrorFixService $service): int
    {
        $this->info('Scanning logs for new errors...');

        $newErrors = $service->collectNewErrors();

        if (empty($newErrors)) {
            $this->info('No new errors found.');
        } else {
            $this->info(count($newErrors) . ' new error(s) found.');

            foreach ($newErrors as $hash => $errorMessage) {
                $this->line("\n--- Error ---");
                $this->line(substr($errorMessage, 0, 200) . (strlen($errorMessage) > 200 ? '...' : ''));

                $analysis = $service->analyzeWithClaude($errorMessage);

                if ($analysis === null) {
                    $this->warn('Claude analysis failed, skipping.');
                    continue;
                }

                $fixType  = $analysis['fix_type']         ?? 'info';
                $fixCmd   = $analysis['fix_command']       ?? null;
                $fixDesc  = $analysis['fix_description']   ?? 'No description.';
                $proposed = $analysis['proposed_solution'] ?? null;

                $this->line("Fix type: {$fixType}");
                $this->line("Fix: {$fixDesc}");

                $applied = false;
                $result  = 'Not applied (code change required or info only).';

                if (in_array($fixType, ['db', 'artisan'])) {
                    $result  = $service->applyFix($fixType, $fixCmd);
                    $applied = !str_starts_with($result, 'Fix failed') && !str_starts_with($result, 'Blocked');
                    $this->line("Result: {$result}");
                }

                // Use updateOrCreate to safely handle duplicate hashes
                ErrorFix::updateOrCreate(
                    ['error_hash' => $hash],
                    [
                        'error_message'     => $errorMessage,
                        'error_context'     => null,
                        'fix_description'   => $fixDesc,
                        'proposed_solution' => $proposed,
                        'fix_command'       => $fixCmd,
                        'fix_type'          => $fixType,
                        'fix_applied'       => $applied,
                        'fix_result'        => $result,
                    ]
                );

                $this->info($applied ? '✓ Fix applied and saved.' : '→ Saved for review.');
            }
        }

        $this->checkUnprocessedArticles();

        $this->info("\nDone.");
        return 0;
    }

    private function checkUnprocessedArticles(): void
    {
        // Articles older than 30 minutes that are still unprocessed (scoring should have run by then)
        $stale = Article::where('is_processed', false)
            ->where('created_at', '<', now()->subMinutes(30))
            ->count();

        if ($stale === 0) {
            return;
        }

        $oldest = Article::where('is_processed', false)
            ->where('created_at', '<', now()->subMinutes(30))
            ->oldest()
            ->value('created_at');

        BotLogger::warning('scraper',
            "{$stale} Artikel seit mehr als 30 Minuten unverarbeitet — Scoring möglicherweise fehlgeschlagen.",
            ['unprocessed_count' => $stale, 'oldest_created_at' => (string) $oldest]
        );

        $this->warn("{$stale} unprocessed articles older than 30 min detected.");
    }
}
