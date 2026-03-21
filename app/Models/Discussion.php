<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discussion extends Model
{
    protected $fillable = [
        'title',
        'title_hash',
        'suggestion',
        'affected_files',
        'priority',
        'status',
        'turns',
        'round',
        'consensus_summary',
        'implementation_notes',
    ];

    protected $casts = [
        'affected_files' => 'array',
        'turns'          => 'array',
    ];

    public function addTurn(string $role, string $content): void
    {
        $turns   = $this->turns ?? [];
        $turns[] = ['role' => $role, 'content' => $content, 'at' => now()->toIso8601String()];
        $this->turns = $turns;
        $this->round = count($turns);
        $this->save();
    }

    public function lastTurnContent(): ?string
    {
        $turns = $this->turns ?? [];
        return empty($turns) ? null : end($turns)['content'];
    }

    public function lastTurnRole(): ?string
    {
        $turns = $this->turns ?? [];
        return empty($turns) ? null : end($turns)['role'];
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'pending'      => 'text-white/50',
            'discussing'   => 'text-neon-blue',
            'agreed'       => 'neon-text-green',
            'rejected'     => 'text-red-400',
            'implementing' => 'text-yellow-400',
            'finished'     => 'neon-text-green',
            default        => 'text-white/40',
        };
    }

    public function priorityColor(): string
    {
        return match ($this->priority) {
            'high'   => 'text-red-400',
            'medium' => 'text-yellow-400',
            'low'    => 'text-white/50',
            default  => 'text-white/40',
        };
    }
}
