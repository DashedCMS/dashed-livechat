<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Ai\Tools;

use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedLivechat\Ai\Contracts\ChatTool;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedEcommerceCore\Services\BackInStockService;

class SubscribeBackInStockTool implements ChatTool
{
    public function name(): string
    {
        return 'subscribeBackInStock';
    }

    public function description(): string
    {
        return 'Meld de bezoeker aan voor een "weer op voorraad"-melding voor EEN uitverkocht product. Vereist het e-mailadres van de bezoeker (gebruik het bekende adres als dat er is, vraag er anders om). Zoekt het product op slug of naam.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'product' => ['type' => 'string', 'description' => 'De slug of naam van het product.'],
                'email' => ['type' => 'string', 'description' => 'Het e-mailadres waarop de bezoeker de melding wil ontvangen.'],
            ],
            'required' => ['product'],
        ];
    }

    public function handle(array $input, ChatConversation $conversation): array
    {
        if (! class_exists(Product::class) || ! class_exists(BackInStockService::class)) {
            return ['ok' => false, 'message' => 'Terug-op-voorraad-meldingen zijn hier niet beschikbaar.'];
        }

        $email = trim((string) ($input['email'] ?? $conversation->visitor_email ?? ''));
        if ($email === '') {
            return ['ok' => false, 'message' => 'Op welk e-mailadres mag ik je een berichtje sturen zodra het weer op voorraad is?'];
        }

        $needle = trim((string) ($input['product'] ?? ''));
        $locale = $conversation->locale ?: app()->getLocale();

        $product = Product::query()
            ->whereJsonContains('site_ids', $conversation->site_id)
            ->where(function ($q) use ($needle, $locale) {
                $q->where('slug->' . $locale, $needle)
                    ->orWhere('name->' . $locale, 'like', "%{$needle}%");
            })
            ->first();

        if (! $product) {
            return ['ok' => false, 'found' => false, 'message' => 'Ik kan dat product niet vinden.'];
        }

        if ($product->hasDirectSellableStock()) {
            return ['ok' => true, 'already_in_stock' => true, 'message' => 'Goed nieuws: dit product is nu al op voorraad.'];
        }

        // In de agent-testomgeving niet echt aanmelden.
        if ($conversation->is_sandbox) {
            return ['ok' => true, 'simulated' => true, 'message' => '[Testmodus] De aanmelding is niet echt opgeslagen.'];
        }

        app(BackInStockService::class)->subscribe((string) $conversation->site_id, (int) $product->id, $email);

        return ['ok' => true, 'message' => 'Gelukt! Je krijgt een e-mail zodra dit product weer op voorraad is.'];
    }
}
