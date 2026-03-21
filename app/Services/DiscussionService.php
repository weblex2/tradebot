<?php

namespace App\Services;

use App\Models\Discussion;
use App\Services\BotLogger;
use Illuminate\Support\Facades\Process;

class DiscussionService
{
    // Files to include as context when generating suggestions
    private const CONTEXT_FILES = [
        'app/Services/ClaudeAnalysisService.php',
        'app/Services/TradeExecutor.php',
        'app/Services/CoinbaseService.php',
        'app/Services/ScraperService.php',
        'app/Services/ErrorFixService.php',
        'app/Console/Kernel.php',
        'config/trading.php',
    ];

    // Max new suggestions per generate run
    private const MAX_SUGGESTIONS = 5;

    // Total rounds before Claude makes binding final decision
    private const MAX_ROUNDS = 5;

    // -------------------------------------------------------------------------
    // Step 1: Generate improvement suggestions
    // -------------------------------------------------------------------------

    public function generateSuggestions(): array
    {
        $context = $this->buildProjectContext();

        $system = <<<SYSTEM
You are a senior Laravel/PHP architect performing a code review of a crypto trading bot.
Analyse the provided source code and identify concrete, actionable improvements.

Return ONLY valid JSON — no markdown, no explanation outside the JSON.

Response shape:
{"suggestions": [{"title": "...", "suggestion": "Detailed description of the problem and proposed fix", "affected_files": ["app/Services/Foo.php"], "priority": "high|medium|low"}]}

Rules:
- Maximum 5 suggestions
- Focus on: performance, reliability, security, code quality, missing features
- Each suggestion must be specific and implementable (not vague like "add more logging")
- Title must be unique and descriptive (max 80 chars)
- affected_files: list of relative file paths involved (can be empty array if new file needed)
- Avoid suggesting things already handled well in the code
SYSTEM;

        BotLogger::info('discussions', 'Generating improvement suggestions via Claude');

        $data = $this->callClaudeJson($system, $context, 'generate suggestions');
        if (!$data || empty($data['suggestions'])) {
            BotLogger::error('discussions', 'Failed to generate suggestions or empty response');
            return [];
        }

        $created = [];
        foreach (array_slice($data['suggestions'], 0, self::MAX_SUGGESTIONS) as $s) {
            $title = trim($s['title'] ?? '');
            if (!$title) continue;

            $hash = hash('sha256', strtolower($title));
            if (Discussion::where('title_hash', $hash)->exists()) {
                BotLogger::info('discussions', "Skipping duplicate suggestion: {$title}");
                continue;
            }

            $discussion = Discussion::create([
                'title'          => $title,
                'title_hash'     => $hash,
                'suggestion'     => trim($s['suggestion'] ?? ''),
                'affected_files' => $s['affected_files'] ?? [],
                'priority'       => in_array($s['priority'] ?? '', ['low', 'medium', 'high']) ? $s['priority'] : 'medium',
                'status'         => 'pending',
            ]);

            $created[] = $discussion;
            BotLogger::info('discussions', "Created discussion [{$discussion->id}]: {$title}");
        }

        BotLogger::info('discussions', 'Generated ' . count($created) . ' new suggestions');
        return $created;
    }

    // -------------------------------------------------------------------------
    // Step 2: Advance discussion by one round
    // -------------------------------------------------------------------------

    public function runDiscussionRound(Discussion $discussion): void
    {
        $nextRound = $discussion->round + 1;

        if ($discussion->status === 'pending') {
            $discussion->status = 'discussing';
            $discussion->save();
        }

        if ($nextRound > self::MAX_ROUNDS) {
            BotLogger::warning('discussions', "Discussion [{$discussion->id}] already at max rounds");
            return;
        }

        BotLogger::info('discussions', "Discussion [{$discussion->id}] round {$nextRound}/{$this->maxRounds()}");

        // Round 5: Claude makes the final binding decision (JSON verdict)
        if ($nextRound === self::MAX_ROUNDS) {
            $this->claudeFinalVerdict($discussion);
            return;
        }

        // Odd rounds (1, 3): Gemini speaks
        // Even rounds (2, 4): Claude responds
        if ($nextRound % 2 === 1) {
            $this->geminiTurn($discussion, $nextRound);
        } else {
            $this->claudeTurn($discussion, $nextRound);
        }
    }

    public function maxRounds(): int
    {
        return self::MAX_ROUNDS;
    }

    // -------------------------------------------------------------------------
    // Step 3: Implement agreed changes
    // -------------------------------------------------------------------------

