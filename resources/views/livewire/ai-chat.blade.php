<div>
    <div class="mb-6">
        {{-- Title row --}}
        <div class="flex items-center justify-between">
            <p class="text-sm text-white/40 mt-1">Stelle Fragen zum Projekt oder lass dir bei Aufgaben helfen</p>
            @if(!empty($messages))
                <button wire:click="clearChat" class="text-xs text-white/30 hover:text-neon-red transition-colors">
                    Chat leeren
                </button>
            @endif
        </div>

        {{-- Session Dropdown --}}
        <div class="flex items-center gap-2 mt-3">
            {{-- Select --}}
            <div class="flex-1">
                <select
                    x-on:change="$wire.loadSession($event.target.value)"
                    class="select-glass w-full text-sm"
                >
                    @foreach($sessions as $session)
                        <option value="{{ $session['id'] }}" {{ $session['id'] === $activeSessionId ? 'selected' : '' }}>
                            {{ $session['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- New session button --}}
            <button
                wire:click="createNewSession"
                title="Neue Session starten"
                class="shrink-0 w-8 h-8 rounded-lg bg-neon-green/10 border border-neon-green/30 text-neon-green hover:bg-neon-green/20 transition-colors flex items-center justify-center"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </button>

            {{-- Delete session button (only if more than one session exists) --}}
            @if(count($sessions) > 1)
                <button
                    wire:click="deleteSession('{{ $activeSessionId }}')"
                    wire:confirm="Diese Session wirklich löschen?"
                    title="Aktive Session löschen"
                    class="shrink-0 w-8 h-8 rounded-lg bg-neon-red/10 border border-neon-red/30 text-neon-red hover:bg-neon-red/20 transition-colors flex items-center justify-center"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            @endif
        </div>
    </div>

    <div
        class="glass-card flex flex-col"
        style="height: calc(100vh - 220px);"
        x-data="{
            msg: '',
            streaming: false,
            streamingText: '',
            streamingThinking: '',
            streamingTools: [],
            showThinking: false,
            pastedImages: [],
            currentReader: null,

            scrollToBottom() {
                const el = this.$refs.msgs;
                if (el) el.scrollTop = el.scrollHeight;
            },

            send() {
                if (this.streaming) return;
                if (!this.msg.trim() && this.pastedImages.length === 0) return;
                const images = this.pastedImages.map(p => p.data);
                const text   = this.msg;
                this.msg     = '';
                this.pastedImages = [];
                this.$nextTick(() => {
                    if (this.$refs.input) {
                        this.$refs.input.style.height = '44px';
                    }
                });
                // Wait for Livewire to persist the user message, then start streaming
                $wire.sendMessage(text, images).then(() => {
                    this.$nextTick(() => { this.scrollToBottom(); this.startStream(); });
                });
                $nextTick(() => this.$refs.input?.focus());
            },

            handlePaste(e) {
                const items = e.clipboardData?.items;
                if (!items) return;
                for (const item of items) {
                    if (item.type.startsWith('image/')) {
                        e.preventDefault();
                        const file   = item.getAsFile();
                        const reader = new FileReader();
                        reader.onload = (ev) => {
                            this.pastedImages.push({ preview: ev.target.result, data: ev.target.result });
                        };
                        reader.readAsDataURL(file);
                    }
                }
            },

            removeImage(index) { this.pastedImages.splice(index, 1); },

            async startStream() {
                // Gather history from Livewire messages (exclude last user msg – it's the current one)
                const messages = $wire.messages ?? [];
                const history  = messages.slice(0, -1);  // all but last user message
                const last     = messages[messages.length - 1];
                if (!last || last.role !== 'user') return;

                this.streaming      = true;
                this.streamingText  = '';
                this.streamingThinking = '';
                this.streamingTools = [];
                this.showThinking   = false;

                $nextTick(() => this.scrollToBottom());

                const csrfToken = document.querySelector('meta[name=csrf-token]')?.content ?? '';

                try {
                    const resp = await fetch('{{ route('tradebot.chat.stream') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'text/event-stream',
                        },
                        body: JSON.stringify({
                            message: last.content,
                            history: history,
                            images:  last.images ?? [],
                        }),
                    });

                    if (!resp.ok) {
                        this.streamingText = 'Fehler: Server antwortete mit ' + resp.status;
                        this.finishStream(this.streamingText);
                        return;
                    }

                    const reader  = resp.body.getReader();
                    this.currentReader = reader;
                    const decoder = new TextDecoder();
                    let   buffer  = '';
                    let   finalText = '';

                    while (true) {
                        const { done, value } = await reader.read();
                        if (done) break;

                        buffer += decoder.decode(value, { stream: true });

                        // Parse SSE events (double-newline delimited)
                        let boundary;
                        while ((boundary = buffer.indexOf('\n\n')) !== -1) {
                            const eventBlock = buffer.slice(0, boundary);
                            buffer = buffer.slice(boundary + 2);

                            let eventName = 'message';
                            let data      = '';
                            for (const line of eventBlock.split('\n')) {
                                if (line.startsWith('event: ')) eventName = line.slice(7).trim();
                                if (line.startsWith('data: '))  data      = line.slice(6).trim();
                            }

                            if (!data) continue;
                            let parsed;
                            try { parsed = JSON.parse(data); } catch { continue; }

                            if (eventName === 'text') {
                                this.streamingText += parsed.text ?? '';
                                $nextTick(() => this.scrollToBottom());
                            } else if (eventName === 'thinking') {
                                this.streamingThinking += parsed.text ?? '';
                            } else if (eventName === 'tool_use') {
                                this.streamingTools.push({ name: parsed.name, input: parsed.input });
                                $nextTick(() => this.scrollToBottom());
                            } else if (eventName === 'tool_result') {
                                const last = this.streamingTools[this.streamingTools.length - 1];
                                if (last) last.result = parsed.text ?? '';
                            } else if (eventName === 'result') {
                                finalText = parsed.text ?? this.streamingText;
                            } else if (eventName === 'error') {
                                this.streamingText = 'Fehler: ' + (parsed.message ?? 'Unbekannter Fehler');
                            } else if (eventName === 'done') {
                                // handled after loop
                            }
                        }
                    }

                    this.finishStream(finalText || this.streamingText);

                } catch (err) {
                    this.streamingText = 'Verbindungsfehler: ' + err.message;
                    this.finishStream(this.streamingText);
                }
            },

            finishStream(finalText) {
                this.streaming = false;
                if (finalText) {
                    $wire.appendResponse(finalText);
                }
                this.streamingText     = '';
                this.streamingThinking = '';
                this.streamingTools    = [];
                this.currentReader     = null;
            },

        }"
        x-on:message-sent.window="$nextTick(() => scrollToBottom())"
        x-init="scrollToBottom()"
    >

        {{-- Messages --}}
        <div x-ref="msgs" class="flex-1 overflow-y-auto p-6 space-y-4">
            @if(empty($messages))
                <div class="flex flex-col items-center justify-center h-full text-center">
                    <div class="w-16 h-16 rounded-2xl bg-neon-green/10 border border-neon-green/20 flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-neon-green" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                    </div>
                    <div class="text-white font-medium mb-1">AI Assistant</div>
                    <div class="text-white/30 text-sm max-w-sm">
                        Frag mich alles über das Tradebot-Projekt – Architektur, Code, Konfiguration oder Trading-Strategien.
                    </div>
                </div>
            @else
                @foreach($messages as $msg)
                    <div class="flex {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }} gap-3">

                        @if($msg['role'] === 'assistant')
                            <div class="w-7 h-7 rounded-lg bg-neon-green/20 border border-neon-green/30 flex items-center justify-center shrink-0 mt-1">
                                <svg class="w-3.5 h-3.5 text-neon-green" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                            </div>
                        @endif

                        <div class="max-w-[75%]">
                            @if(!empty($msg['images']))
                                <div class="flex gap-2 mb-1.5 flex-wrap {{ $msg['role'] === 'user' ? 'justify-end' : '' }}">
                                    @foreach($msg['images'] as $imgData)
                                        <img src="{{ $imgData }}" class="h-24 rounded-lg border border-white/20 object-cover">
                                    @endforeach
                                </div>
                            @endif
                            <div class="px-4 py-3 rounded-2xl text-sm leading-relaxed
                                {{ $msg['role'] === 'user'
                                    ? 'bg-neon-blue/20 border border-neon-blue/30 text-white rounded-tr-sm'
                                    : 'bg-white/[0.06] border border-white/[0.10] text-white/90 rounded-tl-sm' }}">
                                {!! nl2br(e($msg['content'])) !!}
                            </div>
                            <div class="text-xs text-white/20 mt-1 {{ $msg['role'] === 'user' ? 'text-right' : 'text-left' }}">
                                {{ $msg['time'] }}
                            </div>
                        </div>

                        @if($msg['role'] === 'user')
                            <div class="w-7 h-7 rounded-lg bg-neon-blue/20 border border-neon-blue/30 flex items-center justify-center shrink-0 mt-1">
                                <svg class="w-3.5 h-3.5 text-neon-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                        @endif
                    </div>
                @endforeach
            @endif

            {{-- ── Live streaming bubble ── --}}
            <template x-if="streaming || streamingText">
                <div class="flex justify-start gap-3">
                    <div class="w-7 h-7 rounded-lg bg-neon-green/20 border border-neon-green/30 flex items-center justify-center shrink-0 mt-1">
                        <svg class="w-3.5 h-3.5 text-neon-green" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                    </div>
                    <div class="max-w-[75%] space-y-2">

                        {{-- Thinking block (collapsible) --}}
                        <template x-if="streamingThinking">
                            <div class="rounded-xl border border-neon-blue/20 bg-neon-blue/5 text-xs overflow-hidden">
                                <button
                                    type="button"
                                    x-on:click="showThinking = !showThinking"
                                    class="w-full flex items-center gap-2 px-3 py-2 text-neon-blue/70 hover:text-neon-blue transition-colors text-left"
                                >
                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                    </svg>
                                    <span x-text="showThinking ? 'Gedanken verbergen' : 'Gedanken anzeigen'"></span>
                                    <svg class="w-3 h-3 ml-auto transition-transform" :class="showThinking ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div x-show="showThinking" class="px-3 pb-3 text-white/50 whitespace-pre-wrap leading-relaxed border-t border-neon-blue/10 pt-2" x-text="streamingThinking"></div>
                            </div>
                        </template>

                        {{-- Tool calls --}}
                        <template x-for="(tool, i) in streamingTools" :key="i">
                            <div class="rounded-xl border border-yellow-500/20 bg-yellow-500/5 px-3 py-2 text-xs">
                                <div class="flex items-center gap-2 text-yellow-400/80 mb-1">
                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <span>Tool: </span><span class="font-mono text-yellow-300" x-text="tool.name"></span>
                                    <template x-if="tool.result">
                                        <svg class="w-3 h-3 ml-auto text-neon-green" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </template>
                                    <template x-if="!tool.result">
                                        <svg class="w-3 h-3 ml-auto text-yellow-400/60 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                    </template>
                                </div>
                                <template x-if="Object.keys(tool.input).length > 0">
                                    <div class="font-mono text-white/40 truncate" x-text="JSON.stringify(tool.input)"></div>
                                </template>
                            </div>
                        </template>

                        {{-- Streaming text --}}
                        <div class="px-4 py-3 rounded-2xl rounded-tl-sm text-sm leading-relaxed bg-white/[0.06] border border-white/[0.10] text-white/90">
                            <template x-if="!streamingText && streaming">
                                {{-- Dots while waiting for first token --}}
                                <div class="flex gap-1 items-center h-4">
                                    <div class="w-1.5 h-1.5 rounded-full bg-neon-green/60 animate-bounce" style="animation-delay: 0ms"></div>
                                    <div class="w-1.5 h-1.5 rounded-full bg-neon-green/60 animate-bounce" style="animation-delay: 150ms"></div>
                                    <div class="w-1.5 h-1.5 rounded-full bg-neon-green/60 animate-bounce" style="animation-delay: 300ms"></div>
                                </div>
                            </template>
                            <template x-if="streamingText">
                                <span>
                                    <span x-text="streamingText"></span>
                                    {{-- Blinking cursor while streaming --}}
                                    <template x-if="streaming">
                                        <span class="inline-block w-0.5 h-4 bg-neon-green/80 ml-0.5 animate-pulse align-text-bottom"></span>
                                    </template>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

        </div>

        {{-- Input --}}
        <div class="border-t border-white/[0.08] p-4">
            {{-- Pasted image previews --}}
            <div x-show="pastedImages.length > 0" class="flex gap-2 mb-3 flex-wrap">
                <template x-for="(img, i) in pastedImages" :key="i">
                    <div class="relative group">
                        <img :src="img.preview" class="h-16 w-16 object-cover rounded-lg border border-white/20">
                        <button
                            type="button"
                            x-on:click="removeImage(i)"
                            class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-neon-red/80 text-white text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                        >×</button>
                    </div>
                </template>
            </div>

            <div class="flex gap-3 items-end">
                <textarea
                    x-ref="input"
                    x-model="msg"
                    x-on:keydown="if ($event.key === 'Enter' && !$event.shiftKey) { $event.preventDefault(); send(); }"
                    x-on:paste="handlePaste($event)"
                    x-on:input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 200) + 'px'"
                    placeholder="Frag mich etwas… (Enter = senden, Shift+Enter = Zeilenumbruch)"
                    class="input-glass flex-1 resize-none overflow-y-auto leading-relaxed"
                    style="height: 44px; min-height: 44px; max-height: 200px;"
                    rows="1"
                    autocomplete="off"
                    :disabled="streaming"
                ></textarea>
                <button
                    type="button"
                    x-on:click="send()"
                    class="btn-neon-green px-5 shrink-0"
                    style="height: 44px;"
                    :disabled="streaming"
                >
                    <span x-show="!streaming">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                    </span>
                    <span x-show="streaming">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </span>
                </button>
            </div>
            <div class="text-xs text-white/20 mt-2 text-center">
                Claude Sonnet · Streaming · Kontext: letzte 6 Nachrichten · Screenshots per Strg+V · Shift+Enter für Zeilenumbruch
            </div>
        </div>

    </div>
</div>
