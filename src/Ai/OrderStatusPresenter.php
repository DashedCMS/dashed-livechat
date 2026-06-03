<?php

namespace Dashed\DashedLivechat\Ai;

use Dashed\DashedEcommerceCore\Models\Order;

class OrderStatusPresenter
{
    public function present(Order $order): array
    {
        return [
            'order_number' => $order->invoice_id ?: $order->hash,
            'status' => $order->status,
            'fulfillment_status' => $order->fulfillment_status,
            'order_date' => optional($order->created_at)->format('Y-m-d'),
            'products' => $order->orderProducts
                ->reject(fn ($p) => method_exists($p, 'trashed') && $p->trashed())
                ->map(fn ($p) => [
                    'name' => $p->name,
                    'quantity' => (int) $p->quantity,
                ])->values()->all(),
            'track_and_trace' => $order->trackAndTraces->map(fn ($t) => array_filter([
                'delivery_company' => $t->delivery_company,
                'code' => $t->code,
                'url' => $t->url,
                'status' => $t->status,
            ]))->values()->all(),
        ];
    }
}
