<?php
namespace App\Services;

use App\Jobs\GetOrderStatusJob;
use App\Models\Execution;
use App\Models\TradeDecision;
use App\Services\BotLogger;
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

        try {
            // Pre-execution checks
            $checkResult = $this->runChecks($decision, $liveConfirmed);
            if ($checkResult !== null) {
                [$checkStatus, $failReason] = $checkResult;
                $execution->status         = $checkStatus;
                $execution->failure_reason = $failReason;
                $execution->save();
                BotLogger::warning('executor', "Trade {$checkStatus}: {$failReason}", [
                    'decision_id' => $decision->id,
                    'asset'       => $decision->asset_symbol,
                    'action'      => $decision->action,
                    'confidence'  => $decision->confidence,
                    'amount_eur'  => $decision->amount_usd / 100,
                ], null, null, $execution->id);
                $this->notifyNtfy($execution);
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

        } catch (\Throwable $e) {
            $execution->status         = 'failed';
            $execution->failure_reason = 'Exception: ' . $e->getMessage();
            $execution->save();
            BotLogger::error('executor', "Unexpected exception during trade execution: {$e->getMessage()}", [
                'decision_id' => $decision->id,
                'asset'       => $decision->asset_symbol,
                'action'      => $decision->action,
                'exception'   => $e->getMessage(),
                'file'        => $e->getFile() . ':' . $e->getLine(),
            ], null, null, $execution->id);
            $this->notifyNtfy($execution);
            return $execution;
        }
    }

    /**
     * Run pre-execution checks.
     * Returns [status, reason] tuple on failure, or null if all checks pass.
     * Status is 'failed' for hard failures (config/logic), 'cancelled' for insufficient funds.
     */
    private function runChecks(TradeDecision $decision, bool $liveConfirmed): ?array
    {
        // 1. Not expired
        if ($decision->isExpired()) {
            return ['failed', 'Decision expired at ' . $decision->expires_at->toIso8601String()];
        }

        // 2. Confidence threshold
        $minConfidence = TradingSettings::minConfidence();
        if ($decision->confidence < $minConfidence) {
            return ['failed', "Confidence {$decision->confidence} below minimum {$minConfidence}"];
        }

        // 3. Amount within bounds
        $minTradeCents = TradingSettings::minTradeUsd() * 100;
        if ($decision->amount_usd < $minTradeCents) {
            return ['failed', "Amount €" . number_format($decision->amount_usd / 100, 2) . " is below minimum €" . number_format($minTradeCents / 100, 2)];
        }

        $maxTradeCents = TradingSettings::maxTradeUsd() * 100;
        if ($decision->amount_usd > $maxTradeCents) {
            return ['failed', "Amount €" . number_format($decision->amount_usd / 100, 2) . " exceeds maximum €" . number_format($maxTradeCents / 100, 2)];
        }

        // 4. Allowed asset guard
        $allowedAssets = config('trading.allowed_assets', ['BTC', 'ETH', 'SOL', 'XRP']);
        if (!in_array($decision->asset_symbol, $allowedAssets)) {
            Log::warning('TradeExecutor: disallowed asset attempted', ['asset' => $decision->asset_symbol]);
            return ['failed', "Asset {$decision->asset_symbol} not in allowed list"];
        }

        // 5. Hold actions skip financial checks
        if ($decision->action === 'hold') {
            return null;
        }

        // 6. Live-mode balance checks
        if ($this->isLiveMode($liveConfirmed)) {
            $breakdown = $this->coinbase->getPortfolioBreakdown();
            if ($breakdown === null) {
                return ['failed', 'Could not retrieve portfolio for pre-execution check'];
            }

            if ($decision->action === 'buy') {
                $cashCents       = (int) round($breakdown['cash_eur'] * 100);
                $minReserveCents = (int) round(TradingSettings::minReserve() * 100);

                if (($cashCents - $decision->amount_usd) < $minReserveCents) {
                    return ['cancelled', "Insufficient cash. Available: €" . number_format($breakdown['cash_eur'], 2)
                        . ", Required: €" . number_format($decision->amount_usd / 100, 2)
                        . ", Reserve: €" . number_format(TradingSettings::minReserve(), 2)];
                }
            }

            if ($decision->action === 'sell') {
                // Use available_balance from accounts endpoint – portfolio breakdown includes
                // staked/locked amounts that Coinbase will NOT let us sell.
                $availableCrypto = $this->coinbase->getAvailableBalance($decision->asset_symbol);
                if ($availableCrypto === null) {
                    return ['failed', "Could not retrieve available balance for {$decision->asset_symbol}"];
                }

                if ($availableCrypto <= 0) {
                    return ['cancelled', "No {$decision->asset_symbol} spot balance available to sell"];
                }

                // Compare in crypto units to avoid EUR/price fluctuation rounding errors.
                // Use float price for full precision – integer cents round to 0 for sub-cent coins (e.g. SHIB).
                // Apply 1% price-drift tolerance: treat available as 1% more than nominal.
                $priceEur = $this->coinbase->getPriceEurFloat($decision->asset_symbol);
                if ($priceEur === null || $priceEur <= 0) {
                    // Price unavailable: available balance > 0 is already confirmed above.
                    // Skip quantity check and let Coinbase reject with INSUFFICIENT_FUND if needed.
                    BotLogger::info('executor', "Price unavailable for {$decision->asset_symbol}, skipping quantity pre-check (balance confirmed > 0)");
                    return null;
                }
                $neededCrypto           = ($decision->amount_usd / 100) / $priceEur;
                $availableWithTolerance = $availableCrypto * 1.01;

                if ($availableWithTolerance < $neededCrypto) {
                    $availableEur = $availableCrypto * ($priceCents / 100);
                    return ['cancelled', "Insufficient {$decision->asset_symbol} spot balance. Available: €" . number_format($availableEur, 2)
                        . " ({$availableCrypto} {$decision->asset_symbol}), Need: €" . number_format($decision->amount_usd / 100, 2)];
                }
            }
        }

        return null;
    }

    private function executeLive(Execution $execution, TradeDecision $decision): Execution
    {
        $amount = $decision->amount_usd;
        $order  = $this->coinbase->placeMarketOrder($decision->asset_symbol, $decision->action, $amount);

        // On insufficient funds, reduce by 5% and retry once (covers EUR rounding/balance drift)
        if (($order['error_response']['error'] ?? $order['error'] ?? null) === 'INSUFFICIENT_FUND') {
            $amount = (int) round($amount * 0.95);
            $execution->amount_usd = $amount;
            $order = $this->coinbase->placeMarketOrder($decision->asset_symbol, $decision->action, $amount);
        }

        if ($order === null) {
            $execution->status         = 'failed';
            $execution->failure_reason = 'Coinbase API returned null';
            $execution->save();
            BotLogger::error('executor', "Coinbase returned null for {$decision->asset_symbol} {$decision->action}", [
                'asset'  => $decision->asset_symbol,
                'action' => $decision->action,
            ], null, null, $execution->id);
            $this->notifyNtfy($execution);
            return $execution;
        }

        $orderId = $order['order_id'] ?? null;
        $execution->exchange_order_id = $orderId;

        if ($orderId) {
            $execution->status = 'pending';
            $execution->save();
            GetOrderStatusJob::dispatch($execution)->delay(now()->addSeconds(10));
            $amountEur = number_format($amount / 100, 2);
            BotLogger::info('executor', "Live order placed: {$decision->asset_symbol} {$decision->action} €{$amountEur}", [
                'order_id' => $orderId,
                'asset'    => $decision->asset_symbol,
                'action'   => $decision->action,
            ], null, null, $execution->id);
        } else {
            $errorCode    = $order['error'] ?? $order['error_response']['error'] ?? 'UNKNOWN';
            $errorMessage = $order['error_response']['message'] ?? $order['preview_failure_reason'] ?? 'No order_id returned';
            $execution->status         = 'failed';
            $execution->failure_reason = "Coinbase order failed: {$errorCode} – {$errorMessage}";
            $execution->save();
            BotLogger::error('executor', "Coinbase order failed: {$errorCode} – {$errorMessage}", [
                'asset'    => $decision->asset_symbol,
                'action'   => $decision->action,
                'response' => $order,
            ], null, null, $execution->id);
        }

        $this->notifyNtfy($execution);

        return $execution;
    }

    private function executePaper(Execution $execution, TradeDecision $decision): Execution
    {
        // Simulate a fill at current market price (best effort)
        $price = $this->coinbase->getPrice($decision->asset_symbol);

        $execution->price_at_execution = $price;
        $execution->status             = 'filled';
        $execution->save();

        $this->notifyNtfy($execution);

        $amountEur = number_format($decision->amount_usd / 100, 2);
        BotLogger::info('executor', "Paper trade: {$decision->action} {$decision->asset_symbol} €{$amountEur}", [
            'action'     => $decision->action,
            'asset'      => $decision->asset_symbol,
            'amount_eur' => $decision->amount_usd / 100,
            'price'      => $price,
        ], null, null, $execution->id);

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


    public function notifyNtfy(Execution $execution): void
    {
        $ntfyUrl = config('trading.ntfy_url', '');
        if (empty($ntfyUrl)) return;

        $topic = config('trading.ntfy_topic', 'tradebot');
        $token = config('trading.ntfy_token', '');

        $action = strtoupper($execution->action);
        $asset  = $execution->asset_symbol;
        $amount = '€' . number_format($execution->amountInDollars(), 2);
        $mode   = strtoupper($execution->mode);

        [$title, $message, $priority, $tags] = match ($execution->status) {
            'filled' => [
                "{$action} {$asset} ausgeführt",
                "{$mode}: {$action} {$asset} für {$amount}" . ($execution->priceInDollars() ? " @ €" . number_format($execution->priceInDollars(), 2) : ''),
                'default',
                $execution->action === 'buy' ? ['chart_with_upwards_trend'] : ['chart_with_downwards_trend'],
            ],
            'failed' => [
                "Trade fehlgeschlagen: {$asset}",
                "{$mode}: {$action} {$asset} ({$amount}) – " . ($execution->failure_reason ?? 'Unbekannter Fehler'),
                'high',
                ['warning'],
            ],
            default => [
                "Trade pending: {$action} {$asset}",
                "{$mode}: {$action} {$asset} {$amount}",
                'low',
                ['hourglass_flowing_sand'],
            ],
        };

        try {
            $request = Http::timeout(5)->withHeaders([
                'Title'    => $title,
                'Priority' => $priority,
                'Tags'     => implode(',', $tags),
            ]);

            if (!empty($token)) {
                $request = $request->withToken($token);
            }

            $request->post("{$ntfyUrl}/{$topic}", $message);
        } catch (\Throwable $e) {
            BotLogger::warning('executor', "ntfy notification failed: {$e->getMessage()}", ['exception' => $e->getMessage()]);
        }
    }
}
