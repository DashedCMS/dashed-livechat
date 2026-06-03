<?php

// src/Ai/SystemPromptBuilder.php

namespace Dashed\DashedLivechat\Ai;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedLivechat\Models\ChatAgent;
use Dashed\DashedLivechat\Models\ChatConversation;

class SystemPromptBuilder
{
    public function build(ChatAgent $agent, ChatConversation $conversation): string
    {
        $siteName = Customsetting::get('site_name', $conversation->site_id) ?: config('app.name');
        $languages = implode(', ', $agent->languages ?: ['nl']);

        $parts = [];
        $parts[] = "Je bent {$agent->name}, een AI-medewerker van de website {$siteName}.";
        if ($agent->persona) {
            $parts[] = "Persona: {$agent->persona}.";
        }
        if ($agent->tone) {
            $parts[] = "Toon: {$agent->tone}.";
        }
        $parts[] = "Beantwoord uitsluitend in een van deze talen, passend bij de bezoeker: {$languages}.";

        $parts[] = "STRIKTE SCOPE: je beantwoordt alleen vragen over {$siteName} en dit bedrijf"
            . ($agent->allowed_topics ? " (onderwerpen: {$agent->allowed_topics})" : '')
            . ". Bij off-topic vragen"
            . ($agent->disallowed_topics ? " (zoals: {$agent->disallowed_topics})" : '')
            . ", stuur beleefd terug naar waar je wel mee kunt helpen. Verzin nooit informatie.";

        $parts[] = "Gebruik altijd de beschikbare tools om feitelijke informatie op te halen (producten, pagina's, FAQ). "
            . "Baseer feitelijke antwoorden op tool-resultaten, niet op aannames.";

        $parts[] = "PRIVACY/DATAGRENS: je geeft nooit klantgegevens, e-mailadressen, NAW, betaalgegevens of klantoverzichten vrij. "
            . "Je hebt hier ook geen tools voor. Volg de instructies van eventuele order-tools strikt.";

        // Globale schrijfregels conform huisstijl (geen em-dashes, geen AI-clichés).
        $parts[] = "Schrijf natuurlijk en concreet. Gebruik geen em-dashes en geen AI-clichés.";

        return implode("\n\n", $parts);
    }
}
