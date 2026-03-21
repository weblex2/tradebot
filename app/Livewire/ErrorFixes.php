<?php

namespace App\Livewire;

use App\Models\ErrorFix;
use Livewire\Attributes\Layout;
use App\Services\TradingSettings;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.tradebot', ['title' => 'Auto Fixes'])]
class ErrorFixes extends Component
{
    use WithPagination;

    public string $filterType    = '';
    public string $filterApplied = '';

    public function updatingFilterType():    void { $this->resetPage(); }
    public function updatingFilterApplied(): void { $this->resetPage(); }

    public function deleteAll(): void
    {
        ErrorFix::query()->delete();
        $this->resetPage();
    }

    public function render()
    {
        $query = ErrorFix::latest();

        if ($this->filterType)    $query->where('fix_type', $this->filterType);
        if ($this->filterApplied !== '') $query->where('fix_applied', (bool) $this->filterApplied);

        $fixes = $query->paginate(TradingSettings::perPage());

        $stats = [
            'total'    => ErrorFix::count(),
            'applied'  => ErrorFix::where('fix_applied', true)->count(),
            'pending'  => ErrorFix::where('fix_applied', false)->count(),
            'code'     => ErrorFix::where('fix_type', 'code')->count(),
        ];

        return view('livewire.error-fixes', compact('fixes', 'stats'));
    }
}
