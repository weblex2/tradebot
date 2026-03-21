<?php

namespace App\Services;

use App\Models\ErrorFix;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ErrorFixService
{
    private const LOG_PATH    = '/var/www/trading/storage/logs/laravel.log';
    private const MAX_ERRORS  = 10; // max errors per cycle to avoid token overuse

    /**
     * Read recent ERROR-level log entries from the Laravel log file.
     * Only returns entries not yet stored in error_fixes (by hash).
     */
    public function collectNewErrors(): array
    {
        if (!file_exists(self::LOG_PATH)) {
            return [];
        }

        // Read last 500 lines to catch recent errors without loading the whole file
        $lines  = $this->tailFile(self::LOG_PATH, 500);
        $errors = [];
        $buffer = '';

        foreach ($lines as $line) {
            // New log entry starts with a timestamp like [2026-03-20 22:36:09]
            if (preg_match('/^\[\d{4}-\d{2}-\d{2}/', $line)) {
                if ($buffer !== '') {
                    $errors[] = trim($buffer);
                }
                $buffer = $line;
            } else {
                $buffer .= "\n" . $line;
            }
        }
        if ($buffer !== '') {
            $errors[] = trim($buffer);
        }

        // Keep only ERROR-level entries
        $errors = array_filter($errors, fn($e) => str_contains($e, '.ERROR:'));

        // Deduplicate by hash against DB
        $knownHashes = ErrorFix::pluck('error_hash')->flip();

        $new = [];
        foreach ($errors as $entry) {
            $hash = hash('sha256', $this->normalizeError($entry));
            if (!$knownHashes->has($hash)) {
                $new[$hash] = $entry;
            }
        }

        return array_slice($new, 0, self::MAX_ERRORS, true);
    }

    /**
     * Ask Claude to analyze an error and return a structured fix.
     * Uses the claude CLI (same as ClaudeAnalysisService).
     */
    public function analyzeWithClaude(string $errorMessage): ?array
    {
        $system = <<<SYSTEM
You are an expert Laravel/PHP debugger for a crypto trading bot. Analyze the given error log entry and respond with ONLY valid JSON (no markdown).

Response shape:
{"fix_description":"Short explanation of the error and what the fix does","proposed_solution":"Step-by-step solution a developer should follow to fix this permanently","fix_type":"db|artisan|code|info","fix_command":"SQL query OR artisan command string OR null"}

fix_type rules:
- "db": fix_command is a raw SQL statement safe to execute (UPDATE/DELETE only, never DROP/TRUNCATE/ALTER)
- "artisan": fix_command is an artisan command string (e.g. "cache:clear")
- "code": fix_command is null – code change required, describe concrete steps in proposed_solution
- "info": not a real error or already handled, fix_command is null

Keep fix_description under 200 chars. In proposed_solution, give concrete actionable steps (file paths, method names, config keys). Max 600 chars. When unsure, use "info" or "code".
SYSTEM;

        $user = "Error log entry:\n\n" . substr($errorMessage, 0, 800);

        $result = Process::timeout(60)->env([
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
            Log::warning('ErrorFixService: claude CLI failed', ['stderr' => substr($result->errorOutput(), 0, 200)]);
            return null;
        }

        $envelope = json_decode($result->output(), true);
        $raw      = $envelope['result'] ?? $result->output();

        $raw  = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $raw  = preg_replace('/```\s*$/m', '', $raw);
        $data = json_decode(trim($raw), true);

        if (!isset($data['fix_description'], $data['fix_type'], $data['proposed_solution'])) {
            Log::warning('ErrorFixService: invalid Claude response', ['raw' => substr($raw, 0, 200)]);
            return null;
        }

        return $data;
    }

    /**
     * Let Claude implement a code fix by producing file-level search/replace patches.
     * Returns a human-readable result string and updates the ErrorFix record.
     */
    public function applyCodeFixWithClaude(ErrorFix $fix): string
    {
        $base = base_path();

        // Collect candidate PHP files mentioned in the error or proposed solution
        $candidates = $this->extractFilePaths($fix->error_message . ' ' . ($fix->proposed_solution ?? ''));

        $fileContext = '';
        foreach ($candidates as $rel) {
            $abs = $base . '/' . ltrim($rel, '/');
            if (is_file($abs) && filesize($abs) < 60_000) {
                $content = file_get_contents($abs);
                $fileContext .= "\n\n### File: {$rel}\n```php\n" . substr($content, 0, 4000) . "\n```";
            }
        }

        $system = <<<SYSTEM
You are an expert Laravel/PHP developer fixing a bug in a crypto trading bot at /var/www/trading.
Respond with ONLY a valid JSON array of file patches (no markdown, no explanation).

Response shape:
[{"file":"app/Services/Foo.php","search":"exact existing code to replace","replace":"new code"}]

Rules:
- file paths are relative to /var/www/trading (e.g. "app/Services/TradeExecutor.php")
- "search" must be an EXACT substring of the current file content (whitespace matters)
- keep changes minimal and targeted — only fix the bug described
- maximum 3 patches
- if you cannot produce a safe, targeted patch, return []
SYSTEM;

        $user = "Error:\n" . substr($fix->error_message, 0, 600)
            . "\n\nProposed solution:\n" . substr($fix->proposed_solution ?? '', 0, 600)
            . ($fileContext ? "\n\nRelevant files:" . $fileContext : '');

        $result = Process::timeout(120)->env([
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
            $err = substr($result->errorOutput(), 0, 300);
            Log::warning('ErrorFixService: applyCodeFix claude failed', ['stderr' => $err]);
            return "Claude call failed: {$err}";
        }

        $envelope = json_decode($result->output(), true);
        $raw      = $envelope['result'] ?? $result->output();
        $raw      = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $raw      = preg_replace('/```\s*$/m', '', $raw);
        $patches  = json_decode(trim($raw), true);

        if (!is_array($patches) || empty($patches)) {
            return 'Claude returned no patches. Manual fix required.';
        }

        $applied = [];
        $errors  = [];

        foreach ($patches as $patch) {
            $rel     = $patch['file']    ?? null;
            $search  = $patch['search']  ?? null;
            $replace = $patch['replace'] ?? null;

            if (!$rel || !$search || $replace === null) {
                $errors[] = "Malformed patch (missing fields).";
                continue;
            }

            $abs = $base . '/' . ltrim($rel, '/');

            if (!is_file($abs)) {
                $errors[] = "File not found: {$rel}";
                continue;
            }

            $content = file_get_contents($abs);

            if (!str_contains($content, $search)) {
                $errors[] = "Search string not found in {$rel}.";
                continue;
            }

            $new = str_replace($search, $replace, $content, $count);
            file_put_contents($abs, $new);
            $applied[] = "{$rel} ({$count} replacement(s))";
        }

        $fix->update([
            'fix_applied' => !empty($applied),
            'fix_result'  => empty($applied)
                ? 'No patches applied. Errors: ' . implode(' | ', $errors)
                : 'Patched: ' . implode(', ', $applied) . (empty($errors) ? '' : '. Skipped: ' . implode(' | ', $errors)),
        ]);

        Log::info('ErrorFixService: code fix applied', ['applied' => $applied, 'errors' => $errors]);

        return $fix->fix_result;
    }

    // -------------------------------------------------------------------------

    private function extractFilePaths(string $text): array
    {
        // Match patterns like app/Services/Foo.php or /var/www/trading/app/...
        preg_match_all('/(?:app|config|database|routes|resources)\/[\w\/\-\.]+\.php/', $text, $m);
        $paths = array_unique($m[0]);

        // Also strip absolute prefix if present
        return array_map(fn($p) => ltrim(str_replace('/var/www/trading/', '', $p), '/'), $paths);
    }

    /**
     * Apply a fix if it is safe to execute automatically (db or artisan).
     * Returns a result string.
     */
    public function applyFix(string $fixType, ?string $fixCommand): string
    {
        if (empty($fixCommand)) {
            return 'No command to execute.';
        }

        try {
            if ($fixType === 'db') {
                // Safety: only allow SELECT/UPDATE/DELETE
                $verb = strtoupper(explode(' ', trim($fixCommand))[0]);
                if (!in_array($verb, ['SELECT', 'UPDATE', 'DELETE'])) {
                    return "Blocked: unsafe SQL verb '{$verb}'.";
                }
                $affected = DB::statement($fixCommand) ? 'executed' : 'no rows affected';
                return "DB fix applied: {$affected}";
            }

            if ($fixType === 'artisan') {
                $exitCode = Artisan::call($fixCommand);
                $output   = trim(Artisan::output());
                return "Artisan '{$fixCommand}' exited {$exitCode}. " . substr($output, 0, 200);
            }
        } catch (\Throwable $e) {
            return 'Fix failed: ' . $e->getMessage();
        }

        return 'Fix type not auto-applicable.';
    }

    // -------------------------------------------------------------------------

    /**
     * Normalize an error entry to remove timestamps/IDs so the same error
     * on different dates produces the same hash.
     */
    private function normalizeError(string $entry): string
    {
        // Remove timestamp
        $entry = preg_replace('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]\s*/', '', $entry);
        // Remove numeric IDs like "#123"
        $entry = preg_replace('/\b\d{4,}\b/', 'N', $entry);
        return trim($entry);
    }

    private function tailFile(string $path, int $lines): array
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $start = max(0, $totalLines - $lines);
        $file->seek($start);

        $result = [];
        while (!$file->eof()) {
            $result[] = rtrim($file->fgets());
        }

        return $result;
    }
}
