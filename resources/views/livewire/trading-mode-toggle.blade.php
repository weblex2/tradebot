<div>
    {{-- Toggle Button --}}
    <button wire:click="requestToggle"
            class="flex items-center gap-2 glass-card px-3 py-1.5 hover:bg-white/[0.1] transition-colors cursor-pointer border
                   {{ $isLive ? 'border-yellow-400/40 hover:border-yellow-400/60' : 'border-neon-blue/30 hover:border-neon-blue/50' }}">
        <span class="w-2 h-2 rounded-full pulse-neon {{ $isLive ? 'bg-yellow-400' : 'bg-neon-blue' }}"></span>
        <span class="text-xs font-medium {{ $isLive ? 'text-yellow-400' : 'text-white/60' }}">
            {{ $isLive ? 'LIVE TRADING' : 'PAPER TRADING' }}
        </span>
        <svg class="w-3 h-3 {{ $isLive ? 'text-yellow-400/60' : 'text-white/30' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
        </svg>
    </button>

    {{-- Confirmation Modal --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" wire:click="cancelModal"></div>

            {{-- Modal --}}
            <div class="relative z-10 w-full max-w-md glass-card p-6 border border-yellow-400/30 rounded-2xl shadow-2xl">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-yellow-400/20 border border-yellow-400/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-yellow-400">Switch to Live Trading?</h3>
                        <p class="text-xs text-white/40">This will execute real trades with real money</p>
                    </div>
                </div>

                <div class="space-y-2 mb-6 text-sm text-white/60">
                    <p>By switching to <span class="text-yellow-400 font-semibold">LIVE mode</span>, the bot will:</p>
                    <ul class="list-disc list-inside space-y-1 pl-2 text-white/50">
                        <li>Place real orders on your Coinbase account</li>
                        <li>Use actual funds from your portfolio</li>
                        <li>Not be reversible without switching back manually</li>
                    </ul>
                </div>

                <div class="flex gap-3">
                    <button wire:click="cancelModal"
                            class="flex-1 px-4 py-2.5 rounded-xl bg-white/[0.06] hover:bg-white/[0.1] border border-white/[0.1] text-white/60 text-sm font-medium transition-colors">
                        Cancel
                    </button>
                    <button wire:click="confirmLive"
                            class="flex-1 px-4 py-2.5 rounded-xl bg-yellow-400/20 hover:bg-yellow-400/30 border border-yellow-400/40 text-yellow-400 text-sm font-bold transition-colors">
                        Yes, go Live
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
