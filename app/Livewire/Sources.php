<?php
namespace App\Livewire;

use App\Models\Source;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.tradebot', ['title' => 'Sources'])]
class Sources extends Component
{
    use WithPagination;

    public bool $showForm = false;
    public ?int $editingId = null;

    public string $name            = '';
    public string $url             = '';
    public string $category        = 'news';
    public string $weight          = '1.00';
    public int    $refresh_minutes = 60;
    public bool   $is_active       = true;

    protected function rules(): array
    {
        return [
            'name'            => 'required|string|max:255',
            'url'             => 'required|url|max:2048',
            'category'        => 'required|in:news,social,blog,official,other',
            'weight'          => 'required|numeric|min:0.10|max:2.00',
            'refresh_minutes' => 'required|integer|min:5|max:1440',
            'is_active'       => 'boolean',
        ];
    }

    public function openCreate(): void
    {
        $this->reset(['name', 'url', 'category', 'weight', 'refresh_minutes', 'is_active', 'editingId']);
        $this->weight     = '1.00';
        $this->is_active  = true;
        $this->showForm   = true;
    }

    public function openEdit(int $id): void
    {
        $source = Source::findOrFail($id);
        $this->editingId        = $id;
        $this->name             = $source->name;
        $this->url              = $source->url;
        $this->category         = $source->category;
        $this->weight           = $source->weight;
        $this->refresh_minutes  = $source->refresh_minutes;
        $this->is_active        = $source->is_active;
        $this->showForm         = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name'            => $this->name,
            'url'             => $this->url,
            'category'        => $this->category,
            'weight'          => (float) $this->weight,
            'refresh_minutes' => $this->refresh_minutes,
            'is_active'       => $this->is_active,
        ];

        if ($this->editingId) {
            Source::findOrFail($this->editingId)->update($data);
        } else {
            Source::create($data);
        }

        $this->showForm  = false;
        $this->editingId = null;
        $this->dispatch('saved');
    }

    public function toggleActive(int $id): void
    {
        $source = Source::findOrFail($id);
        $source->update(['is_active' => !$source->is_active]);
    }

    public function delete(int $id): void
    {
        Source::findOrFail($id)->delete();
    }

    public function render()
    {
        $sources = Source::withCount('articles')->latest()->paginate(20);
        return view('livewire.sources', compact('sources'));
    }
}
