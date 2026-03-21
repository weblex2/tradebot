<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TradeDecisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'analysis_id'     => null,
            'mode'            => 'live',
            'asset_symbol'    => $this->faker->randomElement(['BTC', 'ETH', 'SOL', 'XRP']),
            'action'          => $this->faker->randomElement(['buy', 'sell', 'hold']),
            'confidence'      => $this->faker->numberBetween(60, 95),
            'amount_usd'      => $this->faker->numberBetween(1000, 50000),
            'stop_loss_pct'   => null,
            'take_profit_pct' => null,
            'rationale'       => $this->faker->sentence(),
            'expires_at'      => now()->addMinutes(30),
        ];
    }
}
