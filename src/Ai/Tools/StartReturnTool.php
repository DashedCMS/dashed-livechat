<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Ai\Tools;

use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedLivechat\Ai\Contracts\ChatTool;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Services\OrderVerification;

class StartReturnTool implements ChatTool
{
    public function __construct(private OrderVerification $verification)
    {
    }

    public function name(): string
    {
        return 'startReturn';
    }

    public function description(): string
    {
        return 'Start een retour voor een geverifieerde bestelling. Vereist ordernummer + e-mail (moeten kloppen) en de te retourneren regels (order_product_id + aantal). Gebruik dit pas nadat de klant heeft aangegeven wat en waarom te retourneren.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'orderNumber' => ['type' => 'string', 'description' => 'Ordernummer/factuurnummer.'],
                'email' => ['type' => 'string', 'description' => 'E-mailadres van de bestelling.'],
                'lines' => [
                    'type' => 'array',
                    'description' => 'De te retourneren regels.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'order_product_id' => ['type' => 'integer'],
                            'quantity' => ['type' => 'integer'],
                        ],
                        'required' => ['order_product_id', 'quantity'],
                    ],
                ],
                'reason' => ['type' => 'string', 'description' => 'Optionele retourreden.'],
            ],
            'required' => ['orderNumber', 'email', 'lines'],
        ];
    }

    public function handle(array $input, ChatConversation $conversation): array
    {
        $result = $this->verification->verify(
            $conversation,
            $input['orderNumber'] ?? null,
            $input['email'] ?? null,
        );

        if ($result->status === 'blocked') {
            return ['ok' => false, 'message' => 'Te veel pogingen. Ik verbind je door met een collega.'];
        }
        if ($result->status !== 'found' || $result->order === null) {
            return ['ok' => false, 'message' => 'Ik kan geen bestelling vinden met dat ordernummer en e-mailadres.'];
        }

        $lines = array_values(array_filter(array_map(
            fn ($l) => [
                'order_product_id' => (int) ($l['order_product_id'] ?? 0),
                'quantity' => (int) ($l['quantity'] ?? 0),
            ],
            is_array($input['lines'] ?? null) ? $input['lines'] : [],
        ), fn ($l) => $l['order_product_id'] > 0 && $l['quantity'] > 0));

        if ($lines === []) {
            return ['ok' => false, 'message' => 'Geef aan welke producten en aantallen je wilt retourneren.'];
        }

        if ($conversation->is_sandbox) {
            return [
                'ok' => true,
                'simulated' => true,
                'message' => '[Testmodus] De retour is niet echt aangemaakt — in de testomgeving voeren we geen wijzigingen door.',
            ];
        }

        try {
            $result->order->registerReturn($lines, restock: true, markForRefund: true);
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $reason = isset($input['reason']) ? trim((string) $input['reason']) : '';
        if ($reason !== '') {
            OrderLog::createLog(
                orderId: $result->order->id,
                tag: 'order.return.reason',
                note: 'Retourreden (via chat): '.$reason,
            );
        }

        return [
            'ok' => true,
            'message' => 'De retour is aangemeld. Je ontvangt de retourinstructies per e-mail.',
            'reason' => $reason !== '' ? $reason : null,
        ];
    }
}
