<?php

namespace Tests\Unit;

use App\Models\Analysis;
use App\Models\Setting;
use App\Models\TradeDecision;
use App\Services\CoinbaseService;
use App\Services\TradeExecutor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

/**
 * Tests that sell decisions are never incorrectly cancelled.
 *
 * Covered scenarios:
 * - Sell passes when available crypto exactly covers the needed amount
 * - Sell passes when price drifts up to 1% (rounding tolerance)
 * - Sell is cancelled when spot balance is genuinely insufficient (staked)
 * - Sell is cancelled when spot balance is zero
 * - Sell fails (not cancelled) when Coinbase API is unreachable
 * - Buy is cancelled when cash is insufficient
 * - Buy passes when cash is sufficient
 */
class TradeExecutorSellCheckTest extends TestCase
{
    use DatabaseTransactions;

    private Analysis $analysis;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure live mode so balance checks run
        Cache::forget('trading.mode');
        Setting::set('trading_mode', 'live');

        $this->analysis = Analysis::create([
            'triggered_by'       => 'test',
            'portfolio_snapshot' => [],
            'articles_evaluated' => [],
            'signals_summary'    => [],
            'claude_reasoning'   => 'test',
        ]);
    }

    private function makeDecision(string $action, int $amountCents, string $asset = 'BTC'): TradeDecision
    {
        return TradeDecision::create([
            'analysis_id'  => $this->analysis->id,
            'mode'         => 'live',
            'asset_symbol' => $asset,
            'action'       => $action,
            'amount_usd'   => $amountCents,
            'confidence'   => 80,
            'expires_at'   => now()->addMinutes(30),
            'rationale'    => 'test',
        ]);
    }

    private function makeCoinbaseMock(
        ?float $availableBalance,
        ?int   $priceCents,
        float  $cashEur = 1000.0,
    ): CoinbaseService {
        $mock = Mockery::mock(CoinbaseService::class);
        $mock->allows('getAvailableBalance')->andReturn($availableBalance);
        $mock->allows('getPrice')->andReturn($priceCents);
        $mock->allows('getPortfolioBreakdown')->andReturn([
            'cash_eur'  => $cashEur,
            'positions' => [],
        ]);
        return $mock;
    }

    private function executor(CoinbaseService $coinbase): TradeExecutor
    {
        return new TradeExecutor($coinbase);
    }

    // -------------------------------------------------------------------------
    // SELL tests
    // -------------------------------------------------------------------------

    /** BTC: available exactly equals needed – must pass */
    public function test_sell_passes_when_available_equals_needed(): void
    {
        // €61 at €61,000/BTC = exactly 0.001 BTC needed
        $decision = $this->makeDecision('sell', 6100, 'BTC');
        $coinbase = $this->makeCoinbaseMock(
            availableBalance: 0.001,
            priceCents:       6100000,
        );

        $execution = $this->executor($coinbase)->execute($decision, liveConfirmed: true);

        $this->assertNotEquals('cancelled', $execution->status,
            'Should not cancel: ' . ($execution->failure_reason ?? ''));
    }

    /** Price drifts 0.9% down between decision and execution – 1% tolerance must save it */
    public function test_sell_passes_with_minor_price_drift(): void
    {
        // Decision created at €61,000, now €60,451 (−0.9% drift)
        // Needed: €61 / €60,451 = 0.001009 BTC, available: 0.001 → within 1% tolerance
        $decision = $this->makeDecision('sell', 6100, 'BTC');
        $coinbase = $this->makeCoinbaseMock(
            availableBalance: 0.001,
            priceCents:       6045100,
        );

        $execution = $this->executor($coinbase)->execute($decision, liveConfirmed: true);

        $this->assertNotEquals('cancelled', $execution->status,
            'Minor price drift must not cancel sell: ' . ($execution->failure_reason ?? ''));
    }

    /** SOL 95% staked – only 0.066 of 1.29 available – must cancel */
    public function test_sell_cancelled_when_mostly_staked(): void
    {
        // Trying to sell €20 of SOL @ €78 = 0.256 SOL needed, only 0.066 available
        $decision = $this->makeDecision('sell', 2000, 'SOL');
        $coinbase = $this->makeCoinbaseMock(
            availableBalance: 0.066,
            priceCents:       7800,
        );

        $execution = $this->executor($coinbase)->execute($decision, liveConfirmed: true);

        $this->assertEquals('cancelled', $execution->status);
        $this->assertStringContainsString('spot balance', $execution->failure_reason);
    }

    /** Zero spot balance – must cancel */
    public function test_sell_cancelled_when_zero_balance(): void
    {
        $decision = $this->makeDecision('sell', 5000, 'ETH');
        $coinbase = $this->makeCoinbaseMock(
            availableBalance: 0.0,
            priceCents:       18600000,
        );

        $execution = $this->executor($coinbase)->execute($decision, liveConfirmed: true);

        $this->assertEquals('cancelled', $execution->status);
    }

    /** Coinbase API unavailable – must be failed (not cancelled) */
    public function test_sell_fails_not_cancelled_when_api_unreachable(): void
    {
        $decision = $this->makeDecision('sell', 5000, 'BTC');
        $coinbase = $this->makeCoinbaseMock(
            availableBalance: null,
            priceCents:       6100000,
        );

        $execution = $this->executor($coinbase)->execute($decision, liveConfirmed: true);

        $this->assertEquals('failed', $execution->status);
        $this->assertStringContainsString('Could not retrieve available balance', $execution->failure_reason);
    }

    // -------------------------------------------------------------------------
    // BUY tests
    // -------------------------------------------------------------------------

    /** Sufficient cash – buy must not be cancelled */
    public function test_buy_passes_with_sufficient_cash(): void
    {
        $decision = $this->makeDecision('buy', 5000, 'BTC'); // €50
        $coinbase = $this->makeCoinbaseMock(
            availableBalance: 0,
            priceCents:       6100000,
            cashEur:          500.0, // €500: after €50.50 order → €449.50 remaining, well above €200 reserve
        );

        $execution = $this->executor($coinbase)->execute($decision, liveConfirmed: true);

        $this->assertNotEquals('cancelled', $execution->status,
            'Buy should not cancel with €500 cash for €50 order: ' . ($execution->failure_reason ?? ''));
    }

    /** Not enough cash after 1% fee buffer – must cancel */
    public function test_buy_cancelled_when_cash_insufficient(): void
    {
        $decision = $this->makeDecision('buy', 5000, 'BTC'); // €50
        $coinbase = $this->makeCoinbaseMock(
            availableBalance: 0,
            priceCents:       6100000,
            cashEur:          40.0, // less than €50 + 1% fee = €50.50
        );

        $execution = $this->executor($coinbase)->execute($decision, liveConfirmed: true);

        $this->assertEquals('cancelled', $execution->status);
        $this->assertStringContainsString('Insufficient cash', $execution->failure_reason);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
