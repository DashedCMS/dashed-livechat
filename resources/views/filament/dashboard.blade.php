<x-filament::page>
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px;">

        @php
            $cards = [
                ['label' => 'Gesprekken', 'value' => number_format($stats['conversations'])],
                ['label' => 'AI', 'value' => number_format($stats['by_mode']['ai'])],
                ['label' => 'Wacht op mens', 'value' => number_format($stats['by_mode']['waiting_human'])],
                ['label' => 'Menselijk', 'value' => number_format($stats['by_mode']['human'])],
                ['label' => 'Escalaties', 'value' => number_format($stats['escalations']), 'color' => '#ef4444'],
                ['label' => 'Tokens in', 'value' => number_format($stats['tokens_in'])],
                ['label' => 'Tokens uit', 'value' => number_format($stats['tokens_out'])],
                ['label' => 'Geschatte kosten (USD)', 'value' => '$' . number_format($stats['estimated_cost'], 4)],
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
            </x-filament::section>
        @endforeach

    </div>
</x-filament::page>