    public function implementChanges(Discussion $discussion): bool
    {
        if ($discussion->status !== 'agreed') {
            BotLogger::warning('discussions', "Discussion [{$discussion->id}] is not agreed, cannot implement");
            return false;
        }

        $discussion->status = 'implementing';
        $discussion->save();

        BotLogger::info('discussions', "Implementing discussion [{$discussion->id}]: {$discussion->title}");

        // Build file context for affected files
        $fileContents = $this->readAffectedFiles($discussion->affected_files ?? []);

        $discussionText = $this->formatTurnsAsText($discussion);

        $system = <<<SYSTEM
You are a senior Laravel developer implementing an agreed code improvement in a crypto trading bot.
You will receive: the original suggestion, the full discussion, and the current file contents.

Implement the agreed changes. Return ONLY valid JSON — no markdown, no preamble.

Response shape:
{"changes": [{"file": "relative/path/file.php", "description": "What was changed", "old_snippet": "exact existing code to replace", "new_snippet": "replacement code"}], "notes": "Brief summary of what was done"}

Rules:
- old_snippet must be an EXACT match of existing code (will be used with str_replace)
- Keep changes minimal and focused on what was agreed
- Do not refactor unrelated code
- If a completely new file is needed: use old_snippet "" and new_snippet = full file content
- Maximum 10 changes
SYSTEM;

        $user = "## Original Suggestion\n{$discussion->suggestion}"
              . "\n\n## Agreed Implementation Plan\n{$discussion->consensus_summary}"
              . "\n\n## Full Discussion\n{$discussionText}"
              . "\n\n## Current File Contents\n{$fileContents}";

        $data = $this->callClaudeJson($system, $user, 'implement changes');
        if (!$data || empty($data['changes'])) {
            BotLogger::error('discussions', "Failed to get implementation plan for [{$discussion->id}]");
            $discussion->status = 'agreed'; // revert so it can be retried
            $discussion->save();
            return false;
        }

        $appliedCount = 0;
        $errors       = [];

        foreach ($data['changes'] as $change) {
            $relPath = $change['file'] ?? '';
            $absPath = base_path($relPath);

            if (!$relPath || !str_starts_with(realpath(dirname($absPath)) ?: '', base_path())) {
                $errors[] = "Skipped unsafe path: {$relPath}";
                continue;
            }

            $oldSnippet = $change['old_snippet'] ?? '';
            $newSnippet = $change['new_snippet'] ?? '';

            if ($oldSnippet === '' && $newSnippet !== '') {
                // New file
                @mkdir(dirname($absPath), 0755, true);
                file_put_contents($absPath, $newSnippet);
                $appliedCount++;
                BotLogger::info('discussions', "Created new file: {$relPath}");
                continue;
            }

            if (!file_exists($absPath)) {
                $errors[] = "File not found: {$relPath}";
                continue;
            }

            $content = file_get_contents($absPath);
            if (!str_contains($content, $oldSnippet)) {
                $errors[] = "Snippet not found in {$relPath}: " . substr($oldSnippet, 0, 60) . '...';
                continue;
            }

            $updated = str_replace($oldSnippet, $newSnippet, $content);
            file_put_contents($absPath, $updated);
            $appliedCount++;
            BotLogger::info('discussions', "Modified: {$relPath} — {$change['description']}");
        }

        $notes = ($data['notes'] ?? 'No notes provided.')
               . "\n\nApplied {$appliedCount} of " . count($data['changes']) . " changes.";

        if (!empty($errors)) {
            $notes .= "\n\nErrors:\n- " . implode("\n- ", $errors);
        }

        $discussion->implementation_notes = $notes;
        $discussion->status               = 'finished';
        $discussion->save();

        BotLogger::info('discussions', "Discussion [{$discussion->id}] finished — {$appliedCount} changes applied");
        return true;
    }

    // -------------------------------------------------------------------------
    // Private: Discussion turn logic
    // -------------------------------------------------------------------------

    private function geminiTurn(Discussion $discussion, int $round): void
    {
        $priorTurns = $this->formatTurnsAsText($discussion);
        $remaining  = self::MAX_ROUNDS - $round;

        if ($round === 1) {
            $context = "## Proposed Improvement\n**Title:** {$discussion->title}\n\n{$discussion->suggestion}";
            if (!empty($discussion->affected_files)) {
                $context .= "\n\n**Affected files:** " . implode(', ', $discussion->affected_files);
            }
        } else {
            $context = "## Original Proposal\n{$discussion->suggestion}\n\n## Discussion so far\n{$priorTurns}";
        }

        $system = <<<SYSTEM
You are a pragmatic senior developer critically reviewing a proposed code improvement for a Laravel crypto trading bot.
Your goal is constructive review — identify real issues but support genuinely good ideas.
Be concise (max 300 words). If the core idea is sound even if implementation details could be tweaked, lean towards supporting it.
Focus on: Is it worth doing? Are there serious risks? Is the approach sensible?
Do NOT write code — only discuss the concept and approach.
SYSTEM;

        $user = $context . "\n\n(Round {$round} of " . self::MAX_ROUNDS . ". {$remaining} rounds remain after this.)";

        $text = $this->callClaudeText('gemini', $system, $user, "discussion round {$round}");
        if ($text === null) {
            BotLogger::error('discussions', "Gemini failed in discussion [{$discussion->id}] round {$round}");
            return;
        }

        $discussion->addTurn('gemini', $text);
    }

