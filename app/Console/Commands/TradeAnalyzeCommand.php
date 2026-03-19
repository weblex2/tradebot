<?php
namespace App\Console\Commands;

use App\Models\Analysis;
use App\Models\SentimentSignal;
use App\Models\TradeDecision;
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
            return self::SUCCESS;
        }

        $this->info('Signals gathered for: ' . implode(', ', array_column($signals, 'asset')));

        // 2. Get portfolio state
        $portfolio = $this->getPortfolio($liveConfirmed);
        $this->info('Portfolio snapshot taken.');

        // 3. Run Claude analysis
        $this->info('Calling Claude API...');
        $result = $this->claude->runAnalysis($signals, $portfolio);

        if ($result === null) {
            $this->error('Claude analysis failed.');
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
        $this->info("Decisions from Claude: " . count($decisions));

        foreach ($decisions as $d) {
            $assetSymbol   = strtoupper($d['asset_symbol'] ?? '');
            $allowedAssets = config('trading.allowed_assets', ['BTC', 'ETH', 'SOL', 'XRP']);

            if (!in_array($assetSymbol, $allowedAssets)) {
                $this->warn("Skipping disallowed asset: {$assetSymbol}");
                continue;
            }

            if (($d['action'] ?? '') === 'hold') {
                $this->line("  [hold] {$assetSymbol} → skipped");
                continue;
            }

            $amountCents = (int) round(($d['amount_usd'] ?? 0) * 100);

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
        }

        $this->info('trade:analyze complete.');
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
