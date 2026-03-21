<?php
namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tradebot', ['title' => 'Dokumentation'])]
class Docs extends Component
{
    public function render()
    {
        return view('livewire.docs');
    }
}
