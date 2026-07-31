<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Support;

use Dashed\DashedLivechat\Ai\LivechatAi;
use Dashed\DashedLivechat\Models\ChatMessage;

/**
 * Vertaalt chat-berichten via het bestaande Claude-pad en cachet de vertaling
 * op het bericht (translated_content) zodat we niet dubbel vertalen.
 */
class MessageTranslator
{
    /**
     * Vertaalt losse tekst naar de doel-taalcode. Faalt de AI → gooit door.
     */
    public function translate(string $text, string $targetLocale): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $system = "Je bent een vertaler. Vertaal het bericht naar taal-code '{$targetLocale}'. Geef ALLEEN de vertaling terug, zonder uitleg of aanhalingstekens.";

        $response = LivechatAi::requireClaude()->messages(
            [['role' => 'user', 'content' => $text]],
            ['system' => $system, 'temperature' => 0.2, 'max_tokens' => 800]
        );

        $translated = collect($response['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        return trim($translated);
    }

    /**
     * Zorgt dat het bericht een vertaling naar $targetLocale heeft en geeft die
     * terug. Cachet in translated_content. Best-effort: bij een fout valt het
     * terug op het origineel (nooit leeg, nooit een exception naar boven).
     */
    public function ensureTranslated(ChatMessage $message, string $targetLocale): string
    {
        $original = (string) $message->content;
        $targetLocale = trim($targetLocale);

        if ($targetLocale === '' || $original === '') {
            return $original;
        }

        // Al een vertaling naar dit doel gecachet? Hergebruik.
        if ($message->translated_content && $message->source_locale === $targetLocale) {
            return $message->translated_content;
        }

        try {
            $translated = $this->translate($original, $targetLocale);
        } catch (\Throwable $e) {
            report($e);

            return $original;
        }

        if ($translated === '' || $translated === $original) {
            return $original;
        }

        $message->forceFill([
            'translated_content' => $translated,
            'source_locale' => $targetLocale,
        ])->save();

        return $translated;
    }
}
