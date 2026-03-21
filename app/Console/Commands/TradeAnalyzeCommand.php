<?php
namespace App\Console\Commands;

use App\Models\Analysis;
use App\Models\SentimentSignal;
use App\Models\TradeDecision;
use App\Services\BotLogger;
use App\Services\ClaudeAnalysisService;
use App\Services\CoinbaseService;
use App\Services\TradeExecutor;
use App\Services\TradingSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TradeAnalyzeCommand extends Command
{
    protected $signature   = 'trade:analyze
                                {--live        : Enable live trading (requires --confirm-live)}
                                {--confirm-live : Confirm you understand this will execute real trades}
                                {--hours=6     : Signal lookback window in hours}';
    protected $description = 'Run analysis cycle and execute trade decisions';

    public function __construct(
        private ClaudeAnalysisService $claude,
        private CoinbaseService       $coinbase,
        private TradeExecutor         $executor
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $liveConfirmed = $this->option('live') && $this->option('confirm-live');

        if ($this->option('live') && !$this->option('confirm-live')) {
            Log::warning('trade:analyze: --live passed without --confirm-live; running in paper mode');
            $this->warn('⚠ --live requires --confirm-live. Running in paper mode.');
        }

        // When called from scheduler (no flags), respect the TradingSettings mode
        if (!$this->option('live') && TradingSettings::isLive()) {
            $liveConfirmed = true;
        }

        $mode = $liveConfirmed ? 'LIVE' : 'PAPER';
        $this->info("trade:analyze starting [{$mode}]");
        BotLogger::info('scheduler', "Analysis cycle started [{$mode}]", ['mode' => strtolower($mode)]);

        // 1. Gather recent signals
        $hours   = (int) $this->option('hours');
        $signals = SentimentSignal::with('article')
            ->where('created_at', '>=', now()->subHours($hours))
            ->get()
            ->groupBy('asset_symbol')
            ->map(function ($group, $asset) {
                return [
                    'asset'          => $asset,
                    'avg_score'      => round($group->avg('signal_score'), 3),
                    'signal_count'   => $group->count(),
                    'signal_types'   => $group->pluck('signal_type')->unique()->values()->toArray(),
                    'top_headlines'  => $group->take(3)->map(fn($s) => $s->article->title ?? '')->values()->toArray(),
                ];
            })
            ->values()
            ->toArray();

        if (empty($signals)) {
            $this->warn("No signals in the last {$hours} hours. Aborting analysis.");
            BotLogger::info('scheduler', "Analysis skipped: no signals in last {$hours}h", ['hours' => $hours]);
            return self::SUCCESS;
        }

        $this->info('Signals gathered for: ' . implode(', ', array_column($signals, 'asset')));

        // 2. Get portfolio state
        $portfolio = $this->getPortfolio($liveConfirmed);
        $this->info('Portfolio snapshot taken.');

        // 3. Run AI analysis
        $this->info('Calling AI analysis (Claude with Gemini fallback)...');
        $result = $this->claude->runAnalysis($signals, $portfolio);

        if ($result === null) {
            $this->error('AI analysis failed (both models returned null).');
            BotLogger::error('scheduler', 'Analysis failed: Both Claude and Gemini returned null');
            return self::FAILURE;
        }

        // 4. Persist analysis
        $articleIds = SentimentSignal::where('created_at', '>=', now()->subHours($hours))
            ->pluck('article_id')
            ->unique()
            ->values()
            ->toArray();

        $analysis = Analysis::create([
            'triggered_by'       => $liveConfirmed ? 'cli:live' : 'cli:paper',
            'portfolio_snapshot' => $portfolio,
            'articles_evaluated' => $articleIds,
            'signals_summary'    => $signals,
            'claude_reasoning'   => $result['reasoning'],
        ]);

        $this->info("Analysis #{$analysis->id} created. Reasoning: " . substr($result['reasoning'], 0, 100) . '...');

        // 5. Create and execute decisions
        $decisions = $result['decisions'] ?? [];
        $this->info("Decisions from AI: " . count($decisions));

        // Build a set of held assets for sell-guard (currency => value_eur)
        $heldAssets = collect($portfolio['positions'] ?? [])
            ->filter(fn($p) => ($p['balance'] ?? 0) > 0)
            ->keyBy('currency');

        $summary = ['hold' => 0, 'skipped' => 0, 'created' => 0];

        foreach ($decisions as $d) {
            $assetSymbol   = strtoupper($d['asset_symbol'] ?? '');
            $allowedAssets = config('trading.allowed_assets', ['BTC', 'ETH', 'SOL', 'XRP']);

            if (!in_array($assetSymbol, $allowedAssets)) {
                $this->warn("Skipping disallowed asset: {$assetSymbol}");
                BotLogger::info('analyzer', "Decision skipped: {$assetSymbol} not in allowed assets", [
                    'asset' => $assetSymbol,
                ], null, $analysis->id);
                $summary['skipped']++;
                continue;
            }

            // Hard sell-guard: reject sell decisions for assets not in the portfolio
            if (($d['action'] ?? '') === 'sell' && !$heldAssets->has($assetSymbol)) {
                $this->warn("Skipping sell for {$assetSymbol}: not in portfolio");
                BotLogger::info('analyzer', "Sell decision skipped: {$assetSymbol} not held in portfolio", [
                    'asset' => $assetSymbol,
                ], null, $analysis->id);
                $summary['skipped']++;
                continue;
            }

            if (($d['action'] ?? '') === 'hold') {
                $this->line("  [hold] {$assetSymbol} → " . ($d['rationale'] ?? 'no rationale'));
                BotLogger::info('analyzer', "Hold: {$assetSymbol} – " . substr($d['rationale'] ?? '', 0, 120), [
                    'asset'      => $assetSymbol,
                    'confidence' => $d['confidence'] ?? null,
                    'rationale'  => $d['rationale'] ?? null,
                ], null, $analysis->id);
                $summary['hold']++;
                continue;
            }

            $amountCents   = (int) round(($d['amount_usd'] ?? 0) * 100);
            $minTradeCents = TradingSettings::minTradeUsd() * 100;
            $maxTradeCents = TradingSettings::maxTradeUsd() * 100;

            if ($amountCents < $minTradeCents) {
                $this->warn("  [skip] {$assetSymbol}: €" . number_format($amountCents / 100, 2) . " unter Minimum €" . number_format($minTradeCents / 100, 2));
                BotLogger::info('analyzer', "Decision skipped: {$assetSymbol} amount €" . number_format($amountCents / 100, 2) . " below minimum €" . number_format($minTradeCents / 100, 2), [
                    'asset'       => $assetSymbol,
                    'amount_eur'  => $amountCents / 100,
                    'minimum_eur' => $minTradeCents / 100,
                ], null, $analysis->id);
                continue;
            }

            if ($amountCents > $maxTradeCents) {
                $this->warn("  [cap] {$assetSymbol}: €" . number_format($amountCents / 100, 2) . " auf Maximum €" . number_format($maxTradeCents / 100, 2) . " begrenzt");
                $amountCents = $maxTradeCents;
            }

            $decision = TradeDecision::create([
                'analysis_id'     => $analysis->id,
                'mode'            => $liveConfirmed ? 'live' : 'paper',
                'asset_symbol'    => $assetSymbol,
                'action'          => $d['action'],
                'confidence'      => (int) ($d['confidence'] ?? 0),
                'amount_usd'      => $amountCents,
                'stop_loss_pct'   => $d['stop_loss_pct'] ?? null,
                'take_profit_pct' => $d['take_profit_pct'] ?? null,
                'rationale'       => $d['rationale'] ?? null,
                'expires_at'      => now()->addMinutes(config('trading.decision_ttl_minutes', 30)),
            ]);

            $this->line("  [{$decision->action}] {$assetSymbol} confidence={$decision->confidence}% amount=\${$decision->amountInDollars()}");

            if (TradingSettings::autoTrade()) {
                $execution = $this->executor->execute($decision, $liveConfirmed);
                $this->line("    → Execution #{$execution->id}: {$execution->status} [{$execution->mode}]");
            } else {
                $this->line("    → Auto-Trade OFF: decision #{$decision->id} saved, awaiting manual approval");
            }
            $summary['created']++;
        }

        $this->info('trade:analyze complete.');
        BotLogger::info('scheduler', "Analysis cycle done: {$summary['created']} traded, {$summary['hold']} hold, {$summary['skipped']} skipped", [
            'decision_count' => count($decisions),
            'created'        => $summary['created'],
            'hold'           => $summary['hold'],
            'skipped'        => $summary['skipped'],
            'analysis_id'    => $analysis->id,
        ], null, $analysis->id);
        return self::SUCCESS;
    }

    private function getPortfolio(bool $live): array
    {
        $breakdown = $this->coinbase->getPortfolioBreakdown();

        if ($breakdown === null) {
            $this->warn('Could not fetch real portfolio from Coinbase. Using empty snapshot.');
            return [
                'mode'       => $live ? 'live' : 'paper',
                'cash_eur'   => 0,
                'total_eur'  => 0,
                'positions'  => [],
                'error'      => 'Could not fetch portfolio',
            ];
        }

        return [
            'mode'      => $live ? 'live' : 'paper',
            'cash_eur'  => $breakdown['cash_eur'],
            'total_eur' => $breakdown['total_eur'],
            'positions' => $breakdown['positions'],
        ];
    }
}
