<?php

namespace Dashed\DashedLivechat\Ai\Knowledge\Contracts;

use Illuminate\Support\Collection;

interface KnowledgeSearchDriver
{
    public function search(string $modelClass, array $columns, string $siteId, string $term, int $limit): Collection;
}
