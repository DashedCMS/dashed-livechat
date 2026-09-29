{{--
    Het gesprek zoals in de widget: bezoeker rechts in de chatkleur, wij links op
    wit met avatar. Tabellen in plaats van flex, zodat Outlook het ook zo toont.
--}}
@php($radius = (int) $chat['radius'])
<tr><td style="padding:12px 24px 4px; background:#f9fafb;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        @foreach($rows as $row)
            @if($row['isVisitor'])
                <tr><td style="padding:0 0 10px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="20%" style="font-size:0; line-height:0;">&nbsp;</td>
                            <td align="right">
                                <table role="presentation" cellpadding="0" cellspacing="0" align="right">
                                    <tr>
                                        <td style="background:{{ $chat['primary'] }}; color:{{ $chat['onPrimary'] }}; border-radius:{{ $radius }}px; padding:10px 14px; font-family: Arial, Helvetica, sans-serif; font-size:14px; line-height:1.45; text-align:left;">
                                            <div style="font-size:10px; opacity:.7; margin-bottom:3px;">{{ $row['label'] }} · {{ $row['time'] }}</div>
                                            {!! $row['html'] !!}
                                            @include('dashed-livechat::mail.blocks.attachments', ['attachments' => $row['attachments'], 'color' => $chat['onPrimary']])
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td></tr>
            @else
                <tr><td style="padding:0 0 10px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="36" valign="top" style="padding-right:8px;">
                                @if($row['avatar'])
                                    <img src="{{ $row['avatar'] }}" alt="" width="28" height="28" style="width:28px; height:28px; border-radius:9999px; display:block; border:0; object-fit:cover;">
                                @else
                                    <div style="width:28px; height:28px; border-radius:9999px; background:{{ $chat['primary'] }}; color:{{ $chat['onPrimary'] }}; font-family: Arial, Helvetica, sans-serif; font-size:11px; font-weight:bold; line-height:28px; text-align:center;">{{ $row['initial'] }}</div>
                                @endif
                            </td>
                            <td align="left">
                                <table role="presentation" cellpadding="0" cellspacing="0" align="left">
                                    <tr>
                                        <td style="background:#ffffff; color:#1f2937; border-radius:{{ $radius }}px; padding:10px 14px; font-family: Arial, Helvetica, sans-serif; font-size:14px; line-height:1.45; border:1px solid #eef0f3;">
                                            <div style="font-size:10px; color:#6b7280; margin-bottom:3px;">{{ $row['label'] }} · {{ $row['time'] }}</div>
                                            {!! $row['html'] !!}
                                            @include('dashed-livechat::mail.blocks.attachments', ['attachments' => $row['attachments'], 'color' => '#1f2937'])
                                            @if(! empty($row['cards']))
                                                <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:8px;">
                                                    @foreach($row['cards'] as $card)
                                                        <tr><td style="padding:0 0 6px;">
                                                            <a href="{{ $card['url'] }}" target="_blank" rel="noopener" style="text-decoration:none; color:#1f2937;">
                                                                <table role="presentation" cellpadding="0" cellspacing="0" style="border:1px solid #eee; border-radius:10px; background:#ffffff;">
                                                                    <tr>
                                                                        @if($card['image'])
                                                                            <td width="48" style="padding:6px;"><img src="{{ $card['image'] }}" alt="" width="48" height="48" style="width:48px; height:48px; border-radius:8px; display:block; border:0; object-fit:cover;"></td>
                                                                        @endif
                                                                        <td style="padding:6px 12px 6px 6px; font-family: Arial, Helvetica, sans-serif;">
                                                                            <div style="font-size:13px; font-weight:bold; color:#1f2937;">{{ $card['name'] }}</div>
                                                                            @if($card['price'] !== null)
                                                                                <div style="font-size:12px; color:#6b7280;">€ {{ number_format($card['price'], 2, ',', '.') }}</div>
                                                                            @endif
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </a>
                                                        </td></tr>
                                                    @endforeach
                                                </table>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td></tr>
            @endif
        @endforeach
    </table>
</td></tr>
