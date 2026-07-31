<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedLivechat\Models\ChatTag;
use Dashed\DashedLivechat\Models\ChatNote;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Services\HandoffService;
use Dashed\DashedLivechat\Services\ConversationManager;
use Dashed\DashedLivechat\Services\ConversationContextService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Dashed\DashedLivechat\Http\Resources\Api\Mobile\MessageResource;
use Dashed\DashedLivechat\Http\Resources\Api\Mobile\ConversationResource;
use Dashed\DashedLivechat\Http\Resources\Api\Mobile\ConversationDetailResource;

class ConversationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        // visitorSession + tags eager-loaden zodat visitorPresence()/tags geen N+1 doen.
        $query = ChatConversation::query()->with(['visitorSession', 'tags'])->where('site_id', Sites::getActive());

        if ($mode = $request->query('mode')) {
            $query->where('mode', (string) $mode);
        }

        if ($tagId = $request->query('tag_id')) {
            $query->whereHas('tags', fn ($q) => $q->where('dashed__chat_tags.id', (int) $tagId));
        }

        if (($status = $request->query('status')) && $status !== 'all') {
            $query->where('status', (string) $status);
        }

        if ($search = trim((string) $request->query('search'))) {
            // Slim multi-term: elk woord moet matchen op naam óf e-mail (AND over woorden).
            $terms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [$search];
            $query->where(function ($outer) use ($terms): void {
                foreach ($terms as $term) {
                    $outer->where(function ($q) use ($term): void {
                        $q->where('visitor_name', 'like', "%{$term}%")
                            ->orWhere('visitor_email', 'like', "%{$term}%");
                    });
                }
            });
        }

        // Filter 'wacht op mijn antwoord': alleen gesprekken waar de bezoeker als
        // laatste iets stuurde. Gesloten gesprekken vallen weg (niks te doen).
        if ($request->query('awaiting') === 'agent') {
            $query->awaitingAgent()->where('status', '!=', 'closed');
        }

        $perPage = (int) config('dashed-mobile-api.default_page_size', 25);

        // Gesprekken die op jou wachten bovenaan, daarna nieuwste eerst.
        return ConversationResource::collection(
            $query
                ->orderByRaw("CASE WHEN last_message_role = 'visitor' THEN 0 ELSE 1 END")
                ->orderByDesc('last_message_at')
                ->paginate($perPage),
        );
    }

    public function show(int $conversation, ConversationContextService $context): ConversationDetailResource
    {
        $model = $this->resolve($conversation);
        $model->load(['aiAgent', 'assignedAgent', 'tags', 'notes', 'visitorSession']);
        $model->related_context = $context->relatedFor($model);

        return new ConversationDetailResource($model);
    }

    /** Alle tags van de actieve site (voor de tag-picker in de app). */
    public function tags(): JsonResponse
    {
        $tags = ChatTag::query()
            ->where('site_id', Sites::getActive())
            ->orderBy('sort')
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        return response()->json(['data' => $tags]);
    }

    /** Koppelt de opgegeven tags aan het gesprek (vervangt de huidige set). */
    public function setTags(Request $request, int $conversation): JsonResponse
    {
        $model = $this->resolve($conversation);

        $data = $request->validate([
            'tag_ids' => ['present', 'array'],
            'tag_ids.*' => ['integer'],
        ]);

        // Alleen tags van dezelfde site mogen gekoppeld worden.
        $validIds = ChatTag::query()
            ->where('site_id', Sites::getActive())
            ->whereIn('id', $data['tag_ids'])
            ->pluck('id')
            ->all();

        $model->tags()->sync($validIds);

        $tags = $model->tags()->orderBy('sort')->orderBy('name')->get(['dashed__chat_tags.id', 'name', 'color']);

        return response()->json(['data' => $tags]);
    }

    /** AI-copilot: genereert een concept-antwoord (verstuurt niet). */
    public function suggestReply(Request $request, int $conversation): JsonResponse
    {
        $model = $this->resolve($conversation);

        try {
            $suggestion = \Dashed\DashedLivechat\Support\ReplySuggester::suggest($model);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Kon geen suggestie ophalen: ' . $e->getMessage()], 502);
        }

        return response()->json(['suggestion' => $suggestion]);
    }

    /** Voegt een interne notitie toe (niet zichtbaar voor de bezoeker). */
    public function addNote(Request $request, int $conversation): JsonResponse
    {
        $model = $this->resolve($conversation);

        $data = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $user = $request->user();
        $note = ChatNote::create([
            'chat_conversation_id' => $model->id,
            'user_id' => $user?->id,
            'author_name' => $user?->name,
            'body' => trim((string) $data['body']),
        ]);

        return response()->json([
            'data' => [
                'id' => $note->id,
                'body' => $note->body,
                'author' => $note->author_name,
                'created_at' => optional($note->created_at)->toIso8601String(),
            ],
        ], 201);
    }

    public function messages(Request $request, int $conversation): JsonResponse
    {
        $model = $this->resolve($conversation);

        $query = $model->messages()->where('is_internal', false)->with('agent');

        if ($afterId = $request->query('after_id')) {
            $query->where('id', '>', (int) $afterId);
        }

        $perPage = (int) config('dashed-mobile-api.default_page_size', 25);

        $messages = $query->limit($perPage)->get();

        // Houd de `data`-sleutel met de berichten zodat bestaande parsing blijft
        // werken; voeg de huidige leesbevestiging van de bezoeker ernaast toe.
        return response()->json([
            'data' => MessageResource::collection($messages),
            'visitor_read_at' => optional($model->visitor_read_at)->toIso8601String(),
        ]);
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
