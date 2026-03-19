<div>
    <div class="mb-6">
        <p class="text-sm text-white/40 mt-1">Konfiguriere das AI-Modell und den Fallback für Analyse und Scoring</p>
    </div>

    {{-- Saved flash --}}
    <div
        x-data="{ show: false }"
        x-on:saved.window="show = true; setTimeout(() => show = false, 2500)"
        x-show="show"
        x-transition.opacity
        class="mb-6 px-4 py-3 rounded-xl bg-neon-green/10 border border-neon-green/30 text-neon-green text-sm"
    >
        Einstellungen gespeichert.
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- AI Model Selection --}}
        <div class="glass-card p-6">
            <h2 class="text-sm font-semibold text-white/60 uppercase tracking-wider mb-5">AI Modelle</h2>

            <form wire:submit="saveModels" class="space-y-5">

                {{-- Primary Model --}}
                <div>
                    <label class="block text-xs text-white/50 mb-3">Primary Model</label>
                    <div class="space-y-2">
                        @foreach($modelOptions as $value => $label)
                            <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-colors
                                {{ $primaryModel === $value
                                    ? 'bg-neon-green/10 border-neon-green/40'
                                    : 'bg-white/[0.03] border-white/10 hover:border-white/20' }}">
                                <input
                                    type="radio"
                                    wire:model="primaryModel"
                                    value="{{ $value }}"
                                    class="accent-[#00ff87]"
                                >
                                <div>
                                    <div class="text-sm font-medium text-white">{{ $label }}</div>
                                    <div class="text-xs text-white/30 mt-0.5">
                                        @if($value === 'claude') Anthropic Claude Sonnet via CLI
                                        @elseif($value === 'gemini') Google Gemini via CLI
                                        @endif
                                    </div>
                                </div>
                                @if($primaryModel === $value)
                                    <span class="ml-auto text-xs text-neon-green font-medium">Aktiv</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                    @error('primaryModel') <span class="text-neon-red text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Fallback Model --}}
                <div>
                    <label class="block text-xs text-white/50 mb-3">Fallback Model</label>
                    <div class="space-y-2">
                        @foreach($fallbackOptions as $value => $label)
                            <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-colors
                                {{ $fallbackModel === $value
                                    ? 'bg-neon-blue/10 border-neon-blue/40'
                                    : 'bg-white/[0.03] border-white/10 hover:border-white/20' }}">
                                <input
                                    type="radio"
                                    wire:model="fallbackModel"
                                    value="{{ $value }}"
                                    class="accent-[#00b4d8]"
                                >
                                <div>
                                    <div class="text-sm font-medium text-white">{{ $label }}</div>
                                    <div class="text-xs text-white/30 mt-0.5">
                                        @if($value === 'claude') Anthropic Claude Sonnet via CLI
                                        @elseif($value === 'gemini') Google Gemini via CLI
                                        @elseif($value === 'none') Kein Fallback – bei Fehler schlägt der Zyklus fehl
                                        @endif
                                    </div>
                                </div>
                                @if($fallbackModel === $value)
                                    <span class="ml-auto text-xs text-neon-blue font-medium">Aktiv</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                    @error('fallbackModel') <span class="text-neon-red text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Warning when primary = fallback --}}
                @if($primaryModel === $fallbackModel)
                    <div class="px-4 py-3 rounded-xl bg-yellow-400/10 border border-yellow-400/30 text-yellow-400 text-xs">
                        Primary und Fallback sind identisch – der Fallback wird nicht genutzt.
                    </div>
                @endif

                <button type="submit" class="btn-neon-green w-full">
                    Speichern
                </button>
            </form>
        </div>

        {{-- Current Config Overview --}}
        <div class="glass-card p-6">
            <h2 class="text-sm font-semibold text-white/60 uppercase tracking-wider mb-5">Aktueller Ablauf</h2>

            <div class="space-y-3">
                <div class="flex items-center gap-3 p-3 rounded-xl bg-white/[0.03] border border-white/10">
                    <div class="w-7 h-7 rounded-lg bg-neon-green/20 border border-neon-green/30 flex items-center justify-center text-xs font-bold text-neon-green shrink-0">1</div>
                    <div>
                        <div class="text-sm text-white font-medium">{{ $modelOptions[$primaryModel] ?? $primaryModel }}</div>
                        <div class="text-xs text-white/30">Primäres Modell wird aufgerufen</div>
                    </div>
                </div>

                @if($fallbackModel !== 'none' && $fallbackModel !== $primaryModel)
                    <div class="flex items-center gap-3 px-3">
                        <div class="w-7 flex justify-center">
                            <div class="w-px h-5 bg-white/10"></div>
                        </div>
                        <div class="text-xs text-white/30">bei Fehler</div>
                    </div>
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-white/[0.03] border border-white/10">
                        <div class="w-7 h-7 rounded-lg bg-neon-blue/20 border border-neon-blue/30 flex items-center justify-center text-xs font-bold text-neon-blue shrink-0">2</div>
                        <div>
                            <div class="text-sm text-white font-medium">{{ $fallbackOptions[$fallbackModel] ?? $fallbackModel }}</div>
                            <div class="text-xs text-white/30">Fallback wird aufgerufen</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 px-3">
                        <div class="w-7 flex justify-center">
                            <div class="w-px h-5 bg-white/10"></div>
                        </div>
                        <div class="text-xs text-white/30">bei erneutem Fehler</div>
                    </div>
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-neon-red/5 border border-neon-red/20">
                        <div class="w-7 h-7 rounded-lg bg-neon-red/20 border border-neon-red/30 flex items-center justify-center shrink-0">
                            <svg class="w-3.5 h-3.5 text-neon-red" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </div>
                        <div>
                            <div class="text-sm text-neon-red font-medium">Zyklus schlägt fehl</div>
                            <div class="text-xs text-white/30">Kein Trade wird ausgeführt</div>
                        </div>
                    </div>
                @elseif($fallbackModel === 'none')
                    <div class="flex items-center gap-3 px-3">
                        <div class="w-7 flex justify-center">
                            <div class="w-px h-5 bg-white/10"></div>
                        </div>
                        <div class="text-xs text-white/30">bei Fehler</div>
                    </div>
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-neon-red/5 border border-neon-red/20">
                        <div class="w-7 h-7 rounded-lg bg-neon-red/20 border border-neon-red/30 flex items-center justify-center shrink-0">
                            <svg class="w-3.5 h-3.5 text-neon-red" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </div>
                        <div>
                            <div class="text-sm text-neon-red font-medium">Zyklus schlägt fehl</div>
                            <div class="text-xs text-white/30">Kein Fallback konfiguriert</div>
                        </div>
                    </div>
                @else
                    <div class="px-4 py-3 rounded-xl bg-yellow-400/10 border border-yellow-400/30 text-yellow-400 text-xs mt-2">
                        Primary und Fallback sind identisch – kein echter Fallback aktiv.
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
