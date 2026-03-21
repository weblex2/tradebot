<?php
namespace App\Services;

class TechnicalAnalysisService
{
    /**
     * Build a full technical analysis prompt block for one asset.
     * Returns null if neither daily nor hourly data is available.
     */
    public function buildPromptBlock(string $symbol, ?array $dailyCandles, ?array $hourlyCandles): ?string
    {
        $lines = [];

        if ($hourlyCandles && count($hourlyCandles) >= 28) {
            $closes = $this->closes($hourlyCandles);
            $rsi    = $this->rsi($closes);
            $macd   = $this->macd($closes);

            if ($rsi !== null) {
                $lines[] = "RSI14(1h)=" . number_format($rsi, 1) . " " . $this->rsiLabel($rsi);
            }
            if ($macd !== null) {
                $hist = $macd['histogram'];
                $lines[] = "MACD(1h): line=" . number_format($macd['macd'], 4)
                    . " signal=" . number_format($macd['signal'], 4)
                    . " hist=" . ($hist >= 0 ? '+' : '') . number_format($hist, 4)
                    . " " . $this->macdLabel($macd);
            }
        }

        if ($dailyCandles && count($dailyCandles) >= 20) {
            $closes = $this->closes($dailyCandles);
            $highs  = $this->highs($dailyCandles);
            $lows   = $this->lows($dailyCandles);
            $price  = end($closes);

            $sma20 = $this->sma($closes, 20);
            $sma50 = $this->sma($closes, 50);
            $bb    = $this->bollingerBands($closes, 20);
            $sr    = $this->supportResistance($highs, $lows, 14);

            if ($sma20 !== null) {
                $pctVsSma20 = number_format(($price - $sma20) / $sma20 * 100, 1);
                $cross = ($sma50 !== null)
                    ? ($sma20 > $sma50 ? 'golden cross' : 'death cross')
                    : '';
                $smaLine = "SMA20=" . number_format($sma20, 2)
                    . ($sma50 !== null ? " SMA50=" . number_format($sma50, 2) : '')
                    . " (price " . ($pctVsSma20 >= 0 ? '+' : '') . "{$pctVsSma20}% vs SMA20"
                    . ($cross ? ", {$cross}" : '') . ")";
                $lines[] = $smaLine;
            }

            if ($bb !== null) {
                $lines[] = "BB: upper=" . number_format($bb['upper'], 2)
                    . " mid=" . number_format($bb['middle'], 2)
                    . " lower=" . number_format($bb['lower'], 2)
                    . " width=" . number_format($bb['width_pct'], 1) . "%"
                    . " " . $this->bbLabel($price, $bb);
            }

            $pctFromSupport    = number_format(($price - $sr['support']) / $sr['support'] * 100, 1);
            $pctFromResistance = number_format(($sr['resistance'] - $price) / $sr['resistance'] * 100, 1);
            $lines[] = "S/R(14d): support=" . number_format($sr['support'], 2)
                . " resistance=" . number_format($sr['resistance'], 2)
                . " (+{$pctFromSupport}% from support, -{$pctFromResistance}% from resistance)";
        }

        if (empty($lines)) return null;

        return "[{$symbol} Technical]\n" . implode("\n", $lines);
    }

    // ── Private: data extraction ──────────────────────────────────────────────

    /** Extract close prices, oldest-first */
    private function closes(array $candles): array
    {
        return array_map(fn($c) => (float) $c['close'], array_reverse($candles));
    }

    /** Extract high prices, oldest-first */
    private function highs(array $candles): array
    {
        return array_map(fn($c) => (float) $c['high'], array_reverse($candles));
    }

    /** Extract low prices, oldest-first */
    private function lows(array $candles): array
    {
        return array_map(fn($c) => (float) $c['low'], array_reverse($candles));
    }

    // ── Private: indicators ───────────────────────────────────────────────────

    private function sma(array $closes, int $period): ?float
    {
        if (count($closes) < $period) return null;
        return array_sum(array_slice($closes, -$period)) / $period;
    }

    private function ema(array $closes, int $period): ?float
    {
        if (count($closes) < $period) return null;
        $k   = 2 / ($period + 1);
        $ema = array_sum(array_slice($closes, 0, $period)) / $period;
        foreach (array_slice($closes, $period) as $close) {
            $ema = $close * $k + $ema * (1 - $k);
        }
        return $ema;
    }

