<?php

return [
    'mode'               => env('TRADING_MODE', 'paper'),       // paper | live
    'paper_trading'      => env('PAPER_TRADING', true),          // second safety flag
    'max_trade_usd'      => (int) env('MAX_TRADE_USD', 500),      // dollars (display), stored as cents
    'max_exposure_pct'   => (float) env('MAX_EXPOSURE_PCT', 10),
    'min_reserve_usd'    => (int) env('MIN_RESERVE_USD', 200),
    'min_confidence'     => (int) env('MIN_CONFIDENCE', 60),
    'decision_ttl_minutes' => (int) env('DECISION_TTL_MINUTES', 30),
    'allowed_assets'     => explode(',', env('ALLOWED_ASSETS', 'BTC,ETH,SOL,XRP')),
    'n8n_webhook_url'    => env('N8N_WEBHOOK_URL', ''),          // optional trade notification webhook
];
