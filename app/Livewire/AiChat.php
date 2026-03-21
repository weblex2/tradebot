<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Session;
use App\Models\ChatSession;
use App\Models\ChatMessage;

#[Layout('layouts.tradebot', ['title' => 'AI Chat'])]
class AiChat extends Component
{
    /** PHP Session key to remember which session is active on THIS device */
    private const ACTIVE_KEY = 'aichat.active_session';

    /** Maximum number of saved sessions to keep in DB */
    private const MAX_SESSIONS = 10;

    /** Maximum messages kept per session */
    private const MAX_MESSAGES = 50;

    // ── Livewire public state ────────────────────────────────────────────────
    /** Messages of the currently active session */
    public array $messages = [];

    /** Input field value */
    public string $input = '';

    /** Metadata list for the dropdown: [{id, name, created_at}] */
    public array $sessions = [];

    /** Currently active session key */
    public string $activeSessionId = '';

    // ── Lifecycle ─────────────────────────────────────────────────────────────
    public function mount(): void
    {
        $this->migrateLegacySession();

        $allSessions = ChatSession::orderBy('updated_at', 'desc')
            ->limit(self::MAX_SESSIONS)
            ->get();

        if ($allSessions->isEmpty()) {
            $this->createNewSession();
            return;
        }

        // Restore last-used session for this device from PHP session
        $storedKey = Session::get(self::ACTIVE_KEY, '');
        $active    = $storedKey
            ? $allSessions->firstWhere('session_key', $storedKey)
            : null;

        // Fall back to most recently updated session
        if (! $active) {
            $active = $allSessions->first();
        }

        $this->activeSessionId = $active->session_key;
        Session::put(self::ACTIVE_KEY, $this->activeSessionId);

        $this->messages = $this->loadMessagesFromDb($active);
        $this->sessions = $this->buildMetadata($allSessions);
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
        $session = ChatSession::where('session_key', $id)->first();
        if (! $session) return;

        $this->activeSessionId = $id;
        Session::put(self::ACTIVE_KEY, $id);
        $this->messages = $this->loadMessagesFromDb($session);

        $this->dispatch('message-sent');
    }

