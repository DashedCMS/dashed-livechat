@php($cfg = \Dashed\DashedLivechat\Support\WidgetConfig::for($siteId))
<div
    x-data="{
        open: @entangle('open'),
        publicToken: @entangle('publicToken'),
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
        },
        initWidget() {
            this.initProactive();
            const _key = 'dashed_livechat_token_' + @js($siteId);
            const _saved = localStorage.getItem(_key);
            if (_saved && !this.publicToken) { this.$wire.resumeConversation(_saved); }
            this.$watch('publicToken', v => { if (v) { localStorage.setItem(_key, v); } });
            this.$watch('streamUrl', url => { if (url) this.startStream(url); });
        }
    }"
    x-init="initWidget()"
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
    <style>[x-cloak]{display:none !important;}</style>
    {{-- Launcher --}}
    <button x-show="!open" @click="open = true" type="button"
        style="background: var(--chat-primary); color: var(--chat-on-primary); border-radius: 9999px; width: 60px; height: 60px; box-shadow: 0 8px 24px rgba(0,0,0,.18); border: 0; cursor: pointer;"
        aria-label="Open chat">
        @if($cfg['avatar'])
            <img src="{{ $cfg['avatar'] }}" alt="" style="width: 36px; height: 36px; border-radius: 9999px; margin: 0 auto;">
        @else
            <span style="font-size: 24px;">&#128172;</span>
        @endif
    </button>

    {{-- Panel --}}
    <div x-show="open" x-cloak x-transition
        style="width: 360px; max-width: calc(100vw - 32px); height: 520px; max-height: calc(100vh - 48px); display: flex; flex-direction: column; background: #fff; border-radius: var(--chat-radius); overflow: hidden; box-shadow: 0 16px 48px rgba(0,0,0,.22);">
        <header style="background: var(--chat-primary); color: var(--chat-on-primary); padding: 14px 16px; display: flex; align-items: center; gap: 10px;">
            @if($cfg['avatar'])<img src="{{ $cfg['avatar'] }}" alt="" style="width: 32px; height: 32px; border-radius: 9999px;">@endif
            <strong style="flex: 1;">{{ $cfg['title'] }}</strong>
            <button @click="open = false" type="button" aria-label="Sluiten" style="background: transparent; border: 0; color: inherit; font-size: 20px; cursor: pointer;">&times;</button>
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

            @if($messages->isEmpty())
                {{-- Persoonlijke welkomststaat (alleen als chat nog niet gestart) --}}
                <div style="text-align: center; padding: 24px 16px 16px;">
                    @php($welcomeAvatar = $agentAvatarUrl ?? $cfg['avatar'])
                    @if($welcomeAvatar)
                        <img src="{{ $welcomeAvatar }}" alt="{{ $agentName ?? $cfg['title'] }}"
                             style="width: 64px; height: 64px; border-radius: 9999px; margin: 0 auto 12px; display: block; object-fit: cover; box-shadow: 0 2px 8px rgba(0,0,0,.12);">
                    @else
                        <div style="width: 64px; height: 64px; border-radius: 9999px; background: var(--chat-primary); color: var(--chat-on-primary); display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 12px;">&#128172;</div>
                    @endif
                    @if($agentName)
                        <div style="font-weight: 600; font-size: 15px; color: #111827; margin-bottom: 6px;">{{ $agentName }}</div>
                        <div style="font-size: 14px; color: #374151; line-height: 1.5;">
                            {{ $agentGreeting ?: 'Hoi! Ik ben ' . $agentName . '. Waar kan ik je mee helpen?' }}
                        </div>
                    @elseif($agentGreeting)
                        <div style="font-size: 14px; color: #374151; line-height: 1.5;">{{ $agentGreeting }}</div>
                    @endif
                    @if(!empty($availableAgents) && count($availableAgents) > 0)
                        <div style="margin-top: 16px; text-align: left;">
                            <div style="font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">Beschikbaar</div>
                            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                @foreach($availableAgents as $avAgent)
                                    <div style="display: flex; align-items: center; gap: 8px; background: #fff; border-radius: 9999px; padding: 4px 12px 4px 4px; box-shadow: 0 1px 3px rgba(0,0,0,.08);">
                                        <div style="position: relative; flex-shrink: 0;">
                                            @if($avAgent['avatar'])
                                                <img src="{{ $avAgent['avatar'] }}" alt="{{ $avAgent['name'] }}"
                                                     style="width: 28px; height: 28px; border-radius: 9999px; object-fit: cover; display: block;">
                                            @else
                                                <div style="width: 28px; height: 28px; border-radius: 9999px; background: var(--chat-primary); color: var(--chat-on-primary); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">{{ mb_strtoupper(mb_substr($avAgent['name'], 0, 1)) }}</div>
                                            @endif
                                            <span style="position: absolute; bottom: 0; right: 0; width: 9px; height: 9px; background: #22c55e; border-radius: 9999px; border: 1.5px solid #fff; display: block;"></span>
                                        </div>
                                        <span style="font-size: 12px; font-weight: 500; color: #374151; white-space: nowrap;">{{ $avAgent['name'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if($cfg['phone'] || $cfg['email'])
                        <div style="margin-top: 16px; display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
                            @if($cfg['phone'])
                                <a href="tel:{{ preg_replace('/\s+/', '', $cfg['phone']) }}"
                                   style="display: inline-flex; align-items: center; gap: 6px; background: var(--chat-primary); color: var(--chat-on-primary); text-decoration: none; border-radius: 9999px; padding: 8px 18px; font-size: 13px; font-weight: 500; box-shadow: 0 1px 4px rgba(0,0,0,.12);">
                                    <span style="font-size:16px; line-height:1;">&#128222;</span> {{ $cfg['phone'] }}
                                </a>
                            @endif
                            @if($cfg['email'])
                                <a href="mailto:{{ $cfg['email'] }}"
                                   style="display: inline-flex; align-items: center; gap: 6px; background: #fff; color: var(--chat-primary); text-decoration: none; border-radius: 9999px; padding: 8px 18px; font-size: 13px; font-weight: 500; border: 1.5px solid var(--chat-primary); box-shadow: 0 1px 4px rgba(0,0,0,.08);">
                                    <span style="font-size:16px; line-height:1;">&#9993;&#65039;</span> {{ $cfg['email'] }}
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
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

        @if($needsContact)
            <div style="border-top: 1px solid #eee; padding: 12px; background: #fafafa;">
                <p style="margin: 0 0 8px; font-size: 13px; color: #374151; line-height: 1.4;">Mogen we je naam en e-mailadres? Dan kunnen we je verder helpen, ook als je later terugkomt.</p>
                <div style="display: flex; flex-direction: column; gap: 6px;">
                    <input wire:model.defer="contactName" type="text" placeholder="Naam"
                        style="border: 1px solid #ddd; border-radius: 9999px; padding: 8px 12px; font-size: 13px; outline: none;">
                    <input wire:model.defer="contactEmail" type="email" placeholder="E-mailadres"
                        style="border: 1px solid #ddd; border-radius: 9999px; padding: 8px 12px; font-size: 13px; outline: none;">
                    @error('contactEmail') <div style="color:#b91c1c; font-size:12px; padding: 0 4px;">{{ $message }}</div> @enderror
                    <div style="display: flex; align-items: center; gap: 10px; margin-top: 2px;">
                        <button wire:click="saveContact" type="button"
                            style="background: var(--chat-primary); color: var(--chat-on-primary); border: 0; border-radius: 9999px; padding: 8px 18px; font-size: 13px; font-weight: 500; cursor: pointer;">Versturen</button>
                        <button wire:click="dismissContact" type="button"
                            style="background: transparent; border: 0; color: #9ca3af; font-size: 12px; cursor: pointer; text-decoration: underline;">Later</button>
                    </div>
                </div>
            </div>
        @endif

        @error('draft') <div style="color:#b91c1c; font-size:12px; padding:4px 12px 0;">{{ $message }}</div> @enderror
        <form wire:submit.prevent="sendMessage" style="display: flex; gap: 8px; padding: 12px; border-top: 1px solid #eee; margin: 0;">
            <input wire:model="draft" type="text" placeholder="Typ je bericht…" autocomplete="off"
                style="flex: 1; border: 1px solid #ddd; border-radius: 9999px; padding: 10px 14px; outline: none;">
            <button type="submit" style="background: var(--chat-primary); color: var(--chat-on-primary); border: 0; border-radius: 9999px; padding: 0 16px; cursor: pointer;">&uarr;</button>
        </form>
    </div>
</div>
