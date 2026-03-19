<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Execution extends Model
{
    protected $fillable = [
        'trade_decision_id', 'mode', 'status', 'exchange_order_id',
        'asset_symbol', 'action', 'amount_usd', 'price_at_execution', 'fee_usd', 'failure_reason',
    ];

    protected $casts = [
        'amount_usd'          => 'integer',
        'price_at_execution'  => 'integer',
        'fee_usd'             => 'integer',
    ];

    public function tradeDecision(): BelongsTo
    {
        return $this->belongsTo(TradeDecision::class);
    }

    public function amountInDollars(): float
    {
        return $this->amount_usd / 100;
    }

    public function priceInDollars(): ?float
    {
        return $this->price_at_execution ? $this->price_at_execution / 100 : null;
    }

    public function feeInDollars(): ?float
    {
        return $this->fee_usd ? $this->fee_usd / 100 : null;
    }
}
