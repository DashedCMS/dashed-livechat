<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedLivechat\Models\ChatOpeningHour;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Dashed\DashedLivechat\Http\Resources\Api\Mobile\OpeningHourResource;

class OpeningHourController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return OpeningHourResource::collection(
            ChatOpeningHour::query()
                ->where('site_id', (string) Sites::getActive())
                ->orderByRaw('day_of_week is null') // wekelijkse regels eerst
                ->orderBy('day_of_week')
                ->orderBy('date')
                ->get(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['site_id'] = (string) Sites::getActive();

        $hour = ChatOpeningHour::create($data);

        return (new OpeningHourResource($hour->fresh()))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $openingHour): OpeningHourResource
    {
        $model = $this->resolve($openingHour);
        $model->update($this->validated($request));

        return new OpeningHourResource($model->fresh());
    }

    public function destroy(int $openingHour): JsonResponse
    {
        $this->resolve($openingHour)->delete();

        return response()->json(null, 204);
    }

    private function resolve(int $openingHour): ChatOpeningHour
    {
        return ChatOpeningHour::query()
            ->where('site_id', (string) Sites::getActive())
            ->findOrFail($openingHour);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'day_of_week' => ['nullable', 'integer', 'min:0', 'max:6'],
            'date' => ['nullable', 'date'],
            'is_closed' => ['sometimes', 'boolean'],
            'opens_at' => ['nullable', 'date_format:H:i'],
            'closes_at' => ['nullable', 'date_format:H:i'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
