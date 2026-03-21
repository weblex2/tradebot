<?php
namespace App\Services;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class CoinbaseService
{
    private string $apiKey;
    private string $apiSecret;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey    = config('services.coinbase.api_key', '');
        $this->apiSecret = config('services.coinbase.api_secret', '');
        $this->baseUrl   = config('services.coinbase.base_url', 'https://api-sandbox.coinbase.com');
    }

    /**
     * Get current portfolio balances (all pages).
     * Returns ['accounts' => [...]] or null on error.
     */
    public function getPortfolio(): ?array
    {
        $allAccounts = [];
        $cursor      = null;

        do {
            $path     = '/api/v3/brokerage/accounts?limit=250';
            if ($cursor) $path .= '&cursor=' . urlencode($cursor);

            $response = $this->request('GET', $path);
            if ($response === null) return null;

            if (isset($response['error'])) {
                Log::error('CoinbaseService::getPortfolio error', ['error' => $response['error']]);
                return null;
            }

            $allAccounts = array_merge($allAccounts, $response['accounts'] ?? []);
            $cursor      = ($response['has_next'] ?? false) ? ($response['cursor'] ?? null) : null;
        } while ($cursor !== null);

        return ['accounts' => $allAccounts];
    }

    /**
     * Get the available (tradeable spot) balance for a single asset.
     * Uses the accounts endpoint – NOT the portfolio breakdown which includes staked/locked amounts.
     * Returns crypto quantity or null on error.
     */
    public function getAvailableBalance(string $currency): ?float
    {
        $portfolio = $this->getPortfolio();
        if ($portfolio === null) return null;

        $account = collect($portfolio['accounts'])
            ->firstWhere('currency', strtoupper($currency));

        if (!$account) return 0.0;

        return (float) ($account['available_balance']['value'] ?? 0);
    }

    /**
     * Get the full portfolio breakdown including total EUR value and per-asset EUR values.
     * Uses the portfolio UUID from the portfolios list endpoint.
     * Returns ['total_eur' => float, 'positions' => [...]] or null on error.
     */
    public function getPortfolioBreakdown(): ?array
    {
        // Get the default portfolio UUID
        $listResponse = $this->request('GET', '/api/v3/brokerage/portfolios');
        if ($listResponse === null || isset($listResponse['error'])) {
            Log::error('CoinbaseService::getPortfolioBreakdown: failed to list portfolios');
            return null;
        }

        $portfolios = $listResponse['portfolios'] ?? [];
        $uuid = collect($portfolios)
            ->firstWhere('type', 'DEFAULT')['uuid']
            ?? ($portfolios[0]['uuid'] ?? null);

        if (!$uuid) {
            Log::error('CoinbaseService::getPortfolioBreakdown: no portfolio UUID found');
            return null;
        }

        $response = $this->request('GET', '/api/v3/brokerage/portfolios/' . $uuid);
        if ($response === null || isset($response['error'])) {
            Log::error('CoinbaseService::getPortfolioBreakdown error', ['error' => $response['error'] ?? 'null response']);
            return null;
        }

        $breakdown = $response['breakdown'] ?? [];
        $balances  = $breakdown['portfolio_balances'] ?? [];
        $totalEur  = (float) ($balances['total_balance']['value'] ?? 0);

        // Fetch available (tradeable spot) balances from accounts endpoint.
        // The portfolio breakdown includes staked/locked amounts which cannot be sold.
        $accountsData     = $this->getPortfolio();
        $availableByAsset = collect($accountsData['accounts'] ?? [])
            ->keyBy('currency')
            ->map(fn($a) => (float) ($a['available_balance']['value'] ?? 0));

        // Build positions using available_balance (not total_balance_crypto)
        $merged = [];
        foreach ($breakdown['spot_positions'] ?? [] as $pos) {
            $currency = $pos['asset'] ?? '';
            if (!$currency) continue;
            if (!isset($merged[$currency])) {
                $merged[$currency] = ['currency' => $currency, 'balance' => 0.0, 'value_eur' => 0.0, 'allocation' => 0.0];
            }
            // Use available spot balance; fall back to total if accounts data missing
            $available = $availableByAsset->get($currency);
            if ($available !== null) {
                $merged[$currency]['balance'] = $available;
                // Recalculate EUR value proportionally from total fiat
                $total = (float) ($pos['total_balance_crypto'] ?? 0);
                $totalFiat = (float) ($pos['total_balance_fiat'] ?? 0);
                $merged[$currency]['value_eur'] = $total > 0 ? ($available / $total) * $totalFiat : 0.0;
            } else {
                $merged[$currency]['balance']   += (float) ($pos['total_balance_crypto'] ?? 0);
                $merged[$currency]['value_eur'] += (float) ($pos['total_balance_fiat'] ?? 0);
            }
            $merged[$currency]['allocation'] += (float) ($pos['allocation'] ?? 0);
        }

        $positions = array_values($merged);

        // Sort by EUR value descending
        usort($positions, fn($a, $b) => $b['value_eur'] <=> $a['value_eur']);

        // Use actual EUR spot balance – NOT total_cash_equivalent which includes DAI/EURC
        // that Coinbase won't accept for market orders
        $cashEur = $availableByAsset->get('EUR', (float) ($merged['EUR']['balance'] ?? 0));

        return [
            'total_eur' => $totalEur,
            'cash_eur'  => $cashEur,
            'positions' => $positions,
        ];
    }

    /**
     * Get best bid/ask price for a single asset.
     * Returns price in cents or null.
     */
    public function getPrice(string $assetSymbol): ?int
    {
        $prices = $this->getPrices([$assetSymbol]);
        if (isset($prices[$assetSymbol])) {
            return $prices[$assetSymbol];
        }

        // USD pair returned no data (common for small-cap coins on EUR accounts).
        // Fall back to the EUR pair price directly.
        return $this->getPriceFromEurPair($assetSymbol);
    }

    /**
     * Fetch price as a float in EUR (full precision, no cent rounding).
     * Essential for sub-cent coins like SHIB where integer cents = 0.
     * Tries USD pair first (converts via EUR/USD rate), falls back to EUR pair.
     */
    public function getPriceEurFloat(string $assetSymbol): ?float
    {
        // Try USD pair first via best_bid_ask
        $query    = 'product_ids=' . urlencode($assetSymbol . '-USD');
        $response = $this->request('GET', '/api/v3/brokerage/best_bid_ask?' . $query);

        if ($response !== null && !isset($response['error'])) {
            foreach ($response['pricebooks'] ?? [] as $book) {
                if (($book['product_id'] ?? '') !== $assetSymbol . '-USD') continue;
                $bid = (float) ($book['bids'][0]['price'] ?? 0);
                $ask = (float) ($book['asks'][0]['price'] ?? 0);
                if ($bid > 0 && $ask > 0) {
                    $eurUsdRate = $this->getEurUsdRate() ?? 1.0;
                    return (($bid + $ask) / 2) / $eurUsdRate;
                }
            }
        }

        // Fallback: EUR pair directly
        $query    = 'product_ids=' . urlencode($assetSymbol . '-EUR');
        $response = $this->request('GET', '/api/v3/brokerage/best_bid_ask?' . $query);

        if ($response === null || isset($response['error'])) return null;

        foreach ($response['pricebooks'] ?? [] as $book) {
            if (($book['product_id'] ?? '') !== $assetSymbol . '-EUR') continue;
            $bid = (float) ($book['bids'][0]['price'] ?? 0);
            $ask = (float) ($book['asks'][0]['price'] ?? 0);
            if ($bid > 0 && $ask > 0) {
                return ($bid + $ask) / 2;
            }
        }

        return null;
    }

    /**
     * Fetch price in EUR cents directly from the EUR trading pair.
     * Used as fallback in getPrice() when USD pair is unavailable.
     */
    private function getPriceFromEurPair(string $assetSymbol): ?int
    {
        $priceFloat = $this->getPriceEurFloat($assetSymbol);
        if ($priceFloat === null || $priceFloat <= 0) return null;

        $cents = (int) round($priceFloat * 100);
        // Sub-cent coins (SHIB, etc.) round to 0 – return null so callers use the float method instead
        return $cents > 0 ? $cents : null;
    }

    /**
     * Batch-fetch prices for multiple assets in a single API call.
     * Fetches USD pairs internally and converts to EUR cents for display.
     * Returns ['BTC' => 8492300, ...] (EUR cents as int).
     */
    public function getPrices(array $assetSymbols): array
    {
        if (empty($assetSymbols)) return [];

        $query = implode('&', array_map(
            fn($s) => 'product_ids=' . urlencode($s . '-USD'),
            $assetSymbols
        ));
        $path = '/api/v3/brokerage/best_bid_ask?' . $query;

        $response = $this->request('GET', $path);
        if ($response === null || isset($response['error'])) return [];

        $eurUsdRate = $this->getEurUsdRate() ?? 1.0;

        $result = [];
        foreach ($response['pricebooks'] ?? [] as $book) {
            $productId = $book['product_id'] ?? '';
            $symbol    = str_replace('-USD', '', $productId);
            // Mid-price between best bid and ask; convert USD → EUR cents
            $bid = (float) ($book['bids'][0]['price'] ?? 0);
            $ask = (float) ($book['asks'][0]['price'] ?? 0);
            if ($bid > 0 && $ask > 0) {
                $midUsd = ($bid + $ask) / 2;
                $result[$symbol] = (int) round(($midUsd / $eurUsdRate) * 100);
            }
        }

        return $result;
    }

    /**
     * Derive EUR/USD exchange rate using BTC cross-rate (BTC-EUR / BTC-USD).
     * Returns e.g. 1.08 meaning 1 EUR = 1.08 USD.
     * Returns null if either price is unavailable.
     */
    public function getEurUsdRate(): ?float
    {
        $path = '/api/v3/brokerage/products?product_ids[]=BTC-EUR&product_ids[]=BTC-USD';
        $response = $this->request('GET', $path);
        if ($response === null || isset($response['error'])) return null;

        $btcEur = null;
        $btcUsd = null;
        foreach ($response['products'] ?? [] as $product) {
            $price = (float) ($product['price'] ?? 0);
            if ($price <= 0) continue;
            if ($product['product_id'] === 'BTC-EUR') $btcEur = $price;
            if ($product['product_id'] === 'BTC-USD') $btcUsd = $price;
        }

        if ($btcEur === null || $btcUsd === null || $btcEur <= 0) return null;

        return round($btcUsd / $btcEur, 6);
    }

    /**
     * Fetch USD price and base_increment for a single asset.
     * Returns ['price' => float (USD), 'base_increment' => string] or null.
     */
    public function getProductInfo(string $assetSymbol): ?array
    {
        $path     = '/api/v3/brokerage/products/' . urlencode($assetSymbol . '-USD');
        $response = $this->request('GET', $path);
        if ($response === null || isset($response['error'])) return null;

        $price = (float) ($response['price'] ?? 0);
        if ($price <= 0) return null;

        return [
            'price'          => $price,
            'base_increment' => $response['base_increment'] ?? '0.00000001',
        ];
    }

    /**
     * Fetch 24h volume and 7-day average volume for multiple assets.
     * Returns ['BTC' => ['volume_24h_usd' => 1234567, 'volume_7d_avg_usd' => 987654, 'volume_ratio' => 1.25], ...]
     * volume_ratio > 1 means today is above average (e.g. 1.4 = 40% above).
     */
    public function getVolumeData(array $assetSymbols): array
    {
        $result = [];

        foreach ($assetSymbols as $symbol) {
            $end   = time();
            $start = $end - (55 * 86400); // 55 days for SMA50 + volume

            $candles = $this->getCandles($symbol, 'ONE_DAY', $start, $end);
            if (!$candles || count($candles) < 2) {
                usleep(150000);
                continue;
            }

            $price = (float) ($candles[0]['close'] ?? 0);
            if ($price <= 0) {
                usleep(150000);
                continue;
            }

            $volumes = array_map(fn($c) => (float) $c['volume'], $candles);
            $today   = $volumes[0];
            $prev7d  = array_slice($volumes, 1, 7); // always exactly 7 days
            $avg7d   = array_sum($prev7d) / count($prev7d);

            $result[$symbol] = [
                'volume_24h_usd'    => (int) round($today * $price),
                'volume_7d_avg_usd' => (int) round($avg7d * $price),
                'volume_ratio'      => $avg7d > 0 ? round($today / $avg7d, 2) : 1.0,
                'daily_candles'     => $candles, // for technical analysis reuse
            ];

            usleep(150000);
        }

        return $result;
    }

    /**
     * Fetch OHLCV candles for one asset. Returns newest-first array or null.
     */
    public function getCandles(string $symbol, string $granularity, int $start, int $end): ?array
    {
        $path     = '/api/v3/brokerage/products/' . urlencode($symbol . '-USD')
                  . '/candles?start=' . $start . '&end=' . $end . '&granularity=' . $granularity;
        $response = $this->request('GET', $path);
        if (!$response || isset($response['error'])) return null;
        return $response['candles'] ?? null;
    }

    /**
     * Fetch hourly candles for technical analysis (RSI, MACD).
     * Returns newest-first array or null.
     */
    public function getHourlyCandles(string $symbol, int $hours = 60): ?array
    {
        $end   = time();
        $start = $end - ($hours * 3600);
        return $this->getCandles($symbol, 'ONE_HOUR', $start, $end);
    }

    /**
     * Derive number of decimal places from a Coinbase base_increment string.
     * e.g. "1" → 0, "0.01" → 2, "0.00000001" → 8
     */
    private function decimalPlacesFromIncrement(string $increment): int
    {
        if (!str_contains($increment, '.')) return 0;
        return strlen(rtrim(explode('.', $increment)[1], '0') ?: '0');
    }

    /**
     * Place a market IOC order.
     * amountCents is always in EUR cents. Internally booked via USD pairs;
     * EUR amounts are converted to USD using the live EUR/USD rate.
     * Returns the order data array or null on failure.
     */
    public function placeMarketOrder(string $assetSymbol, string $side, int $amountCents): ?array
    {
        $path = '/api/v3/brokerage/orders';

        $clientOrderId = 'tradebot-' . uniqid();

        // Coinbase requires:
        //   BUY  → EUR pair + quote_size in EUR  (cash is held in EUR)
        //   SELL → USD pair + base_size in crypto (more assets have USD pairs)
        if (strtoupper($side) === 'SELL') {
            $productId  = $assetSymbol . '-EUR';
            $eurProduct = $this->request('GET', '/api/v3/brokerage/products/' . urlencode($productId));
            if (!$eurProduct || isset($eurProduct['error']) || ($eurProduct['price'] ?? 0) <= 0) {
                Log::error('CoinbaseService::placeMarketOrder: could not fetch EUR product info for sell', ['asset' => $assetSymbol]);
                return null;
            }
            $priceEur  = (float) $eurProduct['price'];
            $decimals  = $this->decimalPlacesFromIncrement($eurProduct['base_increment'] ?? '0.001');
            $baseSize  = number_format(($amountCents / 100) / $priceEur, $decimals, '.', '');
            $orderSize = ['base_size' => $baseSize];
        } else {
            $productId = $assetSymbol . '-EUR';
            $orderSize = ['quote_size' => number_format($amountCents / 100, 2, '.', '')];
        }

        $body = [
            'client_order_id' => $clientOrderId,
            'product_id'      => $productId,
            'side'            => strtoupper($side),
            'order_configuration' => [
                'market_market_ioc' => $orderSize,
            ],
        ];

        $response = $this->request('POST', $path, $body);
        if ($response === null) return null;

        if (isset($response['error'])) {
            Log::error('CoinbaseService::placeMarketOrder error', [
                'error'           => $response['error'],
                'error_details'   => $response['error_details'] ?? null,
                'preview_failure' => $response['preview_failure_reason'] ?? null,
                'asset'           => $assetSymbol,
                'side'            => $side,
                'amount'          => $amountCents,
                'body'            => $body,
            ]);
            return $response; // Return error response so caller can inspect the error code
        }

        return $response['success_response'] ?? $response;
    }

    /**
     * Get order status by exchange order ID.
     */
    public function getOrderStatus(string $orderId): ?array
    {
        $path     = '/api/v3/brokerage/orders/historical/' . $orderId;
        $response = $this->request('GET', $path);
        if ($response === null) return null;

        if (isset($response['error'])) {
            Log::error('CoinbaseService::getOrderStatus error', ['error' => $response['error']]);
            return null;
        }

        return $response['order'] ?? $response;
    }

    private function request(string $method, string $path, array $body = []): ?array
    {
        // Rate limiting: 10 req/s private endpoint
        $key = 'coinbase-api';
        if (RateLimiter::tooManyAttempts($key, 10)) {
            Log::warning('CoinbaseService: rate limit hit, waiting');
            sleep(1);
        }
        RateLimiter::hit($key, 1);

        try {
            $jwt  = $this->buildJwt($method, $path);
            $http = Http::withHeaders([
                'Authorization' => 'Bearer ' . $jwt,
                'Content-Type'  => 'application/json',
            ])->timeout(30);

            $url = $this->baseUrl . $path;

            $response = match (strtoupper($method)) {
                'GET'    => $http->get($url),
                'POST'   => $http->post($url, $body),
                'DELETE' => $http->delete($url),
                default  => null,
            };

            if ($response === null) return null;

            if (!$response->successful() && $response->status() !== 400) {
                Log::error('CoinbaseService: HTTP error', ['status' => $response->status(), 'path' => $path]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('CoinbaseService: exception', ['message' => $e->getMessage(), 'path' => $path]);
            return null;
        }
    }

    private function buildJwt(string $method, string $path): string
    {
        // Strip query string from path for URI claim
        $pathWithoutQuery = strtok($path, '?');
        $uri = strtoupper($method) . ' api.coinbase.com' . $pathWithoutQuery;

        // Extract key ID from full API key string
        $keyId = $this->apiKey;

        $payload = [
            'sub' => $this->apiKey,
            'iss' => 'cdp',
            'nbf' => time(),
            'exp' => time() + 120,
            'uri' => $uri,
        ];

        // The secret may have literal \n escape sequences from env; normalize them
        $secret = str_replace('\n', "\n", $this->apiSecret);

        return JWT::encode($payload, $secret, 'EdDSA', $keyId);
    }
}
