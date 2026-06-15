<?php

// src/Ai/SystemPromptBuilder.php

namespace Dashed\DashedLivechat\Ai;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedLivechat\Models\ChatAgent;
use Dashed\DashedLivechat\Models\ChatLearning;
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

        $parts[] = "Als de bezoeker zelf een naam of e-mailadres noemt (bijvoorbeeld \"mijn naam is Kees\" of een opgegeven e-mailadres), "
            . "roep dan direct de tool saveContactDetails aan om dit op te slaan.";

        $parts[] = "PRIVACY/DATAGRENS: je geeft nooit klantgegevens, e-mailadressen, NAW, betaalgegevens of klantoverzichten vrij. "
            . "Je hebt hier ook geen tools voor. Volg de instructies van eventuele order-tools strikt.";

        $parts[] = "Belangrijk: antwoord altijd in de taal van de bezoeker en blijf strikt bij {$siteName}. "
            . "Beschrijf nooit losse teksten of documenten ('de tekst gaat over...') en wijk nooit uit naar een ander onderwerp. "
            . "Heb je ergens geen tool of gegevens voor (zoals verkoopaantallen of populariteit), zeg dat dan eerlijk en bied aan "
            . "om door te verbinden met een medewerker. Verzin nooit informatie.";

        // Globale schrijfregels conform huisstijl (geen em-dashes, geen AI-clichés).
        $parts[] = "Schrijf natuurlijk en concreet. Gebruik geen em-dashes en geen AI-clichés.";

        if ($agent->escalation_rules) {
            $parts[] = "ESCALATIE: verbind door naar een medewerker — gebruik de tool requestHumanHandoff — in deze gevallen: {$agent->escalation_rules}.";
        }
        $parts[] = "Als je het antwoord niet betrouwbaar uit de tools/kennis kunt halen of je twijfelt: VERZIN NIETS. "
            . "Zeg eerlijk dat je het niet zeker weet en gebruik de tool requestHumanHandoff om een medewerker erbij te halen. "
            . "Buiten openingstijden: vraag om contactgegevens met saveContactDetails zodat een collega kan terugmailen.";

        // Geleerde voorbeelden en correcties.
        $learnings = ChatLearning::where('site_id', $conversation->site_id)
            ->where('is_active', true)
            ->latest('id')
            ->limit(20)
            ->get();

        if ($learnings->isNotEmpty()) {
            $lines = ["GELEERDE VOORBEELDEN EN CORRECTIES (pas deze toe waar relevant):"];
            foreach ($learnings as $learning) {
                $lines[] = '- Vraag: "' . $learning->question . '" -> Gewenst antwoord: "' . $learning->answer . '"';
            }
            $parts[] = implode("\n", $lines);
        }

        return implode("\n\n", $parts);
    }
}