    private function claudeTurn(Discussion $discussion, int $round): void
    {
        $priorTurns = $this->formatTurnsAsText($discussion);
        $remaining  = self::MAX_ROUNDS - $round;

        $system = <<<SYSTEM
You are a senior Laravel developer who proposed a code improvement for a crypto trading bot.
You are discussing this with Gemini (another AI reviewer).
Be open to valid criticism and update your thinking accordingly, but defend sound ideas with clear reasoning.
Be concise (max 300 words). Acknowledge Gemini's points, address concerns, and clarify your intent.
Do NOT write code — only discuss the concept and approach.
SYSTEM;

        $user = "## Original Proposal\n{$discussion->suggestion}"
              . "\n\n## Discussion so far\n{$priorTurns}"
              . "\n\n(Round {$round} of " . self::MAX_ROUNDS . ". {$remaining} rounds remain. In the final round you will make a binding decision.)";

        $text = $this->callClaudeText('claude', $system, $user, "discussion round {$round}");
        if ($text === null) {
            BotLogger::error('discussions', "Claude failed in discussion [{$discussion->id}] round {$round}");
            return;
        }

        $discussion->addTurn('claude', $text);
    }

    private function claudeFinalVerdict(Discussion $discussion): void
    {
        $priorTurns = $this->formatTurnsAsText($discussion);

        $system = <<<SYSTEM
You are a senior Laravel developer making a final binding decision on a proposed code improvement.
You have discussed this with Gemini across multiple rounds. Now you must decide.

Return ONLY valid JSON — no markdown, no preamble.

Response shape:
{"verdict": "approve"|"reject", "consensus_summary": "Concrete implementation plan (if approve) or reason for rejection (if reject)", "reason": "One sentence explaining your final decision"}

Rules:
- approve: The improvement is worthwhile and implementable. Write a clear, actionable implementation plan in consensus_summary.
- reject: The risks outweigh the benefits, or the idea is fundamentally flawed. Be specific about why.
- If both perspectives had merit and the core idea is sound, lean towards approve.
- Prolonged disagreement without clear resolution defaults to reject.
SYSTEM;

        $user = "## Original Proposal\n{$discussion->suggestion}"
              . "\n\n## Full Discussion\n{$priorTurns}"
              . "\n\nThis is round " . self::MAX_ROUNDS . " (FINAL). Make your binding decision now.";

        $data = $this->callClaudeJson($system, $user, 'final verdict');

        if (!$data || !isset($data['verdict'])) {
            BotLogger::error('discussions', "Claude failed to produce verdict for discussion [{$discussion->id}]");
            // Add error turn but don't change status
            $discussion->addTurn('claude', '[ERROR: Failed to produce final verdict. Will retry next run.]');
            return;
        }

        $verdict = strtolower($data['verdict']);
        $summary = $data['consensus_summary'] ?? '';
        $reason  = $data['reason'] ?? '';

        $turnContent = "**FINAL VERDICT: " . strtoupper($verdict) . "**\n\n{$reason}";
        $discussion->addTurn('claude', $turnContent);

        $discussion->status            = $verdict === 'approve' ? 'agreed' : 'rejected';
        $discussion->consensus_summary = $summary;
        $discussion->save();

        BotLogger::info('discussions', "Discussion [{$discussion->id}] verdict: {$verdict} — {$reason}");
    }

    // -------------------------------------------------------------------------
    // Private: Helpers
    // -------------------------------------------------------------------------

    private function formatTurnsAsText(Discussion $discussion): string
    {
        $turns = $discussion->turns ?? [];
        if (empty($turns)) return '(No turns yet)';

        return collect($turns)->map(function ($turn, $i) {
            $label = strtoupper($turn['role']);
            return "**[Turn " . ($i + 1) . " – {$label}]**\n{$turn['content']}";
        })->implode("\n\n---\n\n");
    }

    private function buildProjectContext(): string
    {
        $parts = ["# Tradebot Project – Code Review Context\n"];

        foreach (self::CONTEXT_FILES as $relPath) {
            $absPath = base_path($relPath);
            if (!file_exists($absPath)) continue;

            $content = file_get_contents($absPath);
            // Truncate large files to keep prompt manageable
            if (strlen($content) > 8000) {
                $content = substr($content, 0, 8000) . "\n... [truncated]";
            }

            $parts[] = "## {$relPath}\n```php\n{$content}\n```";
        }

        return implode("\n\n", $parts);
    }

