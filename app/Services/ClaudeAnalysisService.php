<?php
namespace App\Services;

use App\Models\Article;
use App\Services\BotLogger;
use App\Services\TradingSettings;
use App\Services\GeminiAnalysisService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ClaudeAnalysisService
{
    private const BATCH_SIZE = 10;

    public function __construct(private GeminiAnalysisService $gemini) {}

    // Keyword filter – articles without any of these get skipped without an API call
    private const CRYPTO_KEYWORDS = [
        'bitcoin', 'btc', 'ethereum', 'eth', 'solana', 'sol', 'xrp', 'ripple',
        'crypto', 'blockchain', 'defi', 'stablecoin', 'altcoin', 'coinbase',
        'binance', 'exchange', 'wallet', 'token', 'nft', 'web3', 'mining',
        'halving', 'staking', 'yield', 'ledger', 'satoshi',
        'dogecoin', 'doge', 'shib', 'shiba', 'cardano', 'ada', 'avalanche',
        'avax', 'chainlink', 'link', 'polkadot', 'dot', 'litecoin', 'ltc',
        'uniswap', 'uni', 'cosmos', 'atom', 'filecoin', 'fil', 'algorand',
        'algo', 'decentraland', 'mana', 'curve', 'crv', 'graph', 'grt',
        'basic attention', 'bat', 'chiliz', 'chz', 'mina', 'synthetix', 'snx',
        'stellar', 'xlm', 'tezos', 'xtz', '1inch', 'ape', 'apecoin',
    ];

    /**
     * Score a batch of articles in a single Claude call.
     * Returns array keyed by article ID: ['sentiment_score' => float, 'signals' => array]
     */
    public function scoreArticles(Collection $articles): array
    {
        // Optimisation 2: keyword filter – skip irrelevant articles immediately
        $relevant = $articles->filter(fn($a) => $this->isRelevant($a));
        $skipped  = $articles->count() - $relevant->count();

        if ($relevant->isEmpty()) {
            return [];
        }

        $results = [];

        // Optimisation 1: batch into groups of BATCH_SIZE
        foreach ($relevant->chunk(self::BATCH_SIZE) as $batch) {
            $batchResults = $this->scoreBatch($batch);
            $results     += $batchResults;
        }

        $signalCount = array_sum(array_map(fn($r) => count($r['signals'] ?? []), $results));
        BotLogger::info('claude', "Scored {$relevant->count()} articles ({$skipped} skipped), {$signalCount} signals", [
            'scored'   => $relevant->count(),
            'skipped'  => $skipped,
            'signals'  => $signalCount,
        ]);

        return $results;
    }

    /**
     * Score a single article (convenience wrapper around scoreArticles).
     */
    public function scoreArticle(Article $article): ?array
    {
        $results = $this->scoreArticles(collect([$article]));
        return $results[$article->id] ?? null;
    }

    /**
     * Run a full analysis cycle over recent signals and portfolio state.
     */
    public function runAnalysis(array $signals, array $portfolio, array $volumeData = [], array $technicalData = []): ?array
    {
        $allowedAssets = implode(', ', config('trading.allowed_assets', ['BTC', 'ETH', 'SOL', 'XRP']));

        $cashEur     = (float) ($portfolio['cash_eur'] ?? 0);
        $maxTradeEur = (float) TradingSettings::maxTradeUsd();
        $minReserve  = TradingSettings::minReserve();
        $spendable   = max(0, round($cashEur - $minReserve, 2));

        // Build a map of held assets with their EUR value for the prompt
        $positions   = $portfolio['positions'] ?? [];
        $heldAssets  = collect($positions)
            ->filter(fn($p) => ($p['balance'] ?? 0) > 0)
            ->map(fn($p) => $p['currency'] . ' (€' . number_format($p['value_eur'] ?? 0, 2) . ')')
            ->values()
            ->implode(', ');
        $sellableNote = $heldAssets
            ? "Sellable assets (you currently hold these): {$heldAssets}."
            : "You hold NO crypto assets – do NOT generate any sell decisions.";

        $system = <<<SYSTEM
You are an expert crypto portfolio manager. Based on the provided signals and portfolio state, generate trading decisions.
Return ONLY valid JSON (no markdown). Allowed assets: {$allowedAssets}.

Response shape:
{"reasoning": "...", "decisions": [{"asset_symbol":"BTC","action":"buy","confidence":72,"amount_usd":250.00,"stop_loss_pct":5,"take_profit_pct":null,"rationale":"..."}]}

Rules:
- action: buy | sell | hold
- confidence: integer 0-100
- amount_usd: EUR amount (float) to spend, or 0 for hold
- stop_loss_pct: float or null
- take_profit_pct: float or null
- Capital preservation first. When in doubt, hold.
- Use volume data as confirmation signal: high volume (ratio >= 1.3) strengthens a move, low volume weakens it. Do NOT buy solely because of high volume.
- Use technical indicators to confirm or counter sentiment signals:
  RSI > 70 = overbought (weakens buy), RSI < 30 = oversold (strengthens buy / weakens sell).
  MACD histogram positive and rising = bullish momentum. Negative and falling = bearish.
  Price above BB upper = stretched, caution on buys. Price below BB lower = potential reversal.
  Death cross (SMA20 < SMA50) = bearish trend, prefer hold/sell. Golden cross = bullish trend.
  Price near support with oversold RSI = high-conviction buy setup.
- Only recommend trades with confidence >= 60
- Maximum one decision per asset
- For hold decisions, set amount_usd to 0
- Available cash for new buys: €{$cashEur} (keep at least €{$minReserve} as reserve → max spendable: €{$spendable})
- Never suggest a buy with amount_usd > €{$maxTradeEur} or > €{$spendable}
- {$sellableNote}
- NEVER suggest sell for an asset not listed as sellable above
SYSTEM;

        // Build volume context string
        $volumeLines = [];
        foreach ($volumeData as $symbol => $v) {
            $ratio   = $v['volume_ratio'];
            $vol24h  = number_format($v['volume_24h_usd'] / 1_000_000, 1);
            $avg7d   = number_format($v['volume_7d_avg_usd'] / 1_000_000, 1);
            $label   = $ratio >= 1.5 ? '🔥 significantly above average'
                     : ($ratio >= 1.2 ? '↑ above average'
                     : ($ratio <= 0.7 ? '↓ below average' : 'normal'));
            $volumeLines[] = "{$symbol}: \${$vol24h}M today vs \${$avg7d}M 7d-avg (ratio {$ratio} – {$label})";
        }
        $volumeSection = $volumeLines
            ? "\n\n## Trading Volume (24h vs 7-day average)\n" . implode("\n", $volumeLines)
            : '';

        $taSection = !empty($technicalData)
            ? "\n\n## Technical Analysis\n" . implode("\n\n", $technicalData)
            : '';

        $user = "## Current Portfolio\n" . json_encode($portfolio, JSON_PRETTY_PRINT)
              . "\n\n## Recent Signals (last 6h)\n" . json_encode($signals, JSON_PRETTY_PRINT)
              . $volumeSection
              . $taSection;

        $data = $this->callModel($system, $user, 'trade analysis');
        if ($data === null) {
            return null;
        }

        if (!isset($data['reasoning']) || !isset($data['decisions']) || !is_array($data['decisions'])) {
            BotLogger::warning('claude', 'Analysis response missing required keys');
            return null;
        }

        foreach ($data['decisions'] as &$decision) {
            if (($decision['action'] ?? '') === 'hold') {
                $decision['amount_usd'] = 0;
            }
        }

        return $data;
    }

    // -------------------------------------------------------------------------

    private function scoreBatch(Collection $batch): array
    {
        $system = <<<SYSTEM
You are a crypto market sentiment analyst. Score each article and return ONLY a valid JSON array – no markdown, no explanation.

Response shape (one object per article, same order as input):
[{"id":1,"sentiment_score":0.0,"signals":[{"asset_symbol":"BTC","signal_score":0.7,"signal_type":"regulatory"}],"relevance":0.9}]

Rules:
- sentiment_score: float -1.0 to 1.0
- signal_score: float -1.0 to 1.0 per asset mentioned
- signal_type: regulatory | market_move | adoption | technical | macro | other
- relevance: float 0.0 to 1.0
- Only include assets from this list: BTC, ETH, SOL, XRP, DOGE, SHIB, APE, ADA, AVAX, LINK, DOT, LTC, UNI, ATOM, FIL, ALGO, MANA, CRV, GRT, BAT, CHZ, MINA, SNX, XLM, XTZ, 1INCH
- If no relevant signals, use "signals": []
SYSTEM;

        // Optimisation 3: only title + first 500 chars of content
        $items = $batch->map(fn($a) => [
            'id'      => $a->id,
            'title'   => $a->title,
            'content' => mb_substr($a->content, 0, 500),
        ])->values()->toArray();

        $user = json_encode($items, JSON_UNESCAPED_UNICODE);

        $task = 'score ' . $batch->count() . ' articles';
        $data = $this->callModel($system, $user, $task);
        if (!is_array($data)) {
            BotLogger::warning('claude', 'Scoring batch failed — no results for ' . $batch->count() . ' articles', ['ids' => $batch->pluck('id')->toArray()]);
            return [];
        }

        // Normalise: handle both indexed array and single object
        if (isset($data['id'])) {
            $data = [$data];
        }

        $results = [];
        foreach ($data as $row) {
            $id = $row['id'] ?? null;
            if (!$id) continue;
            $results[$id] = [
                'sentiment_score' => (float) ($row['sentiment_score'] ?? 0),
                'signals'         => $row['signals'] ?? [],
            ];
        }

        return $results;
    }

    private function isRelevant(Article $article): bool
    {
        $text = strtolower($article->title . ' ' . mb_substr($article->content, 0, 500));
        foreach (self::CRYPTO_KEYWORDS as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }
        return false;
    }

    private function callModel(string $system, string $user, string $task = ''): ?array
    {
        $primary  = TradingSettings::primaryModel();
        $fallback = TradingSettings::fallbackModel();

        $data = $this->callByName($primary, $system, $user, $task);

        if ($data === null && $fallback !== 'none' && $fallback !== $primary) {
            BotLogger::warning($primary, "{$primary} failed for [{$task}], trying fallback: {$fallback}");
            $data = $this->callByName($fallback, $system, $user, $task);

            if ($data !== null) {
                BotLogger::info($fallback, "{$fallback} fallback succeeded for [{$task}]");
            } else {
                BotLogger::error($fallback, "Both {$primary} and {$fallback} failed for [{$task}]");
            }
        }

        return $data;
    }

    private function callByName(string $model, string $system, string $user, string $task = ''): ?array
    {
        return match ($model) {
            'claude' => $this->callClaude($system, $user, $task),
            'gemini' => $this->gemini->callGemini($system, $user, $task),
            default  => null,
        };
    }

    private function callClaude(string $system, string $user, string $task = ''): ?array
    {
        BotLogger::info('claude', "Claude › {$task}");
        try {
            $result = Process::timeout(300)->env([
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
                $stderr = trim(substr($result->errorOutput(), 0, 300));
                BotLogger::error('claude', "Claude process failed (exit {$result->exitCode()}): {$stderr}");
                return null;
            }

            $envelope = json_decode($result->output(), true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($envelope['result'])) {
                $preview = trim(substr($result->output(), 0, 200));
                BotLogger::error('claude', "Claude unexpected output: {$preview}");
                return null;
            }

            return $this->parseJson($envelope['result']);
        } catch (\Throwable $e) {
            BotLogger::error('claude', "Claude exception: {$e->getMessage()}");
            return null;
        }
    }

    private function parseJson(string $raw): ?array
    {
        $clean = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $clean = preg_replace('/\s*```\s*$/m', '', $clean);
        $clean = trim($clean);

        $decoded = json_decode($clean, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            BotLogger::warning('claude', 'Claude returned unparseable JSON', ['raw' => substr($raw, 0, 200)]);
            return null;
        }

        return $decoded;
    }
}
