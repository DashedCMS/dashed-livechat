<?php

// src/Http/Middleware/InjectChatWidget.php

namespace Dashed\DashedLivechat\Http\Middleware;

use Closure;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Blade;
use Dashed\DashedLivechat\Support\WidgetConfig;
use Dashed\DashedLivechat\Services\TriggerMatcher;

class InjectChatWidget
{
    public function __construct(private TriggerMatcher $matcher)
    {
    }

    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Alleen HTML-responses op de frontend (geen admin/api).
        $contentType = $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'text/html')) {
            return $response;
        }
        // Alleen op de frontend: nooit in het Filament-admin (panel-routes heten
        // filament.*), niet op api-routes en niet op het admin-pad.
        $adminPrefix = config('filament-old.path', env('FILAMENT_PATH', 'dashed'));
        if ($request->routeIs('filament.*') || $request->is($adminPrefix) || $request->is($adminPrefix . '/*') || $request->is('api/*')) {
            return $response;
        }

        $siteId = Sites::getActive();
        if (! WidgetConfig::for($siteId)['enabled']) {
            return $response;
        }
        if (! $this->matcher->matches($siteId, $request->path())) {
            return $response;
        }

        $content = $response->getContent();
        if (! is_string($content) || ! str_contains($content, '</body>')) {
            return $response;
        }

        $trigger = $this->matcher->matchingTrigger($siteId, $request->path());
        $payload = $trigger ? [
            'type' => $trigger->trigger_type,
            'value' => $trigger->trigger_value,
            'message' => $trigger->proactive_message,
        ] : null;
        $widget = Blade::render('@livewire(\'chat.widget\', [\'siteId\' => $siteId, \'trigger\' => $trigger])', ['siteId' => $siteId, 'trigger' => $payload]);
        $response->setContent(str_replace('</body>', $widget . '</body>', $content));

        return $response;
    }
}
