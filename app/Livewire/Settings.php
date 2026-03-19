<?php
namespace App\Livewire;

use App\Services\TradingSettings;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tradebot', ['title' => 'Settings'])]
class Settings extends Component
{
    public string $primaryModel  = 'claude';
    public string $fallbackModel = 'gemini';

    public const MODEL_OPTIONS = [
        'claude' => 'Claude (Sonnet)',
        'gemini' => 'Gemini (CLI)',
    ];

    public const FALLBACK_OPTIONS = [
        'claude' => 'Claude (Sonnet)',
        'gemini' => 'Gemini (CLI)',
        'none'   => 'Kein Fallback',
    ];

    public function mount(): void
    {
        $this->primaryModel  = TradingSettings::primaryModel();
        $this->fallbackModel = TradingSettings::fallbackModel();
    }

    public function saveModels(): void
    {
        $this->validate([
            'primaryModel'  => ['required', 'in:claude,gemini'],
            'fallbackModel' => ['required', 'in:claude,gemini,none'],
        ]);

        TradingSettings::setPrimaryModel($this->primaryModel);
        TradingSettings::setFallbackModel($this->fallbackModel);

        $this->dispatch('saved');
    }

    public function render()
    {
        return view('livewire.settings', [
            'modelOptions'    => self::MODEL_OPTIONS,
            'fallbackOptions' => self::FALLBACK_OPTIONS,
        ]);
    }
}
