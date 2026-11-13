<?php

namespace Dashed\DashedLivechat\Support;

use Closure;
use Illuminate\Http\UploadedFile;
use Dashed\DashedCore\Rules\SafeUploadedFile;

class AttachmentRules
{
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'pdf'];

    /**
     * Chat-bijlagen: alleen afbeeldingen en PDF. De extensie moet altijd in de lijst staan én het opgegeven
     * type moet een afbeelding of PDF zijn (of onbekend: libmagic en iPhones geven HEIC vaak als
     * application/octet-stream). Tot v4.34.0 was één van beide genoeg, dus kwam `shell.php` met type
     * image/png erdoor. Daarbovenop de wachter van dashed-core: geen verstopte extensie (foto.php.jpg) en geen
     * servercode in de inhoud. Bewust geen allowlist op het gesnoven type: HEIC van iPhone-foto's werd
     * daarop onterecht geweigerd. Wel een blokkade op wat de inhoud aantoonbaar is: tekst, HTML, XML/SVG of
     * script onder een afbeeldingsnaam. De opslag schrijft het Content-Type namelijk uit de inhoud, dus
     * `x.jpg` met HTML erin werd anders als text/html uitgeserveerd vanaf de publieke bibliotheek.
     */
    public static function clientImageOrPdf(): Closure
    {
        return function (string $attribute, $value, Closure $fail): void {
            if (($reden = static::rejectionReason($value)) !== null) {
                $fail($reden);
            }
        };
    }

    public static function rejectionReason(mixed $value): ?string
    {
        if (! $value instanceof UploadedFile) {
            return 'Alleen afbeeldingen of een PDF zijn toegestaan.';
        }

        $ext = strtolower((string) $value->getClientOriginalExtension());
        $mime = strtolower((string) $value->getClientMimeType());
        $mimeOk = $mime === '' || str_starts_with($mime, 'image/') || $mime === 'application/pdf' || $mime === 'application/octet-stream';

        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true) || ! $mimeOk) {
            return 'Alleen afbeeldingen of een PDF zijn toegestaan.';
        }

        if (class_exists(SafeUploadedFile::class) && ($reden = SafeUploadedFile::rejectionReason($value)) !== null) {
            return $reden;
        }

        if (static::contentIsMarkupOrText($value)) {
            return 'Alleen afbeeldingen of een PDF zijn toegestaan.';
        }

        return null;
    }

    private static function contentIsMarkupOrText(UploadedFile $file): bool
    {
        try {
            $sniffed = strtolower((string) $file->getMimeType());
        } catch (\Throwable) {
            return false;
        }

        if (str_starts_with($sniffed, 'text/')) {
            return true;
        }

        foreach (['html', 'xml', 'svg', 'javascript', 'ecmascript', 'json'] as $needle) {
            if (str_contains($sniffed, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** Opslagnaam: willekeurig, met de gecontroleerde extensie (nooit de door de inhoud geraden extensie). */
    public static function storageName(UploadedFile $file): string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());

        return \Illuminate\Support\Str::random(40).'.'.(in_array($ext, self::ALLOWED_EXTENSIONS, true) ? $ext : 'bin');
    }
}
