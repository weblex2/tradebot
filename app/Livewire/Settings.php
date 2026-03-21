<?php
namespace App\Livewire;

use App\Services\TradingSettings;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tradebot', ['title' => 'Settings'])]
class Settings extends Component
{
    public string $primaryModel       = 'claude';
    public string $fallbackModel      = 'gemini';
    public string $timezone           = 'UTC';
    public int    $minTradeUsd        = 1;
    public int    $maxTradeUsd        = 500;
    public int    $minConfidence      = 60;
    public int    $minReserveUsd      = 200;
    public float  $maxExposurePct     = 10.0;
    public int    $decisionTtlMinutes = 60;
    public int    $perPage            = 25;

    public const MODEL_OPTIONS = [
        'claude' => 'Claude (Sonnet)',
        'gemini' => 'Gemini (CLI)',
    ];

    public const FALLBACK_OPTIONS = [
        'claude' => 'Claude (Sonnet)',
        'gemini' => 'Gemini (CLI)',
        'none'   => 'Kein Fallback',
    ];

    // Grouped timezone list: ['Group' => ['tz_identifier' => 'Display Label']]
    public const TIMEZONE_GROUPS = [
        'Europa' => [
            'Europe/Berlin'     => 'Deutschland (Berlin)',
            'Europe/Vienna'     => 'Österreich (Wien)',
            'Europe/Zurich'     => 'Schweiz (Zürich)',
            'Europe/London'     => 'Großbritannien (London)',
            'Europe/Paris'      => 'Frankreich (Paris)',
            'Europe/Amsterdam'  => 'Niederlande (Amsterdam)',
            'Europe/Madrid'     => 'Spanien (Madrid)',
            'Europe/Rome'       => 'Italien (Rom)',
            'Europe/Warsaw'     => 'Polen (Warschau)',
            'Europe/Stockholm'  => 'Schweden (Stockholm)',
            'Europe/Moscow'     => 'Russland (Moskau)',
        ],
        'Amerika' => [
            'America/New_York'    => 'USA Eastern (New York)',
            'America/Chicago'     => 'USA Central (Chicago)',
            'America/Denver'      => 'USA Mountain (Denver)',
            'America/Los_Angeles' => 'USA Pacific (Los Angeles)',
            'America/Sao_Paulo'   => 'Brasilien (São Paulo)',
        ],
        'Asien / Pazifik' => [
            'Asia/Tokyo'      => 'Japan (Tokio)',
            'Asia/Shanghai'   => 'China (Shanghai)',
            'Asia/Singapore'  => 'Singapur',
            'Asia/Dubai'      => 'Dubai (VAE)',
            'Australia/Sydney'=> 'Australien (Sydney)',
        ],
        'Andere' => [
            'UTC' => 'UTC (Koordinierte Weltzeit)',
        ],
    ];

    public function mount(): void
    {
        $this->primaryModel       = TradingSettings::primaryModel();
        $this->fallbackModel      = TradingSettings::fallbackModel();
        $this->timezone           = TradingSettings::timezone();
        $this->minTradeUsd        = TradingSettings::minTradeUsd();
        $this->maxTradeUsd        = TradingSettings::maxTradeUsd();
        $this->minConfidence      = TradingSettings::minConfidence();
        $this->minReserveUsd      = (int) TradingSettings::minReserve();
        $this->maxExposurePct     = TradingSettings::maxExposurePct();
        $this->decisionTtlMinutes = TradingSettings::decisionTtlMinutes();
        $this->perPage            = TradingSettings::perPage();
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

    public function saveRiskParams(): void
    {
        $this->validate([
            'minTradeUsd'        => ['required', 'integer', 'min:1', 'max:10000'],
            'maxTradeUsd'        => ['required', 'integer', 'min:1', 'max:100000'],
            'minConfidence'      => ['required', 'integer', 'min:0', 'max:100'],
            'minReserveUsd'      => ['required', 'integer', 'min:0'],
            'maxExposurePct'     => ['required', 'numeric', 'min:1', 'max:100'],
            'decisionTtlMinutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'perPage'            => ['required', 'integer', 'min:5', 'max:200'],
        ]);

        if ($this->minTradeUsd > $this->maxTradeUsd) {
            $this->addError('minTradeUsd', 'Minimum muss kleiner als Maximum sein.');
            return;
        }

        TradingSettings::setMinTradeUsd($this->minTradeUsd);
        TradingSettings::setMaxTradeUsd($this->maxTradeUsd);
        TradingSettings::setMinConfidence($this->minConfidence);
        TradingSettings::setMinReserve($this->minReserveUsd);
        TradingSettings::setMaxExposurePct($this->maxExposurePct);
        TradingSettings::setDecisionTtlMinutes($this->decisionTtlMinutes);
        TradingSettings::setPerPage($this->perPage);

        $this->dispatch('saved');
    }

    public function saveTimezone(): void
    {
        $allTimezones = collect(self::TIMEZONE_GROUPS)
            ->flatMap(fn($zones) => array_keys($zones))
            ->all();

        $this->validate([
            'timezone' => ['required', 'string', 'in:' . implode(',', $allTimezones)],
        ]);

        TradingSettings::setTimezone($this->timezone);

        // Apply immediately for the current request so the flash message is correct
        date_default_timezone_set($this->timezone);

        $this->dispatch('saved');
    }

    public function render()
    {
        return view('livewire.settings', [
            'modelOptions'    => self::MODEL_OPTIONS,
            'fallbackOptions' => self::FALLBACK_OPTIONS,
            'timezoneGroups'  => self::TIMEZONE_GROUPS,
        ]);
    }
}
