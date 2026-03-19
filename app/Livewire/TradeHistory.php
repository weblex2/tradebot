<?php
namespace App\Livewire;

use App\Models\Execution;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.tradebot', ['title' => 'Trade History'])]
class TradeHistory extends Component
{
    use WithPagination;

    public string $filterMode   = '';
    public string $filterStatus = '';
    public string $filterAsset  = '';

    public function updatingFilterMode():   void { $this->resetPage(); }
    public function updatingFilterStatus(): void { $this->resetPage(); }
    public function updatingFilterAsset():  void { $this->resetPage(); }

    public function render()
    {
        $query = Execution::with(['tradeDecision.analysis'])
            ->latest();

        if ($this->filterMode)   $query->where('mode', $this->filterMode);
        if ($this->filterStatus) $query->where('status', $this->filterStatus);
        if ($this->filterAsset)  $query->where('asset_symbol', $this->filterAsset);

        $executions = $query->paginate(25);

        $stats = [
            'total'   => Execution::count(),
            'filled'  => Execution::where('status', 'filled')->count(),
            'failed'  => Execution::where('status', 'failed')->count(),
            'paper'   => Execution::where('mode', 'paper')->count(),
            'live'    => Execution::where('mode', 'live')->count(),
        ];

        return view('livewire.trade-history', compact('executions', 'stats'));
    }
}
