<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Support;

use Dashed\DashedLivechat\Ai\LivechatAi;
use Dashed\DashedLivechat\Models\ChatMessage;
use Dashed\DashedLivechat\Models\ChatConversation;

/**
 * Genereert een concept-antwoord voor de medewerker op basis van het
 * gespreksverloop. Bevat GEEN tools en verstuurt niets — puur tekst.
 * Gedeeld door de Filament-copilot en de mobile-api-copilot.
 */
class ReplySuggester
{
    /**
     * @throws \Throwable wanneer de AI-aanroep faalt.
     */
    public static function suggest(ChatConversation $conversation): string
    {
        $messages = $conversation->messages()
            ->whereIn('role', ['visitor', 'ai', 'human'])
            ->where('is_internal', false)
            ->orderBy('id')
            ->get()
            ->map(fn (ChatMessage $m) => [
                'role' => $m->role === 'visitor' ? 'user' : 'assistant',
                'content' => (string) $m->content,
            ])
            ->values()
            ->all();

        $messages = self::normalizeForClaude($messages);

        if (empty($messages)) {
            return '';
        }

        $agent = $conversation->aiAgent;
        $system = 'Je bent een medewerker van de klantenservice. Stel een kort, vriendelijk en concreet concept-antwoord in het Nederlands voor op het laatste bericht van de klant, dat de medewerker kan versturen. Geef alleen het antwoord zelf, zonder inleiding of uitleg.';

        $response = LivechatAi::requireClaude()->messages($messages, [
            'system' => $system,
            'model' => $agent?->model,
            'temperature' => 0.4,
            'max_tokens' => 400,
        ]);

        $draft = collect($response['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        return trim((string) $draft);
    }

    /**
     * Normaliseert de reeks voor de Anthropic Messages API: moet met een
     * user-bericht beginnen en mag geen opeenvolgende same-role berichten
     * bevatten.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<int, array{role: string, content: string}>
     */
    private static function normalizeForClaude(array $messages): array
    {
        while (! empty($messages) && ($messages[0]['role'] ?? null) !== 'user') {
            array_shift($messages);
        }

        $normalized = [];
        foreach ($messages as $message) {
            $content = trim((string) ($message['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            $lastIndex = count($normalized) - 1;
            if ($lastIndex >= 0 && $normalized[$lastIndex]['role'] === $message['role']) {
                $normalized[$lastIndex]['content'] .= "\n\n" . $content;

                continue;
            }

            $normalized[] = ['role' => $message['role'], 'content' => $content];
        }

        return $normalized;
    }
}
