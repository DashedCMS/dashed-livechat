<?php

// src/Ai/Tools/RequestHumanHandoffTool.php

namespace Dashed\DashedLivechat\Ai\Tools;

use Dashed\DashedLivechat\Ai\Contracts\ChatTool;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Services\HandoffService;

class RequestHumanHandoffTool implements ChatTool
{
    public function __construct(private HandoffService $handoff)
    {
    }

    public function name(): string
    {
        return 'requestHumanHandoff';
    }

    public function description(): string
    {
        return 'Schakel over naar een menselijke medewerker wanneer de bezoeker daarom vraagt of wanneer je de vraag niet kunt afhandelen. Geef een korte reden mee.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reason' => [
                    'type' => 'string',
                    'description' => 'Korte reden voor de overdracht.',
                ],
            ],
            'required' => ['reason'],
        ];
    }

    public function handle(array $input, ChatConversation $conversation): array
    {
        return $this->handoff->requestHandoff($conversation, $input['reason'] ?? null);
    }
}
