<?php
namespace App\Livewire;

use App\Models\Analysis;
use Livewire\Attributes\Layout;
use App\Services\TradingSettings;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.tradebot', ['title' => 'Analysis'])]
class AnalysisViewer extends Component
{
    use WithPagination;

    public ?int $selectedId = null;
    public bool $showAll    = false;

    public function updatedShowAll(): void
    {
        $this->resetPage();
    }

    public function select(int $id): void
    {
        $this->selectedId = ($this->selectedId === $id) ? null : $id;
    }

    public function render()
    {
        $analyses = Analysis::withCount('tradeDecisions')
            ->when(!$this->showAll, fn($q) => $q->has('tradeDecisions'))
            ->latest()
            ->paginate(TradingSettings::perPage());

        $selected = $this->selectedId
            ? Analysis::with('tradeDecisions.execution')->find($this->selectedId)
            : null;

        return view('livewire.analysis-viewer', compact('analyses', 'selected'));
    }
}
