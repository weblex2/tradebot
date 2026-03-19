<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_id', 'url', 'title', 'content', 'content_hash',
        'published_at', 'sentiment_score', 'is_processed',
    ];

    protected $casts = [
        'published_at'    => 'datetime',
        'sentiment_score' => 'decimal:3',
        'is_processed'    => 'boolean',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function sentimentSignals(): HasMany
    {
        return $this->hasMany(SentimentSignal::class);
    }
}
