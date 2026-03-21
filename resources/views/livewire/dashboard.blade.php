<div wire:poll.30s>

    {{-- Stats grid --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

        <div class="glass-card p-5">
            <div class="text-xs text-white/40 uppercase tracking-wider mb-2">Portfolio Value</div>
            <div class="text-3xl font-bold neon-text-green">
                @if($portfolio['total_eur'] ?? null)
                    €{{ number_format($portfolio['total_eur'], 2) }}
                @else
                    —
                @endif
            </div>
            <div class="text-xs text-white/30 mt-1">coinbase total</div>
        </div>

        <div class="glass-card p-5">
            <div class="text-xs text-white/40 uppercase tracking-wider mb-2">Verfügbar</div>
            <div class="text-3xl font-bold neon-text-blue">
                @if(($portfolio['cash_eur'] ?? null) !== null)
                    €{{ number_format($portfolio['cash_eur'], 2) }}
                @else
                    —
                @endif
            </div>
            <div class="text-xs text-white/30 mt-1">für neue Käufe</div>
        </div>

        <div class="glass-card p-5">
            <div class="text-xs text-white/40 uppercase tracking-wider mb-2">G/V (offen)</div>
            @if($totalPnl !== null)
                <div class="text-3xl font-bold {{ $totalPnl >= 0 ? 'neon-text-green' : 'neon-text-red' }}">
                    {{ $totalPnl >= 0 ? '+' : '' }}€{{ number_format($totalPnl, 2) }}
                </div>
                <div class="text-xs text-white/30 mt-1">unrealisiert</div>
            @else
                <div class="text-3xl font-bold text-white/20">–</div>
                <div class="text-xs text-white/30 mt-1">keine offenen Positionen</div>
            @endif
        </div>

        {{-- DB Stats --}}
        <div class="glass-card p-5">
            <div class="text-xs text-white/40 uppercase tracking-wider mb-2">Datenbank</div>
            <div class="text-3xl font-bold neon-text-blue">{{ $dbStats['total_mb'] }} MB</div>
            <div class="flex gap-3 mt-2">
                @foreach($dbStats['tables'] as $table)
                    <div class="text-xs text-white/30 truncate">{{ $table->table_name }}: {{ $table->size_mb }}MB</div>
                @endforeach
            </div>
        </div>

        <div class="glass-card p-5">
            <div class="text-xs text-white/40 uppercase tracking-wider mb-2">Sources / Articles</div>
            <div class="text-3xl font-bold text-white">{{ $stats['total_sources'] }}</div>
            <div class="text-xs text-white/30 mt-1">{{ $stats['articles_today'] }} Artikel heute</div>
        </div>

        <div class="glass-card p-5">
            <div class="text-xs text-white/40 uppercase tracking-wider mb-2">Signals (24h)</div>
            <div class="text-3xl font-bold neon-text-blue">{{ $stats['signals_6h'] }}</div>
            <div class="text-xs text-white/30 mt-1">active signals</div>
        </div>

        <div class="glass-card p-5">
            <div class="text-xs text-white/40 uppercase tracking-wider mb-2">Trades Today</div>
            <div class="text-3xl font-bold neon-text-green">{{ $stats['executions_today'] }}</div>
            <div class="text-xs text-white/30 mt-1">{{ \App\Services\TradingSettings::mode() }} trades</div>
        </div>

        <div class="glass-card p-5">
            <div class="text-xs text-white/40 uppercase tracking-wider mb-2">Mindest-Reserve</div>
            <form wire:submit.prevent="saveMinReserve" class="flex items-center gap-2 mt-1">
                <span class="text-white/50 text-sm">€</span>
                <input
                    type="number"
                    min="0"
                    step="1"
                    wire:model="minReserveInput"
                    class="input-glass w-full text-lg font-bold"
                >
                <button type="submit" class="text-xs px-2 py-1 rounded-lg bg-neon-blue/20 border border-neon-blue/40 text-neon-blue hover:bg-neon-blue/30 transition-colors shrink-0">
                    OK
                </button>
            </form>
            <div class="text-xs text-white/30 mt-2">nicht unterschreiten</div>
        </div>

    </div>

    {{-- Recent decisions + Asset sentiment --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

        {{-- Recent Decisions --}}
        <div class="glass-card p-6 lg:col-span-2 flex flex-col" style="max-height: 420px;">
            <div class="flex items-center justify-between mb-4 shrink-0">
                <h2 class="text-sm font-semibold text-white/60 uppercase tracking-wider">Recent Decisions</h2>

                <div class="flex items-center gap-3">
                {{-- Test Trade Button --}}
                <button wire:click="createTestTrade"
                        wire:confirm="Test-Trade erstellen: SOL verkaufen für €1?"
                        class="text-xs text-white/30 hover:text-neon-blue transition-colors inline-flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Test Trade
                </button>

                {{-- Clear pending decisions (no execution) --}}
                @if($expiredPendingCount > 0)
                    <button wire:click="deleteExpiredDecisions"
                            wire:confirm="Alle {{ $expiredPendingCount }} pending Decisions (ohne Execution) löschen?"
                            class="text-xs text-white/30 hover:text-neon-red transition-colors inline-flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        {{ $expiredPendingCount }} pending
                    </button>
                @endif

                {{-- Auto Trade Toggle --}}
                <button wire:click="toggleAutoTrade" class="flex items-center gap-2.5 group">
                    <span class="text-xs font-medium {{ $autoTrade ? 'text-neon-green' : 'text-white/40' }} transition-colors">
                        Auto Trade
                    </span>
                    <div class="relative w-10 h-5 rounded-full transition-colors duration-300 {{ $autoTrade ? 'bg-neon-green/30 border border-neon-green/50' : 'bg-white/10 border border-white/20' }}">
                        <div class="absolute top-0.5 w-4 h-4 rounded-full shadow transition-all duration-300 {{ $autoTrade ? 'left-5 bg-neon-green' : 'left-0.5 bg-white/40' }}"></div>
                    </div>
                </button>
                </div>
            </div>

            @if($recentDecisions->isEmpty())
                <div class="text-center py-8 text-white/30 text-sm">No decisions yet. Run <code class="text-neon-blue">php artisan trade:analyze</code></div>
            @else
                <div class="overflow-x-auto overflow-y-auto">
                <table class="table-glass">
                    <thead>
                        <tr>
                            <th>Zeit</th>
                            <th class="text-white/30">ID</th>
                            <th>Asset</th>
                            <th>Action</th>
                            <th class="hidden md:table-cell">Confidence</th>
                            <th>Betrag</th>
                            <th class="hidden lg:table-cell">Kaufpreis</th>
                            <th class="hidden lg:table-cell">Akt. Preis</th>
                            <th>G/V</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentDecisions as $d)
                            <tr>
                                <td class="text-xs text-white/40 whitespace-nowrap">
                                    {{ $d->created_at->local()->format('d.m.') }}
                                    <div class="text-white/25">{{ $d->created_at->local()->format('H:i') }}</div>
                                </td>
                                <td class="font-mono text-white/25 text-xs whitespace-nowrap">
                                    #{{ $d->id }}
                                    @if($d->execution)
                                        <div class="text-white/20">#{{ $d->execution->id }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <x-asset-icon :symbol="$d->asset_symbol" :size="5" />
                                        <span class="font-medium text-white">{{ $d->asset_symbol }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-xs px-2 py-0.5 rounded-full
                                        @if($d->action === 'buy') bg-neon-green/10 text-neon-green border border-neon-green/20
                                        @elseif($d->action === 'sell') bg-neon-red/10 text-neon-red border border-neon-red/20
                                        @else bg-white/5 text-white/40 border border-white/10 @endif">
                                        {{ strtoupper($d->action) }}
                                    </span>
                                </td>
                                <td class="hidden md:table-cell">
                                    <div class="flex items-center gap-2">
                                        <div class="w-16 h-1 bg-white/10 rounded-full">
                                            <div class="h-full rounded-full bg-neon-blue" style="width: {{ $d->confidence }}%"></div>
                                        </div>
                                        <span class="text-xs font-mono text-white/60">{{ $d->confidence }}%</span>
                                    </div>
                                </td>
                                <td class="font-mono text-sm">
                                    €{{ number_format($d->amountInDollars(), 2) }}
                                    @if($d->execution?->filled_size)
                                        <div class="text-xs text-white/50 font-normal">{{ rtrim(rtrim(number_format($d->execution->filled_size, 8), '0'), '.') }} {{ $d->asset_symbol }}</div>
                                    @endif
                                    @if($d->execution?->fee_usd)
                                        <div class="text-xs text-white/30 font-normal">fee €{{ number_format($d->execution->fee_usd / 100, 2) }}</div>
                                    @endif
                                </td>
                                <td class="font-mono text-sm hidden lg:table-cell">
                                    @if($d->execution?->price_at_execution)
                                        €{{ number_format($d->execution->price_at_execution / 100, 4) }}
                                    @else
                                        <span class="text-white/25">—</span>
                                    @endif
                                </td>
                                <td class="font-mono text-sm hidden lg:table-cell">
                                    @php $curPrice = isset($currentPrices[$d->asset_symbol]) ? $currentPrices[$d->asset_symbol] / 100 : null; @endphp
                                    @if($curPrice)
                                        €{{ number_format($curPrice, 4) }}
                                    @else
                                        <span class="text-white/25">—</span>
                                    @endif
                                </td>
                                <td class="font-mono text-sm">
                                    @php
                                        $pnl = null;
                                        $curPrice = isset($currentPrices[$d->asset_symbol]) ? $currentPrices[$d->asset_symbol] / 100 : null;
                                        if ($curPrice && $d->execution?->price_at_execution && $d->execution?->filled_size) {
                                            $fillPrice = $d->execution->price_at_execution / 100;
                                            $size      = (float) $d->execution->filled_size;
                                            $fee       = $d->execution->fee_usd ? $d->execution->fee_usd / 100 : 0;
                                            $pnl       = $d->execution->action === 'sell'
                                                ? ($fillPrice - $curPrice) * $size - $fee
                                                : ($curPrice - $fillPrice) * $size - $fee;
                                        }
                                    @endphp
                                    @if($pnl !== null)
                                        <span class="{{ $pnl >= 0 ? 'neon-text-green' : 'neon-text-red' }}">
                                            {{ $pnl >= 0 ? '+' : '' }}€{{ number_format($pnl, 2) }}
                                        </span>
                                    @else
                                        <span class="text-white/25">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($d->execution)
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="text-xs px-2 py-0.5 rounded-full
                                            @if($d->execution->status === 'filled') bg-neon-green/10 text-neon-green border border-neon-green/20
                                            @elseif($d->execution->status === 'failed') bg-neon-red/10 text-neon-red border border-neon-red/20
                                            @elseif($d->execution->status === 'cancelled') bg-orange-500/10 text-orange-400 border border-orange-500/20
                                            @else bg-white/5 text-white/40 border border-white/10 @endif">
                                            {{ $d->execution->status }}
                                        </span>
                                        </div>
                                    @elseif(!$autoTrade)
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <button wire:click="executeDecision({{ $d->id }})" wire:loading.attr="disabled" wire:target="executeDecision({{ $d->id }})" class="btn-neon-green !py-1 !px-3 !text-xs inline-flex items-center gap-1">
                                                <svg wire:loading.remove wire:target="executeDecision({{ $d->id }})" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                <svg wire:loading wire:target="executeDecision({{ $d->id }})" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                Execute
                                            </button>
                                            <button wire:click="denyDecision({{ $d->id }})" wire:confirm="Delete this decision?" class="btn-neon-red !py-1 !px-3 !text-xs inline-flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                                Deny
                                            </button>
                                        </div>
                                    @else
                                        <span class="badge-hold">pending</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>

        {{-- Asset Sentiment --}}
        <div class="glass-card p-6 flex flex-col" style="max-height: 420px;">
            <h2 class="text-sm font-semibold text-white/60 uppercase tracking-wider mb-4 shrink-0">Asset Sentiment (24h)</h2>
            @php
                $portfolioAssets = collect($portfolio['assets'] ?? [])
                    ->pluck('currency')
                    ->filter(fn($c) => $c !== 'EUR')
                    ->unique()
                    ->sortByDesc(fn($c) => (float) ($assetSentiment[$c]->avg_score ?? 0))
                    ->values();
            @endphp
            <div class="space-y-3 overflow-y-auto pr-1">
                @forelse($portfolioAssets as $asset)
                    @php
                        $data  = $assetSentiment[$asset] ?? null;
                        $score = $data ? (float) $data->avg_score : 0;
                        $color = $score > 0.1 ? 'neon-green' : ($score < -0.1 ? 'neon-red' : 'white');
                        $pct   = ($score + 1) / 2 * 100;
                    @endphp
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm font-medium text-white">{{ $asset }}</span>
                            <span class="text-xs font-mono text-{{ $color }}">
                                {{ $score >= 0 ? '+' : '' }}{{ number_format($score, 2) }}
                            </span>
                        </div>
                        <div class="h-1.5 bg-white/10 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500 bg-{{ $color }}"
                                 style="width: {{ number_format($pct, 1) }}%; opacity: 0.8"></div>
                        </div>
                        <div class="text-xs text-white/30 mt-0.5">{{ $data?->signal_count ?? 0 }} signals</div>
                    </div>
                @empty
                    <div class="text-xs text-white/30">Keine Portfolio-Daten verfügbar.</div>
                @endforelse
            </div>
        </div>

    </div>

    {{-- Signal Feed --}}
    <div class="glass-card p-6 mb-6">
        <h2 class="text-sm font-semibold text-white/60 uppercase tracking-wider mb-4">Live Signal Feed</h2>
        @if($recentSignals->isEmpty())
            <div class="text-center py-6 text-white/30 text-sm">No signals yet. Run <code class="text-neon-blue">php artisan scraper:run</code></div>
        @else
            <div class="overflow-x-auto">
            <table class="w-full table-fixed text-sm min-w-[360px]">
                <colgroup>
                    <col class="w-14">
                    <col class="hidden sm:table-column w-32">
                    <col class="w-16">
                    <col>{{-- Artikel: füllt den Rest --}}
                    <col class="hidden md:table-column w-28">{{-- Source: ab md --}}
                    <col class="w-20">
                </colgroup>
                <thead>
                    <tr class="border-b border-white/[0.08]">
                        <th class="pb-2 pr-3 text-left text-xs text-white/30 font-medium">Asset</th>
                        <th class="pb-2 pr-3 text-left text-xs text-white/30 font-medium">Typ</th>
                        <th class="pb-2 pr-3 text-left text-xs text-white/30 font-medium">Score</th>
                        <th class="pb-2 pr-3 text-left text-xs text-white/30 font-medium">Artikel</th>
                        <th class="pb-2 pr-3 text-left text-xs text-white/30 font-medium hidden md:table-cell">Source</th>
                        <th class="pb-2 text-right text-xs text-white/30 font-medium">Zeit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentSignals as $signal)
                        @php $score = (float) $signal->signal_score; @endphp
                        <tr class="border-b border-white/[0.08] hover:bg-white/[0.04] transition-colors">
                            {{-- Asset --}}
                            <td class="py-2.5 pr-3">
                                <div class="flex items-center gap-1.5">
                                    <x-asset-icon :symbol="$signal->asset_symbol" :size="4" />
                                    <span class="font-bold font-mono text-xs
                                        @if($score > 0.1) neon-text-green @elseif($score < -0.1) neon-text-red @else text-white/50 @endif">
                                        {{ $signal->asset_symbol }}
                                    </span>
                                </div>
                            </td>
                            {{-- Typ (ab sm) --}}
                            <td class="py-2.5 pr-3">
                                <span class="text-xs px-2 py-0.5 rounded-full
                                    @if($score > 0.1) bg-neon-green/10 text-neon-green border border-neon-green/20
                                    @elseif($score < -0.1) bg-neon-red/10 text-neon-red border border-neon-red/20
                                    @else bg-white/5 text-white/40 border border-white/10 @endif">
                                    {{ $signal->signal_type }}
                                </span>
                            </td>
                            {{-- Score --}}
                            <td class="py-2.5 pr-3 font-mono font-semibold text-xs
                                @if($score > 0.1) text-neon-green @elseif($score < -0.1) text-neon-red @else text-white/40 @endif">
                                {{ $score >= 0 ? '+' : '' }}{{ number_format($score, 3) }}
                            </td>
                            {{-- Artikel (immer sichtbar, truncate) --}}
                            <td class="py-2.5 pr-3 text-xs text-white/40 truncate overflow-hidden max-w-0">
                                {{ $signal->article?->title }}
                            </td>
                            {{-- Source (ab md) --}}
                            <td class="py-2.5 pr-3 hidden md:table-cell text-xs text-white/30 truncate overflow-hidden max-w-0">
                                {{ $signal->article?->source?->name }}
                            </td>
                            {{-- Zeit --}}
                            <td class="py-2.5 text-xs text-white/25 text-right whitespace-nowrap">
                                {{ $signal->created_at->diffForHumans() }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </div>

    {{-- Coinbase Portfolio --}}
    <div class="glass-card p-6">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-sm font-semibold text-white/60 uppercase tracking-wider">Coinbase Portfolio</h2>
            <div class="text-right">
                <div class="text-xs text-white/30">Total Value</div>
                <div class="text-2xl font-bold neon-text-green">
                    @if($portfolio['total_eur'] ?? null)
                        €{{ number_format($portfolio['total_eur'], 2) }}
                    @else
                        —
                    @endif
                </div>
            </div>
        </div>

        @if(empty($portfolio['assets']))
            <div class="text-center py-6 text-white/30 text-sm">Keine Portfolio-Daten verfügbar</div>
        @else
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                @foreach($portfolio['assets'] as $asset)
                    @php
                        $color = match($asset['currency']) {
                            'BTC'  => '#f7931a',
                            'ETH'  => '#627eea',
                            'SOL'  => '#9945ff',
                            'XRP'  => '#00aae4',
                            'DOGE' => '#c2a633',
                            'SHIB' => '#e8421e',
                            default => '#94a3b8',
                        };
                    @endphp
                    <div class="bg-white/[0.04] border border-white/[0.08] rounded-xl p-4 hover:bg-white/[0.07] transition-colors">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <x-asset-icon :symbol="$asset['currency']" :size="6" />
                                <span class="text-sm font-bold text-white">{{ $asset['currency'] }}</span>
                            </div>
                            <span class="text-xs font-mono text-white/40">
                                {{ round($asset['allocation'] * 100, 1) }}%
                            </span>
                        </div>
                        <div class="text-base font-bold truncate" style="color: {{ $color }}">
                            {{ rtrim(rtrim(number_format($asset['balance'], 6), '0'), '.') }}
                        </div>
                        @if($asset['value_eur'] > 0)
                            <div class="text-xs text-white/40 mt-1">
                                €{{ number_format($asset['value_eur'], 2) }}
                            </div>
                        @else
                            <div class="text-xs text-white/20 mt-1">—</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>
