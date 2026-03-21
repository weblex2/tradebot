<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TradeDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'analysis_id', 'mode', 'asset_symbol', 'action', 'confidence',
        'amount_usd', 'stop_loss_pct', 'take_profit_pct', 'rationale', 'expires_at',
    ];

    protected $casts = [
        'confidence'      => 'integer',
        'amount_usd'      => 'integer',
        'stop_loss_pct'   => 'decimal:2',
        'take_profit_pct' => 'decimal:2',
        'expires_at'      => 'datetime',
    ];

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }

    public function execution(): HasOne
    {
        return $this->hasOne(Execution::class)->latestOfMany();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function amountInDollars(): float
    {
        return $this->amount_usd / 100;
    }
}