    private function rsi(array $closes, int $period = 14): ?float
    {
        if (count($closes) < $period * 2) return null;

        $gains  = [];
        $losses = [];
        for ($i = 1; $i < count($closes); $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            $gains[]  = max(0, $diff);
            $losses[] = max(0, -$diff);
        }

        $avgGain = array_sum(array_slice($gains, 0, $period)) / $period;
        $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;

        foreach (array_slice($gains, $period) as $i => $gain) {
            $avgGain = ($avgGain * ($period - 1) + $gain) / $period;
            $avgLoss = ($avgLoss * ($period - 1) + $losses[$period + $i]) / $period;
        }

        if ($avgLoss == 0) return 100.0;
        return 100 - (100 / (1 + $avgGain / $avgLoss));
    }

    private function macd(array $closes, int $fast = 12, int $slow = 26, int $signal = 9): ?array
    {
        if (count($closes) < $slow + $signal + 5) return null;

        $k_fast = 2 / ($fast + 1);
        $k_slow = 2 / ($slow + 1);

        $emaFast = array_sum(array_slice($closes, 0, $fast)) / $fast;
        $emaSlow = array_sum(array_slice($closes, 0, $slow)) / $slow;

        $macdValues = [];
        foreach (array_slice($closes, $slow) as $i => $close) {
            // Update both EMAs from slow period onward
            $allClose = $closes[$slow + $i];
            $emaFast  = $allClose * $k_fast + $emaFast * (1 - $k_fast);
            $emaSlow  = $allClose * $k_slow + $emaSlow * (1 - $k_slow);
            $macdValues[] = $emaFast - $emaSlow;
        }

        // Recalculate fast EMA from scratch aligned to slow start
        // (warm up fast EMA using first $fast values before $slow index)
        $emaFast = array_sum(array_slice($closes, $slow - $fast, $fast)) / $fast;
        $macdValues = [];
        for ($i = $slow; $i < count($closes); $i++) {
            $emaFast = $closes[$i] * $k_fast + $emaFast * (1 - $k_fast);
            $emaSlow = $closes[$i] * $k_slow + $emaSlow * (1 - $k_slow);
            $macdValues[] = $emaFast - $emaSlow;
        }

        if (count($macdValues) < $signal) return null;

        $k_sig    = 2 / ($signal + 1);
        $sigLine  = array_sum(array_slice($macdValues, 0, $signal)) / $signal;
        foreach (array_slice($macdValues, $signal) as $m) {
            $sigLine = $m * $k_sig + $sigLine * (1 - $k_sig);
        }

        $lastMacd = end($macdValues);
        return [
            'macd'      => $lastMacd,
            'signal'    => $sigLine,
            'histogram' => $lastMacd - $sigLine,
        ];
    }

    private function bollingerBands(array $closes, int $period = 20, float $mult = 2.0): ?array
    {
        if (count($closes) < $period) return null;
        $slice  = array_slice($closes, -$period);
        $mean   = array_sum($slice) / $period;
        $stdDev = sqrt(array_sum(array_map(fn($c) => ($c - $mean) ** 2, $slice)) / $period);
        $upper  = $mean + $mult * $stdDev;
        $lower  = $mean - $mult * $stdDev;
        return [
            'upper'     => $upper,
            'middle'    => $mean,
            'lower'     => $lower,
            'width_pct' => $mean > 0 ? ($upper - $lower) / $mean * 100 : 0,
        ];
    }

    private function supportResistance(array $highs, array $lows, int $lookback = 14): array
    {
        $recentHighs = array_slice($highs, -$lookback);
        $recentLows  = array_slice($lows, -$lookback);
        return [
            'support'    => min($recentLows),
            'resistance' => max($recentHighs),
        ];
    }

    // ── Private: labels ───────────────────────────────────────────────────────

    private function rsiLabel(float $rsi): string
    {
        if ($rsi >= 70) return 'overbought';
        if ($rsi <= 30) return 'oversold';
        if ($rsi >= 60) return 'bullish';
        if ($rsi <= 40) return 'bearish';
        return 'neutral';
    }

    private function macdLabel(array $macd): string
    {
        $hist = $macd['histogram'];
        $line = $macd['macd'];
        if ($hist > 0 && $line > 0) return 'bullish';
        if ($hist > 0 && $line < 0) return 'bullish cross';
        if ($hist < 0 && $line < 0) return 'bearish';
        if ($hist < 0 && $line > 0) return 'bearish cross';
        return 'neutral';
    }

    private function bbLabel(float $price, array $bb): string
    {
        $range = $bb['upper'] - $bb['lower'];
        if ($range <= 0) return 'neutral';
        $pos = ($price - $bb['lower']) / $range;
        if ($pos >= 0.95) return 'above upper';
        if ($pos >= 0.75) return 'near upper';
        if ($pos <= 0.05) return 'below lower';
        if ($pos <= 0.25) return 'near lower';
        return 'mid';
    }
}
