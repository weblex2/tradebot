<?php
namespace App\Services;

use App\Services\BotLogger;
use Illuminate\Support\Facades\Process;

class GeminiAnalysisService
{
    /**
     * Call the Gemini CLI as a fallback for Claude.
     */
    public function callGemini(string $system, string $user): ?array
    {
        try {
            $prompt = "SYSTEM INSTRUCTION: " . $system . "\n\nUSER INPUT: " . $user;
            
            BotLogger::info('gemini', 'Calling gemini CLI (dynamic user)...', ['prompt_len' => strlen($prompt)]);

            $home = $_SERVER['HOME'] ?? (is_dir('/home/ubuntu') ? '/home/ubuntu' : '/root');

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
                $stderr = trim(substr($result->errorOutput(), 0, 500));
                BotLogger::error('gemini', "Gemini process failed (exit {$result->exitCode()}): {$stderr}");
                return null;
            }

            $output = $result->output();
            $data = $this->parseJson($output);
            
            if ($data === null) {
                BotLogger::warning('gemini', 'Gemini returned invalid JSON structure', [
                    'output_preview' => substr($output, 0, 500)
                ]);
            }

            return $data;
        } catch (\Throwable $e) {
            BotLogger::error('gemini', "Gemini exception: {$e->getMessage()}");
            return null;
        }
    }

    private function parseJson(string $raw): ?array
    {
        $clean = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $clean = preg_replace('/\s*```\s*$/m', '', $clean);
        $clean = trim($clean);

        if (!str_starts_with($clean, '{') && !str_starts_with($clean, '[')) {
            $first = strpos($raw, '{');
            $last = strrpos($raw, '}');
            if ($first !== false && $last !== false) {
                $clean = substr($raw, $first, $last - $first + 1);
            }
        }

        $decoded = json_decode($clean, true);
        
        if (isset($decoded['response'])) {
            $res = $decoded['response'];
            if (is_string($res)) {
                $nested = json_decode($res, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $nested;
                }
                return $this->parseJson($res);
            }
            return $res;
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }
}
