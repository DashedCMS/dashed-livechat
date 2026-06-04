<?php

namespace Dashed\DashedLivechat\Ai;

use Dashed\DashedAi\AiProvider;
use Dashed\DashedAi\Facades\Ai;

/**
 * De live-chat-module draait op Claude (tool-calling + generatie). Deze helper
 * dwingt dat af: zo valt de module nooit stilletjes terug op een andere provider
 * (bv. OpenAI) en krijg je een duidelijke melding als Claude niet verbonden is.
 */
class LivechatAi
{
    public static function requireClaude(): AiProvider
    {
        $claude = Ai::provider('claude');

        if (! $claude || ! $claude->isConnected()) {
            throw new \RuntimeException('Claude is niet verbonden. Controleer de Claude API-sleutel in de AI-instellingen.');
        }

        return $claude;
    }

    public static function json(string $prompt, array $options = []): array
    {
        return static::requireClaude()->json($prompt, $options) ?? [];
    }
}
