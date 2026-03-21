<?php

namespace App\Console\Commands;

use App\Models\ErrorFix;
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
            return 0;
        }

        $this->info(count($newErrors) . ' new error(s) found.');

        foreach ($newErrors as $hash => $errorMessage) {
            $this->line("\n--- Error ---");
            $this->line(substr($errorMessage, 0, 200) . (strlen($errorMessage) > 200 ? '...' : ''));

            $analysis = $service->analyzeWithClaude($errorMessage);

            if ($analysis === null) {
                $this->warn('Claude analysis failed, skipping.');
                continue;
            }

            $fixType    = $analysis['fix_type']    ?? 'info';
            $fixCmd     = $analysis['fix_command']  ?? null;
            $fixDesc    = $analysis['fix_description'] ?? 'No description.';

            $this->line("Fix type: {$fixType}");
            $this->line("Fix: {$fixDesc}");

            $applied = false;
            $result  = 'Not applied (code change required or info only).';

            if (in_array($fixType, ['db', 'artisan'])) {
                $result  = $service->applyFix($fixType, $fixCmd);
                $applied = !str_starts_with($result, 'Fix failed') && !str_starts_with($result, 'Blocked');
                $this->line("Result: {$result}");
            }

            ErrorFix::create([
                'error_hash'      => $hash,
                'error_message'   => $errorMessage,
                'error_context'   => null,
                'fix_description' => $fixDesc,
                'fix_command'     => $fixCmd,
                'fix_type'        => $fixType,
                'fix_applied'     => $applied,
                'fix_result'      => $result,
            ]);

            $this->info($applied ? '✓ Fix applied and saved.' : '→ Saved for review.');
        }

        $this->info("\nDone.");
        return 0;
    }
}
