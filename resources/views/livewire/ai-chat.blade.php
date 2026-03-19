<div>
    <div class="mb-6 flex items-center justify-between">
        <p class="text-sm text-white/40 mt-1">Stelle Fragen zum Projekt oder lass dir bei Aufgaben helfen</p>
        @if(!empty($messages))
            <button wire:click="clearChat" class="text-xs text-white/30 hover:text-neon-red transition-colors">
                Chat leeren
            </button>
        @endif
    </div>

    <div
        class="glass-card flex flex-col"
        style="height: calc(100vh - 220px);"
        x-data="{
            msg: '',
            thinking: false,
            pastedImages: [],
            scrollToBottom() { const el = this.$refs.msgs; if (el) el.scrollTop = el.scrollHeight; },
            send() {
                if (!this.msg.trim() && this.pastedImages.length === 0) return;
                const images = this.pastedImages.map(p => p.data);
                $wire.sendMessage(this.msg, images);
                this.msg = '';
                this.pastedImages = [];
                $nextTick(() => this.$refs.input.focus());
            },
            handlePaste(e) {
                const items = e.clipboardData?.items;
                if (!items) return;
                for (const item of items) {
                    if (item.type.startsWith('image/')) {
                        e.preventDefault();
                        const file = item.getAsFile();
                        const reader = new FileReader();
                        reader.onload = (ev) => {
                            this.pastedImages.push({
                                preview: ev.target.result,
                                data: ev.target.result
                            });
                        };
                        reader.readAsDataURL(file);
                    }
                }
            },
            removeImage(index) {
                this.pastedImages.splice(index, 1);
            }
        }"
        x-on:message-sent.window="thinking = false; $nextTick(() => { scrollToBottom(); $refs.input.focus(); })"
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

                {{-- Triggers getResponse() as soon as the user message is the last entry --}}
                @if(end($messages)['role'] === 'user')
                    <div x-init="$wire.getResponse()"></div>
                @endif
            @endif

            {{-- Thinking indicator --}}
            <div wire:loading.flex wire:target="getResponse" class="justify-start gap-3">
                <div class="w-7 h-7 rounded-lg bg-neon-green/20 border border-neon-green/30 flex items-center justify-center shrink-0 mt-1">
                    <svg class="w-3.5 h-3.5 text-neon-green" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                </div>
                <div class="px-4 py-3 rounded-2xl rounded-tl-sm bg-white/[0.06] border border-white/[0.10]">
                    <div class="flex gap-1 items-center h-4">
                        <div class="w-1.5 h-1.5 rounded-full bg-neon-green/60 animate-bounce" style="animation-delay: 0ms"></div>
                        <div class="w-1.5 h-1.5 rounded-full bg-neon-green/60 animate-bounce" style="animation-delay: 150ms"></div>
                        <div class="w-1.5 h-1.5 rounded-full bg-neon-green/60 animate-bounce" style="animation-delay: 300ms"></div>
                    </div>
                </div>
            </div>
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

            <div class="flex gap-3">
                <input
                    type="text"
                    x-ref="input"
                    x-model="msg"
                    x-on:keydown.enter.prevent="send()"
                    x-on:paste="handlePaste($event)"
                    placeholder="Frag mich etwas… (Strg+V für Screenshots)"
                    class="input-glass flex-1"
                    autocomplete="off"
                    :disabled="thinking"
                >
                <button
                    type="button"
                    x-on:click="send()"
                    class="btn-neon-green px-5 shrink-0"
                    :disabled="thinking"
                >
                    <span x-show="!thinking">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                    </span>
                    <span x-show="thinking">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </span>
                </button>
            </div>
            <div class="text-xs text-white/20 mt-2 text-center">
                Claude Sonnet · Kontext: letzte 6 Nachrichten · Screenshots per Strg+V
            </div>
        </div>

    </div>
</div>
