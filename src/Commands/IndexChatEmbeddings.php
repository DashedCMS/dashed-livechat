<?php

namespace Dashed\DashedLivechat\Commands;

use Illuminate\Console\Command;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Schema;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedLivechat\Ai\Knowledge\EmbeddingService;

class IndexChatEmbeddings extends Command
{
    protected $signature = 'chat:index-embeddings {site? : Site ID to index (defaults to all sites)}';

    protected $description = 'Index product and content embeddings for semantic search per site.';

    public function handle(EmbeddingService $embeddings): int
    {
        $siteArg = $this->argument('site');

        $sites = $siteArg
            ? [['id' => $siteArg]]
            : Sites::getSites();

        foreach ($sites as $site) {
            $siteId = $site['id'];

            $driver = Customsetting::get('chat_search_driver', $siteId, 'fulltext');

            if ($driver !== 'embedding') {
                $this->line("Site {$siteId}: embedding-driver niet actief, overgeslagen.");

                continue;
            }

            $this->line("Site {$siteId}: embeddings indexeren...");

            // Products (always available via dashed-ecommerce-core).
            $this->indexModel(
                \Dashed\DashedEcommerceCore\Models\Product::class,
                $siteId,
                $embeddings,
                fn ($p) => trim(($p->name ?? '') . ' ' . ($p->short_description ?? '')),
            );

            // Pages (optional; dashed-pages).
            if (class_exists(\Dashed\DashedPages\Models\Page::class)) {
                $this->indexModel(
                    \Dashed\DashedPages\Models\Page::class,
                    $siteId,
                    $embeddings,
                    fn ($page) => trim(($page->name ?? '') . ' ' . ($page->content ?? '')),
                );
            }

            // Articles (optional; dashed-articles).
            if (class_exists(\Dashed\DashedArticles\Models\Article::class)) {
                $this->indexModel(
                    \Dashed\DashedArticles\Models\Article::class,
                    $siteId,
                    $embeddings,
                    fn ($article) => trim(($article->name ?? '') . ' ' . ($article->content ?? '')),
                );
            }

            $this->line("Site {$siteId}: klaar.");
        }

        return self::SUCCESS;
    }

    private function indexModel(string $modelClass, string $siteId, EmbeddingService $embeddings, callable $textFn): void
    {
        $model = new $modelClass();

        // Guard site-column: prefer site_ids (JSON array), fall back to site_id (string).
        if (in_array('site_ids', $model->getFillable()) || Schema::hasColumn($model->getTable(), 'site_ids')) {
            $query = $modelClass::query()->whereJsonContains('site_ids', $siteId);
        } elseif (Schema::hasColumn($model->getTable(), 'site_id')) {
            $query = $modelClass::query()->where('site_id', $siteId);
        } else {
            $query = $modelClass::query();
        }

        $total = $query->count();

        if ($total === 0) {
            return;
        }

        $this->line("  {$modelClass}: {$total} record(s)...");

        $query->each(function ($record) use ($embeddings, $textFn, $siteId) {
            $text = $textFn($record);
            if ($text === '') {
                return;
            }
            $embeddings->upsertFor($record, $text, $siteId);
        });
    }
}
