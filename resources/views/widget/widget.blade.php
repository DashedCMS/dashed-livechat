@php($cfg = \Dashed\DashedLivechat\Support\WidgetConfig::for($siteId))
<div
    x-data="{
        open: @entangle('open'),
        proactive: @js($trigger ?? null),
        proactiveShown: false,
        streamUrl: @js($streamUrl ?? null),
        streamBuffer: '',
        _es: null,
        fireProactive() {
            if (this.proactiveShown || !this.proactive || !this.proactive.message) return;
            this.proactiveShown = true;
            this.open = true;
            this.$refs.proactiveBubble && (this.$refs.proactiveBubble.textContent = this.proactive.message);
            this.$refs.proactiveWrap && (this.$refs.proactiveWrap.style.display = 'block');
        },
        initProactive() {
            if (!this.proactive || !this.proactive.type || this.proactive.type === 'none') return;
            const t = this.proactive.type, v = parseInt(this.proactive.value || 0);
            if (t === 'immediate') { this.fireProactive(); }
            else if (t === 'time_on_page') { setTimeout(() => this.fireProactive(), (v || 5) * 1000); }
            else if (t === 'scroll_depth') {
                const h = () => { const p = (window.scrollY + window.innerHeight) / document.body.scrollHeight * 100; if (p >= (v || 50)) { this.fireProactive(); window.removeEventListener('scroll', h); } };
                window.addEventListener('scroll', h, { passive: true });
            } else if (t === 'exit_intent') {
                const h = (e) => { if (e.clientY <= 0) { this.fireProactive(); document.removeEventListener('mouseleave', h); } };
                document.addEventListener('mouseleave', h);
            }
        },
        startStream(url) {
            if (this._es) { this._es.close(); this._es = null; }
            this.streamBuffer = '';
            const es = new EventSource(url);
            this._es = es;
            es.onmessage = (e) => {
                try { const d = JSON.parse(e.data); if (d.t) this.streamBuffer += d.t; } catch(err) {}
            };
            es.addEventListener('done', () => {
                es.close(); this._es = null;
                $wire.$refresh();
            });
            es.addEventListener('reset', () => {
                this.streamBuffer = '';
            });
            es.addEventListener('error', () => {
                es.close(); this._es = null;
                $wire.$refresh();
            });
            es.onerror = () => { es.close(); this._es = null; $wire.$refresh(); };
        }
    }"
    x-init="initProactive(); $watch('streamUrl', url => { if (url) startStream(url); })"
    class="dashed-chat"
    style="
        --chat-primary: {{ $cfg['primary'] }};
        --chat-on-primary: {{ $cfg['on_primary'] }};
        --chat-radius: {{ $cfg['radius'] }}px;
        position: fixed; z-index: 2147483000;
        {{ $cfg['position'] === 'left' ? 'left' : 'right' }}: {{ $cfg['offset'] }}px; bottom: {{ $cfg['offset'] }}px;
    "
    wire:poll.{{ config('dashed-livechat.poll_interval_ms', 1500) }}ms="pollReply"
