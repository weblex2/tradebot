<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SentimentSignal extends Model
{
    protected $fillable = [
        'article_id', 'asset_symbol', 'signal_score', 'signal_type',
    ];

    protected $casts = [
        'signal_score' => 'decimal:3',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
