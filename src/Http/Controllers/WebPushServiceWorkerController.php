<?php

namespace Dashed\DashedLivechat\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class WebPushServiceWorkerController extends Controller
{
    public function __invoke(): Response
    {
        $path = __DIR__ . '/../../../resources/js/web-push-sw.js';

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            // Root-scope zodat de worker meldingen voor het hele CMS mag tonen.
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
