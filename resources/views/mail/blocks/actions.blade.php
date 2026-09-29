{{-- Hervat-knop in de chatkleur, met daaronder de contactregel uit de widget. --}}
<tr><td align="center" style="padding:24px 24px 8px; font-family: Arial, Helvetica, sans-serif;">
    <a href="{{ $resumeUrl }}" style="display:inline-block; background:{{ $chat['primary'] }}; color:{{ $chat['onPrimary'] }}; text-decoration:none; font-size:14px; font-weight:bold; padding:12px 24px; border-radius:{{ min((int) $chat['radius'], 12) }}px;">Verder chatten</a>
    @if($replyHint)
        <div style="font-size:12px; color:#9ca3af; margin-top:10px;">{{ $replyHint }}</div>
    @endif
</td></tr>
@if($chat['phone'] || $chat['email'])
    <tr><td align="center" style="padding:4px 24px 24px; font-family: Arial, Helvetica, sans-serif; font-size:13px; color:#6b7280;">
        Liever direct contact?
        @if($chat['phone'])
            <a href="tel:{{ preg_replace('/\s+/', '', $chat['phone']) }}" style="color:{{ $chat['primary'] }}; text-decoration:none; font-weight:bold;">{{ $chat['phone'] }}</a>
        @endif
        @if($chat['phone'] && $chat['email'])
            <span style="color:#d1d5db;">|</span>
        @endif
        @if($chat['email'])
            <a href="mailto:{{ $chat['email'] }}" style="color:{{ $chat['primary'] }}; text-decoration:none; font-weight:bold;">{{ $chat['email'] }}</a>
        @endif
    </td></tr>
@else
    <tr><td style="padding:0 0 16px;"></td></tr>
@endif
