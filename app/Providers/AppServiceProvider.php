<?php

namespace App\Providers;

use App\Services\TradingSettings;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.tailwind');

        // Register a Carbon macro that converts any UTC Carbon to the user's display timezone.
        // Views use: $carbon->local()->format('...')  or  $carbon->local()->diffForHumans()
        \Carbon\Carbon::macro('local', function () {
            try {
                $tz = TradingSettings::timezone();
            } catch (\Throwable) {
                $tz = 'UTC';
            }
            return $this->copy()->setTimezone($tz);
        });
    }
}
