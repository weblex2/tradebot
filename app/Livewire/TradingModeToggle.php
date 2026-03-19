<?php

namespace App\Livewire;

use App\Services\TradingSettings;
use Livewire\Component;

class TradingModeToggle extends Component
{
    public bool $isLive    = false;
    public bool $showModal = false;

    public function mount(): void
    {
        $this->isLive = TradingSettings::isLive();
    }

    public function requestToggle(): void
    {
        if (!$this->isLive) {
            // Paper → Live: show confirmation modal
            $this->showModal = true;
        } else {
            // Live → Paper: safe, switch immediately
            TradingSettings::setPaper();
            $this->isLive = false;
        }
    }

    public function confirmLive(): void
    {
        TradingSettings::setLive();
        $this->isLive    = true;
        $this->showModal = false;
    }

    public function cancelModal(): void
    {
        $this->showModal = false;
    }

    public function render()
    {
        return view('livewire.trading-mode-toggle');
    }
}
