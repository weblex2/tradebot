<div>
    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold text-white">{{ $stats['total'] }}</div>
            <div class="text-xs text-white/40 mt-1">Total</div>
        </div>
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold neon-text-blue">{{ $stats['pending'] }}</div>
            <div class="text-xs text-white/40 mt-1">Active</div>
        </div>
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold neon-text-green">{{ $stats['agreed'] }}</div>
            <div class="text-xs text-white/40 mt-1">Agreed</div>
        </div>
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold text-purple-400">{{ $stats['finished'] }}</div>
            <div class="text-xs text-white/40 mt-1">Implemented</div>
        </div>
        <div class="glass-card p-4 text-center">
            <div class="text-2xl font-bold text-red-400">{{ $stats['rejected'] }}</div>
            <div class="text-xs text-white/40 mt-1">Rejected</div>
        </div>
    </div>

    {{-- Controls --}}
    <div class="glass-card p-4 mb-6 flex flex-wrap gap-3 items-center">
        {{-- Filter --}}
        <select wire:model.live="filterStatus" class="select-glass">
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="discussing">Discussing</option>
            <option value="agreed">Agreed</option>
            <option value="rejected">Rejected</option>
            <option value="implementing">Implementing</option>
            <option value="finished">Finished</option>
        </select>

        <div class="ml-auto flex gap-2">
            {{-- Generate button --}}
            <button wire:click="runGenerate" wire:loading.attr="disabled"
                    class="btn-neon-blue !py-1.5 !px-3 !text-xs inline-flex items-center gap-1.5">
                <svg wire:loading wire:target="runGenerate" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                <svg wire:loading.remove wire:target="runGenerate" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Generate
            </button>

            {{-- Discuss all button --}}
            <button wire:click="runDiscuss" wire:loading.attr="disabled"
                    wire:confirm="Advance all active discussions by one round?"
                    class="btn-neon !py-1.5 !px-3 !text-xs inline-flex items-center gap-1.5">
                <svg wire:loading wire:target="runDiscuss" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                <svg wire:loading.remove wire:target="runDiscuss" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                Discuss All
            </button>
        </div>
    </div>

    {{-- Discussion list --}}
    <div class="space-y-3">
        @forelse($discussions as $d)
            <div class="glass-card overflow-hidden">
                {{-- Header row --}}
                <div class="p-4 flex items-start gap-3 cursor-pointer hover:bg-white/[0.02] transition-colors"
                     wire:click="toggleExpand({{ $d->id }})">

                    {{-- Priority dot --}}
                    <div class="mt-1 w-2 h-2 rounded-full flex-shrink-0
                        {{ $d->priority === 'high' ? 'bg-red-400' : ($d->priority === 'medium' ? 'bg-yellow-400' : 'bg-white/30') }}">
                    </div>

                    {{-- Title + meta --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-white text-sm">{{ $d->title }}</span>

                            {{-- Status badge --}}
                            @if($d->status === 'pending')
                                <span class="badge-hold">PENDING</span>
                            @elseif($d->status === 'discussing')
                                <span class="badge-paper">DISCUSSING</span>
                            @elseif($d->status === 'agreed')
                                <span class="badge-buy">AGREED</span>
                            @elseif($d->status === 'rejected')
                                <span class="badge-sell">REJECTED</span>
                            @elseif($d->status === 'implementing')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-400/10 border border-yellow-400/30 text-yellow-400">IMPLEMENTING</span>
                            @elseif($d->status === 'finished')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-400/10 border border-purple-400/30 text-purple-400">FINISHED</span>
                            @endif

                            {{-- Priority --}}
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border
                                {{ $d->priority === 'high' ? 'bg-red-400/10 border-red-400/30 text-red-400' : ($d->priority === 'medium' ? 'bg-yellow-400/10 border-yellow-400/30 text-yellow-400' : 'bg-white/5 border-white/15 text-white/40') }}">
                                {{ strtoupper($d->priority) }}
                            </span>
                        </div>

                        {{-- Round progress --}}
                        @if(in_array($d->status, ['discussing', 'pending']))
                            <div class="mt-2 flex items-center gap-2">
                                <div class="flex gap-1">
                                    @for($i = 1; $i <= 5; $i++)
                                        <div class="w-5 h-1.5 rounded-full transition-all
                                            {{ $i <= $d->round ? 'bg-neon-blue shadow-[0_0_6px_rgba(0,180,216,0.6)]' : 'bg-white/10' }}">
                                        </div>
                                    @endfor
                                </div>
                                <span class="text-[10px] font-medium text-white/40">
                                    {{ $d->round === 0 ? 'Not started' : "Round {$d->round} / 5" }}
                                </span>
                            </div>
                        @endif

                        {{-- Snippet of suggestion --}}
                        @if($expandedId !== $d->id)
                            <p class="text-xs text-white/40 mt-1 line-clamp-1">{{ $d->suggestion }}</p>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-2 flex-shrink-0" @click.stop>

                        {{-- Discuss one --}}
                        @if(in_array($d->status, ['pending', 'discussing']) && $d->round < 5)
                            <button wire:click="runDiscussOne({{ $d->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="runDiscussOne({{ $d->id }})"
                                    class="text-[11px] px-2 py-1 rounded-lg border border-blue-400/30 text-neon-blue hover:bg-blue-400/10 transition-colors">
                                <svg wire:loading wire:target="runDiscussOne({{ $d->id }})" class="w-3 h-3 animate-spin inline" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                </svg>
                                <span wire:loading.remove wire:target="runDiscussOne({{ $d->id }})">+1 Round</span>
                            </button>
                        @endif

                        {{-- Implement --}}
                        @if($d->status === 'agreed')
                            <button wire:click="runImplement({{ $d->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="runImplement({{ $d->id }})"
                                    wire:confirm="Implement '{{ $d->title }}'? This will modify project files."
                                    class="text-[11px] px-2 py-1 rounded-lg border border-green-400/30 neon-text-green hover:bg-green-400/10 transition-colors">
                                <svg wire:loading wire:target="runImplement({{ $d->id }})" class="w-3 h-3 animate-spin inline" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                </svg>
                                <span wire:loading.remove wire:target="runImplement({{ $d->id }})">Implement</span>
                            </button>
                        @endif

                        {{-- Chevron --}}
                        <svg class="w-4 h-4 text-white/30 transition-transform {{ $expandedId === $d->id ? 'rotate-180' : '' }}"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24"
                             wire:click="toggleExpand({{ $d->id }})">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </div>

                {{-- Expanded detail --}}
                @if($expandedId === $d->id)
                    <div class="border-t border-white/[0.06] px-4 pb-4 pt-3 space-y-4">

                        {{-- Original suggestion --}}
                        <div>
                            <div class="text-[11px] font-semibold text-white/40 uppercase tracking-wider mb-1">Proposal</div>
                            <p class="text-sm text-white/70 leading-relaxed">{{ $d->suggestion }}</p>
                        </div>

                        {{-- Affected files --}}
                        @if(!empty($d->affected_files))
                            <div class="flex flex-wrap gap-1">
                                @foreach($d->affected_files as $file)
                                    <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-white/5 text-white/40">{{ $file }}</span>
                                @endforeach
                            </div>
                        @endif

                        {{-- Discussion turns --}}
                        @if(!empty($d->turns))
                            <div>
                                <div class="text-[11px] font-semibold text-white/40 uppercase tracking-wider mb-2">Discussion</div>
                                <div class="space-y-3">
                                    @foreach($d->turns as $i => $turn)
                                        <div class="flex gap-3">
                                            {{-- Avatar --}}
                                            <div class="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold
                                                {{ $turn['role'] === 'claude' ? 'bg-orange-500/15 text-orange-400 border border-orange-400/40' : 'bg-neon-blue/10 text-neon-blue border border-neon-blue/40' }}">
                                                {{ $turn['role'] === 'claude' ? 'C' : 'G' }}
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="text-[11px] font-semibold
                                                        {{ $turn['role'] === 'claude' ? 'text-orange-400' : 'text-neon-blue' }}">
                                                        {{ ucfirst($turn['role']) }}
                                                    </span>
                                                    <span class="text-[10px] text-white/20">Round {{ $i + 1 }}</span>
                                                    <span class="text-[10px] text-white/20">{{ \Carbon\Carbon::parse($turn['at'])->format('d.m H:i') }}</span>
                                                </div>
                                                <p class="text-xs text-white/60 leading-relaxed whitespace-pre-wrap">{{ $turn['content'] }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Consensus summary --}}
                        @if($d->consensus_summary)
                            <div class="p-3 rounded-xl bg-green-400/5 border border-green-400/20">
                                <div class="text-[11px] font-semibold neon-text-green uppercase tracking-wider mb-1">Implementation Plan</div>
                                <p class="text-xs text-white/70 leading-relaxed whitespace-pre-wrap">{{ $d->consensus_summary }}</p>
                            </div>
                        @endif

                        {{-- Implementation notes --}}
                        @if($d->implementation_notes)
                            <div class="p-3 rounded-xl bg-purple-400/5 border border-purple-400/20">
                                <div class="text-[11px] font-semibold text-purple-400 uppercase tracking-wider mb-1">Implementation Notes</div>
                                <p class="text-xs text-white/70 leading-relaxed whitespace-pre-wrap">{{ $d->implementation_notes }}</p>
                            </div>
                        @endif

                        {{-- Footer actions --}}
                        <div class="flex justify-end">
                            <button wire:click="deleteDiscussion({{ $d->id }})"
                                    wire:confirm="Delete this discussion?"
                                    class="text-[11px] px-2 py-1 rounded-lg text-red-400/60 hover:text-red-400 hover:bg-red-400/10 transition-colors">
                                Delete
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="glass-card p-12 text-center">
                <div class="text-white/20 text-sm">No discussions yet — click Generate to start</div>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($discussions->hasPages())
        <div class="mt-6">
            {{ $discussions->links() }}
        </div>
    @endif
</div>
