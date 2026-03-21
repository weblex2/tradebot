<?php

namespace App\Livewire;

use Livewire\Component;

class SystemStatus extends Component
{
    public function render()
    {
        return view('livewire.system-status', [
            'queueRunning' => !empty(shell_exec('pgrep -f "queue:work" 2>/dev/null')),
            'nextScraper'  => $this->nextMinuteMark([0, 15, 30, 45]),
            'nextAnalyzer' => $this->nextMinuteMark([5, 20, 35, 50]),
            'nextAutoFix'  => $this->nextMinuteMark([0, 20, 40]),
        ]);
    }

    private function nextMinuteMark(array $minutes): \Carbon\Carbon
    {
        $now = now();
        $cur = (int) $now->format('i');
        foreach ($minutes as $m) {
            if ($m > $cur) {
                return $now->copy()->setMinute($m)->setSecond(0);
            }
        }
        return $now->copy()->addHour()->setMinute($minutes[0])->setSecond(0);
    }
}
