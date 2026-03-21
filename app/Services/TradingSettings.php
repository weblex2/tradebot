<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class TradingSettings
{
    private const CACHE_KEY = 'trading.mode';
    private const CACHE_TTL = 60;

    public static function isLive(): bool
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return Setting::get('trading_mode', 'paper') === 'live';
        });
    }

    public static function mode(): string
    {
        return self::isLive() ? 'live' : 'paper';
    }

    public static function setLive(): void
    {
        Setting::set('trading_mode', 'live');
        Cache::forget(self::CACHE_KEY);
    }

    public static function setPaper(): void
    {
        Setting::set('trading_mode', 'paper');
        Cache::forget(self::CACHE_KEY);
    }

    public static function minReserve(): float
    {
        return (float) Setting::get('min_reserve_eur', config('trading.min_reserve_usd', 200));
    }

    public static function setMinReserve(float $eur): void
    {
        Setting::set('min_reserve_eur', max(0, $eur));
        Cache::forget('trading.min_reserve');
    }

    public static function autoTrade(): bool
    {
        return Cache::remember('trading.auto_trade', 60, function () {
            return (bool) Setting::get('auto_trade', true);
        });
    }

    public static function setAutoTrade(bool $enabled): void
    {
        Setting::set('auto_trade', $enabled ? '1' : '0');
        Cache::forget('trading.auto_trade');
    }

    public static function primaryModel(): string
    {
        return Cache::remember('trading.primary_model', self::CACHE_TTL, function () {
            return Setting::get('primary_model', 'claude');
        });
    }

    public static function setPrimaryModel(string $model): void
    {
        Setting::set('primary_model', $model);
        Cache::forget('trading.primary_model');
    }

    public static function fallbackModel(): string
    {
        return Cache::remember('trading.fallback_model', self::CACHE_TTL, function () {
            return Setting::get('fallback_model', 'gemini');
        });
    }

    public static function setFallbackModel(string $model): void
    {
        Setting::set('fallback_model', $model);
        Cache::forget('trading.fallback_model');
    }

    public static function timezone(): string
    {
        return Cache::remember('trading.timezone', self::CACHE_TTL, function () {
            return Setting::get('timezone', 'UTC');
        });
    }

    public static function setTimezone(string $tz): void
    {
        Setting::set('timezone', $tz);
        Cache::forget('trading.timezone');
    }

    public static function minTradeUsd(): int
    {
        return (int) Setting::get('min_trade_usd', config('trading.min_trade_usd', 1));
    }

    public static function setMinTradeUsd(int $usd): void
    {
        Setting::set('min_trade_usd', max(1, $usd));
        Cache::forget('trading.min_trade_usd');
    }

    public static function maxTradeUsd(): int
    {
        return (int) Setting::get('max_trade_usd', config('trading.max_trade_usd', 500));
    }

    public static function setMaxTradeUsd(int $usd): void
    {
        Setting::set('max_trade_usd', max(1, $usd));
        Cache::forget('trading.max_trade_usd');
    }

    public static function minConfidence(): int
    {
        return (int) Setting::get('min_confidence', config('trading.min_confidence', 60));
    }

    public static function setMinConfidence(int $pct): void
    {
        Setting::set('min_confidence', max(0, min(100, $pct)));
        Cache::forget('trading.min_confidence');
    }

    public static function maxExposurePct(): float
    {
        return (float) Setting::get('max_exposure_pct', config('trading.max_exposure_pct', 10));
    }

    public static function setMaxExposurePct(float $pct): void
    {
        Setting::set('max_exposure_pct', max(1, min(100, $pct)));
        Cache::forget('trading.max_exposure_pct');
    }

    public static function decisionTtlMinutes(): int
    {
        return (int) Setting::get('decision_ttl_minutes', config('trading.decision_ttl_minutes', 30));
    }

    public static function setDecisionTtlMinutes(int $minutes): void
    {
        Setting::set('decision_ttl_minutes', max(5, $minutes));
        Cache::forget('trading.decision_ttl_minutes');
    }

    public static function perPage(): int
    {
        return (int) Setting::get('per_page', 25);
    }

    public static function setPerPage(int $n): void
    {
        Setting::set('per_page', max(5, min(200, $n)));
        Cache::forget('trading.per_page');
    }
}
