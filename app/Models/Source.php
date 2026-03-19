<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'url', 'category', 'weight', 'refresh_minutes', 'is_active', 'last_scraped_at',
    ];

    protected $casts = [
        'weight'          => 'decimal:2',
        'is_active'       => 'boolean',
        'last_scraped_at' => 'datetime',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function isDueForScrape(): bool
    {
        if ($this->last_scraped_at === null) {
            return true;
        }
        return $this->last_scraped_at->addMinutes($this->refresh_minutes)->isPast();
    }
}
