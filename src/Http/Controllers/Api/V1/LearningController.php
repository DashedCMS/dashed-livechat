<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedLivechat\Models\ChatLearning;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Dashed\DashedLivechat\Http\Resources\Api\Mobile\LearningResource;

class LearningController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ChatLearning::query()
            ->where('site_id', (string) Sites::getActive());

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search): void {
                $q->where('question', 'like', "%{$search}%")
                    ->orWhere('answer', 'like', "%{$search}%");
            });
        }

        return LearningResource::collection(
            $query->orderByDesc('id')->get(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['site_id'] = (string) Sites::getActive();
        $data['source'] = $data['source'] ?? 'human';

        $model = ChatLearning::create($data);

        return (new LearningResource($model->fresh()))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $learning): LearningResource
    {
        $model = $this->resolve($learning);
        $model->update($this->validated($request));

        return new LearningResource($model->fresh());
    }

    public function destroy(int $learning): JsonResponse
    {
        $this->resolve($learning)->delete();

        return response()->json(null, 204);
    }

    private function resolve(int $learning): ChatLearning
    {
        return ChatLearning::query()
            ->where('site_id', (string) Sites::getActive())
            ->findOrFail($learning);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'question' => ['required', 'string'],
            'answer' => ['required', 'string'],
            'source' => ['sometimes', Rule::in(['feedback', 'human', 'ai'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
