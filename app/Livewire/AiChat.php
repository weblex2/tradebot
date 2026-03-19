<?php
namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Session;

#[Layout('layouts.tradebot', ['title' => 'AI Chat'])]
class AiChat extends Component
{
    private const SESSION_KEY = 'aichat.messages';

    public array $messages = [];
    public string $input   = '';

    public function mount(): void
    {
        $this->messages = Session::get(self::SESSION_KEY, []);
    }

    public function sendMessage(string $message = '', array $images = []): void
    {
        $text = trim($message);
        if (empty($text) && empty($images)) return;

        $entry = [
            'role'    => 'user',
            'content' => $text,
            'time'    => now()->format('H:i'),
        ];

        if (!empty($images)) {
            $entry['images'] = $images; // base64 data-URLs
        }

        $this->messages[] = $entry;
        $this->persist();
        $this->dispatch('message-sent');
    }

    public function getResponse(): void
    {
        $last = collect($this->messages)->last(fn($m) => $m['role'] === 'user');
        if (!$last) return;

        $images = $last['images'] ?? [];
        $response = empty($images)
            ? $this->callClaude($last['content'])
            : $this->callClaudeWithImages($last['content'], $images);

        $this->messages[] = [
            'role'    => 'assistant',
            'content' => $response,
            'time'    => now()->format('H:i'),
        ];

        $this->persist();
        $this->dispatch('message-sent');
    }

    public function clearChat(): void
    {
        $this->messages = [];
        Session::forget(self::SESSION_KEY);
    }

    private function persist(): void
    {
        // Keep only the last 50 messages to avoid bloated sessions
        if (count($this->messages) > 50) {
            $this->messages = array_slice($this->messages, -50);
        }
        Session::put(self::SESSION_KEY, $this->messages);
    }

    private function callClaudeWithImages(string $userMessage, array $images): string
    {
        $system = 'Du bist ein Assistent für ein Laravel Crypto Trading Bot Projekt (Tradebot). '
                . 'Antworte präzise und auf Deutsch wenn nicht anders gewünscht.';

        $contentBlocks = [];
        foreach ($images as $dataUrl) {
            if (!preg_match('/^data:(image\/[\w+]+);base64,(.+)$/s', $dataUrl, $m)) continue;
            $contentBlocks[] = [
                'type'   => 'image',
                'source' => ['type' => 'base64', 'media_type' => $m[1], 'data' => $m[2]],
            ];
        }

        if (!empty($userMessage)) {
            $contentBlocks[] = ['type' => 'text', 'text' => $userMessage];
        }

        if (empty($contentBlocks)) {
            return 'Keine gültigen Bilddaten erhalten.';
        }

        $payload = json_encode([
            'type'    => 'user',
            'message' => ['role' => 'user', 'content' => $contentBlocks],
        ]);

        return $this->runStreamJson($payload, $system);
    }

    private function callClaude(string $userMessage): string
    {
        $system = 'Du bist ein Assistent für ein Laravel Crypto Trading Bot Projekt (Tradebot). '
                . 'Antworte präzise und auf Deutsch wenn nicht anders gewünscht.';

        // Build conversation context from last 6 messages
        $history = array_slice($this->messages, -7, 6);
        $contentText = '';
        foreach ($history as $msg) {
            $role = $msg['role'] === 'user' ? 'User' : 'Assistant';
            $contentText .= "{$role}: {$msg['content']}\n";
        }
        $contentText .= "User: {$userMessage}";

        $payload = json_encode([
            'type'    => 'user',
            'message' => ['role' => 'user', 'content' => $contentText],
        ]);

        return $this->runStreamJson($payload, $system);
    }

    private function runStreamJson(string $payload, string $system): string
    {
        try {
            $result = Process::timeout(120)->env([
                'HOME' => '/home/ubuntu',
                'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            ])->input($payload)->run([
                'claude',
                '--print',
                '--system-prompt', $system,
                '--no-session-persistence',
                '--model', 'sonnet',
                '--input-format', 'stream-json',
                '--output-format', 'stream-json',
                '--verbose',
                '--permission-mode', 'bypassPermissions',
            ]);

            if (!$result->successful()) {
                return 'Fehler: Claude konnte nicht erreicht werden. (Exit ' . $result->exitCode() . ')';
            }

            // Parse JSONL — grab result text from the final "result" line
            foreach (array_reverse(explode("\n", trim($result->output()))) as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $obj = json_decode($line, true);
                if (!$obj) continue;
                if (($obj['type'] ?? '') === 'result' && isset($obj['result'])) {
                    return $obj['result'];
                }
            }

            return 'Keine Antwort erhalten.';
        } catch (\Throwable $e) {
            return 'Fehler: ' . $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.ai-chat');
    }
}
