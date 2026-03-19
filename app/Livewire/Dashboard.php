<?php
namespace App\Livewire;

use App\Models\Analysis;
use App\Models\Article;
use App\Models\Execution;
use App\Models\SentimentSignal;
use App\Models\Source;
use App\Models\TradeDecision;
use App\Services\CoinbaseService;
use App\Services\TradeExecutor;
use App\Services\TradingSettings;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tradebot', ['title' => 'Dashboard'])]
class Dashboard extends Component
{
    public string $minReserveInput = '';

    public function mount(): void
    {
        $this->minReserveInput = (string) TradingSettings::minReserve();
    }

    public function saveMinReserve(): void
    {
        $value = (float) str_replace(',', '.', $this->minReserveInput);
        TradingSettings::setMinReserve($value);
        Cache::forget('dashboard.portfolio');
    }

    public function toggleAutoTrade(): void
    {
        TradingSettings::setAutoTrade(!TradingSettings::autoTrade());
    }

    public function executeDecision(int $id): void
    {
        $decision = TradeDecision::with('execution')->find($id);
        if (!$decision || $decision->execution) return;

        $executor = app(TradeExecutor::class);
        $executor->execute($decision, TradingSettings::isLive());
        Cache::forget('dashboard.stats');
        Cache::forget('dashboard.portfolio');
    }

    public function denyDecision(int $id): void
    {
        $decision = TradeDecision::with('execution')->find($id);
        if (!$decision || $decision->execution) return;

        $decision->delete();
    }

    public function render()
    {
        $stats = Cache::remember('dashboard.stats', 60, function () {
            return [
                'total_sources'    => Source::where('is_active', true)->count(),
                'articles_today'   => Article::where('created_at', '>=', today())->count(),
                'signals_6h'       => SentimentSignal::where('created_at', '>=', now()->subHours(6))->count(),
                'analyses_today'   => Analysis::where('created_at', '>=', today())->count(),
                'executions_today' => Execution::where('created_at', '>=', today())->count(),
                'paper_trades'     => Execution::where('mode', 'paper')->where('status', 'filled')->count(),
                'live_trades'      => Execution::where('mode', 'live')->where('status', 'filled')->count(),
            ];
        });

        $portfolio = Cache::remember('dashboard.portfolio', 60, function () {
            $coinbase   = app(CoinbaseService::class);
            $breakdown  = $coinbase->getPortfolioBreakdown();

            if ($breakdown === null) {
                return ['assets' => [], 'total_eur' => null];
            }

            return [
                'assets'    => $breakdown['positions'],
                'total_eur' => $breakdown['total_eur'],
                'cash_eur'  => $breakdown['cash_eur'],
            ];
        });

        $currentMode = TradingSettings::mode();
        $recentDecisions = TradeDecision::with(['analysis', 'execution'])
            ->where('mode', $currentMode)
            ->latest()
            ->limit(10)
            ->get();

        $recentSignals = SentimentSignal::with('article.source')
            ->where('created_at', '>=', now()->subHours(24))
            ->latest()
            ->limit(15)
            ->get();

        $assetSentiment = Cache::remember('dashboard.sentiment', 30, function () {
            return SentimentSignal::where('created_at', '>=', now()->subHours(24))
                ->selectRaw('asset_symbol, AVG(signal_score) as avg_score, COUNT(*) as signal_count')
                ->groupBy('asset_symbol')
                ->get()
                ->keyBy('asset_symbol');
        });

        $autoTrade = TradingSettings::autoTrade();

        return view('livewire.dashboard', compact(
            'stats', 'portfolio', 'recentDecisions', 'recentSignals', 'assetSentiment', 'autoTrade'
        ));
    }
}
