<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'channel', 'level', 'message', 'context',
        'source_id', 'analysis_id', 'execution_id',
    ];

    protected $casts = [
        'context'    => 'array',
        'created_at' => 'datetime',
    ];

    public function scopeForLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    public function scopeForChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }
}
