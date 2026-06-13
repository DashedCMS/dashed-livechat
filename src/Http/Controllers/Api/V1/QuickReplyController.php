<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedLivechat\Models\ChatQuickReply;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Dashed\DashedLivechat\Http\Resources\Api\Mobile\QuickReplyResource;

class QuickReplyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $replies = ChatQuickReply::query()
            ->where('site_id', (string) Sites::getActive())
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return QuickReplyResource::collection($replies);
    }
}