>
    {{-- Launcher --}}
    <button x-show="!open" @click="$wire.toggle()" type="button"
        style="background: var(--chat-primary); color: var(--chat-on-primary); border-radius: 9999px; width: 60px; height: 60px; box-shadow: 0 8px 24px rgba(0,0,0,.18); border: 0; cursor: pointer;"
        aria-label="Open chat">
        @if($cfg['avatar'])
            <img src="{{ $cfg['avatar'] }}" alt="" style="width: 36px; height: 36px; border-radius: 9999px; margin: 0 auto;">
        @else
            <span style="font-size: 24px;">&#128172;</span>
        @endif
    </button>

    {{-- Panel --}}
    <div x-show="open" x-transition
        style="width: 360px; max-width: calc(100vw - 32px); height: 520px; max-height: calc(100vh - 48px); display: flex; flex-direction: column; background: #fff; border-radius: var(--chat-radius); overflow: hidden; box-shadow: 0 16px 48px rgba(0,0,0,.22);">
        <header style="background: var(--chat-primary); color: var(--chat-on-primary); padding: 14px 16px; display: flex; align-items: center; gap: 10px;">
            @if($cfg['avatar'])<img src="{{ $cfg['avatar'] }}" alt="" style="width: 32px; height: 32px; border-radius: 9999px;">@endif
            <strong style="flex: 1;">{{ $cfg['title'] }}</strong>
            <button @click="$wire.toggle()" type="button" aria-label="Sluiten" style="background: transparent; border: 0; color: inherit; font-size: 20px; cursor: pointer;">&times;</button>
        </header>

        <div style="flex: 1; overflow-y: auto; padding: 16px; background: #f7f7f8;" x-ref="scroll"
             x-effect="$nextTick(() => $refs.scroll.scrollTop = $refs.scroll.scrollHeight)">

            {{-- Proactief bericht bubble (client-side via Alpine) --}}
            <div x-ref="proactiveWrap" style="display:none; margin-bottom:10px;">
                <div x-ref="proactiveBubble" class="dashed-chat__msg dashed-chat__msg--ai"
                     style="background:#fff; color:#1f2937; padding:10px 12px; border-radius:12px; box-shadow:0 1px 2px rgba(0,0,0,.06);"></div>
            </div>

            {{-- Serverside fallback voor tests en SEO --}}
            @if(!empty($trigger['message']))
                <span style="display:none">{{ $trigger['message'] }}</span>
            @endif

            @if($messages->isEmpty() && $cfg['greeting'])
                <div class="dashed-chat__msg dashed-chat__msg--ai">{{ $cfg['greeting'] }}</div>
            @endif
            @foreach($messages as $message)
                <div class="dashed-chat__msg dashed-chat__msg--{{ $message->role === 'visitor' ? 'visitor' : 'ai' }}"
                     style="margin-bottom: 10px; max-width: 80%; padding: 10px 12px; border-radius: 12px; line-height: 1.4;
                        {{ $message->role === 'visitor'
                            ? 'margin-left: auto; background: var(--chat-primary); color: var(--chat-on-primary);'
                            : 'background: #fff; color: #1f2937; box-shadow: 0 1px 2px rgba(0,0,0,.06);' }}">
                    {!! nl2br(e($message->content)) !!}
                </div>
            @endforeach
            <div wire:loading wire:target="sendMessage" class="dashed-chat__typing" style="color:#6b7280; font-size: 13px;">Aan het typen…</div>
            @if($awaitingReply)
                {{-- Streaming: show live buffer bubble; non-streaming: show typing indicator --}}
                <template x-if="_es !== null || streamBuffer !== ''">
                    <div class="dashed-chat__msg dashed-chat__msg--ai"
                         style="background:#fff; color:#1f2937; padding:10px 12px; border-radius:12px; box-shadow:0 1px 2px rgba(0,0,0,.06); margin-bottom:10px; max-width:80%; line-height:1.4;"
                         x-text="streamBuffer || '…'"></div>
                </template>
                <template x-if="_es === null && streamBuffer === ''">
                    <div class="dashed-chat__typing" style="color:#6b7280; font-size: 13px;">Aan het typen…</div>
                </template>
            @endif
        </div>

        <form wire:submit.prevent="sendMessage" style="display: flex; gap: 8px; padding: 12px; border-top: 1px solid #eee;">
            <input wire:model="draft" type="text" placeholder="Typ je bericht…" autocomplete="off"
                style="flex: 1; border: 1px solid #ddd; border-radius: 9999px; padding: 10px 14px; outline: none;">
            <button type="submit" style="background: var(--chat-primary); color: var(--chat-on-primary); border: 0; border-radius: 9999px; padding: 0 16px; cursor: pointer;">&uarr;</button>
        </form>
        @error('draft') <div style="color:#b91c1c; font-size:12px; padding:0 12px 10px;">{{ $message }}</div> @enderror
    </div>
</div>
