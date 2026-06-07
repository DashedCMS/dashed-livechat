<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Dashed\DashedLivechat\Models\ChatAgent;
use Dashed\DashedLivechat\Http\Resources\Api\Mobile\AgentResource;

class AgentController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AgentResource::collection(
            ChatAgent::query()
                ->where('site_id', (string) Sites::getActive())
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        );
    }

    public function show(int $agent): AgentResource
    {
        return new AgentResource($this->resolve($agent));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['site_id'] = (string) Sites::getActive();

        $agent = ChatAgent::create($data);

        return (new AgentResource($agent->fresh()))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $agent): AgentResource
    {
        $model = $this->resolve($agent);
        $model->update($this->validated($request));

        return new AgentResource($model->fresh());
    }

    public function destroy(int $agent): JsonResponse
    {
        $this->resolve($agent)->delete();

        return response()->json(null, 204);
    }

    private function resolve(int $agent): ChatAgent
    {
        return ChatAgent::query()
            ->where('site_id', (string) Sites::getActive())
            ->findOrFail($agent);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['ai', 'human'])],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
            'avatar' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'user_id' => ['nullable', 'integer'],
            'persona' => ['nullable', 'string'],
            'tone' => ['nullable', 'string'],
            'languages' => ['nullable', 'array'],
            'allowed_topics' => ['nullable', 'string'],
            'disallowed_topics' => ['nullable', 'string'],
            'escalation_rules' => ['nullable', 'string'],
            'greeting' => ['nullable', 'string'],
            'model' => ['nullable', 'string'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'guardrail_mode' => ['nullable', Rule::in(['standard', 'strict'])],
            'enabled_tools' => ['nullable', 'array'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => [Rule::in(['chat.read', 'chat.reply', 'chat.takeover', 'chat.manage'])],
        ]);
    }
}
