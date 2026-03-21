<?php

namespace App\Livewire;

use App\Models\Discussion;
use App\Services\DiscussionService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.tradebot', ['title' => 'AI Discussions'])]
class Discussions extends Component
{
    use WithPagination;

    public string $filterStatus = '';
    public ?int   $expandedId   = null;

    public bool $isGenerating   = false;
    public bool $isDiscussing   = false;
    public bool $isImplementing = false;

    public function updatingFilterStatus(): void { $this->resetPage(); }

    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function runGenerate(DiscussionService $service): void
    {
        $this->isGenerating = true;
        $service->generateSuggestions();
        $this->isGenerating = false;
        $this->resetPage();
    }

    public function runDiscuss(DiscussionService $service): void
    {
        $this->isDiscussing = true;

        $discussions = Discussion::whereIn('status', ['pending', 'discussing'])
                                 ->where('round', '<', $service->maxRounds())
                                 ->orderBy('priority', 'desc')
                                 ->get();

        foreach ($discussions as $d) {
            $service->runDiscussionRound($d);
        }

        $this->isDiscussing = false;
    }

    public function runDiscussOne(int $id, DiscussionService $service): void
    {
        $d = Discussion::findOrFail($id);
        if (in_array($d->status, ['pending', 'discussing']) && $d->round < $service->maxRounds()) {
            $service->runDiscussionRound($d);
        }
    }

    public function runImplement(int $id, DiscussionService $service): void
    {
        $d = Discussion::findOrFail($id);
        $service->implementChanges($d);
    }

    public function deleteDiscussion(int $id): void
    {
        Discussion::findOrFail($id)->delete();
        if ($this->expandedId === $id) {
            $this->expandedId = null;
        }
        $this->resetPage();
    }

    public function render()
    {
        $query = Discussion::latest();
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        $discussions = $query->paginate(10);

        $stats = [
            'total'        => Discussion::count(),
            'pending'      => Discussion::whereIn('status', ['pending', 'discussing'])->count(),
            'agreed'       => Discussion::where('status', 'agreed')->count(),
            'finished'     => Discussion::where('status', 'finished')->count(),
            'rejected'     => Discussion::where('status', 'rejected')->count(),
        ];

        return view('livewire.discussions', compact('discussions', 'stats'));
    }
}
