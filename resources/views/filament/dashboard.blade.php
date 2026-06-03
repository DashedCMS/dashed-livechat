<x-filament::page>
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px;">

        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px;">
            <div style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Gesprekken</div>
            <div style="font-size:32px; font-weight:700; margin-top:6px;">{{ $stats['conversations'] }}</div>
        </div>

        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px;">
            <div style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">AI</div>
            <div style="font-size:32px; font-weight:700; margin-top:6px;">{{ $stats['by_mode']['ai'] }}</div>
        </div>

        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px;">
            <div style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Wacht op mens</div>
            <div style="font-size:32px; font-weight:700; margin-top:6px;">{{ $stats['by_mode']['waiting_human'] }}</div>
        </div>

        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px;">
            <div style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Menselijk</div>
            <div style="font-size:32px; font-weight:700; margin-top:6px;">{{ $stats['by_mode']['human'] }}</div>
        </div>

        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px;">
            <div style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Escalaties</div>
            <div style="font-size:32px; font-weight:700; margin-top:6px; color:#dc2626;">{{ $stats['escalations'] }}</div>
        </div>

        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px;">
            <div style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Tokens in</div>
            <div style="font-size:32px; font-weight:700; margin-top:6px;">{{ number_format($stats['tokens_in']) }}</div>
        </div>

        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px;">
            <div style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Tokens uit</div>
            <div style="font-size:32px; font-weight:700; margin-top:6px;">{{ number_format($stats['tokens_out']) }}</div>
        </div>

        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px;">
            <div style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Geschatte kosten (USD)</div>
            <div style="font-size:32px; font-weight:700; margin-top:6px;">${{ number_format($stats['estimated_cost'], 4) }}</div>
        </div>

    </div>
</x-filament::page>
