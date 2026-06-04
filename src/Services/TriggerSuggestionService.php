<?php

namespace Dashed\DashedLivechat\Services;

use Dashed\DashedLivechat\Ai\LivechatAi;
use Dashed\DashedLivechat\Enums\TriggerType;
use Dashed\DashedLivechat\Enums\TriggerPlacement;

class TriggerSuggestionService
{
    public function suggest(string $siteId): array
    {
        $context = $this->siteContext($siteId);
        $prompt = "Je helpt een webshop proactieve chat-triggers bedenken. "
            . "Op basis van deze site-inhoud, stel 3 tot 5 triggers voor die de conversie verhogen. "
            . "Geef geldige JSON: {\"suggestions\":[{\"name\":string,\"placement\":\"all_pages|include_urls|url_pattern\",\"url_rules\":[string],\"trigger_type\":\"immediate|time_on_page|scroll_depth|exit_intent\",\"trigger_value\":number,\"proactive_message\":string}]}. "
            . "Schrijf de proactive_message in het Nederlands, kort en uitnodigend, zonder em-dashes.\n\nSite-inhoud:\n" . $context;

        $response = LivechatAi::json($prompt);
        $raw = $response['suggestions'] ?? [];

        return collect($raw)->map(fn ($s) => [
            'name' => (string) ($s['name'] ?? 'Voorstel'),
            'placement' => $this->enumValue($s['placement'] ?? null, TriggerPlacement::cases(), 'all_pages'),
            'url_rules' => array_values(array_filter((array) ($s['url_rules'] ?? []))),
            'trigger_type' => $this->enumValue($s['trigger_type'] ?? null, TriggerType::cases(), 'none'),
            'trigger_value' => isset($s['trigger_value']) ? (int) $s['trigger_value'] : null,
            'proactive_message' => (string) ($s['proactive_message'] ?? ''),
        ])->values()->all();
    }

    protected function enumValue(?string $value, array $cases, string $default): string
    {
        $allowed = array_map(fn ($c) => $c->value, $cases);

        return in_array($value, $allowed, true) ? $value : $default;
    }

    protected function siteContext(string $siteId): string
    {
        $lines = [];

        if (class_exists(\Dashed\DashedPages\Models\Page::class)) {
            foreach (\Dashed\DashedPages\Models\Page::query()->whereJsonContains('site_ids', $siteId)->limit(25)->get() as $p) {
                $lines[] = 'Pagina: ' . $p->name;
            }
        }

        if (class_exists(\Dashed\DashedEcommerceCore\Models\Product::class)) {
            foreach (\Dashed\DashedEcommerceCore\Models\Product::query()->whereJsonContains('site_ids', $siteId)->limit(25)->get() as $p) {
                $lines[] = 'Product: ' . $p->name;
            }
        }

        return implode("\n", $lines) ?: 'Geen specifieke inhoud bekend.';
    }
}
