<x-filament::page>
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px;">

        @php
            $cards = [
                ['label' => 'Gesprekken', 'value' => number_format($stats['conversations'])],
                ['label' => 'AI', 'value' => number_format($stats['by_mode']['ai'])],
                ['label' => 'Wacht op mens', 'value' => number_format($stats['by_mode']['waiting_human'])],
                ['label' => 'Menselijk', 'value' => number_format($stats['by_mode']['human'])],
                ['label' => 'Escalaties', 'value' => number_format($stats['escalations']), 'color' => '#ef4444'],
                ['label' => 'Zelf afgehandeld', 'value' => ($stats['self_handled_pct'] ?? 0) . '%', 'color' => '#22c55e', 'hint' => 'Aandeel gesprekken dat de AI afhandelde zonder escalatie naar een mens.'],
                ['label' => '👍 Positief', 'value' => number_format($stats['feedback_good'] ?? 0), 'color' => '#22c55e', 'hint' => 'AI-antwoorden met een duim omhoog.'],
                ['label' => '👎 Negatief', 'value' => number_format($stats['feedback_bad'] ?? 0), 'color' => '#ef4444', 'hint' => 'AI-antwoorden met een duim omlaag — werkvoorraad voor de leer-loop.'],
                ['label' => 'Tokens in', 'value' => number_format($stats['tokens_in'])],
                ['label' => 'Tokens uit', 'value' => number_format($stats['tokens_out'])],
                ['label' => 'Geschatte AI-kosten', 'value' => '€ ' . number_format($stats['estimated_cost_eur'], 2, ',', '.'), 'hint' => 'Schatting van het AI-tokenverbruik (Anthropic), omgerekend naar euro.'],
            ];
        @endphp

        @foreach ($cards as $card)
            <x-filament::section>
                <div style="font-size:12px; text-transform:uppercase; letter-spacing:.05em; opacity:.65;">
                    {{ $card['label'] }}
                </div>
                <div style="font-size:32px; font-weight:700; margin-top:6px; line-height:1.1;{{ isset($card['color']) ? ' color:' . $card['color'] . ';' : '' }}">
                    {{ $card['value'] }}
                </div>
                @if (! empty($card['hint']))
                    <div style="font-size:11px; margin-top:8px; opacity:.6; line-height:1.3;">{{ $card['hint'] }}</div>
                @endif
            </x-filament::section>
        @endforeach

    </div>
</x-filament::page>
