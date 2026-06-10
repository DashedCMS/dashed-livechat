<?php

namespace Dashed\DashedLivechat\Services;

use Throwable;
use Illuminate\Database\Eloquent\Model;

class VisitableLineage
{
    private const MAX_DEPTH = 20;

    /** @return array<int, array{type: string, id: int}> */
    public function for(Model $model): array
    {
        $lineage = [];
        $seen = [];
        $current = $model;
        $depth = 0;

        while ($current instanceof Model && $depth < self::MAX_DEPTH) {
            $key = $current::class . '#' . $current->getKey();
            if (isset($seen[$key])) {
                break;
            }
            $seen[$key] = true;
            $lineage[] = ['type' => $current::class, 'id' => (int) $current->getKey()];
            $depth++;
            $current = $this->parentOf($current);
        }

        return $lineage;
    }

    private function parentOf(Model $model): ?Model
    {
        if (! method_exists($model, 'parent')) {
            return null;
        }

        try {
            $parent = $model->parent;
        } catch (Throwable) {
            return null;
        }

        return $parent instanceof Model ? $parent : null;
    }
}
