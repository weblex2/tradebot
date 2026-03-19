<?php
namespace App\Services;

use App\Models\Execution;
use App\Models\TradeDecision;
use App\Services\TradingSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TradeExecutor
{
    public function __construct(private CoinbaseService $coinbase) {}

    public function execute(TradeDecision $decision, bool $liveConfirmed = false): Execution
    {
        // Always record an execution attempt
        $execution = new Execution([
            'trade_decision_id' => $decision->id,
            'mode'              => $this->determinMode($liveConfirmed),
            'asset_symbol'      => $decision->asset_symbol,
            'action'            => $decision->action,
            'amount_usd'        => $decision->amount_usd,
            'status'            => 'pending',
        ]);

        // Pre-execution checks
        $failReason = $this->runChecks($decision, $liveConfirmed);
        if ($failReason !== null) {
            $execution->status         = 'failed';
            $execution->failure_reason = $failReason;
            $execution->save();
            Log::warning('TradeExecutor: decision failed checks', ['decision_id' => $decision->id, 'reason' => $failReason]);
            return $execution;
        }

        // Hold actions → just record
        if ($decision->action === 'hold') {
            $execution->status = 'filled';
            $execution->save();
            return $execution;
        }

        if ($this->isLiveMode($liveConfirmed)) {
            return $this->executeLive($execution, $decision);
        }

        return $this->executePaper($execution, $decision);
    }

    private function runChecks(TradeDecision $decision, bool $liveConfirmed): ?string
    {
        // 1. Not expired
        if ($decision->isExpired()) {
            return 'Decision expired at ' . $decision->expires_at->toIso8601String();
        }

        // 2. Confidence threshold
        $minConfidence = config('trading.min_confidence', 60);
        if ($decision->confidence < $minConfidence) {
            return "Confidence {$decision->confidence} below minimum {$minConfidence}";
        }

        // 3. Amount limit
        $maxTradeUsd = config('trading.max_trade_usd', 500) * 100; // convert to cents
        if ($decision->amount_usd > $maxTradeUsd) {
            return "Amount {$decision->amount_usd} cents exceeds max {$maxTradeUsd} cents";
        }

        // 4. Allowed asset guard
        $allowedAssets = config('trading.allowed_assets', ['BTC', 'ETH', 'SOL', 'XRP']);
        if (!in_array($decision->asset_symbol, $allowedAssets)) {
            Log::warning('TradeExecutor: disallowed asset attempted', ['asset' => $decision->asset_symbol]);
            return "Asset {$decision->asset_symbol} not in allowed list";
        }

        // 5. Hold actions skip financial checks
        if ($decision->action === 'hold') {
            return null;
        }

        // 6. Cash reserve check for buys (live mode only)
        if ($this->isLiveMode($liveConfirmed) && $decision->action === 'buy') {
            $breakdown = $this->coinbase->getPortfolioBreakdown();
            if ($breakdown === null) {
                return 'Could not retrieve portfolio for pre-execution check';
            }

            $cashCents       = (int) round($breakdown['cash_eur'] * 100);
            $minReserveCents = (int) round(TradingSettings::minReserve() * 100);

            if (($cashCents - $decision->amount_usd) < $minReserveCents) {
                return "Insufficient cash reserve. Available: €" . number_format($breakdown['cash_eur'], 2)
                    . ", Required reserve: €" . number_format(TradingSettings::minReserve(), 2);
            }
        }

        return null;
    }

    private function executeLive(Execution $execution, TradeDecision $decision): Execution
    {
        $order = $this->coinbase->placeMarketOrder(
            $decision->asset_symbol,
            $decision->action,
            $decision->amount_usd
        );

        if ($order === null) {
            $execution->status         = 'failed';
            $execution->failure_reason = 'Coinbase API returned null';
            $execution->save();
            $this->notifyN8n($execution);
            return $execution;
        }

        $orderId = $order['order_id'] ?? null;
        $execution->exchange_order_id = $orderId;
        $execution->status            = $orderId ? 'pending' : 'failed';
        $execution->save();

        $this->notifyN8n($execution);

        Log::info('TradeExecutor: live order placed', [
            'decision_id' => $decision->id,
            'order_id'    => $orderId,
            'asset'       => $decision->asset_symbol,
        ]);

        return $execution;
    }

    private function executePaper(Execution $execution, TradeDecision $decision): Execution
    {
        // Simulate a fill at current market price (best effort)
        $price = $this->coinbase->getPrice($decision->asset_symbol);

        $execution->price_at_execution = $price;
        $execution->status             = 'filled';
        $execution->save();

        $this->notifyN8n($execution);

        Log::info('TradeExecutor: paper trade recorded', [
            'decision_id' => $decision->id,
            'asset'       => $decision->asset_symbol,
            'action'      => $decision->action,
            'amount_usd'  => $decision->amount_usd,
            'price'       => $price,
        ]);

        return $execution;
    }

    private function isLiveMode(bool $liveConfirmed): bool
    {
        return TradingSettings::isLive() && $liveConfirmed;
    }

    private function determinMode(bool $liveConfirmed): string
    {
        return $this->isLiveMode($liveConfirmed) ? 'live' : 'paper';
    }


    private function notifyN8n(Execution $execution): void
    {
        $webhookUrl = config('trading.n8n_webhook_url', '');
        if (empty($webhookUrl)) return;

        try {
            Http::timeout(5)->post($webhookUrl, [
                'execution_id'      => $execution->id,
                'mode'              => $execution->mode,
                'status'            => $execution->status,
                'asset_symbol'      => $execution->asset_symbol,
                'action'            => $execution->action,
                'amount_usd_cents'  => $execution->amount_usd,
                'amount_usd'        => $execution->amountInDollars(),
                'price_at_execution'=> $execution->priceInDollars(),
                'failure_reason'    => $execution->failure_reason,
                'timestamp'         => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('TradeExecutor: n8n notification failed', ['message' => $e->getMessage()]);
        }
    }
}
