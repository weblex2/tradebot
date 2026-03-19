<?php
namespace App\Livewire;

use App\Models\BotLog;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.tradebot', ['title' => 'Bot Logs'])]
class BotLogs extends Component
{
    use WithPagination;

    public string $filterLevel   = '';
    public string $filterChannel = '';
    public string $filterSince   = '24';

    public function updatingFilterLevel():   void { $this->resetPage(); }
    public function updatingFilterChannel(): void { $this->resetPage(); }
    public function updatingFilterSince():   void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->filterLevel   = '';
        $this->filterChannel = '';
        $this->filterSince   = '24';
        $this->resetPage();
    }

    public function render()
    {
        $query = BotLog::orderByDesc('created_at');

        if ($this->filterLevel)   $query->where('level', $this->filterLevel);
        if ($this->filterChannel) $query->where('channel', $this->filterChannel);
        if ($this->filterSince !== 'all') {
            $query->where('created_at', '>=', now()->subHours((int) $this->filterSince));
        }

        $logs = $query->paginate(50);

        $since = now()->subHours(24);
        $counts = [
            'errors'   => BotLog::where('level', 'error')->where('created_at', '>=', $since)->count(),
            'warnings' => BotLog::where('level', 'warning')->where('created_at', '>=', $since)->count(),
            'total'    => BotLog::where('created_at', '>=', $since)->count(),
        ];

        return view('livewire.bot-logs', compact('logs', 'counts'));
    }
}
