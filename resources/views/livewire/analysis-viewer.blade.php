<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Analysis List --}}
    <div class="glass-card overflow-hidden">
        <div class="p-4 border-b border-white/[0.06]">
            <h3 class="text-sm font-semibold text-white/60 uppercase tracking-wider">Analysis Runs</h3>
        </div>
        <div class="divide-y divide-white/[0.05]">
            @forelse($analyses as $analysis)
            <button wire:click="select({{ $analysis->id }})"
                    class="w-full text-left p-4 hover:bg-white/[0.04] transition-colors
                           {{ $selectedId === $analysis->id ? 'bg-white/[0.06] border-l-2 border-neon-blue' : '' }}">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="text-xs text-white/40 font-mono">#{{ $analysis->id }}</span>
                        <span class="ml-2 text-xs px-2 py-0.5 rounded-full bg-white/5 border border-white/10 text-white/40">
                            {{ $analysis->triggered_by }}
                        </span>
                    </div>
                    <span class="text-xs text-white/30">{{ $analysis->created_at->diffForHumans() }}</span>
                </div>
                <div class="mt-2 text-xs text-white/50 line-clamp-2">
                    {{ $analysis->claude_reasoning ? Str::limit($analysis->claude_reasoning, 120) : 'No reasoning recorded' }}
                </div>
                <div class="mt-2 flex items-center gap-3">
                    <span class="text-xs text-neon-blue">{{ $analysis->trade_decisions_count }} decisions</span>
                    @if($analysis->prompt_tokens)
                        <span class="text-xs text-white/25">{{ $analysis->prompt_tokens + $analysis->completion_tokens }} tokens</span>
                    @endif
                </div>
            </button>
            @empty
            <div class="p-8 text-center text-white/30 text-sm">No analyses yet.</div>
            @endforelse
        </div>
        @if($analyses->hasPages())
        <div class="p-4 border-t border-white/[0.05]">{{ $analyses->links() }}</div>
        @endif
    </div>

    {{-- Analysis Detail --}}
    <div class="glass-card p-6">
        @if($selected)
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-white">Analysis #{{ $selected->id }}</h3>
                <span class="text-xs text-white/30">{{ $selected->created_at->format('d.m.Y H:i:s') }}</span>
            </div>

            {{-- Reasoning --}}
            <div class="mb-5">
                <div class="text-xs text-white/40 uppercase tracking-wider mb-2">Claude's Reasoning</div>
                <div class="text-sm text-white/70 bg-white/[0.03] rounded-xl p-4 leading-relaxed border border-white/[0.06]">
                    {{ $selected->claude_reasoning ?? 'No reasoning recorded.' }}
                </div>
            </div>

            {{-- Decisions --}}
            <div class="mb-5">
                <div class="text-xs text-white/40 uppercase tracking-wider mb-2">Trade Decisions</div>
                <div class="space-y-2">
                    @foreach($selected->tradeDecisions as $d)
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-white/[0.03] border border-white/[0.05]">
                        <span class="font-bold text-white w-10">{{ $d->asset_symbol }}</span>
                        <span class="badge-{{ $d->action }}">{{ strtoupper($d->action) }}</span>
                        <div class="flex-1">
                            <div class="text-xs text-white/50">{{ $d->rationale }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-mono text-white">{{ $d->confidence }}%</div>
                            <div class="text-xs text-white/30">${{ number_format($d->amountInDollars(), 2) }}</div>
                        </div>
                        @if($d->execution)
                            <span class="badge-{{ $d->execution->status }}">{{ $d->execution->status }}</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Signals Summary --}}
            @if($selected->signals_summary)
            <div>
                <div class="text-xs text-white/40 uppercase tracking-wider mb-2">Signals Summary</div>
                <pre class="text-xs text-white/40 bg-white/[0.02] rounded-xl p-4 overflow-x-auto border border-white/[0.04] font-mono">{{ json_encode($selected->signals_summary, JSON_PRETTY_PRINT) }}</pre>
            </div>
            @endif
        @else
            <div class="flex flex-col items-center justify-center h-full py-16 text-center">
                <div class="w-12 h-12 rounded-2xl bg-neon-blue/10 border border-neon-blue/20 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-neon-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <p class="text-white/30 text-sm">Select an analysis to view details</p>
            </div>
        @endif
    </div>
</div>
