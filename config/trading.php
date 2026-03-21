<?php

return [
    'mode'               => env('TRADING_MODE', 'paper'),       // paper | live
    'paper_trading'      => env('PAPER_TRADING', true),          // second safety flag
    'min_trade_usd'      => (int) env('MIN_TRADE_USD', 1),        // Coinbase minimum is $1.00
    'max_trade_usd'      => (int) env('MAX_TRADE_USD', 500),      // dollars (display), stored as cents
    'max_exposure_pct'   => (float) env('MAX_EXPOSURE_PCT', 10),
    'min_reserve_usd'    => (int) env('MIN_RESERVE_USD', 200),
    'min_confidence'     => (int) env('MIN_CONFIDENCE', 60),
    'decision_ttl_minutes' => (int) env('DECISION_TTL_MINUTES', 30),
    'allowed_assets'     => explode(',', env('ALLOWED_ASSETS', 'BTC,ETH,SOL,XRP')),
    'ntfy_url'           => env('NTFY_URL', ''),                  // ntfy server (e.g. http://192.168.178.108)
    'ntfy_topic'         => env('NTFY_TOPIC', 'tradebot'),
    'ntfy_token'         => env('NTFY_TOKEN', ''),                // ntfy access token (optional)
];
