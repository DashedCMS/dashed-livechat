<?php

namespace Dashed\DashedLivechat\Ai\Tools;

use Dashed\DashedLivechat\Ai\Contracts\ChatTool;
use Dashed\DashedLivechat\Models\ChatConversation;

class SaveContactDetailsTool implements ChatTool
{
    public function name(): string
    {
        return 'saveContactDetails';
    }

    public function description(): string
    {
        return 'Sla de naam en/of het e-mailadres van de bezoeker op zodra de bezoeker dit zelf deelt (bijv. "mijn naam is Kees" of een opgegeven e-mailadres). Roep dit direct aan wanneer je een naam of e-mailadres van de bezoeker hoort.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'De naam van de bezoeker, indien genoemd.'],
                'email' => ['type' => 'string', 'description' => 'Het e-mailadres van de bezoeker, indien genoemd.'],
            ],
        ];
    }

    public function handle(array $input, ChatConversation $conversation): array
    {
        $saved = [];

        $name = trim((string) ($input['name'] ?? ''));
        if ($name !== '') {
            $conversation->visitor_name = mb_substr($name, 0, 255);
            $saved['name'] = $conversation->visitor_name;
        }

        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $conversation->visitor_email = $email;
            $saved['email'] = $email;
        }

        if ($saved) {
            $conversation->save();
        }

        return ['saved' => $saved, 'ok' => ! empty($saved)];
    }
}
