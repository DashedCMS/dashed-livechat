<?php

namespace Dashed\DashedLivechat\Ai\Knowledge;

use Illuminate\Support\Collection;
use Dashed\DashedLivechat\Ai\Knowledge\Contracts\KnowledgeSearchDriver;

class EmbeddingSearchDriver implements KnowledgeSearchDriver
{
    public function __construct(private EmbeddingService $embeddings)
    {
    }

    public function search(string $modelClass, array $columns, string $siteId, string $term, int $limit): Collection
    {
        return $this->embeddings->searchModels($modelClass, $siteId, $term, $limit);
    }
}
