<div>
    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold text-white">{{ $stats['total'] }}</div>
            <div class="text-xs text-white/40 mt-1">Total</div>
        </div>
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold neon-text-green">{{ $stats['applied'] }}</div>
            <div class="text-xs text-white/40 mt-1">Auto-applied</div>
        </div>
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold text-yellow-400">{{ $stats['pending'] }}</div>
            <div class="text-xs text-white/40 mt-1">Pending review</div>
        </div>
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold neon-text-blue">{{ $stats['code'] }}</div>
            <div class="text-xs text-white/40 mt-1">Code fixes</div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="glass-card p-4 mb-6 flex gap-4 items-center">
        <select wire:model.live="filterType" class="select-glass">
            <option value="">All Types</option>
            <option value="db">DB</option>
            <option value="artisan">Artisan</option>
            <option value="code">Code</option>
            <option value="info">Info</option>
        </select>
        <select wire:model.live="filterApplied" class="select-glass">
            <option value="">All</option>
            <option value="1">Applied</option>
            <option value="0">Not applied</option>
        </select>
        @if($stats['total'] > 0)
            <button wire:click="deleteAll"
                    wire:confirm="Alle {{ $stats['total'] }} Einträge löschen?"
                    class="btn-neon-red !py-1.5 !px-3 !text-xs ml-auto inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Alle löschen
            </button>
        @endif
    </div>

    {{-- List --}}
    <div class="space-y-4">
        @forelse($fixes as $fix)
            <div class="glass-card p-5">
                <div class="flex items-start justify-between gap-4 mb-3">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs text-white/25 font-mono">#{{ $fix->id }}</span>
                        {{-- Type badge --}}
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium
                            @if($fix->fix_type === 'db') bg-neon-blue/10 text-neon-blue border border-neon-blue/20
                            @elseif($fix->fix_type === 'artisan') bg-purple-500/10 text-purple-400 border border-purple-500/20
                            @elseif($fix->fix_type === 'code') bg-yellow-500/10 text-yellow-400 border border-yellow-500/20
                            @else bg-white/5 text-white/40 border border-white/10
                            @endif">
                            {{ strtoupper($fix->fix_type) }}
                        </span>
                        {{-- Applied badge --}}
                        @if($fix->fix_applied)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-neon-green/10 text-neon-green border border-neon-green/20">
                                ✓ Applied
                            </span>
                        @else
                            <span class="text-xs px-2 py-0.5 rounded-full bg-white/5 text-white/40 border border-white/10">
                                Review needed
                            </span>
                        @endif
                        <span class="text-xs text-white/30">{{ $fix->created_at->diffForHumans() }}</span>
                    </div>
                </div>

                {{-- Fix description --}}
                <p class="text-sm text-white/80 mb-3">{{ $fix->fix_description }}</p>

                {{-- Proposed solution --}}
                @if($fix->proposed_solution)
                    <div class="bg-white/[0.03] border border-white/[0.07] rounded-lg p-3 mb-3">
                        <div class="text-xs text-white/30 mb-1.5">Proposed Solution</div>
                        <p class="text-xs text-white/65 leading-relaxed whitespace-pre-line">{{ $fix->proposed_solution }}</p>
                    </div>
                @endif

                {{-- Fix command --}}
                @if($fix->fix_command)
                    <div class="bg-black/30 rounded-lg p-3 mb-3">
                        <div class="text-xs text-white/30 mb-1">Command</div>
                        <code class="text-xs text-neon-blue font-mono">{{ $fix->fix_command }}</code>
                    </div>
                @endif

                {{-- Fix result --}}
                @if($fix->fix_result)
                    @php
                        $isSuccess = $fix->fix_applied;
                        $resultColor = $isSuccess ? 'border-neon-green/20 bg-neon-green/5 text-neon-green/80' : 'border-yellow-500/20 bg-yellow-500/5 text-yellow-400/80';
                        $resultIcon  = $isSuccess ? '✓' : '⚠';
                    @endphp
                    <div class="rounded-lg border px-3 py-2 mb-3 text-xs font-mono {{ $resultColor }}">
                        <span class="font-bold mr-1">{{ $resultIcon }}</span>{{ $fix->fix_result }}
                    </div>
                @endif

                {{-- Apply Fix button (code-type, not yet applied) --}}
                @if($fix->fix_type === 'code' && !$fix->fix_applied)
                    <div class="mt-3">
                        <button wire:click="applyFix({{ $fix->id }})"
                                wire:confirm="Claude wird versuchen den Code-Fix automatisch anzuwenden. Fortfahren?"
                                wire:loading.attr="disabled"
                                wire:target="applyFix({{ $fix->id }})"
                                class="btn-neon-blue !py-1.5 !px-4 !text-xs inline-flex items-center gap-2">
                            <span wire:loading.remove wire:target="applyFix({{ $fix->id }})">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </span>
                            <span wire:loading wire:target="applyFix({{ $fix->id }})" class="w-3.5 h-3.5 border-2 border-white/30 border-t-neon-blue rounded-full animate-spin"></span>
                            <span wire:loading.remove wire:target="applyFix({{ $fix->id }})">Apply Fix with Claude</span>
                            <span wire:loading wire:target="applyFix({{ $fix->id }})">Claude is working…</span>
                        </button>
                    </div>
                @endif

                {{-- Error message (collapsible) --}}
                <details class="group">
                    <summary class="text-xs text-white/25 cursor-pointer hover:text-white/50 transition-colors select-none">
                        Show error log
                    </summary>
                    <pre class="mt-2 text-xs text-neon-red/60 bg-black/20 rounded p-3 overflow-x-auto whitespace-pre-wrap break-all font-mono">{{ $fix->error_message }}</pre>
                </details>
            </div>
        @empty
            <div class="glass-card p-12 text-center text-white/30 text-sm">
                Keine Einträge. Der Auto-Fix Job läuft alle 20 Minuten.
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-6">
        {{ $fixes->links() }}
    </div>
</div>
