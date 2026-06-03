<x-filament::page>
    <div style="margin-bottom:12px; display:flex; gap:8px; align-items:center;">
        <span>Modus: <strong>{{ $mode }}</strong></span>
        @if($mode !== 'human')
            <x-filament::button wire:click="takeOver">Overnemen</x-filament::button>
        @else
            <x-filament::button color="gray" wire:click="releaseToAi">Teruggeven aan AI</x-filament::button>
        @endif
    </div>

    <div wire:poll.3s="pollMessages" style="display:flex; flex-direction:column; gap:10px; max-width:760px;">
        @foreach($conversation->messages as $message)
            <div style="align-self: {{ $message->role === 'visitor' ? 'flex-start' : 'flex-end' }}; max-width:75%; padding:10px 12px; border-radius:12px; background: {{ $message->role === 'visitor' ? '#f3f4f6' : ($message->role === 'human' ? '#1d4ed8' : '#111827') }}; color: {{ $message->role === 'visitor' ? '#111827' : '#fff' }};">
                <div style="font-size:11px; opacity:.7; margin-bottom:4px;">
                    {{ ucfirst($message->role) }}{{ $message->agent?->name ? ' · ' . $message->agent->name : '' }} · {{ $message->created_at?->format('d-m H:i') }}
                </div>
                {!! nl2br(e($message->content)) !!}
                @if($message->tool_calls)
                    <div style="font-size:11px; opacity:.6; margin-top:6px;">tools: {{ collect($message->tool_calls)->pluck('name')->join(', ') }}</div>
                @endif
            </div>
        @endforeach
    </div>

    @if($mode === 'human')
        <form wire:submit.prevent="sendReply" style="display:flex; gap:8px; margin-top:12px; max-width:760px;">
            <input wire:model="reply" type="text" placeholder="Typ je antwoord…" style="flex:1; border:1px solid #ddd; border-radius:8px; padding:10px;">
            <x-filament::button type="submit">Verstuur</x-filament::button>
        </form>
    @endif
</x-filament::page>
