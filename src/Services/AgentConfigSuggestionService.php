<?php

namespace Dashed\DashedLivechat\Services;

use Dashed\DashedLivechat\Ai\LivechatAi;
use Dashed\DashedCore\Models\Customsetting;

class AgentConfigSuggestionService
{
    public function suggest(string $siteId): array
    {
        $context = $this->siteContext($siteId);
        $siteName = (string) (Customsetting::get('site_name', $siteId) ?? $siteId);

        $prompt = "Je helpt een webshop een AI-chat-medewerker configureren. "
            . "Op basis van de naam en inhoud van deze site, stel passende waarden voor. "
            . "Geef geldige JSON: {\"persona\":string,\"tone\":string,\"languages\":[string],\"allowed_topics\":string,\"disallowed_topics\":string,\"escalation_rules\":string,\"greeting\":string}. "
            . "Schrijf alle tekst in het Nederlands, kort en professioneel, zonder em-dashes.\n\n"
            . "Sitenaam: " . $siteName . "\n\nSite-inhoud:\n" . $context;

        $response = LivechatAi::json($prompt);

        return $this->normalize($response);
    }

    protected function normalize(array $raw): array
    {
        $languages = $raw['languages'] ?? ['nl'];

        if (is_string($languages)) {
            $languages = array_values(array_filter(array_map('trim', explode(',', $languages))));
        }

        if (! is_array($languages) || empty($languages)) {
            $languages = ['nl'];
        }

        return [
            'persona' => (string) ($raw['persona'] ?? ''),
            'tone' => (string) ($raw['tone'] ?? ''),
            'languages' => array_values(array_map('strval', $languages)),
            'allowed_topics' => (string) ($raw['allowed_topics'] ?? ''),
            'disallowed_topics' => (string) ($raw['disallowed_topics'] ?? ''),
            'escalation_rules' => (string) ($raw['escalation_rules'] ?? ''),
            'greeting' => (string) ($raw['greeting'] ?? ''),
        ];
    }

    protected function siteContext(string $siteId): string
    {
        $lines = [];

        if (class_exists(\Dashed\DashedPages\Models\Page::class)) {
            foreach (\Dashed\DashedPages\Models\Page::query()->whereJsonContains('site_ids', $siteId)->limit(20)->get() as $p) {
                $lines[] = 'Pagina: ' . $p->name;
            }
        }

        if (class_exists(\Dashed\DashedEcommerceCore\Models\Product::class)) {
            foreach (\Dashed\DashedEcommerceCore\Models\Product::query()->whereJsonContains('site_ids', $siteId)->limit(20)->get() as $p) {
                $lines[] = 'Product: ' . $p->name;
            }
        }

        return implode("\n", $lines) ?: 'Geen specifieke inhoud bekend.';
    }
}
