<div wire:poll.30s>

    {{-- Stats grid --}}
    <div class="grid grid-cols-6 gap-4 mb-6">

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
            <div class="text-xs text-white/40 uppercase tracking-wider mb-2">Active Sources</div>
            <div class="text-3xl font-bold text-white">{{ $stats['total_sources'] }}</div>
            <div class="text-xs text-neon-blue mt-1">news feeds</div>
        </div>

        <div class="glass-card p-5">
            <div class="text-xs text-white/40 uppercase tracking-wider mb-2">Articles Today</div>
            <div class="text-3xl font-bold text-white">{{ $stats['articles_today'] }}</div>
            <div class="text-xs text-white/30 mt-1">scraped & processed</div>
        </div>

        <div class="glass-card p-5">
            <div class="text-xs text-white/40 uppercase tracking-wider mb-2">Signals (24h)</div>
            <div class="text-3xl font-bold neon-text-blue">{{ $stats['signals_6h'] }}</div>
            <div class="text-xs text-white/30 mt-1">active signals</div>
        </div>

        <div class="glass-card p-5">
            <div class="text-xs text-white/40 uppercase tracking-wider mb-2">Trades Today</div>
            <div class="text-3xl font-bold neon-text-green">{{ $stats['executions_today'] }}</div>
            <div class="text-xs text-white/30 mt-1">{{ $stats['paper_trades'] }} paper · {{ $stats['live_trades'] }} live</div>
        </div>

    </div>

    {{-- Asset sentiment + Recent decisions --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

        {{-- Asset Sentiment --}}
        <div class="glass-card p-6">
            <h2 class="text-sm font-semibold text-white/60 uppercase tracking-wider mb-4">Asset Sentiment (24h)</h2>
            <div class="space-y-3">
                @foreach(['BTC', 'ETH', 'SOL', 'XRP'] as $asset)
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
                @endforeach
            </div>
        </div>

        {{-- Recent Decisions --}}
        <div class="glass-card p-6 lg:col-span-2">
            <h2 class="text-sm font-semibold text-white/60 uppercase tracking-wider mb-4">Recent Decisions</h2>
            @if($recentDecisions->isEmpty())
                <div class="text-center py-8 text-white/30 text-sm">No decisions yet. Run <code class="text-neon-blue">php artisan trade:analyze</code></div>
            @else
                <table class="table-glass">
                    <thead>
                        <tr>
                            <th>Asset</th>
                            <th>Action</th>
                            <th>Confidence</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentDecisions as $d)
                            <tr>
                                <td class="font-medium text-white">{{ $d->asset_symbol }}</td>
                                <td><span class="badge-{{ $d->action }}">{{ strtoupper($d->action) }}</span></td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="w-16 h-1 bg-white/10 rounded-full">
                                            <div class="h-full rounded-full bg-neon-blue" style="width: {{ $d->confidence }}%"></div>
                                        </div>
                                        <span class="text-xs font-mono text-white/60">{{ $d->confidence }}%</span>
                                    </div>
                                </td>
                                <td class="font-mono text-sm">${{ number_format($d->amountInDollars(), 2) }}</td>
                                <td>
                                    @if($d->execution)
                                        <span class="badge-{{ $d->execution->status }}">{{ $d->execution->status }}</span>
                                        <span class="badge-{{ $d->execution->mode }} ml-1">{{ $d->execution->mode }}</span>
                                    @else
                                        <span class="badge-hold">pending</span>
                                    @endif
                                </td>
                                <td class="text-xs text-white/30">{{ $d->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

    </div>

    {{-- Signal Feed --}}
    <div class="glass-card p-6 mb-6">
        <h2 class="text-sm font-semibold text-white/60 uppercase tracking-wider mb-4">Live Signal Feed</h2>
        @if($recentSignals->isEmpty())
            <div class="text-center py-6 text-white/30 text-sm">No signals yet. Run <code class="text-neon-blue">php artisan scraper:run</code></div>
        @else
            <div class="space-y-2">
                @foreach($recentSignals as $signal)
                    @php $score = (float) $signal->signal_score; @endphp
                    <div class="flex items-center gap-4 p-3 rounded-xl bg-white/[0.03] hover:bg-white/[0.06] transition-colors">
                        <span class="text-sm font-bold w-12 font-mono
                            @if($score > 0.1) neon-text-green @elseif($score < -0.1) neon-text-red @else text-white/50 @endif">
                            {{ $signal->asset_symbol }}
                        </span>
                        <span class="text-xs px-2 py-0.5 rounded-full
                            @if($score > 0.1) bg-neon-green/10 text-neon-green border border-neon-green/20
                            @elseif($score < -0.1) bg-neon-red/10 text-neon-red border border-neon-red/20
                            @else bg-white/5 text-white/40 border border-white/10 @endif">
                            {{ $signal->signal_type }}
                        </span>
                        <span class="text-xs font-mono font-semibold w-14
                            @if($score > 0.1) text-neon-green @elseif($score < -0.1) text-neon-red @else text-white/40 @endif">
                            {{ $score >= 0 ? '+' : '' }}{{ number_format($score, 3) }}
                        </span>
                        <span class="flex-1 text-xs text-white/40 truncate">{{ $signal->article?->title }}</span>
                        <span class="text-xs text-white/25 shrink-0">{{ $signal->created_at->diffForHumans() }}</span>
                    </div>
                @endforeach
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
                            <span class="text-sm font-bold text-white">{{ $asset['currency'] }}</span>
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
