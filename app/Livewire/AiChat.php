<?php
namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Session;

#[Layout('layouts.tradebot', ['title' => 'AI Chat'])]
class AiChat extends Component
{
    /** Laravel session key that holds ALL sessions (id → {name, created_at, messages}) */
    private const SESSIONS_KEY = 'aichat.sessions';

    /** Laravel session key for the currently active session ID */
    private const ACTIVE_KEY = 'aichat.active_session';

    /** Legacy single-session key – migrated automatically on first load */
    private const LEGACY_KEY = 'aichat.messages';

    /** Maximum number of saved sessions to keep */
    private const MAX_SESSIONS = 10;

    /** Maximum messages per session */
    private const MAX_MESSAGES = 50;

    // ── Livewire public state ────────────────────────────────────────────────
    /** Messages of the currently active session */
    public array $messages = [];

    /** Input field value */
    public string $input = '';

    /** Metadata list for the dropdown: [{id, name, created_at}] */
    public array $sessions = [];

    /** Currently active session ID */
    public string $activeSessionId = '';

    // ── Lifecycle ─────────────────────────────────────────────────────────────
    public function mount(): void
    {
        $this->migrateLegacySession();

        $allSessions = Session::get(self::SESSIONS_KEY, []);

        if (empty($allSessions)) {
            $this->createNewSession();
            return;
        }

        $this->activeSessionId = Session::get(self::ACTIVE_KEY, '');

        // Fall back to first session if the stored ID is gone
        if (! $this->activeSessionId || ! isset($allSessions[$this->activeSessionId])) {
            $this->activeSessionId = array_key_first($allSessions);
            Session::put(self::ACTIVE_KEY, $this->activeSessionId);
        }

        $this->messages  = $allSessions[$this->activeSessionId]['messages'] ?? [];
        $this->sessions  = $this->buildMetadata($allSessions);
    }

    // ── Persona ───────────────────────────────────────────────────────────────
    private function loadPersona(): string
    {
        $path = resource_path('ai-chat-persona.md');
        return file_exists($path) ? file_get_contents($path) : '';
    }

    private function buildSystemPrompt(): string
    {
        $persona = $this->loadPersona();
        $base    = 'Du bist ein Assistent für ein Laravel Crypto Trading Bot Projekt (Tradebot). '
                 . 'Antworte präzise und auf Deutsch wenn nicht anders gewünscht.';

        return $persona ?: $base;
    }

    private function fetchGreeting(): string
    {
        $system  = $this->buildSystemPrompt();
        $payload = json_encode([
            'type'    => 'user',
            'message' => [
                'role'    => 'user',
                'content' => 'Begrüße den Nutzer jetzt. Halte es kurz, individuell und freundlich.',
            ],
        ]);

        return $this->runStreamJson($payload, $system);
    }

    // ── Session management ────────────────────────────────────────────────────
    public function loadSession(string $id): void
    {
        $allSessions = Session::get(self::SESSIONS_KEY, []);
        if (! isset($allSessions[$id])) return;

        $this->activeSessionId = $id;
        $this->messages        = $allSessions[$id]['messages'] ?? [];
        Session::put(self::ACTIVE_KEY, $id);

        $this->dispatch('message-sent');
    }

    public function createNewSession(): void
    {
        $id   = 'session_' . uniqid();
        $name = 'Session ' . now()->format('d.m.Y H:i');

        $newEntry = [
            'id'         => $id,
            'name'       => $name,
            'created_at' => now()->format('d.m.Y H:i'),
            'messages'   => [],
        ];

        $allSessions      = Session::get(self::SESSIONS_KEY, []);
        $allSessions[$id] = $newEntry;

        // Prune oldest sessions if limit exceeded
        if (count($allSessions) > self::MAX_SESSIONS) {
            reset($allSessions);
            $oldestId = array_key_first($allSessions);
            unset($allSessions[$oldestId]);
        }

        Session::put(self::SESSIONS_KEY, $allSessions);
        Session::put(self::ACTIVE_KEY, $id);

        $this->activeSessionId = $id;
        $this->messages        = [];
        $this->sessions        = $this->buildMetadata($allSessions);

        // Auto-Begrüßung von Tradebot beim Start jeder neuen Session
        $greeting = $this->fetchGreeting();
        $this->messages[] = [
            'role'    => 'assistant',
            'content' => $greeting,
            'time'    => now()->format('H:i'),
        ];
        $this->persist();
        $this->dispatch('message-sent');
    }

    public function deleteSession(string $id): void
    {
        $allSessions = Session::get(self::SESSIONS_KEY, []);
        unset($allSessions[$id]);
        Session::put(self::SESSIONS_KEY, $allSessions);

        $this->sessions = $this->buildMetadata($allSessions);

        if ($this->activeSessionId === $id) {
            if (! empty($allSessions)) {
                $this->loadSession(array_key_first($allSessions));
            } else {
                $this->createNewSession();
            }
        }
    }

