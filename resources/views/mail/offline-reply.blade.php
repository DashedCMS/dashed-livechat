<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nieuw bericht van {{ $businessName }}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color:#1f2937; background:#f7f7f8; margin:0; padding:24px;">
    <div style="max-width:600px; margin:0 auto; background:#ffffff; border-radius:12px; padding:24px;">
        <h2 style="margin:0 0 4px; font-size:18px;">Nieuw bericht van {{ $businessName }}</h2>
        <p style="color:#6b7280; font-size:13px; margin:0 0 20px;">
            Je was net weg uit de chat, dus we sturen je het antwoord ook even per e-mail.
        </p>

        <div style="border:1px solid #f3f4f6; border-radius:12px; padding:14px 16px; background:#f9fafb;">
            <div style="font-size:11px; color:#6b7280; margin-bottom:6px;">
                {{ $agentName ?: $businessName }} · {{ $message->created_at?->format('d-m-Y H:i') }}
            </div>
            <div style="font-size:15px; line-height:1.5; color:#1f2937;">
                {!! nl2br(e($message->content)) !!}
            </div>
        </div>

        <div style="text-align:center; margin:24px 0 8px;">
            <a href="{{ $resumeUrl }}"
               style="display:inline-block; background:#111827; color:#ffffff; text-decoration:none; font-size:14px; font-weight:bold; padding:12px 22px; border-radius:10px;">
                Verder chatten
            </a>
        </div>

        <p style="color:#9ca3af; font-size:12px; text-align:center; margin:8px 0 0;">
            Of reageer gewoon op deze e-mail, dan pakken we het daar op.
        </p>
    </div>
</body>
</html>
