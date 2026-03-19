<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Analysis extends Model
{
    protected $fillable = [
        'triggered_by', 'portfolio_snapshot', 'articles_evaluated',
        'signals_summary', 'claude_reasoning', 'prompt_tokens', 'completion_tokens',
    ];

    protected $casts = [
        'portfolio_snapshot'  => 'array',
        'articles_evaluated'  => 'array',
        'signals_summary'     => 'array',
        'prompt_tokens'       => 'integer',
        'completion_tokens'   => 'integer',
    ];

    public function tradeDecisions(): HasMany
    {
        return $this->hasMany(TradeDecision::class);
    }

    public function evaluatedArticles()
    {
        return Article::whereIn('id', $this->articles_evaluated ?? [])->get();
    }
}
