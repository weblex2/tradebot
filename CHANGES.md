# Änderungen – 2026-03-19

## 1. Gemini Fallback – Code-Qualität

Der bestehende Gemini-Fallback wurde bereinigt:

- `exec('whoami')` zur Home-Verzeichnis-Erkennung entfernt → `$_SERVER['HOME']` mit Fallback
- Timeout von 300 s auf 90 s reduziert (Claude: 120 s → zusammen max. 210 s pro Zyklus)
- `GeminiAnalysisService` wird jetzt per Konstruktor-Injection übergeben statt `new GeminiAnalysisService()` inline
- Prompt-Truncation auf 10 000 Zeichen entfernt – das Abschneiden mitten im JSON erzeugte invalide Eingaben; bei `Process::run([...])` (Array-Form) gibt es kein Shell-Limit das eine Kürzung rechtfertigt

## 2. Konfigurierbares AI-Modell (Settings-Page)

Primary- und Fallback-Modell können jetzt zur Laufzeit über die UI gewechselt werden, ohne `.env` anzufassen.

**Neue Dateien:**
- `app/Livewire/Settings.php` – Livewire-Komponente mit Validation
- `resources/views/livewire/settings.blade.php` – Settings-Page im Glassmorphism-Stil

**Geänderte Dateien:**
- `app/Services/TradingSettings` – `primaryModel()` / `setPrimaryModel()` / `fallbackModel()` / `setFallbackModel()` (DB-gespeichert, gecacht)
- `app/Services/ClaudeAnalysisService` – `callModel()` liest Primary/Fallback aus Settings; neues `callByName()` dispatcht per `match` auf Claude oder Gemini
- `routes/web.php` – Route `GET /tradebot/settings` (`tradebot.settings`)
- Sidebar – Settings als echtem Nav-Link, alter Footer-Link heißt jetzt "Profile"

**Verfügbare Modelle:** `claude` (Sonnet via CLI) · `gemini` (CLI) · `none` (kein Fallback)

## 3. Dashboard – Asset Sentiment (24h)

- Hardcodierte Liste `['BTC', 'ETH', 'SOL', 'XRP']` ersetzt durch dynamische Portfolio-Assets aus `$portfolio['assets']`
- EUR wird herausgefiltert
- Fallback-Text wenn keine Portfolio-Daten verfügbar

## 4. Dashboard – Scrollbare Cards

- "Asset Sentiment (24h)" und "Recent Decisions" erhalten `max-height: 420px` und `overflow-y-auto`
- Header-Zeilen sind via `shrink-0` fixiert und scrollen nicht mit
- Bestehende globale `::-webkit-scrollbar`-Styles aus `app.css` greifen automatisch

## 5. Bugfix – n8n Notifications

**Problem:** n8n erhielt bei Live-Trades nur den initialen `pending`-Status, nie das finale `filled`/`failed`. Ursache: `GetOrderStatusJob` updated den Execution-Status, rief aber `notifyN8n` nicht auf.

**Fixes:**
- `TradeExecutor::notifyN8n()` → `public`
- Fehlgeschlagene Pre-Execution-Checks senden jetzt ebenfalls eine Notification
- `GetOrderStatusJob` ruft nach dem Status-Update `executor->notifyN8n($execution->fresh())` auf – `fresh()` stellt sicher dass `price_at_execution` und `filled_size` im Payload enthalten sind