    public function clearChat(): void
    {
        $this->messages = [];
        $allSessions    = Session::get(self::SESSIONS_KEY, []);

        if (isset($allSessions[$this->activeSessionId])) {
            $allSessions[$this->activeSessionId]['messages'] = [];
            Session::put(self::SESSIONS_KEY, $allSessions);
        }
    }

    // ── Chat ─────────────────────────────────────────────────────────────────
    public function sendMessage(string $message = '', array $images = []): void
    {
        $text = trim($message);
        if (empty($text) && empty($images)) return;

        $entry = [
            'role'    => 'user',
            'content' => $text,
            'time'    => now()->format('H:i'),
        ];

        if (! empty($images)) {
            $entry['images'] = $images; // base64 data-URLs
        }

        $this->messages[] = $entry;
        $this->persist();
        $this->dispatch('message-sent');
    }

    public function getResponse(): void
    {
        $last = collect($this->messages)->last(fn($m) => $m['role'] === 'user');
        if (! $last) return;

        $images   = $last['images'] ?? [];
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

    public function appendResponse(string $content): void
    {
        $this->messages[] = [
            'role'    => 'assistant',
            'content' => $content,
            'time'    => now()->format('H:i'),
        ];
        $this->persist();
        $this->dispatch('message-sent');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private function persist(): void
    {
        if (count($this->messages) > self::MAX_MESSAGES) {
            $this->messages = array_slice($this->messages, -self::MAX_MESSAGES);
        }

        $allSessions = Session::get(self::SESSIONS_KEY, []);

        if (isset($allSessions[$this->activeSessionId])) {
            $allSessions[$this->activeSessionId]['messages'] = $this->messages;

            // Auto-rename session after the first user message is saved
            $currentName = $allSessions[$this->activeSessionId]['name'] ?? '';
            if (str_starts_with($currentName, 'Session ')) {
                $firstUserMsg = collect($this->messages)->firstWhere('role', 'user');
                if ($firstUserMsg && ! empty($firstUserMsg['content'])) {
                    $title = mb_substr(trim($firstUserMsg['content']), 0, 45);
                    if (mb_strlen($firstUserMsg['content']) > 45) {
                        $title .= '…';
                    }
                    $allSessions[$this->activeSessionId]['name'] = $title;
                }
            }

            Session::put(self::SESSIONS_KEY, $allSessions);
            $this->sessions = $this->buildMetadata($allSessions);
        }
    }

    /** Build the lightweight metadata array shown in the dropdown (last MAX_SESSIONS only) */
    private function buildMetadata(array $allSessions): array
    {
        $meta = array_values(array_map(fn($s) => [
            'id'         => $s['id'],
            'name'       => $s['name'],
            'created_at' => $s['created_at'],
        ], $allSessions));

        // Show only the most recent MAX_SESSIONS sessions
        return array_slice($meta, -self::MAX_SESSIONS);
    }

    /** One-time migration from the old single-session storage format */
    private function migrateLegacySession(): void
    {
        $old = Session::get(self::LEGACY_KEY, []);
        if (empty($old)) return;

        $id         = 'session_' . uniqid();
        $allSessions = Session::get(self::SESSIONS_KEY, []);

        $allSessions[$id] = [
            'id'         => $id,
            'name'       => 'Bisherige Session',
            'created_at' => now()->format('d.m.Y H:i'),
            'messages'   => $old,
        ];

        Session::put(self::SESSIONS_KEY, $allSessions);
        Session::put(self::ACTIVE_KEY, $id);
        Session::forget(self::LEGACY_KEY);
    }

    // ── Claude calls (unchanged) ──────────────────────────────────────────────
    private function callClaudeWithImages(string $userMessage, array $images): string
    {
        $system = $this->buildSystemPrompt();

        $contentBlocks = [];
        foreach ($images as $dataUrl) {
            if (! preg_match('/^data:(image\/[\w+]+);base64,(.+)$/s', $dataUrl, $m)) continue;
            $contentBlocks[] = [
                'type'   => 'image',
                'source' => ['type' => 'base64', 'media_type' => $m[1], 'data' => $m[2]],
            ];
        }

        if (! empty($userMessage)) {
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
        $system = $this->buildSystemPrompt();

        // Build conversation context from last 6 messages
        $history     = array_slice($this->messages, -7, 6);
        $contentText = '';
        foreach ($history as $msg) {
            $role         = $msg['role'] === 'user' ? 'User' : 'Assistant';
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

            if (! $result->successful()) {
                return 'Fehler: Claude konnte nicht erreicht werden. (Exit ' . $result->exitCode() . ')';
            }

            // Parse JSONL — grab result text from the final "result" line
            foreach (array_reverse(explode("\n", trim($result->output()))) as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $obj = json_decode($line, true);
                if (! $obj) continue;
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
