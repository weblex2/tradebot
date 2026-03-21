<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiChatStreamController extends Controller
{
    private const SYSTEM_PROMPT = 'Du bist ein Assistent für ein Laravel Crypto Trading Bot Projekt (Tradebot). '
        . 'Antworte präzise und auf Deutsch wenn nicht anders gewünscht.';

    public function stream(Request $request): StreamedResponse
    {
        $message = $request->input('message', '');
        $history = $request->input('history', []);
        $images  = $request->input('images', []);

        if (!is_array($history)) $history = [];
        if (!is_array($images))  $images  = [];

        $payload = !empty($images)
            ? $this->buildImagePayload($message, $images)
            : $this->buildTextPayload($message, $history);

        // Capture $payload and system for the closure
        $system  = self::SYSTEM_PROMPT;
        $self    = $this;

        return response()->stream(function () use ($payload, $system, $self) {
            $self->runClaudeStream($payload, $system);
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',   // Disable nginx buffering
            'Connection'        => 'keep-alive',
        ]);
    }

    // -------------------------------------------------------------------------
    // Payload builders
    // -------------------------------------------------------------------------

    private function buildTextPayload(string $message, array $history): string
    {
        $contentText = '';
        foreach (array_slice($history, -6) as $msg) {
            $role         = $msg['role'] === 'user' ? 'User' : 'Assistant';
            $contentText .= "{$role}: {$msg['content']}\n";
        }
        $contentText .= "User: {$message}";

        return json_encode([
            'type'    => 'user',
            'message' => ['role' => 'user', 'content' => $contentText],
        ]);
    }

    private function buildImagePayload(string $message, array $images): string
    {
        $contentBlocks = [];
        foreach ($images as $dataUrl) {
            if (!preg_match('/^data:(image\/[\w+]+);base64,(.+)$/s', $dataUrl, $m)) continue;
            $contentBlocks[] = [
                'type'   => 'image',
                'source' => ['type' => 'base64', 'media_type' => $m[1], 'data' => $m[2]],
            ];
        }
        if ($message !== '') {
            $contentBlocks[] = ['type' => 'text', 'text' => $message];
        }

        return json_encode([
            'type'    => 'user',
            'message' => ['role' => 'user', 'content' => $contentBlocks],
        ]);
    }

    // -------------------------------------------------------------------------
    // Streaming runner
    // -------------------------------------------------------------------------

    public function runClaudeStream(string $payload, string $system): void
    {
        // Disable output buffering so SSE events flush immediately
        while (ob_get_level() > 0) ob_end_clean();

        $cmd = [
            'claude', '--print',
            '--system-prompt', $system,
            '--no-session-persistence',
            '--model', 'sonnet',
            '--input-format', 'stream-json',
            '--output-format', 'stream-json',
            '--verbose',
            '--permission-mode', 'bypassPermissions',
        ];

        $env = array_merge($_ENV, [
            'HOME' => '/home/ubuntu',
            'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
        ]);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes, null, $env);

        if (!is_resource($process)) {
            $this->sseEmit('error', ['message' => 'Prozess konnte nicht gestartet werden.']);
            return;
        }

        // Write payload and close stdin
        fwrite($pipes[0], $payload . "\n");
        fclose($pipes[0]);

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $buffer = '';

        while (true) {
            $status = proc_get_status($process);

            $chunk = fread($pipes[1], 8192);
            if ($chunk !== false && $chunk !== '') {
                $buffer .= $chunk;
            }

            // Process complete JSONL lines
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line   = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($line === '') continue;

                $obj = json_decode($line, true);
                if (!is_array($obj)) continue;

                $this->handleEvent($obj);

                // Flush after each processed line
                if (ob_get_level() > 0) ob_flush();
                flush();
            }

            if (!$status['running']) break;
            usleep(5000); // 5 ms poll
        }

        // Drain any remaining output after process exits
        while (!feof($pipes[1])) {
            $chunk = fread($pipes[1], 8192);
            if ($chunk === false || $chunk === '') break;
            $buffer .= $chunk;
        }

        // Process remaining lines
        foreach (explode("\n", $buffer) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $obj = json_decode($line, true);
            if (!is_array($obj)) continue;
            $this->handleEvent($obj);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if (ob_get_level() > 0) ob_flush();
        flush();

        $this->sseEmit('done', []);
    }

    // -------------------------------------------------------------------------
    // Event handler
    // -------------------------------------------------------------------------

    private function handleEvent(array $obj): void
    {
        $type = $obj['type'] ?? '';

        switch ($type) {
            case 'assistant':
                $content = $obj['message']['content'] ?? [];
                foreach ($content as $block) {
                    match ($block['type'] ?? '') {
                        'text'     => $this->sseEmit('text',     ['text' => $block['text']]),
                        'thinking' => $this->sseEmit('thinking', ['text' => $block['thinking']]),
                        'tool_use' => $this->sseEmit('tool_use', [
                            'name'  => $block['name'],
                            'input' => $block['input'] ?? [],
                        ]),
                        default => null,
                    };
                }
                break;

            case 'user':
                // Tool results (from tool calls Claude made)
                $content = $obj['message']['content'] ?? [];
                foreach ($content as $block) {
                    if (($block['type'] ?? '') === 'tool_result') {
                        $resultText = is_array($block['content'] ?? null)
                            ? implode("\n", array_map(fn($b) => $b['text'] ?? '', $block['content']))
                            : ($block['content'] ?? '');
                        $this->sseEmit('tool_result', ['text' => $resultText]);
                    }
                }
                break;

            case 'result':
                // Final consolidated result – use as the persisted response
                $this->sseEmit('result', ['text' => $obj['result'] ?? '']);
                break;

            case 'system':
                // Ignore init metadata
                break;
        }
    }

    // -------------------------------------------------------------------------
    // SSE helper
    // -------------------------------------------------------------------------

    private function sseEmit(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    }
}
