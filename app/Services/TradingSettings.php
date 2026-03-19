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
}
