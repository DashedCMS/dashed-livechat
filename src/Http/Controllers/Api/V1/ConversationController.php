<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Dashed\DashedLivechat\Services\HandoffService;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Services\ConversationManager;
use Dashed\DashedLivechat\Services\ConversationContextService;
use Dashed\DashedLivechat\Http\Resources\Api\Mobile\MessageResource;
use Dashed\DashedLivechat\Http\Resources\Api\Mobile\ConversationResource;
use Dashed\DashedLivechat\Http\Resources\Api\Mobile\ConversationDetailResource;

class ConversationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ChatConversation::query()->where('site_id', Sites::getActive());

        if ($mode = $request->query('mode')) {
            $query->where('mode', (string) $mode);
        }

        if (($status = $request->query('status')) && $status !== 'all') {
            $query->where('status', (string) $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('visitor_name', 'like', '%' . (string) $search . '%')
                    ->orWhere('visitor_email', 'like', '%' . (string) $search . '%');
            });
        }

        $perPage = (int) config('dashed-mobile-api.default_page_size', 25);

        return ConversationResource::collection(
            $query->orderByDesc('last_message_at')->paginate($perPage),
        );
    }

    public function show(int $conversation, ConversationContextService $context): ConversationDetailResource
    {
        $model = $this->resolve($conversation);
        $model->load(['aiAgent', 'assignedAgent']);
        $model->related_context = $context->relatedFor($model);

        return new ConversationDetailResource($model);
    }

    public function messages(Request $request, int $conversation): AnonymousResourceCollection
    {
        $model = $this->resolve($conversation);

        $query = $model->messages()->where('is_internal', false)->with('agent');

        if ($afterId = $request->query('after_id')) {
            $query->where('id', '>', (int) $afterId);
        }

        $perPage = (int) config('dashed-mobile-api.default_page_size', 25);

        return MessageResource::collection($query->limit($perPage)->get());
    }

    public function sendMessage(Request $request, ConversationManager $manager, int $conversation): JsonResponse
    {
        $model = $this->resolve($conversation);

        $data = $request->validate([
            'content' => ['required_without:attachments', 'nullable', 'string'],
            'attachments' => ['nullable', 'array', 'max:5'],
            // Valideer op de CLIENT-aangeleverde mime/extensie i.p.v. server-side
            // inhoud-sniffing (mimetypes/mimes): libmagic herkent HEIC van iPhone-
            // foto's vaak niet → octet-stream → onterechte 422.
            'attachments.*' => ['file', 'max:10240', \Dashed\DashedLivechat\Support\AttachmentRules::clientImageOrPdf()],
        ]);

        $agent = app(HandoffService::class)->humanAgentForUser($request->user(), Sites::getActive());
        $message = $manager->addHumanMessage(
            $model,
            $agent,
            (string) ($data['content'] ?? ''),
            $request->file('attachments', [])
        );

        return (new MessageResource($message))
            ->response()
            ->setStatusCode(201);
    }

    public function takeOver(Request $request, HandoffService $handoff, int $conversation): ConversationResource
    {
        $model = $this->resolve($conversation);

        $agent = $handoff->humanAgentForUser($request->user(), Sites::getActive());
        $handoff->takeOver($model, $agent);

        activity()
            ->performedOn($model)
            ->causedBy($request->user())
            ->log('mobile-api: gesprek overgenomen');

        return new ConversationResource($model->fresh());
    }

    public function release(Request $request, HandoffService $handoff, int $conversation): ConversationResource
    {
        $model = $this->resolve($conversation);

        $handoff->release($model);

        activity()
            ->performedOn($model)
            ->causedBy($request->user())
            ->log('mobile-api: gesprek vrijgegeven');

        return new ConversationResource($model->fresh());
    }

    public function translate(Request $request, int $conversation): JsonResponse
    {
        // Bevestigt dat het gesprek tot de actieve site hoort (en bestaat).
        $this->resolve($conversation);

        $data = $request->validate([
            'text' => ['required', 'string'],
            'target' => ['nullable', 'string', 'max:16'],
        ]);

        $target = (string) ($data['target'] ?? '') !== ''
            ? (string) $data['target']
            : (app()->getLocale() ?: 'nl');

        $system = "Je bent een vertaler. Vertaal het bericht naar taal-code '{$target}'. Geef ALLEEN de vertaling terug, zonder uitleg of aanhalingstekens.";

        try {
            $response = \Dashed\DashedLivechat\Ai\LivechatAi::requireClaude()->messages(
                [['role' => 'user', 'content' => (string) $data['text']]],
                ['system' => $system, 'temperature' => 0.2, 'max_tokens' => 800]
            );

            $translated = collect($response['content'] ?? [])
                ->where('type', 'text')
                ->pluck('text')
                ->implode("\n");

            $translated = trim($translated);

            if ($translated === '') {
                return response()->json(['message' => 'Vertaling mislukt: geen tekst ontvangen.'], 422);
            }

            return response()->json(['translation' => $translated, 'target' => $target]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Vertaling mislukt: ' . $e->getMessage()], 422);
        }
    }

    private function resolve(int $conversation): ChatConversation
    {
        return ChatConversation::query()
            ->where('site_id', Sites::getActive())
            ->findOrFail($conversation);
    }
}
