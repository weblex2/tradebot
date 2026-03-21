<div wire:poll.30s class="flex items-center gap-4">
    {{-- Queue Worker --}}
    <div class="flex items-center gap-1.5">
        <span class="w-1.5 h-1.5 rounded-full {{ $queueRunning ? 'bg-neon-green shadow-[0_0_5px_#00ff87]' : 'bg-neon-red shadow-[0_0_5px_#ff3d71]' }}"></span>
        <span class="text-xs {{ $queueRunning ? 'text-neon-green' : 'text-neon-red' }}">Queue</span>
    </div>

    <div class="w-px h-3 bg-white/10"></div>

    {{-- Next Scraper --}}
    <div class="flex items-center gap-1.5" title="Nächster Scraper-Lauf">
        <svg class="w-3 h-3 text-white/30 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        <span class="text-xs text-white/40 hidden sm:inline">Scraper</span>
        <span class="text-xs font-mono text-neon-blue">{{ $nextScraper->local()->format('H:i') }}</span>
    </div>

    <div class="w-px h-3 bg-white/10"></div>

    {{-- Next Analyzer --}}
    <div class="flex items-center gap-1.5" title="Nächster Analyzer-Lauf">
        <svg class="w-3 h-3 text-white/30 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/>
        </svg>
        <span class="text-xs text-white/40 hidden sm:inline">Analyzer</span>
        <span class="text-xs font-mono text-neon-blue">{{ $nextAnalyzer->local()->format('H:i') }}</span>
    </div>

    <div class="w-px h-3 bg-white/10"></div>

    {{-- Next Auto Fix --}}
    <div class="flex items-center gap-1.5" title="Nächster Auto-Fix-Lauf">
        <svg class="w-3 h-3 text-white/30 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="text-xs text-white/40 hidden sm:inline">Auto Fix</span>
        <span class="text-xs font-mono text-neon-blue">{{ $nextAutoFix->local()->format('H:i') }}</span>
    </div>
</div>
