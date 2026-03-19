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
}
