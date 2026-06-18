<x-filament::page>
    <div class="dlc">
        <div class="dlc__toolbar">
            <span class="dlc__mode">
                <span class="dlc__mode-label">Modus</span>
                <x-filament::badge :color="$mode === 'human' ? 'info' : ($mode === 'ai' ? 'success' : 'gray')">
                    {{ ucfirst($mode) }}
                </x-filament::badge>
            </span>

            <span class="dlc__mode">
                <span class="dlc__mode-label">Status</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="status">
                        <option value="active">Actief</option>
                        <option value="closed">Afgerond</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </span>

            <div class="dlc__actions">
                @if($mode !== 'human')
                    <x-filament::button icon="heroicon-m-hand-raised" wire:click="takeOver" wire:loading.attr="disabled">
                        Overnemen
                    </x-filament::button>
                @else
                    <x-filament::button color="gray" icon="heroicon-m-arrow-uturn-left" wire:click="releaseToAi" wire:loading.attr="disabled">
                        Teruggeven aan AI
                    </x-filament::button>
                @endif
            </div>
        </div>

        <div class="dlc__layout">
            <div class="dlc__main">
        <x-filament::section class="dlc__panel">
            <div class="dlc__thread" wire:poll.3s="pollMessages"
                 x-data="{ lastId: @entangle('lastMessageId') }"
                 x-effect="lastId; $nextTick(() => { $el.scrollTop = $el.scrollHeight; })">
                @forelse($conversation->messages as $message)
                    @if($message->role === 'system')
                        <div class="dlc__sysrow">
                            <div class="dlc__syserror">
                                <div class="dlc__syserror-head">
                                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="dlc__syserror-icon" />
                                    <span>Foutmelding · alleen zichtbaar voor medewerkers</span>
                                    <span class="dlc__time">{{ $message->created_at?->format('d-m H:i') }}</span>
                                </div>
                                <pre class="dlc__syserror-body">{{ $message->content }}</pre>
                            </div>
                        </div>
                        @continue
                    @endif
                    @php($isVisitor = $message->role === 'visitor')
                    <div @class([
                        'dlc__row',
                        'dlc__row--in' => $isVisitor,
                        'dlc__row--out' => ! $isVisitor,
                    ])>
                        <div @class([
                            'dlc__bubble',
                            'dlc__bubble--visitor' => $isVisitor,
                            'dlc__bubble--human' => $message->role === 'human',
                            'dlc__bubble--ai' => ! $isVisitor && $message->role !== 'human',
                        ])>
                            <div class="dlc__meta">
                                <span class="dlc__author">{{ ucfirst($message->role) }}{{ $message->agent?->name ? ' · ' . $message->agent->name : '' }}</span>
                                <span class="dlc__time">{{ $message->created_at?->format('d-m H:i') }}</span>
                            </div>

                            <div class="dlc__body">
                                @if($isVisitor)
                                    {!! nl2br(e($message->content)) !!}
                                @else
                                    {!! \Illuminate\Support\Str::markdown($message->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                @endif
                            </div>

                            @php($atts = $message->attachmentsData())
                            @if(! empty($atts))
                                <div style="display:flex; flex-wrap:wrap; gap:.5rem; margin-top:.5rem;">
                                    @foreach($atts as $att)
                                        @if($att['is_image'])
                                            <button type="button" x-on:click="$dispatch('open-lightbox', { src: @js($att['url']) })" style="border:0; padding:0; background:none; cursor:zoom-in; line-height:0;"><img src="{{ $att['thumb_url'] }}" alt="" style="max-width:160px; border-radius:.5rem;"></button>
                                        @else
                                            <a href="{{ $att['url'] }}" target="_blank" rel="noopener" style="display:inline-flex; align-items:center; gap:.375rem; font-size:.8125rem; text-decoration:underline; color:inherit;">📎 {{ $att['name'] }}</a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            @if($message->tool_calls)
                                <div class="dlc__tools">
                                    <x-filament::icon icon="heroicon-m-wrench-screwdriver" class="dlc__tools-icon" />
                                    {{ collect($message->tool_calls)->pluck('name')->join(', ') }}
                                </div>
                            @endif

                            @if(! $isVisitor && in_array($message->role, ['human', 'ai'], true) && ! $message->is_internal)
                                @php($read = $conversation->visitor_read_at && $message->created_at && $message->created_at->lte($conversation->visitor_read_at))
                                <div class="dlc__receipt" style="font-size:.6875rem; opacity:.6; margin-top:.25rem;">
                                    @if($read)
                                        ✓✓ Gelezen
                                    @else
                                        ✓ Verzonden
                                    @endif
                                </div>
                            @endif

                            @if($message->role === 'ai')
                                <div class="dlc__actions-row">
                                    <button type="button"
                                        wire:click="markFeedback({{ $message->id }}, 'good')"
                                        class="dlc__fb-btn @if($message->feedback === 'good') dlc__fb-btn--active @endif"
                                        title="Goed antwoord">&#128077;</button>
                                    <button type="button"
                                        wire:click="markFeedback({{ $message->id }}, 'bad')"
                                        class="dlc__fb-btn @if($message->feedback === 'bad') dlc__fb-btn--active @endif"
                                        title="Slecht antwoord">&#128078;</button>
                                    <button type="button"
                                        wire:click="learnFromMessage({{ $message->id }})"
                                        class="dlc__learn-btn"
                                        title="Voeg toe als leervoorbeeld">Leer hiervan</button>
                                </div>
                            @elseif($message->role === 'human')
                                <div class="dlc__actions-row">
                                    <button type="button"
                                        wire:click="learnFromMessage({{ $message->id }})"
                                        class="dlc__learn-btn"
                                        title="Voeg toe als leervoorbeeld">Leer van dit antwoord</button>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="dlc__empty">Nog geen berichten in dit gesprek.</p>
                @endforelse
            </div>

            @if($mode === 'human')
                <div style="margin-top:1rem; display:flex; justify-content:flex-end;">
                    <x-filament::button size="sm" color="gray" icon="heroicon-m-sparkles"
                        wire:click="suggestReply" wire:target="suggestReply" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="suggestReply">Stel antwoord voor</span>
                        <span wire:loading wire:target="suggestReply">Bezig…</span>
                    </x-filament::button>
                </div>
                @error('replyAttachments.*') <div style="color:#b91c1c; font-size:12px; margin-top:.5rem;">{{ $message }}</div> @enderror
                @if(! empty($replyAttachments))
                    <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:.5rem;">
                        @foreach($replyAttachments as $att)
                            <span style="display:inline-flex; align-items:center; gap:6px; border:1px solid rgb(228 228 231); border-radius:8px; padding:2px 8px; font-size:12px;">📎 {{ $att->getClientOriginalName() }}</span>
                        @endforeach
                    </div>
                @endif
                <form wire:submit.prevent="sendReply" class="dlc__composer"
                    x-data="{ dragging: false }"
                    x-on:dragover.prevent="dragging = true"
                    x-on:dragleave.prevent="dragging = false"
                    x-on:drop.prevent="dragging = false; if ($event.dataTransfer.files.length) { $wire.uploadMultiple('replyAttachments', $event.dataTransfer.files) }"
                    :style="dragging ? 'outline:2px dashed var(--primary-500); outline-offset:4px; border-radius:10px;' : ''"
                    title="Sleep een afbeelding of PDF hierheen om te versturen">
                    <label style="display:flex; align-items:center; cursor:pointer; padding:0 .25rem;" title="Voeg afbeelding of PDF toe">
                        <x-filament::icon icon="heroicon-m-paper-clip" style="width:1.25rem; height:1.25rem;" />
                        <input type="file" wire:model="replyAttachments" multiple accept="image/*,application/pdf" style="display:none;">
                    </label>
                    <input
                        wire:model="reply"
                        type="text"
                        placeholder="Typ je antwoord…"
                        class="dlc__input fi-input"
                        autocomplete="off"
                    >
                    <x-filament::button type="submit" icon="heroicon-m-paper-airplane" wire:loading.attr="disabled">
                        Verstuur
                    </x-filament::button>
                </form>
            @endif
        </x-filament::section>
            </div>

            <aside class="dlc__aside">
                <x-filament::section>
                    <x-slot name="heading">Gerelateerd</x-slot>

                    <div class="dlc__rel-group">
                        <div class="dlc__rel-title">Contact</div>
                        @if($conversation->visitor_name || $conversation->visitor_email)
                            <div class="dlc__rel-line">
                                @if($conversation->visitor_name)<strong>{{ $conversation->visitor_name }}</strong>@endif
                                @if($conversation->visitor_email)<div>{{ $conversation->visitor_email }}</div>@endif
                            </div>
                        @else
                            <div class="dlc__rel-empty">Geen contactgegevens bekend.</div>
                        @endif
                        @if(count($this->detectedOrderRefs))
                            <div class="dlc__rel-sub">Gevonden referenties: {{ implode(', ', $this->detectedOrderRefs) }}</div>
                        @endif
                    </div>

                    @if($this->relatedCustomers->isNotEmpty())
                        <div class="dlc__rel-group">
                            <div class="dlc__rel-title">Klant</div>
                            @foreach($this->relatedCustomers as $customer)
                                @php($url = $this->customerUrl($customer))
                                <a @if($url) href="{{ $url }}" @endif class="dlc__rel-card">
                                    <span class="dlc__rel-card-main">{{ $customer->name ?: $customer->email }}</span>
                                    <span class="dlc__rel-card-sub">{{ $customer->email }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    <div class="dlc__rel-group">
                        <div class="dlc__rel-title">Bestellingen</div>
                        @forelse($this->relatedOrders as $order)
                            @php($url = $this->orderUrl($order))
                            <a @if($url) href="{{ $url }}" target="_blank" @endif class="dlc__rel-card">
                                <span class="dlc__rel-card-row">
                                    <span class="dlc__rel-card-main">#{{ $order->invoice_id ?: $order->id }}</span>
                                    <x-filament::badge size="sm" :color="match($order->status) {
                                        'paid' => 'success',
                                        'cancelled', 'returned', 'return' => 'danger',
                                        'pending', 'waiting_for_confirmation', 'partially_paid' => 'warning',
                                        default => 'gray',
                                    }">
                                        {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                    </x-filament::badge>
                                </span>
                                <span class="dlc__rel-card-sub">
                                    {{ $order->name ?: $order->email }} · {{ $order->created_at?->format('d-m-Y') }}@if($order->total !== null) · {{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($order->total) }}@endif
                                </span>
                            </a>
                        @empty
                            <div class="dlc__rel-empty">Geen bestellingen gevonden op basis van e-mail of ordernummer.</div>
                        @endforelse
                    </div>
                </x-filament::section>

                <x-filament::section class="dlc__notes">
                    <x-slot name="heading">Interne notities</x-slot>
                    <p class="dlc__rel-empty" style="margin-bottom:.5rem;">Alleen zichtbaar voor medewerkers.</p>

                    <form wire:submit.prevent="addNote" style="display:flex; flex-direction:column; gap:.5rem; margin-bottom:.75rem;">
                        <textarea wire:model="noteBody" rows="2" placeholder="Notitie toevoegen…" class="dlc__input fi-input" style="resize:vertical;"></textarea>
                        <div style="display:flex; justify-content:flex-end;">
                            <x-filament::button size="sm" type="submit" icon="heroicon-m-plus" wire:loading.attr="disabled">Opslaan</x-filament::button>
                        </div>
                    </form>

                    @forelse($this->notes as $note)
                        <div style="padding:.5rem 0; border-top:1px solid rgba(127,127,127,.15); font-size:.8125rem;">
                            <div style="opacity:.6; font-size:.6875rem; margin-bottom:2px;">{{ $note->author_name ?: 'Medewerker' }} · {{ $note->created_at?->format('d-m-Y H:i') }}</div>
                            {!! nl2br(e($note->body)) !!}
                        </div>
                    @empty
                        <div class="dlc__rel-empty">Nog geen notities.</div>
                    @endforelse
                </x-filament::section>
            </aside>
        </div>
    </div>

    {{-- Foto-lightbox: opent een popup over de pagina i.p.v. een nieuw tabblad --}}
    <div
        x-data="{ src: null }"
        x-on:open-lightbox.window="src = $event.detail.src"
        x-on:keydown.escape.window="src = null"
        x-show="src"
        x-cloak
        x-on:click="src = null"
        style="position:fixed; inset:0; z-index:50; display:flex; align-items:center; justify-content:center; padding:2rem; background:rgba(0,0,0,.85); cursor:zoom-out;"
    >
        <img :src="src" alt="" style="max-width:92vw; max-height:92vh; border-radius:.5rem; box-shadow:0 10px 40px rgba(0,0,0,.5);">
    </div>

    <style>
        [x-cloak] { display: none !important; }
        .dlc { display: flex; flex-direction: column; gap: 1rem; }
        .dlc__toolbar { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; }
        .dlc__mode { display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: rgb(113 113 122); }
        .dlc__mode-label { font-weight: 500; }
        .dlc__actions { display: inline-flex; gap: 0.5rem; }

        .dlc__thread {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            max-height: 60vh;
            overflow-y: auto;
            padding: 0.25rem;
        }

        .dlc__row { display: flex; }
        .dlc__row--in { justify-content: flex-start; }
        .dlc__row--out { justify-content: flex-end; }

        .dlc__bubble {
            max-width: min(75%, 40rem);
            padding: 0.625rem 0.875rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            line-height: 1.45;
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.06);
            word-break: break-word;
        }

        .dlc__bubble--visitor { background: rgb(244 244 245); color: rgb(24 24 27); border-bottom-left-radius: 0.25rem; }
        .dlc__bubble--ai { background: rgb(39 39 42); color: rgb(244 244 245); border-bottom-right-radius: 0.25rem; }
        .dlc__bubble--human { background: rgb(37 99 235); color: #fff; border-bottom-right-radius: 0.25rem; }

        .dark .dlc__bubble--visitor { background: rgb(63 63 70); color: rgb(244 244 245); }
        .dark .dlc__bubble--ai { background: rgb(24 24 27); color: rgb(228 228 231); }

        .dlc__meta { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; font-size: 0.6875rem; opacity: 0.75; margin-bottom: 0.25rem; }
        .dlc__author { font-weight: 600; }
        .dlc__body :where(a) { text-decoration: underline; }
        .dlc__body > :first-child { margin-top: 0; }
        .dlc__body > :last-child { margin-bottom: 0; }
        .dlc__body :where(p) { margin: 0 0 .5em; }
        .dlc__body :where(ul, ol) { margin: .25em 0 .5em; padding-left: 1.25em; }
        .dlc__body :where(li) { margin: .1em 0; }
        .dlc__body :where(code) { background: rgba(127,127,127,.18); padding: .05em .3em; border-radius: 4px; font-size: .9em; }
        .dlc__body :where(pre) { background: rgba(127,127,127,.18); padding: .5em; border-radius: 6px; overflow: auto; }

        .dlc__tools { display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.6875rem; opacity: 0.7; margin-top: 0.375rem; }
        .dlc__tools-icon { width: 0.875rem; height: 0.875rem; }

        .dlc__empty { text-align: center; color: rgb(113 113 122); font-size: 0.875rem; padding: 2rem 0; }

        .dlc__sysrow { display: flex; justify-content: center; }
        .dlc__syserror {
            width: 100%;
            max-width: min(90%, 44rem);
            border: 1px solid rgb(248 113 113);
            background: rgb(254 242 242);
            color: rgb(127 29 29);
            border-radius: 0.75rem;
            padding: 0.625rem 0.75rem;
            font-size: 0.8125rem;
        }
        .dark .dlc__syserror { background: rgb(69 10 10 / 0.4); border-color: rgb(153 27 27); color: rgb(252 165 165); }
        .dlc__syserror-head { display: flex; align-items: center; gap: 0.375rem; font-weight: 600; font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 0.375rem; }
        .dlc__syserror-head .dlc__time { margin-left: auto; font-weight: 400; text-transform: none; letter-spacing: 0; opacity: 0.75; }
        .dlc__syserror-icon { width: 0.875rem; height: 0.875rem; flex-shrink: 0; }
        .dlc__syserror-body { margin: 0; white-space: pre-wrap; word-break: break-word; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.75rem; line-height: 1.45; }

        .dlc__composer { display: flex; gap: 0.5rem; margin-top: 1rem; }
        .dlc__input {
            flex: 1;
            border: 1px solid rgb(212 212 216);
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            background: #fff;
            color: rgb(24 24 27);
        }
        .dlc__input:focus { outline: 2px solid rgb(37 99 235 / 0.4); outline-offset: 0; border-color: rgb(37 99 235); }
        .dark .dlc__input { background: rgb(39 39 42); border-color: rgb(63 63 70); color: rgb(244 244 245); }

        .dlc__actions-row { display: flex; align-items: center; gap: 0.25rem; margin-top: 0.375rem; flex-wrap: wrap; }
        .dlc__fb-btn { background: none; border: none; cursor: pointer; font-size: 0.875rem; padding: 0.125rem 0.25rem; border-radius: 0.25rem; opacity: 0.5; line-height: 1; transition: opacity 0.15s; }
        .dlc__fb-btn:hover { opacity: 1; }
        .dlc__fb-btn--active { opacity: 1; background: rgb(255 255 255 / 0.15); }
        .dlc__learn-btn { background: none; border: 1px solid currentColor; cursor: pointer; font-size: 0.6875rem; padding: 0.125rem 0.375rem; border-radius: 0.25rem; opacity: 0.55; line-height: 1.4; transition: opacity 0.15s; color: inherit; }
        .dlc__learn-btn:hover { opacity: 1; }

        .dlc__layout { display: flex; gap: 1rem; align-items: flex-start; }
        .dlc__main { flex: 1; min-width: 0; }
        .dlc__aside { width: 320px; flex-shrink: 0; }
        @media (max-width: 1024px) { .dlc__layout { flex-direction: column; } .dlc__aside { width: 100%; } }

        .dlc__rel-group { margin-bottom: 1.25rem; }
        .dlc__rel-group:last-child { margin-bottom: 0; }
        .dlc__rel-title { font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; opacity: 0.55; margin-bottom: 0.5rem; }
        .dlc__rel-line { font-size: 0.875rem; }
        .dlc__rel-sub { font-size: 0.75rem; opacity: 0.65; margin-top: 0.375rem; word-break: break-word; }
        .dlc__rel-empty { font-size: 0.8125rem; opacity: 0.6; }
        .dlc__rel-card { display: flex; flex-direction: column; gap: 0.125rem; padding: 0.5rem 0.625rem; border: 1px solid rgb(228 228 231); border-radius: 0.5rem; margin-bottom: 0.5rem; text-decoration: none; color: inherit; transition: border-color 0.15s, background 0.15s; }
        .dlc__rel-card:last-child { margin-bottom: 0; }
        .dlc__rel-card:hover { border-color: rgb(37 99 235); background: rgb(37 99 235 / 0.04); }
        .dark .dlc__rel-card { border-color: rgb(63 63 70); }
        .dlc__rel-card-row { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
        .dlc__rel-card-main { font-weight: 600; font-size: 0.875rem; }
        .dlc__rel-card-sub { font-size: 0.75rem; opacity: 0.7; }
    </style>
</x-filament::page>