    public function createNewSession(): void
    {
        $id   = 'session_' . uniqid();
        $name = 'Session ' . now()->format('d.m.Y H:i');

        $session = ChatSession::create([
            'session_key' => $id,
            'name'        => $name,
        ]);

        // Prune oldest session if limit exceeded
        $count = ChatSession::count();
        if ($count > self::MAX_SESSIONS) {
            $oldest = ChatSession::orderBy('updated_at')->first();
            $oldest?->delete(); // cascades to messages
        }

        $this->activeSessionId = $id;
        Session::put(self::ACTIVE_KEY, $id);
        $this->messages = [];
        $this->refreshSessionsList();

        // Auto-greeting from Tradebot on every new session
        $greeting = $this->fetchGreeting();

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role'            => 'assistant',
            'content'         => $greeting,
        ]);
        $session->touch();

        $this->messages[] = [
            'role'    => 'assistant',
            'content' => $greeting,
            'time'    => now()->format('H:i'),
        ];

        $this->refreshSessionsList();
        $this->dispatch('message-sent');
    }

    public function deleteSession(string $id): void
    {
        $session = ChatSession::where('session_key', $id)->first();
        $session?->delete(); // cascades to messages

        $this->refreshSessionsList();

        if ($this->activeSessionId === $id) {
            $next = ChatSession::orderBy('updated_at', 'desc')->first();
            if ($next) {
                $this->loadSession($next->session_key);
            } else {
                $this->createNewSession();
            }
        }
    }

    public function clearChat(): void
    {
        $session = $this->getActiveSession();
        $session?->messages()->delete();
        $this->messages = [];
    }

    // ── Chat ──────────────────────────────────────────────────────────────────
    public function sendMessage(string $message = '', array $images = []): void
    {
        $text = trim($message);
        if (empty($text) && empty($images)) return;

        $session = $this->getActiveSession();
        if (! $session) return;

        $entry = [
            'role'    => 'user',
            'content' => $text,
            'time'    => now()->format('H:i'),
        ];

        if (! empty($images)) {
            $entry['images'] = $images;
        }

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role'            => 'user',
            'content'         => $text,
            'images'          => ! empty($images) ? $images : null,
        ]);
        $session->touch();

        $this->messages[] = $entry;
        $this->enforceMessageLimit($session);
        $this->autoRename($session);
        $this->dispatch('message-sent');
    }

    public function getResponse(): void
    {
        $last = collect($this->messages)->last(fn ($m) => $m['role'] === 'user');
        if (! $last) return;

        $images   = $last['images'] ?? [];
        $response = empty($images)
            ? $this->callClaude($last['content'])
            : $this->callClaudeWithImages($last['content'], $images);

        $this->appendResponse($response);
    }

    public function appendResponse(string $content): void
    {
        $session = $this->getActiveSession();

        if ($session) {
            ChatMessage::create([
                'chat_session_id' => $session->id,
                'role'            => 'assistant',
                'content'         => $content,
            ]);
            $session->touch();
        }

        $this->messages[] = [
            'role'    => 'assistant',
            'content' => $content,
            'time'    => now()->format('H:i'),
        ];

        $this->dispatch('message-sent');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Load messages from DB and convert to the Livewire array format */
    private function loadMessagesFromDb(ChatSession $session): array
    {
        return $session->messages()
            ->orderBy('id')
            ->get()
            ->map(function ($m) {
                $entry = [
                    'role'    => $m->role,
                    'content' => $m->content,
                    'time'    => $m->created_at->format('H:i'),
                ];
                if (! empty($m->images)) {
                    $entry['images'] = $m->images;
                }
                return $entry;
            })
            ->values()
            ->toArray();
    }

    private function getActiveSession(): ?ChatSession
    {
        return ChatSession::where('session_key', $this->activeSessionId)->first();
    }

    private function buildMetadata($sessions): array
    {
        return $sessions->map(fn ($s) => [
            'id'         => $s->session_key,
            'name'       => $s->name,
            'created_at' => $s->created_at->format('d.m.Y H:i'),
        ])->values()->toArray();
    }

    private function refreshSessionsList(): void
    {
        $allSessions    = ChatSession::orderBy('updated_at', 'desc')->limit(self::MAX_SESSIONS)->get();
        $this->sessions = $this->buildMetadata($allSessions);
    }

    /** Auto-rename session after first user message */
    private function autoRename(ChatSession $session): void
    {
        if (! str_starts_with($session->name, 'Session ')) return;

        $firstUser = collect($this->messages)->firstWhere('role', 'user');
        if (! $firstUser || empty($firstUser['content'])) return;

        $title = mb_substr(trim($firstUser['content']), 0, 45);
        if (mb_strlen($firstUser['content']) > 45) {
            $title .= '…';
        }

        $session->update(['name' => $title]);
        $this->refreshSessionsList();
    }

    /** Trim oldest messages from DB if the session grows beyond MAX_MESSAGES */
    private function enforceMessageLimit(ChatSession $session): void
    {
        $count = $session->messages()->count();
        if ($count <= self::MAX_MESSAGES) return;

        $excess = $count - self::MAX_MESSAGES;
        $ids    = $session->messages()->orderBy('id')->limit($excess)->pluck('id');
        ChatMessage::whereIn('id', $ids)->delete();

        // Also trim the in-memory array
        $this->messages = array_slice($this->messages, -self::MAX_MESSAGES);
    }

    // ── Legacy migration: PHP Session → DB ───────────────────────────────────
    private function migrateLegacySession(): void
    {
        // Migrate old multi-session format (stored in PHP session) to DB
        $oldSessions = Session::get('aichat.sessions', []);

        foreach ($oldSessions as $key => $data) {
            if (ChatSession::where('session_key', $key)->exists()) continue;

            $session = ChatSession::create([
                'session_key' => $key,
                'name'        => $data['name'] ?? 'Importierte Session',
            ]);

            foreach ($data['messages'] ?? [] as $msg) {
                ChatMessage::create([
                    'chat_session_id' => $session->id,
                    'role'            => $msg['role'] ?? 'user',
                    'content'         => $msg['content'] ?? '',
                    'images'          => ! empty($msg['images']) ? $msg['images'] : null,
                ]);
            }
        }

        if (! empty($oldSessions)) {
            Session::forget('aichat.sessions');
            Session::forget('aichat.messages'); // legacy single-session format
        }
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
