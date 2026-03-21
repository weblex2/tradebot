<div>
    <div class="mb-6">
        <p class="text-sm text-white/40 mt-1">Bearbeite die AI-Prompts die für Analyse und Artikel-Scoring verwendet werden</p>
    </div>

    {{-- Saved flash --}}
    <div
        x-data="{ show: false }"
        x-on:saved.window="show = true; setTimeout(() => show = false, 2500)"
        x-show="show"
        x-transition.opacity
        class="mb-6 px-4 py-3 rounded-xl bg-neon-green/10 border border-neon-green/30 text-neon-green text-sm"
    >
        ✓ Prompt gespeichert.
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- ===== LINKE SPALTE: Prompt-Liste ===== --}}
        <div class="xl:col-span-1 space-y-3">
            <h2 class="text-xs font-semibold text-white/40 uppercase tracking-wider mb-4">Verfügbare Prompts</h2>

            @forelse($prompts as $prompt)
                <button
                    wire:click="selectPrompt({{ $prompt->id }})"
                    class="w-full text-left p-4 rounded-xl border transition-all
                        {{ $editingId === $prompt->id
                            ? 'bg-neon-blue/10 border-neon-blue/40'
                            : 'bg-white/[0.03] border-white/10 hover:border-white/25 hover:bg-white/[0.05]' }}"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-white truncate">{{ $prompt->name }}</div>
                            <div class="text-xs text-white/35 font-mono mt-0.5">{{ $prompt->key }}</div>
                        </div>
                        <div class="shrink-0 flex flex-col items-end gap-1">
                            @if($prompt->is_active)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-neon-green/15 text-neon-green border border-neon-green/25">aktiv</span>
                            @else
                                <span class="text-xs px-2 py-0.5 rounded-full bg-white/5 text-white/30 border border-white/10">inaktiv</span>
                            @endif
                        </div>
                    </div>

                    @if($prompt->description)
                        <p class="text-xs text-white/30 mt-2 line-clamp-2">{{ $prompt->description }}</p>
                    @endif

                    <div class="text-xs text-white/20 mt-2">
                        {{ mb_strlen($prompt->content) }} Zeichen · zuletzt geändert {{ $prompt->updated_at->diffForHumans() }}
                    </div>
                </button>
            @empty
                <div class="text-sm text-white/30 p-4">Keine Prompts gefunden.</div>
            @endforelse
        </div>

        {{-- ===== RECHTE SPALTE: Editor ===== --}}
        <div class="xl:col-span-2">
            @if($editingId)
                <div class="glass-card p-6">

                    {{-- Header --}}
                    <div class="flex items-start justify-between mb-5">
                        <div>
                            <h2 class="text-sm font-semibold text-white">{{ $editingName }}</h2>
                            <div class="text-xs font-mono text-neon-blue/70 mt-0.5">{{ $editingKey }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            {{-- Active-Toggle --}}
                            @foreach($prompts as $prompt)
                                @if($prompt->id === $editingId)
                                    <button
                                        wire:click="toggleActive({{ $prompt->id }})"
                                        class="text-xs px-3 py-1.5 rounded-lg border transition-colors
                                            {{ $prompt->is_active
                                                ? 'border-neon-green/30 text-neon-green hover:bg-neon-green/10'
                                                : 'border-white/15 text-white/40 hover:bg-white/5' }}"
                                    >
                                        {{ $prompt->is_active ? '● Aktiv' : '○ Inaktiv' }}
                                    </button>
                                @endif
                            @endforeach

                            {{-- Edit-Button (nur im Lesemodus sichtbar) --}}
                            @if(!$isEditing)
                                <button
                                    wire:click="startEditing"
                                    class="flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-lg border
                                           border-neon-blue/35 text-neon-blue hover:bg-neon-blue/10 transition-colors"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5
                                                 m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Bearbeiten
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Beschreibung / Platzhalter-Hinweis --}}
                    @foreach($prompts as $prompt)
                        @if($prompt->id === $editingId && $prompt->description)
                            <div class="mb-4 px-3 py-2.5 rounded-lg bg-neon-blue/5 border border-neon-blue/15">
                                <p class="text-xs text-neon-blue/70">ℹ {{ $prompt->description }}</p>
                            </div>
                        @endif
                    @endforeach

                    {{-- Textarea --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs text-white/40">Prompt-Text</label>
                            @if(!$isEditing)
                                <span class="text-xs text-white/25 italic">Schreibgeschützt – klicke „Bearbeiten" zum Editieren</span>
                            @else
                                <span class="flex items-center gap-1 text-xs text-neon-blue/60">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5
                                                 m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Bearbeitungsmodus aktiv
                                </span>
                            @endif
                        </div>
                        <textarea
                            wire:model="editingContent"
                            rows="22"
                            @if(!$isEditing) readonly @endif
                            class="w-full rounded-xl px-4 py-3 text-sm font-mono leading-relaxed resize-y
                                   focus:outline-none transition-colors
                                   {{ $isEditing
                                       ? 'bg-black/30 border border-neon-blue/25 text-white/85 focus:border-neon-blue/40 focus:ring-1 focus:ring-neon-blue/20'
                                       : 'border border-white/[0.07] text-white/55 cursor-default select-text' }}"
                            style="{{ !$isEditing ? 'background-color: rgba(10, 14, 26, 0.75);' : '' }}"
                            spellcheck="false"
                        ></textarea>
                        @error('editingContent')
                            <p class="text-xs text-neon-red mt-1">{{ $message }}</p>
                        @enderror
                        <div class="flex justify-between items-center mt-1">
                            <span class="text-xs text-white/20">{{ mb_strlen($editingContent) }} Zeichen</span>
                        </div>
                    </div>

                    {{-- Buttons --}}
                    <div class="flex items-center gap-3 mt-5">
                        @if($isEditing)
                            <button
                                wire:click="save"
                                wire:loading.attr="disabled"
                                wire:target="save"
                                class="px-5 py-2 rounded-xl bg-neon-green/20 border border-neon-green/40
                                       text-neon-green text-sm font-medium hover:bg-neon-green/30 transition-colors
                                       disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span wire:loading.remove wire:target="save">Speichern</span>
                                <span wire:loading wire:target="save">Speichert…</span>
                            </button>

                            <button
                                wire:click="resetToOriginal"
                                class="px-5 py-2 rounded-xl bg-white/[0.04] border border-white/10
                                       text-white/50 text-sm hover:bg-white/[0.07] hover:text-white/70 transition-colors"
                                title="Änderungen zurücksetzen (noch nicht gespeicherte Änderungen)"
                            >
                                Zurücksetzen
                            </button>

                            <button
                                wire:click="cancelEditing"
                                class="ml-auto px-4 py-2 rounded-xl bg-white/[0.03] border border-white/[0.08]
                                       text-white/35 text-sm hover:bg-white/[0.06] hover:text-white/55 transition-colors"
                                title="Bearbeiten abbrechen"
                            >
                                Abbrechen
                            </button>
                        @endif
                    </div>
                </div>

            @else
                {{-- Placeholder wenn nichts ausgewählt --}}
                <div class="glass-card p-10 flex flex-col items-center justify-center text-center min-h-64">
                    <div class="w-12 h-12 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <p class="text-sm text-white/25">Wähle links einen Prompt aus um ihn zu bearbeiten</p>
                </div>
            @endif
        </div>

    </div>
</div>
