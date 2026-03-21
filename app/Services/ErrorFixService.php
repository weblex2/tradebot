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
{"fix_description":"Short explanation of the error and what the fix does","fix_type":"db|artisan|code|info","fix_command":"SQL query OR artisan command string OR null"}

fix_type rules:
- "db": fix_command is a raw SQL statement safe to execute (UPDATE/DELETE only, never DROP/TRUNCATE/ALTER)
- "artisan": fix_command is an artisan command string (e.g. "cache:clear")
- "code": fix_command is null – code change required, only describe in fix_description
- "info": not a real error or already handled, fix_command is null

Keep fix_description under 300 chars. When unsure, use "info" or "code".
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

        if (!isset($data['fix_description'], $data['fix_type'])) {
            Log::warning('ErrorFixService: invalid Claude response', ['raw' => substr($raw, 0, 200)]);
            return null;
        }

        return $data;
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