    private function readAffectedFiles(array $relPaths): string
    {
        if (empty($relPaths)) return '(No specific files listed)';

        $parts = [];
        foreach ($relPaths as $relPath) {
            $absPath = base_path($relPath);
            if (!file_exists($absPath)) {
                $parts[] = "## {$relPath}\n(File does not exist yet)";
                continue;
            }
            $content = file_get_contents($absPath);
            if (strlen($content) > 6000) {
                $content = substr($content, 0, 6000) . "\n... [truncated]";
            }
            $parts[] = "## {$relPath}\n```php\n{$content}\n```";
        }

        return implode("\n\n", $parts);
    }

    /**
     * Call Claude CLI and expect JSON back.
     */
    private function callClaudeJson(string $system, string $user, string $task): ?array
    {
        BotLogger::info('discussions', "Claude › {$task}");
        try {
            $result = Process::timeout(300)->env([
                'HOME' => '/home/ubuntu',
                'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            ])->run([
                'claude',
                '--print',
                '--system-prompt', $system,
                '--output-format', 'json',
                '--no-session-persistence',
                '--model', 'sonnet',
                $user,
            ]);

            if (!$result->successful()) {
                $stderr = trim(substr($result->errorOutput(), 0, 300));
                BotLogger::error('discussions', "Claude failed [{$task}]: {$stderr}");
                return null;
            }

            $envelope = json_decode($result->output(), true);
            if (!isset($envelope['result'])) {
                BotLogger::error('discussions', "Claude unexpected output for [{$task}]");
                return null;
            }

            $raw   = $envelope['result'];
            $clean = preg_replace('/^```(?:json)?\s*/m', '', $raw);
            $clean = preg_replace('/\s*```\s*$/m', '', $clean);
            $clean = trim($clean);

            // Extract JSON object/array if surrounded by text
            if (!str_starts_with($clean, '{') && !str_starts_with($clean, '[')) {
                $first = strpos($raw, '{');
                $last  = strrpos($raw, '}');
                if ($first !== false && $last !== false) {
                    $clean = substr($raw, $first, $last - $first + 1);
                }
            }

            $data = json_decode($clean, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                BotLogger::error('discussions', "Claude returned invalid JSON for [{$task}]: " . substr($clean, 0, 200));
                return null;
            }

            return $data;
        } catch (\Throwable $e) {
            BotLogger::error('discussions', "Claude exception [{$task}]: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Call Claude or Gemini CLI and return plain text (for discussion turns).
     */
    private function callClaudeText(string $model, string $system, string $user, string $task): ?string
    {
        BotLogger::info('discussions', ucfirst($model) . " › {$task}");

        if ($model === 'gemini') {
            return $this->callGeminiText($system, $user, $task);
        }

        try {
            $result = Process::timeout(180)->env([
                'HOME' => '/home/ubuntu',
                'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            ])->run([
                'claude',
                '--print',
                '--system-prompt', $system,
                '--output-format', 'text',
                '--no-session-persistence',
                '--model', 'sonnet',
                $user,
            ]);

            if (!$result->successful()) {
                $stderr = trim(substr($result->errorOutput(), 0, 300));
                BotLogger::error('discussions', "Claude text failed [{$task}]: {$stderr}");
                return null;
            }

            return trim($result->output()) ?: null;
        } catch (\Throwable $e) {
            BotLogger::error('discussions', "Claude text exception [{$task}]: {$e->getMessage()}");
            return null;
        }
    }

    private function callGeminiText(string $system, string $user, string $task): ?string
    {
        try {
            $prompt = "SYSTEM INSTRUCTION: {$system}\n\nUSER INPUT: {$user}";
            $home   = $_SERVER['HOME'] ?? '/home/ubuntu';

            $result = Process::timeout(90)->path(base_path())->env([
                'HOME' => $home,
                'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
                'TERM' => 'dumb',
            ])->run([
                'gemini',
                '--prompt', $prompt,
                '--approval-mode', 'yolo',
            ]);

            if (!$result->successful()) {
                $stderr = $result->errorOutput();
                if (str_contains($stderr, 'QuotaError') || str_contains($stderr, 'quota')) {
                    BotLogger::warning('discussions', 'Gemini Quota erschöpft – wird automatisch zurückgesetzt');
                } else {
                    BotLogger::error('discussions', "Gemini text failed [{$task}]: " . substr($stderr, 0, 200));
                }
                return null;
            }

            $output = trim($result->output());
            return $output ?: null;
        } catch (\Throwable $e) {
            BotLogger::error('discussions', "Gemini text exception [{$task}]: {$e->getMessage()}");
            return null;
        }
    }
}
