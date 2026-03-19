<?php
namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ClaudeAnalysisService
{
    private const BATCH_SIZE = 10;

    // Keyword filter – articles without any of these get skipped without an API call
    private const CRYPTO_KEYWORDS = [
        'bitcoin', 'btc', 'ethereum', 'eth', 'solana', 'sol', 'xrp', 'ripple',
        'crypto', 'blockchain', 'defi', 'stablecoin', 'altcoin', 'coinbase',
        'binance', 'exchange', 'wallet', 'token', 'nft', 'web3', 'mining',
        'halving', 'staking', 'yield', 'ledger', 'satoshi',
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

        if ($skipped > 0) {
            Log::info('ClaudeAnalysisService: skipped irrelevant articles', ['count' => $skipped]);
        }

        if ($relevant->isEmpty()) {
            return [];
        }

        $results = [];

        // Optimisation 1: batch into groups of BATCH_SIZE
        foreach ($relevant->chunk(self::BATCH_SIZE) as $batch) {
            $batchResults = $this->scoreBatch($batch);
            $results      = array_merge($results, $batchResults);
        }

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
    public function runAnalysis(array $signals, array $portfolio): ?array
    {
        $allowedAssets = implode(', ', config('trading.allowed_assets', ['BTC', 'ETH', 'SOL', 'XRP']));

        $system = <<<SYSTEM
You are an expert crypto portfolio manager. Based on the provided signals and portfolio state, generate trading decisions.
Return ONLY valid JSON (no markdown). Allowed assets: {$allowedAssets}.

Response shape:
{"reasoning": "...", "decisions": [{"asset_symbol":"BTC","action":"buy","confidence":72,"amount_usd":250.00,"stop_loss_pct":5,"take_profit_pct":null,"rationale":"..."}]}

Rules:
- action: buy | sell | hold
- confidence: integer 0-100
- amount_usd: dollars (float), or 0 for hold
- stop_loss_pct: float or null
- take_profit_pct: float or null
- Capital preservation first. When in doubt, hold.
- Only recommend trades with confidence >= 60
- Maximum one decision per asset
- For hold decisions, set amount_usd to 0
SYSTEM;

        $user = "## Current Portfolio\n" . json_encode($portfolio, JSON_PRETTY_PRINT)
              . "\n\n## Recent Signals (last 6h)\n" . json_encode($signals, JSON_PRETTY_PRINT);

        $data = $this->callClaude($system, $user);
        if ($data === null) return null;

        if (!isset($data['reasoning']) || !isset($data['decisions']) || !is_array($data['decisions'])) {
            Log::warning('ClaudeAnalysisService: invalid runAnalysis response');
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
- Only include assets: BTC, ETH, SOL, XRP
- If no relevant signals, use "signals": []
SYSTEM;

        // Optimisation 3: only title + first 500 chars of content
        $items = $batch->map(fn($a) => [
            'id'      => $a->id,
            'title'   => $a->title,
            'content' => mb_substr($a->content, 0, 500),
        ])->values()->toArray();

        $user = json_encode($items, JSON_UNESCAPED_UNICODE);

        $data = $this->callClaude($system, $user);
        if (!is_array($data)) return [];

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

    private function callClaude(string $system, string $user): ?array
    {
        try {
            $result = Process::timeout(120)->run([
                'claude',
                '--print',
                '--system-prompt', $system,
                '--output-format', 'json',
                '--no-session-persistence',
                '--model', 'sonnet',
                $user,
            ]);

            if (!$result->successful()) {
                Log::error('ClaudeAnalysisService: process failed', [
                    'exit_code' => $result->exitCode(),
                    'stderr'    => substr($result->errorOutput(), 0, 500),
                ]);
                return null;
            }

            $envelope = json_decode($result->output(), true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($envelope['result'])) {
                Log::error('ClaudeAnalysisService: unexpected output envelope', [
                    'raw' => substr($result->output(), 0, 300),
                ]);
                return null;
            }

            return $this->parseJson($envelope['result']);
        } catch (\Throwable $e) {
            Log::error('ClaudeAnalysisService: exception', ['message' => $e->getMessage()]);
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
            Log::warning('ClaudeAnalysisService: JSON parse error', ['raw' => substr($raw, 0, 200)]);
            return null;
        }

        return $decoded;
    }
}
