<?php

namespace App\Console\Commands;

use App\Services\DiscussionService;
use Illuminate\Console\Command;

class DiscussionsGenerateCommand extends Command
{
    protected $signature   = 'discussions:generate';
    protected $description = 'Analyse the project and generate AI improvement suggestions';

    public function handle(DiscussionService $service): int
    {
        $this->info('Analysing project for improvement suggestions...');

        $created = $service->generateSuggestions();

        if (empty($created)) {
            $this->warn('No new suggestions created (all duplicates or AI failed).');
            return self::FAILURE;
        }

        foreach ($created as $d) {
            $this->line("  [{$d->priority}] {$d->title}");
        }

        $this->info(count($created) . ' suggestion(s) created.');
        return self::SUCCESS;
    }
}
