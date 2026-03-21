# Tradebot – AI-Powered Crypto Trading System

## ⚠ CRITICAL RULES

- **NEVER commit automatically.** Only create a git commit when the user explicitly asks for it (e.g. "mach einen commit", "commit das bitte"). No exceptions.
- **NEVER push automatically.** Only push to git when the user explicitly asks for it. No exceptions.
- **After every commit:** Summarize the session content into a `.md` file under `docs/sessions/` and extend the trading skill knowledge base accordingly.
- **ALL 26 tradeable assets matter — not just BTC/ETH/SOL/XRP.** Whenever checking, verifying, or building anything related to assets, always consider the full list: BTC, ETH, SOL, XRP, DOGE, SHIB, APE, ADA, AVAX, LINK, DOT, LTC, UNI, ATOM, FIL, ALGO, MANA, CRV, GRT, BAT, CHZ, MINA, SNX, XLM, XTZ, 1INCH. Never narrow scope to just the 4 default allowed_assets without being explicitly told to.

## Project Location & Stack

- **Root:** `/var/www/trading` (served via nginx → `public/`)
- **PHP:** 8.1 | **Laravel:** 10 | **MySQL:** 8 (DB: `trading`)
- **Queue:** database driver (`jobs` table) | **Scheduler:** Laravel Scheduler (Kernel.php)
- **Frontend:** Blade + Livewire 3 + Tailwind CSS (Glassmorphism theme)
- **Exchange:** Coinbase Advanced Trade REST API (Ed25519 JWT)
- **AI:** Anthropic Claude API (`claude-sonnet-4-20250514`)
- **Notifications:** ntfy (self-hosted push notifications)

## Architecture

```
sources (DB)
    └── ScraperJob → ScraperService → RSS parse + dedup (SHA-256)
            └── articles (DB) → ClaudeAnalysisService::scoreArticle()
                        └── sentiment_signals (DB)
                                    └── trade:analyze command (every 30m)
                                                └── ClaudeAnalysisService::runAnalysis()
                                                            └── analyses (DB)
                                                                        └── trade_decisions (DB)
                                                                                    └── TradeExecutor
                                                                                            ├── paper → execution record
                                                                                            └── live  → CoinbaseService → executions (DB)
                                                                                                            └── n8n webhook notification
```

## Key Files

| File | Purpose |
|------|---------|
| `config/trading.php` | Mode flags, risk limits, allowed assets, n8n URL |
| `app/Services/ClaudeAnalysisService.php` | All Claude API calls (scoreArticle + runAnalysis) |
| `app/Services/CoinbaseService.php` | All Coinbase API calls (Ed25519 JWT auth) |
| `app/Services/TradeExecutor.php` | Risk checks + paper/live execution dispatch |
| `app/Services/ScraperService.php` | RSS fetch, parse, SHA-256 dedup, signal extraction |
| `app/Console/Commands/ScrapeCommand.php` | `scraper:run [--source=ID]` |
| `app/Console/Commands/TradeAnalyzeCommand.php` | `trade:analyze [--live --confirm-live]` |
| `app/Console/Kernel.php` | Scheduler: scraper every min, analyze every 30m, prune weekly |
| `app/Jobs/ScraperJob.php` | Queued scrape per source, 3 tries + backoff |
| `app/Jobs/GetOrderStatusJob.php` | Polls Coinbase for live order fill status |
| `app/Jobs/PruneOldArticlesJob.php` | Weekly: delete articles older than 90 days |
| `app/Livewire/Dashboard.php` | Main dashboard (stats, signals, decisions) |
| `app/Livewire/Sources.php` | CRUD for news sources |
| `app/Livewire/TradeHistory.php` | Execution history with filters |
| `app/Livewire/AnalysisViewer.php` | Claude reasoning + decisions per analysis |

## Coding Rules

- All Claude API calls → `ClaudeAnalysisService` ONLY
- All Coinbase calls → `CoinbaseService` ONLY
- **Never** live-trade unless `TRADING_MODE=live` AND `PAPER_TRADING=false` AND `--confirm-live`
- Always link `TradeDecision` to `analysis_id` – no orphan decisions
- Amounts in DB = **cents** (integer). Convert to USD only at display time.
- Sentiment/signal scores: **float -1.0 to 1.0**
- Confidence: **integer 0–100**

## Commands

```bash
# Dev server
php artisan serve
php artisan queue:work
php artisan schedule:work

# Scraping
php artisan scraper:run                          # All active sources (due)
php artisan scraper:run --source=12              # Single source

# Analysis & Trading
php artisan trade:analyze                        # Paper mode
php artisan trade:analyze --live --confirm-live  # Live trading

# Database
php artisan migrate
php artisan migrate:fresh --seed                 # Reset + 5 demo sources
php artisan db:seed --class=SourceSeeder

# Queue
php artisan queue:work --tries=3
```

## Two-Flag Live Safety

```php
// BOTH env vars + BOTH CLI flags required:
config('trading.mode') === 'live'       // TRADING_MODE=live
config('trading.paper_trading') === false  // PAPER_TRADING=false
$liveConfirmed === true                 // --live --confirm-live passed
```

## Pre-Execution Checks (TradeExecutor)

1. Decision not expired (`expires_at`)
2. Confidence ≥ `MIN_CONFIDENCE` (default 60)
3. `amount_usd` ≤ `MAX_TRADE_USD` * 100 cents (default $500)
4. Asset in `ALLOWED_ASSETS` (BTC, ETH, SOL, XRP)
5. BUY only: cash remaining ≥ `MIN_RESERVE_USD` * 100 cents

Failed check → `executions.status = 'failed'`, no retry.

## Notifications (ntfy)

- **When:** After every execution (paper + live), via `TradeExecutor::notifyNtfy()`
- **Config:** `NTFY_URL`, `NTFY_TOPIC`, `NTFY_TOKEN` in `.env`
- n8n wurde entfernt – ntfy übernimmt alle Push-Notifications direkt

## Frontend Routes

| Route | Name | Component |
|-------|------|-----------|
| `/tradebot` | `tradebot.dashboard` | `Dashboard` Livewire |
| `/tradebot/sources` | `tradebot.sources` | `Sources` Livewire |
| `/tradebot/trades` | `tradebot.trades` | `TradeHistory` Livewire |
| `/tradebot/analysis` | `tradebot.analysis` | `AnalysisViewer` Livewire |

Layout: `resources/views/layouts/tradebot.blade.php` (dark Glassmorphism)

## Glassmorphism Design

- Dark bg: `#0a0e1a` with radial gradient overlays
- Glass cards: `bg-white/[0.06] backdrop-blur-md border border-white/[0.12] rounded-2xl`
- Neon accents: green `#00ff87` (profit/buy), red `#ff3d71` (loss/sell), blue `#00b4d8` (info)
- CSS classes: `.glass-card`, `.glass-card-hover`, `.neon-text-green/red/blue`, `.badge-buy/sell/hold`
- Font: Inter (Google Fonts)

## Gotchas

- **Ed25519 JWT:** `\n` in `.env` secret must be normalized: `str_replace('\n', "\n", $secret)`
- **Claude JSON:** Always strip markdown fences before `json_decode()` (see `parseJson()` in service)
- **Cents math:** `(int) round($dollars * 100)` – never let floats touch the DB
- **content_hash:** `Article::firstOrCreate(['content_hash' => $hash])` – catches dupes gracefully
- **Hold decisions:** Claude sometimes sets `amount_usd > 0` for holds; TradeExecutor zeroes this
- **Queue:** `QUEUE_CONNECTION=database` – run `php artisan queue:work` separately from web server
