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
        expanded: false,
        lastMessageId: @entangle('lastMessageId'),
        preview: @entangle('lastMessagePreview'),
        indicator: @js($newMessageIndicator ?? 'badge'),
        unread: 0,
        previews: [],
        _seenId: 0,
        _justSent: false,
        _audioCtx: null,
        playPing() {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (! Ctx) return;
                this._audioCtx = this._audioCtx || new Ctx();
                const ctx = this._audioCtx;
                if (ctx.state === 'suspended') { ctx.resume(); }
                const o = ctx.createOscillator();
                const g = ctx.createGain();
                o.connect(g); g.connect(ctx.destination);
                o.type = 'sine';
                o.frequency.setValueAtTime(880, ctx.currentTime);
                o.frequency.setValueAtTime(1170, ctx.currentTime + 0.12);
                g.gain.setValueAtTime(0.0001, ctx.currentTime);
                g.gain.exponentialRampToValueAtTime(0.18, ctx.currentTime + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.35);
                o.start();
                o.stop(ctx.currentTime + 0.35);
            } catch (e) {}
        },
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

            // Ongelezen-teller + geluid bij binnenkomende berichten.
            this._seenId = this.lastMessageId || 0;
            this.$watch('lastMessageId', (val) => {
                if (val <= this._seenId) return;
                this._seenId = val;
                const wasOwn = this._justSent;
                this._justSent = false;
                if (! this.open) {
                    this.unread++;
                    this.$nextTick(() => {
                        if (this.preview) {
                            this.previews.push(this.preview);
                            if (this.previews.length > 4) { this.previews.shift(); }
                        }
                    });
                }
                if (! wasOwn) { this.playPing(); }
            });
            this.$watch('open', (isOpen) => {
                if (isOpen) { this._seenId = this.lastMessageId; this.unread = 0; this.previews = []; }
            });
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
    <style>[x-cloak]{display:none !important;}
    .dashed-chat__panel{ display: flex; flex-direction: column; height: 600px; max-height: calc(100dvh - 48px); overflow: hidden; }
    .dashed-chat__panel--expanded{ width: min(960px, calc(100vw - 32px)) !important; height: calc(100dvh - 48px) !important; }
    .dashed-chat__md > :first-child{ margin-top:0; }
    .dashed-chat__md > :last-child{ margin-bottom:0; }
    .dashed-chat__md p{ margin:0 0 .5em; }
    .dashed-chat__md ul, .dashed-chat__md ol{ margin:.25em 0 .5em; padding-left:1.25em; }
    .dashed-chat__md li{ margin:.1em 0; }
    .dashed-chat__md a{ color: var(--chat-primary); text-decoration: underline; }
    .dashed-chat__md code{ background: rgba(0,0,0,.06); padding:.05em .3em; border-radius:4px; font-size:.9em; }
    .dashed-chat__md pre{ background: rgba(0,0,0,.06); padding:.5em; border-radius:6px; overflow:auto; }
    .dashed-chat__md pre code{ background:none; padding:0; }
    @keyframes dashed-chat-pulse { 0% { box-shadow: 0 0 0 0 rgba(34,197,94,.55); } 70% { box-shadow: 0 0 0 9px rgba(34,197,94,0); } 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); } }
    .dashed-chat__badge { animation: dashed-chat-pulse 1.6s ease-out infinite; }
    </style>
    {{-- Voorbeeld(en) van nieuw bericht boven het icoon (indien zo ingesteld); stapelt bij meerdere --}}
    <div x-show="!open && indicator === 'preview' && previews.length > 0" x-cloak
        style="position: absolute; bottom: 74px; {{ $cfg['position'] === 'left' ? 'left' : 'right' }}: 0; display: flex; flex-direction: column; gap: 8px; align-items: {{ $cfg['position'] === 'left' ? 'flex-start' : 'flex-end' }}; width: 320px; max-width: calc(100vw - 48px);">
        <template x-for="(p, i) in previews" :key="i">
            <div @click="open = true" x-transition
                style="width: 100%; background:#fff; color:#1f2937; padding:12px 14px; border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,.18); cursor:pointer; font-size:13px; line-height:1.45;">
                <span x-text="p"></span>
            </div>
        </template>
    </div>

    {{-- Launcher --}}
    <button x-show="!open" @click="open = true" type="button"
        style="position: relative; background: var(--chat-primary); color: var(--chat-on-primary); border-radius: 9999px; width: 60px; height: 60px; box-shadow: 0 8px 24px rgba(0,0,0,.18); border: 0; cursor: pointer;"
        aria-label="Open chat">
        @if($cfg['avatar'])
            <img src="{{ $cfg['avatar'] }}" alt="" style="width: 36px; height: 36px; border-radius: 9999px; margin: 0 auto;">
        @else
            <span style="font-size: 24px;">&#128172;</span>
        @endif
        <span x-show="unread > 0 && indicator !== 'preview'" x-cloak x-text="unread" class="dashed-chat__badge" aria-label="Ongelezen berichten"
            style="position: absolute; top: -4px; right: -4px; min-width: 20px; height: 20px; padding: 0 5px; background: #22c55e; color: #fff; border: 2px solid #fff; border-radius: 9999px; font-size: 11px; font-weight: 700; line-height: 18px; text-align: center;"></span>
    </button>

    {{-- Panel --}}
    <div x-show="open" x-cloak x-transition
        class="dashed-chat__panel"
        :class="{ 'dashed-chat__panel--expanded': expanded }"
        style="width: 400px; max-width: calc(100vw - 32px); background: #fff; border-radius: var(--chat-radius); box-shadow: 0 16px 48px rgba(0,0,0,.22);">
        <header style="background: var(--chat-primary); color: var(--chat-on-primary); padding: 14px 16px; display: flex; flex-shrink: 0; align-items: center; gap: 10px;">
            @if($partnerAvatarUrl)<img src="{{ $partnerAvatarUrl }}" alt="" style="width: 32px; height: 32px; border-radius: 9999px; object-fit: cover;">@endif
            <div style="flex: 1; min-width: 0;">
                <strong style="display:block; line-height:1.2; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $cfg['title'] }}</strong>
                <span style="font-size: 11px; opacity: .9; display:flex; align-items:center; gap:5px; line-height:1.3;">
                    <span style="width:7px; height:7px; border-radius:9999px; flex-shrink:0; background: {{ $partnerType === 'human' ? '#22c55e' : ($partnerType === 'waiting' ? '#f59e0b' : '#a3e635') }};"></span>
                    <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $partnerName }}{{ $partnerType === 'ai' ? ' · AI' : ($partnerType === 'human' ? ' · medewerker' : '') }}</span>
                </span>
            </div>
            <button @click="expanded = !expanded" type="button"
                :aria-label="expanded ? 'Verkleinen' : 'Vergroten'" :title="expanded ? 'Verkleinen' : 'Groot scherm'"
                style="background: transparent; border: 0; color: inherit; cursor: pointer; font-size: 18px; line-height: 1;">
                <span x-show="!expanded">&#10530;</span><span x-show="expanded" x-cloak>&#10529;</span>
            </button>
            @if($publicToken)
            <button type="button" title="Nieuw gesprek"
                wire:click="startNewChat"
                x-on:click="localStorage.removeItem('dashed_livechat_token_' + @js($siteId))"
                style="background: transparent; border: 0; color: inherit; cursor: pointer; font-size: 16px;">&#8635;</button>
            @endif
            <button @click="open = false" type="button" aria-label="Sluiten" style="background: transparent; border: 0; color: inherit; font-size: 20px; cursor: pointer;">&times;</button>
        </header>

        @if($canRequestHuman)
            <div style="padding: 8px 12px; border-bottom: 1px solid #eee; background:#fafafa; display:flex; justify-content:center;">
                <button type="button" wire:click="requestHuman" wire:loading.attr="disabled"
                    style="background: transparent; border: 1px solid var(--chat-primary); color: var(--chat-primary); border-radius: 9999px; padding: 5px 14px; font-size: 12px; cursor: pointer; display:inline-flex; align-items:center; gap:6px;">
                    <span style="font-size:14px; line-height:1;">&#128100;</span> Liever een medewerker spreken?
                </button>
            </div>
        @elseif($chatMode === 'waiting_human')
            <div style="padding: 8px 12px; border-bottom: 1px solid #eee; background:#fff7ed; color:#9a3412; font-size:12px; text-align:center;">
                Je vraag staat klaar voor een collega. Je kunt ondertussen gewoon verder typen.
            </div>
        @endif

        <div data-chat-scroll style="flex: 1; min-height: 0; overflow-y: auto; padding: 16px; background: #f7f7f8;" x-ref="scroll"
             x-effect="open; lastMessageId; $nextTick(() => { $el.scrollTop = $el.scrollHeight; })">

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
                @php($senderLabel = $message->role === 'visitor'
                    ? 'Jij'
                    : ($message->agent?->name
                        ? ($message->role === 'human' ? (explode(' ', trim($message->agent->name))[0] ?: $message->agent->name) : $message->agent->name)
                        : ($message->role === 'human' ? 'Medewerker' : ($agentName ?: 'Assistent'))))
                @if($message->role === 'visitor')
                    <div data-chat-msg class="dashed-chat__msg dashed-chat__msg--visitor"
                         style="margin-bottom: 10px; max-width: 80%; padding: 10px 12px; border-radius: 12px; line-height: 1.4; margin-left: auto; background: var(--chat-primary); color: var(--chat-on-primary);">
                        <div style="font-size:10px; opacity:.65; margin-bottom:3px;">{{ $senderLabel }}</div>
                        {!! nl2br(e($message->content)) !!}
                    </div>
                @else
                    @php($msgAvatar = $messageAvatars[$message->id] ?? null)
                    <div data-chat-msg style="display:flex; gap:8px; align-items:flex-start; margin-bottom:10px; max-width:85%;">
                        @if($msgAvatar)
                            <img src="{{ $msgAvatar }}" alt="" style="width:28px; height:28px; border-radius:9999px; object-fit:cover; flex-shrink:0;">
                        @else
                            <div style="width:28px; height:28px; border-radius:9999px; flex-shrink:0; background: var(--chat-primary); color: var(--chat-on-primary); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:600;">{{ mb_strtoupper(mb_substr($senderLabel, 0, 1)) }}</div>
                        @endif
                        <div class="dashed-chat__msg dashed-chat__msg--ai"
                             style="min-width:0; padding: 10px 12px; border-radius: 12px; line-height: 1.4; background: #fff; color: #1f2937; box-shadow: 0 1px 2px rgba(0,0,0,.06);">
                            <div style="font-size:10px; opacity:.65; margin-bottom:3px;">{{ $senderLabel }}{{ $message->role === 'human' ? ' · medewerker' : '' }}</div>
                            <div class="dashed-chat__md">{!! \Illuminate\Support\Str::markdown($message->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                        </div>
                    </div>
                @endif
            @endforeach
            {{-- Live streaming-buffer (alleen bij streaming; client-side) --}}
            <div x-show="streamBuffer !== ''" x-cloak class="dashed-chat__msg dashed-chat__msg--ai"
                 style="background:#fff; color:#1f2937; padding:10px 12px; border-radius:12px; box-shadow:0 1px 2px rgba(0,0,0,.06); margin-bottom:10px; max-width:80%; line-height:1.4;"
                 x-text="streamBuffer || '…'"></div>

            {{-- Typindicator: server-gestuurd via @if zodat Livewire 'm netjes wegmorpht
                 zodra het antwoord binnen is (plain div, geen verweesde Alpine-knoop). --}}
            @if($awaitingReply)
                <div x-show="streamBuffer === ''" class="dashed-chat__typing" style="color:#6b7280; font-size: 13px;">Aan het typen…</div>
            @endif
        </div>

        @error('draft') <div style="color:#b91c1c; font-size:12px; padding:4px 12px 0;">{{ $message }}</div> @enderror
        <form wire:submit.prevent="sendMessage" x-on:submit="_justSent = true" style="display: flex; flex-shrink: 0; gap: 8px; padding: 12px; border-top: 1px solid #eee; margin: 0;">
            <input wire:model="draft"
                type="text"
                autocomplete="off"
                placeholder="{{ $contactStep === 'name' ? 'Typ je naam…' : 'Typ je bericht…' }}"
                style="flex: 1; border: 1px solid #ddd; border-radius: 9999px; padding: 10px 14px; outline: none;">
            <button type="submit" style="background: var(--chat-primary); color: var(--chat-on-primary); border: 0; border-radius: 9999px; padding: 0 16px; cursor: pointer;">&uarr;</button>
        </form>
    </div>

</div>
