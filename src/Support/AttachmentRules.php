<?php

namespace Dashed\DashedLivechat\Support;

use Closure;

class AttachmentRules
{
    /**
     * Valideer chat-bijlagen op de CLIENT-aangeleverde mime/extensie i.p.v.
     * server-side inhoud-sniffing (mimetypes/mimes). libmagic herkent HEIC van
     * iPhone-foto's vaak niet (→ application/octet-stream) en Livewire-tijdelijke
     * uploads op S3 zijn niet altijd lokaal te snuffelen; beide leidden tot een
     * onterechte weigering. Sta alleen afbeeldingen en PDF toe.
     */
    public static function clientImageOrPdf(): Closure
    {
        return function (string $attribute, $value, Closure $fail): void {
            $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'pdf'];

            $ext = method_exists($value, 'getClientOriginalExtension')
                ? strtolower((string) $value->getClientOriginalExtension())
                : '';
            $mime = method_exists($value, 'getClientMimeType')
                ? (string) $value->getClientMimeType()
                : '';

            $okMime = str_starts_with($mime, 'image/') || $mime === 'application/pdf';
            $okExt = in_array($ext, $allowedExt, true);

            if (! $okMime && ! $okExt) {
                $fail('Alleen afbeeldingen of een PDF zijn toegestaan.');
            }
        };
    }
}
