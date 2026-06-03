<?php

namespace Dashed\DashedLivechat\Ai\Knowledge;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Dashed\DashedLivechat\Ai\Knowledge\Contracts\KnowledgeSearchDriver;

class DatabaseSearchDriver implements KnowledgeSearchDriver
{
    public function search(string $modelClass, array $columns, string $siteId, string $term, int $limit): Collection
    {
        $term = trim($term);
        if ($term === '') {
            return collect();
        }

        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = $modelClass::query();

        // Site-scope: site_ids (json array) of site_id (string).
        $model = new $modelClass();
        if (in_array('site_ids', $model->getFillable()) || Schema::hasColumn($model->getTable(), 'site_ids')) {
            $query->whereJsonContains('site_ids', $siteId);
        } elseif (Schema::hasColumn($model->getTable(), 'site_id')) {
            $query->where('site_id', $siteId);
        }

        // LIKE over (mogelijk translatable JSON) kolommen.
        $query->where(function ($q) use ($columns, $term) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', '%' . $term . '%');
            }
        });

        return $query->limit($limit)->get();
    }
}
