<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Je gesprek met {{ $businessName }}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color:#1f2937; background:#f7f7f8; margin:0; padding:24px;">
    <div style="max-width:600px; margin:0 auto; background:#ffffff; border-radius:12px; padding:24px;">
        <h2 style="margin:0 0 4px; font-size:18px;">Je gesprek met {{ $businessName }}</h2>
        <p style="color:#6b7280; font-size:13px; margin:0 0 20px;">
            Hierbij een overzicht van je chatgesprek. Heb je nog een vraag? Reageer gerust op deze e-mail.
        </p>

        @foreach($messages as $message)
            @php($isVisitor = $message->role === 'visitor')
            <div style="margin-bottom:12px; text-align: {{ $isVisitor ? 'right' : 'left' }};">
                <div style="display:inline-block; max-width:80%; text-align:left; padding:10px 12px; border-radius:12px; background: {{ $isVisitor ? '#111827' : '#f3f4f6' }}; color: {{ $isVisitor ? '#ffffff' : '#1f2937' }};">
                    <div style="font-size:11px; opacity:.7; margin-bottom:3px;">
                        {{ $isVisitor ? 'Jij' : ($message->agent?->name ?: $businessName) }} · {{ $message->created_at?->format('d-m-Y H:i') }}
                    </div>
                    {!! nl2br(e($message->content)) !!}
                </div>
            </div>
        @endforeach

        @if($replyToEmail)
            <p style="color:#6b7280; font-size:13px; margin-top:20px; border-top:1px solid #eee; padding-top:16px;">
                Reageren kan via <a href="mailto:{{ $replyToEmail }}" style="color:#111827;">{{ $replyToEmail }}</a>.
            </p>
        @endif
    </div>
</body>
</html>
