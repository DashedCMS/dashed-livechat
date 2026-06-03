<?php

namespace Dashed\DashedLivechat\Ai\Tools;

use Dashed\DashedLivechat\Ai\Contracts\ChatTool;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Services\OpeningHoursService;

class GetOpeningHoursTool implements ChatTool
{
    public function __construct(private OpeningHoursService $hours)
    {
    }

    public function name(): string
    {
        return 'getOpeningHours';
    }

    public function description(): string
    {
        return 'Geef de openingstijden en of er nu een medewerker beschikbaar is.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function handle(array $input, ChatConversation $conversation): array
    {
        $site = $conversation->site_id;
        $next = $this->hours->nextOpening($site);

        return [
            'is_open_now' => $this->hours->isOpen($site),
            'today' => $this->hours->todaysHours($site),
            'next_opening' => $next?->format('Y-m-d H:i'),
        ];
    }
}
