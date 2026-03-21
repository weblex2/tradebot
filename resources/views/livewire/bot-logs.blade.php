<div wire:poll.30s>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold text-white">{{ $counts['total'] }}</div>
            <div class="text-xs text-white/40 mt-1">Logs (24h)</div>
        </div>
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold text-yellow-400">{{ $counts['warnings'] }}</div>
            <div class="text-xs text-white/40 mt-1">Warnings (24h)</div>
        </div>
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold neon-text-red">{{ $counts['errors'] }}</div>
            <div class="text-xs text-white/40 mt-1">Errors (24h)</div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="glass-card p-4 mb-6 flex gap-4 items-center">
        <select wire:model.live="filterLevel" class="select-glass">
            <option value="">All Levels</option>
            <option value="debug">Debug</option>
            <option value="info">Info</option>
            <option value="warning">Warning</option>
            <option value="error">Error</option>
        </select>
        <select wire:model.live="filterChannel" class="select-glass">
            <option value="">All Channels</option>
            <option value="scraper">Scraper</option>
            <option value="claude">Claude</option>
            <option value="gemini">Gemini</option>
            <option value="executor">Executor</option>
            <option value="coinbase">Coinbase</option>
            <option value="scheduler">Scheduler</option>
            <option value="order_status">Order Status</option>
            <option value="system">System</option>
        </select>
        <select wire:model.live="filterSince" class="select-glass">
            <option value="1">Last 1h</option>
            <option value="6">Last 6h</option>
            <option value="24">Last 24h</option>
            <option value="72">Last 3 days</option>
            <option value="all">All time</option>
        </select>
        <button wire:click="clearFilters" class="text-xs text-white/40 hover:text-white/70 transition-colors ml-auto">
            Reset
        </button>
    </div>

    {{-- Log table --}}
    <div class="glass-card p-6">
        @if($logs->isEmpty())
            <div class="text-center py-10 text-white/30 text-sm">No log entries yet.</div>
        @else
            <div class="space-y-0.5">
                @foreach($logs as $log)
                    @php
                        $levelColor = match($log->level) {
                            'error'   => 'bg-neon-red/10 text-neon-red border-neon-red/20',
                            'warning' => 'bg-yellow-400/10 text-yellow-400 border-yellow-400/20',
                            'info'    => 'bg-neon-blue/10 text-neon-blue border-neon-blue/20',
                            default   => 'bg-white/5 text-white/30 border-white/10',
                        };
                        $channelColor = match($log->channel) {
                            'scraper'      => 'text-neon-green',
                            'claude'       => 'text-purple-400',
                            'gemini'       => 'text-teal-400',
                            'executor'     => 'text-yellow-400',
                            'coinbase'     => 'text-neon-blue',
                            'order_status' => 'text-neon-blue',
                            'scheduler'    => 'text-white/60',
                            default        => 'text-white/40',
                        };
                    @endphp
                    <div x-data="{ open: false }">
                        <div
                            class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/[0.04] transition-colors {{ $log->context ? 'cursor-pointer' : '' }}"
                            @if($log->context) @click="open = !open" @endif
                        >
                            <span class="text-xs text-white/20 font-mono shrink-0 w-10 text-right">#{{ $log->id }}</span>
                            <span class="text-xs text-white/25 font-mono whitespace-nowrap w-28 shrink-0" title="{{ $log->created_at->local()->format('Y-m-d H:i:s') }}">
                                {{ $log->created_at->local()->format('d.m H:i:s') }}
                            </span>
                            <span class="text-xs px-1.5 py-0.5 rounded border font-mono shrink-0 w-16 text-center {{ $levelColor }}">
                                {{ $log->level }}
                            </span>
                            <span class="text-xs font-mono w-24 shrink-0 {{ $channelColor }}">
                                {{ $log->channel }}
                            </span>
                            <span class="text-sm text-white/80 flex-1 truncate">
                                {{ $log->message }}
                            </span>
                            @if($log->execution_id)
                                <span class="text-xs text-white/20 shrink-0">exec#{{ $log->execution_id }}</span>
                            @endif
                            @if($log->context)
                                <svg class="w-3 h-3 text-white/30 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            @endif
                        </div>
                        @if($log->context)
                            <div x-show="open" x-transition class="ml-56 mr-4 mb-1 px-3 py-2 rounded-lg bg-black/30 border border-white/[0.06]">
                                <pre class="text-xs text-white/50 font-mono overflow-x-auto whitespace-pre-wrap">{{ json_encode($log->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

</div>
