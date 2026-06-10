<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedLivechat\Models\ChatTrigger;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Dashed\DashedLivechat\Http\Resources\Api\Mobile\TriggerResource;

class TriggerController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return TriggerResource::collection(
            ChatTrigger::query()
                ->where('site_id', (string) Sites::getActive())
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        );
    }

    public function show(int $trigger): TriggerResource
    {
        return new TriggerResource($this->resolve($trigger));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['site_id'] = (string) Sites::getActive();

        $model = ChatTrigger::create($data);

        return (new TriggerResource($model->fresh()))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $trigger): TriggerResource
    {
        $model = $this->resolve($trigger);
        $model->update($this->validated($request));

        return new TriggerResource($model->fresh());
    }

    public function destroy(int $trigger): JsonResponse
    {
        $this->resolve($trigger)->delete();

        return response()->json(null, 204);
    }

    private function resolve(int $trigger): ChatTrigger
    {
        return ChatTrigger::query()
            ->where('site_id', (string) Sites::getActive())
            ->findOrFail($trigger);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
            'placement' => ['sometimes', Rule::in(['all_pages', 'include_urls', 'url_pattern', 'models'])],
            'url_rules' => ['nullable', 'array'],
            'url_rules.*' => ['string'],
            'exclude_urls' => ['nullable', 'array'],
            'exclude_urls.*' => ['string'],
            'model_links' => ['nullable', 'array'],
            'model_links.*.type' => ['required_with:model_links', 'string'],
            'model_links.*.id' => ['required_with:model_links', 'integer'],
            'trigger_type' => ['sometimes', Rule::in(['none', 'immediate', 'time_on_page', 'scroll_depth', 'exit_intent'])],
            'trigger_value' => ['nullable', 'integer', 'min:0'],
            'proactive_message' => ['nullable', 'string'],
            'ai_agent_id' => ['nullable', 'integer'],
        ]);
    }
}
