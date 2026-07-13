<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
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

        if ($search = trim($request->string('search')->toString())) {
            // Slim multi-term: elk woord moet matchen op vraag óf antwoord (AND over woorden).
            $terms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [$search];
            $query->where(function ($outer) use ($terms): void {
                foreach ($terms as $term) {
                    $outer->where(function ($q) use ($term): void {
                        $q->where('question', 'like', "%{$term}%")
                            ->orWhere('answer', 'like', "%{$term}%");
                    });
                }
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
