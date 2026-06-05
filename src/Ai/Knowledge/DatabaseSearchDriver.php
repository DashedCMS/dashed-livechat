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

        // Naam/omschrijving zijn vaak JSON-kolommen (binaire collation), waardoor
        // LIKE hoofdlettergevoelig is. Daarom LOWER() aan beide kanten. Matcht de
        // hele zoekterm of alle losse woorden (elk in minstens één kolom), zodat
        // "flowy vaas" ook "Flowy 3D vaas" vindt.
        $termLower = mb_strtolower($term);
        $words = preg_split('/\s+/', $termLower, -1, PREG_SPLIT_NO_EMPTY) ?: [$termLower];

        $query->where(function ($outer) use ($columns, $termLower, $words) {
            foreach ($columns as $column) {
                $outer->orWhereRaw('LOWER(' . $column . ') LIKE ?', ['%' . $termLower . '%']);
            }

            $outer->orWhere(function ($all) use ($columns, $words) {
                foreach ($words as $word) {
                    $all->where(function ($perWord) use ($columns, $word) {
                        foreach ($columns as $column) {
                            $perWord->orWhereRaw('LOWER(' . $column . ') LIKE ?', ['%' . $word . '%']);
                        }
                    });
                }
            });
        });

        return $query->limit($limit)->get();
    }
}
