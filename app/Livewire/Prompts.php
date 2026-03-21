<?php

namespace App\Livewire;

use App\Models\PromptTemplate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tradebot', ['title' => 'Prompts'])]
class Prompts extends Component
{
    // Aktiver Prompt (wird in der Editiermaske angezeigt)
    public ?int    $editingId      = null;
    public string  $editingKey     = '';
    public string  $editingName    = '';
    public string  $editingContent = '';

    // Originaler Inhalt zum Reset
    public string  $originalContent = '';

    // Edit-Modus: erst nach Klick auf "Bearbeiten" aktiv
    public bool    $isEditing = false;

    public function selectPrompt(int $id): void
    {
        $prompt = PromptTemplate::findOrFail($id);

        $this->editingId       = $prompt->id;
        $this->editingKey      = $prompt->key;
        $this->editingName     = $prompt->name;
        $this->editingContent  = $prompt->content;
        $this->originalContent = $prompt->content;
        $this->isEditing       = false;  // immer erst im Lesemodus öffnen

        $this->resetValidation();
    }

    public function startEditing(): void
    {
        $this->isEditing = true;
    }

    public function cancelEditing(): void
    {
        $this->editingContent = $this->originalContent;
        $this->isEditing      = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate([
            'editingContent' => ['required', 'string', 'min:20'],
        ], [
            'editingContent.required' => 'Der Prompt-Text darf nicht leer sein.',
            'editingContent.min'      => 'Der Prompt muss mindestens 20 Zeichen enthalten.',
        ]);

        PromptTemplate::where('id', $this->editingId)->update([
            'content' => $this->editingContent,
        ]);

        $this->originalContent = $this->editingContent;
        $this->isEditing       = false;
        $this->dispatch('saved');
    }

    public function resetToOriginal(): void
    {
        $this->editingContent = $this->originalContent;
    }

    public function toggleActive(int $id): void
    {
        $prompt = PromptTemplate::findOrFail($id);
        $prompt->update(['is_active' => !$prompt->is_active]);

        // Falls der aktuell bearbeitete Prompt umgeschaltet wurde, nichts weiter
    }

    public function render()
    {
        return view('livewire.prompts', [
            'prompts' => PromptTemplate::orderBy('name')->get(),
        ]);
    }
}
