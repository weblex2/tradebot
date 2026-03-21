# Session 2026-03-21 – AI Chat Streaming

## Ziel
Der AI Chat soll Claudes Ausgaben live streamen, anstatt nach 30–60s alles auf einmal zu zeigen.

## Problem (vorher)
`Process::run()` blockierte PHP bis Claude vollständig fertig war. Der User sah erst nach dem kompletten Prozessende etwas.

## Lösung: SSE-Streaming

### Neuer Controller: `AiChatStreamController`
- Route: `POST /tradebot/chat/stream`
- Nutzt `proc_open` statt `Process::run()` → liest Claudes stdout zeilenweise
- Gibt `text/event-stream` (SSE) zurück
- Parst JSONL-Events: `text`, `thinking`, `tool_use`, `tool_result`, `result`, `error`, `done`
- **Fix:** Return-Type `StreamedResponse` (nicht `Illuminate\Http\Response` → war 500er)
- **Fix:** `while (ob_get_level() > 0) ob_end_clean()` vor dem Streaming

### Frontend: `ai-chat.blade.php`
- Nutzt `fetch()` + `ReadableStream` (kein EventSource, da POST nötig)
- Stream wird via `$wire.sendMessage().then(() => this.startStream())` in `send()` gestartet
- Live-Streaming-Bubble: Tipp-Punkte, Text mit Cursor, Tool-Call-Karten, Thinking-Panel

### Livewire: `AiChat.php`
- Neue Methode `appendResponse(string $content)`: speichert fertige Antwort nach Stream-Ende
- Multi-Session-Verwaltung (bis zu 10 Sessions, Dropdown, Auto-Rename)
- Persona-System via `resources/ai-chat-persona.md`
- Auto-Begrüßung beim Session-Start

## Commit
`1ebda88` – Feature: AI Chat Streaming + Multi-Session + Discussions

## Gotchas

| Problem | Ursache | Fix |
|---------|---------|-----|
| 500er beim Stream-Endpoint | `response()->stream()` gibt `StreamedResponse` zurück, nicht `Illuminate\Http\Response` | Return-Type auf `Symfony\Component\HttpFoundation\StreamedResponse` |
| Alpine-Fehler "message channel closed" | `startStream()` (async) aus Livewire-Event-Handler → Channel schließt vor Fertigstellung | `startStream()` in `.then()` nach `$wire.sendMessage()` |
| SSE wird nicht geflusht | PHP output buffering aktiv | `ob_end_clean()` am Anfang von `runClaudeStream()` |
| Alpine `x-on:` akzeptiert kein `if`-Block | Expression-Parser, kein Statement-Parser | Logik in `x-data`-Methode auslagern |
