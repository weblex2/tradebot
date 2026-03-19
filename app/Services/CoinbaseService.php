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

        $positions = [];
        foreach ($breakdown['spot_positions'] ?? [] as $pos) {
            $positions[] = [
                'currency'   => $pos['asset'] ?? '',
                'balance'    => (float) ($pos['total_balance_crypto'] ?? 0),
                'value_eur'  => (float) ($pos['total_balance_fiat'] ?? 0),
                'allocation' => (float) ($pos['allocation'] ?? 0),
            ];
        }

        // Sort by EUR value descending
        usort($positions, fn($a, $b) => $b['value_eur'] <=> $a['value_eur']);

        $cashEur = (float) ($balances['total_cash_equivalent_balance']['value'] ?? 0);

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
        return $prices[$assetSymbol] ?? null;
    }

    /**
     * Batch-fetch prices for multiple assets in a single API call.
     * Returns ['BTC' => 69923.5, 'SHIB' => 0.00000574, ...] (USD as float).
     */
    public function getPrices(array $assetSymbols): array
    {
        if (empty($assetSymbols)) return [];

        $query = implode('&', array_map(
            fn($s) => 'product_ids[]=' . urlencode($s . '-EUR'),
            $assetSymbols
        ));
        $path = '/api/v3/brokerage/products?' . $query;

        $response = $this->request('GET', $path);
        if ($response === null || isset($response['error'])) return [];

        $result = [];
        foreach ($response['products'] ?? [] as $product) {
            $productId = $product['product_id'] ?? '';
            $symbol    = str_replace('-EUR', '', $productId);
            $price     = (float) ($product['price'] ?? 0);
            if ($price > 0) {
                $result[$symbol] = $price;
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
     * Place a market IOC order.
     * Returns the order data array or null on failure.
     */
    public function placeMarketOrder(string $assetSymbol, string $side, int $amountCents): ?array
    {
        $path = '/api/v3/brokerage/orders';

        $clientOrderId = 'tradebot-' . uniqid();
        $productId     = $assetSymbol . '-EUR';
        $quoteSize     = number_format($amountCents / 100, 2, '.', '');

        $body = [
            'client_order_id' => $clientOrderId,
            'product_id'      => $productId,
            'side'            => strtoupper($side),
            'order_configuration' => [
                'market_market_ioc' => [
                    'quote_size' => $quoteSize,
                ],
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
            return null;
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
