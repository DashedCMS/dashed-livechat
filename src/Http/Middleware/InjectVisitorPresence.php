<?php

namespace Dashed\DashedLivechat\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InjectVisitorPresence
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (! $this->shouldInject($request, $response)) {
            return $response;
        }

        $content = $response->getContent();
        if (! is_string($content) || ! str_contains($content, '</body>')) {
            return $response;
        }

        $response->setContent(str_replace('</body>', $this->script() . '</body>', $content));

        return $response;
    }

    protected function shouldInject(Request $request, $response): bool
    {
        if ($request->ajax() || $request->isJson() || $request->is('dashed*') || $request->is('admin*') || $request->is('livewire*')) {
            return false;
        }

        if (! method_exists($response, 'getContent') || ! method_exists($response, 'setContent')) {
            return false;
        }

        $contentType = $response->headers->get('Content-Type');

        return $contentType && str_contains($contentType, 'text/html');
    }

    protected function script(): string
    {
        $url = route('dashed-livechat.presence');

        return <<<HTML
<script>
(function () {
    try {
        var KEY = 'dashed_visitor_token';
        var token = localStorage.getItem(KEY);
        if (! token) {
            token = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(16).slice(2));
            localStorage.setItem(KEY, token);
        }
        function beat() {
            var qs = 'token=' + encodeURIComponent(token)
                + '&url=' + encodeURIComponent(location.href)
                + '&referrer=' + encodeURIComponent(document.referrer || '');
            fetch('{$url}?' + qs, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }).catch(function () {});
        }
        beat();
        setInterval(beat, 25000);
        document.addEventListener('visibilitychange', function () { if (! document.hidden) beat(); });
    } catch (e) {}
})();
</script>
HTML;
    }
}
