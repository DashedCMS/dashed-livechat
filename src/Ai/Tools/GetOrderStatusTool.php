<?php

namespace Dashed\DashedLivechat\Ai\Tools;

use Dashed\DashedLivechat\Ai\Contracts\ChatTool;
use Dashed\DashedLivechat\Ai\OrderStatusPresenter;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Services\OrderVerification;

class GetOrderStatusTool implements ChatTool
{
    public function __construct(
        private OrderVerification $verification,
        private OrderStatusPresenter $presenter,
    ) {
    }

    public function name(): string
    {
        return 'getOrderStatus';
    }

    public function description(): string
    {
        return 'Geef de status van EEN specifieke bestelling. Vereist zowel het ordernummer als het e-mailadres dat bij die bestelling hoort; beide moeten kloppen. Dit is geen zoekfunctie over klanten of bestellingen.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'orderNumber' => ['type' => 'string', 'description' => 'Het ordernummer/factuurnummer van de bestelling.'],
                'email' => ['type' => 'string', 'description' => 'Het e-mailadres dat bij die bestelling hoort.'],
            ],
            'required' => ['orderNumber', 'email'],
        ];
    }

    public function handle(array $input, ChatConversation $conversation): array
    {
        $result = $this->verification->verify(
            $conversation,
            $input['orderNumber'] ?? null,
            $input['email'] ?? null,
        );

        return match ($result->status) {
            'found' => [
                'found' => true,
                'order' => $this->presenter->present($result->order),
            ],
            'blocked' => [
                'found' => false,
                'blocked' => true,
                'message' => 'Te veel pogingen. Ik kan dit niet meer controleren in deze chat. Wil je dat ik je doorverbind met een collega?',
            ],
            default => [
                'found' => false,
                'message' => 'Ik kan geen bestelling vinden met dat ordernummer en e-mailadres. Controleer of beide kloppen en zoals in de bevestigingsmail staan.',
            ],
        };
    }
}
