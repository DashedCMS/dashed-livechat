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
                ['label' => 'CSAT gemiddeld', 'value' => ($stats['rating_avg'] ?? null) !== null ? number_format($stats['rating_avg'], 1, ',', '.') . ' / 5' : '—', 'hint' => number_format($stats['rating_count'] ?? 0) . ' beoordelingen.'],
                ['label' => 'CSAT positief', 'value' => ($stats['csat_positive_pct'] ?? 0) . '%', 'color' => '#22c55e', 'hint' => 'Aandeel beoordelingen met 4 of 5.'],
                ['label' => 'Gem. reactietijd', 'value' => ($stats['avg_response_minutes'] ?? null) !== null ? $stats['avg_response_minutes'] . ' min' : '—', 'hint' => 'Tijd tot het eerste antwoord op een bezoekersvraag.'],
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

    @php($tagBreakdown = $stats['tag_breakdown'] ?? [])
    @if (! empty($tagBreakdown))
        <x-filament::section class="mt-4">
            <div style="font-size:12px; text-transform:uppercase; letter-spacing:.05em; opacity:.65; margin-bottom:10px;">Tags</div>
            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                @foreach ($tagBreakdown as $tag)
                    <span style="display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:9999px; border:1px solid {{ $tag['color'] }}; font-size:13px;">
                        <span style="width:9px; height:9px; border-radius:9999px; background:{{ $tag['color'] }};"></span>
                        {{ $tag['name'] }}
                        <strong>{{ number_format($tag['count']) }}</strong>
                    </span>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    @php($busy = $stats['busy_hours'] ?? [])
    @php($busyMax = ! empty($busy) ? max($busy) : 0)
    @if ($busyMax > 0)
        <x-filament::section class="mt-4">
            <div style="font-size:12px; text-transform:uppercase; letter-spacing:.05em; opacity:.65; margin-bottom:12px;">Drukte per uur</div>
            <div style="display:flex; align-items:flex-end; gap:3px; height:120px;">
                @foreach ($busy as $hour => $count)
                    <div style="flex:1; display:flex; flex-direction:column; align-items:center; gap:4px; height:100%; justify-content:flex-end;" title="{{ $count }} gesprekken om {{ $hour }}:00">
                        <div style="width:100%; border-radius:3px 3px 0 0; background:var(--primary-500); opacity:{{ $count > 0 ? 0.85 : 0.12 }}; height:{{ $busyMax > 0 ? max(2, (int) round($count / $busyMax * 100)) : 2 }}%;"></div>
                        @if ($hour % 3 === 0)
                            <span style="font-size:9px; opacity:.5;">{{ $hour }}</span>
                        @else
                            <span style="font-size:9px; opacity:0;">·</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament::page>
