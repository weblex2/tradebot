<?php
namespace App\Livewire;

use App\Models\Execution;
use App\Models\TradeDecision;
use Livewire\Attributes\Layout;
use App\Services\TradingSettings;
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

    public function deleteByStatus(string $status): void
    {
        if (!in_array($status, ['failed', 'cancelled', 'pending'])) return;

        // Delete the executions and their parent decisions together,
        // so no orphaned decisions remain that would show as "pending" in the dashboard.
        $decisionIds = Execution::where('status', $status)->pluck('trade_decision_id');
        Execution::where('status', $status)->delete();
        if ($decisionIds->isNotEmpty()) {
            TradeDecision::whereIn('id', $decisionIds)->delete();
        }

        $this->resetPage();
    }

    public function render()
    {
        $query = Execution::with(['tradeDecision.analysis'])
            ->latest();

        if ($this->filterMode)   $query->where('mode', $this->filterMode);
        if ($this->filterStatus) $query->where('status', $this->filterStatus);
        if ($this->filterAsset)  $query->where('asset_symbol', $this->filterAsset);

        $executions = $query->paginate(TradingSettings::perPage());

        $stats = [
            'total'     => Execution::count(),
            'filled'    => Execution::where('status', 'filled')->count(),
            'failed'    => Execution::where('status', 'failed')->count(),
            'cancelled' => Execution::where('status', 'cancelled')->count(),
            'pending'   => Execution::where('status', 'pending')->count(),
        ];

        return view('livewire.trade-history', compact('executions', 'stats'));
    }
}
