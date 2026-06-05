<x-filament::page>
    <div class="dlc">
        <div class="dlc__toolbar">
            <span class="dlc__mode">
                <span class="dlc__mode-label">Modus</span>
                <x-filament::badge :color="$mode === 'human' ? 'info' : ($mode === 'ai' ? 'success' : 'gray')">
                    {{ ucfirst($mode) }}
                </x-filament::badge>
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

                            @if($message->tool_calls)
                                <div class="dlc__tools">
                                    <x-filament::icon icon="heroicon-m-wrench-screwdriver" class="dlc__tools-icon" />
                                    {{ collect($message->tool_calls)->pluck('name')->join(', ') }}
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
                <form wire:submit.prevent="sendReply" class="dlc__composer">
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
            </aside>
        </div>
    </div>

    <style>
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
