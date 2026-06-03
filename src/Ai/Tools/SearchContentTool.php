<?php

namespace Dashed\DashedLivechat\Ai\Tools;

use Dashed\DashedPages\Models\Page;
use Dashed\DashedArticles\Models\Article;
use Dashed\DashedLivechat\Ai\Contracts\ChatTool;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Ai\Knowledge\Contracts\KnowledgeSearchDriver;

class SearchContentTool implements ChatTool
{
    public function __construct(private KnowledgeSearchDriver $driver)
    {
    }

    public function name(): string
    {
        return 'searchContent';
    }

    public function description(): string
    {
        return 'Zoek in pagina\'s en blogartikelen van deze website op trefwoord. Gebruik dit voor algemene info, beleid, en uitleg.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Zoekterm.'],
                'types' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => ['pages', 'articles']],
                    'description' => 'Welke types doorzoeken; default beide.',
                ],
                'limit' => ['type' => 'integer'],
            ],
            'required' => ['query'],
        ];
    }

    public function handle(array $input, ChatConversation $conversation): array
    {
        $types = $input['types'] ?? ['pages', 'articles'];
        $limit = (int) ($input['limit'] ?? 5);
        $query = (string) ($input['query'] ?? '');
        $results = [];

        if (in_array('pages', $types, true) && class_exists(\Dashed\DashedPages\Models\Page::class)) {
            foreach ($this->driver->search(Page::class, ['name', 'content'], $conversation->site_id, $query, $limit) as $p) {
                $results[] = ['type' => 'page', 'title' => $p->name, 'url' => rescue(fn () => $p->getUrl(), null, false)];
            }
        }

        if (in_array('articles', $types, true) && class_exists(\Dashed\DashedArticles\Models\Article::class)) {
            foreach ($this->driver->search(Article::class, ['name', 'content'], $conversation->site_id, $query, $limit) as $a) {
                $results[] = ['type' => 'article', 'title' => $a->name, 'url' => rescue(fn () => $a->getUrl(), null, false)];
            }
        }

        return ['results' => $results];
    }
}
