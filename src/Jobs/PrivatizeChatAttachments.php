<?php

namespace Dashed\DashedLivechat\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Dashed\DashedCore\Security\Uploads\PrivateUploads;

/**
 * Verhuist oude chatbijlagen (media-id's uit de publieke mediabibliotheek,
 * map "chat") naar de privé opslag van dashed-core. Per bijlage: het bestand
 * privé kopiëren, het id in het bericht vervangen door het pad, en daarna het
 * media-item met zijn conversies verwijderen. Elke bijlage was een eigen item
 * (zie ConversationManager), dus een ander gesprek of een pagina raakt niets kwijt.
 *
 * Gestart door de migratie privatize_chat_attachments (alleen in productie) of
 * met `php artisan dashed-livechat:privatize-attachments`. Veilig om opnieuw
 * te draaien.
 */
class PrivatizeChatAttachments implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 3;

    public const BUDGET_SECONDS = 600;

    /**
     * Nooit twee runs tegelijk; een vervolgrun die de job zelf inplant komt
     * daardoor gewoon achter de lopende aan.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping(static::class))->releaseAfter(120)->expireAfter(1200)];
    }

    public function handle(): void
    {
        $itemClass = '\\RalphJSmit\\Filament\\MediaLibrary\\Models\\MediaLibraryItem';
        if (! class_exists($itemClass)) {
            return;
        }

        $started = microtime(true);
        $stats = ['messages' => 0, 'moved' => 0, 'missing' => 0];
        $done = true;

        $query = DB::table('dashed__chat_messages')->whereNotNull('attachments')->where('attachments', '!=', '[]');

        foreach ($query->orderBy('id')->lazyById(200) as $row) {
            if (microtime(true) - $started > static::BUDGET_SECONDS) {
                $done = false;

                break;
            }

            $attachments = json_decode((string) $row->attachments, true);
            if (! is_array($attachments) || ! collect($attachments)->contains(fn ($a) => is_int($a) || ctype_digit((string) $a))) {
                continue;
            }

            $new = [];
            $toDelete = [];
            foreach ($attachments as $attachment) {
                if (PrivateUploads::isPrivate($attachment) || ! (is_int($attachment) || ctype_digit((string) $attachment))) {
                    $new[] = $attachment;

                    continue;
                }

                $item = $itemClass::find((int) $attachment);
                $media = $item ? rescue(fn () => $item->getItem(), null, false) : null;
                $target = $media
                    ? PrivateUploads::moveIntoPrivate((string) $media->disk, (string) $media->getPathRelativeToRoot(), 'livechat', deleteSource: false)
                    : null;

                if (! $target) {
                    // Bestand onvindbaar: het id laten staan, dan toont de chat wat er nog is.
                    $new[] = $attachment;
                    $stats['missing']++;

                    continue;
                }

                $new[] = $target;
                $toDelete[] = $item;
                $stats['moved']++;
            }

            if ($toDelete === []) {
                continue;
            }

            DB::table('dashed__chat_messages')->where('id', $row->id)->update(['attachments' => json_encode($new)]);
            $stats['messages']++;

            // Pas na het bijwerken van het bericht: het publieke item (met conversies) weg.
            foreach ($toDelete as $item) {
                rescue(function () use ($item) {
                    method_exists($item, 'forceDelete') ? $item->forceDelete() : $item->delete();
                }, null, true);
            }
        }

        Log::info('[dashed-livechat] chatbijlagen naar privé opslag', $stats + ['klaar' => $done]);

        if (! $done) {
            static::dispatch()->delay(now()->addMinute());
        }
    }
}
