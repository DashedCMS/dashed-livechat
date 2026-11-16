<?php

namespace Dashed\DashedLivechat\Commands;

use Illuminate\Console\Command;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Schema;
use Dashed\DashedCore\Models\BackgroundTask;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedLivechat\Ai\Knowledge\EmbeddingService;

class IndexChatEmbeddings extends Command
{
    protected $signature = 'chat:index-embeddings {site? : Site ID to index (defaults to all sites)}';

    protected $description = 'Index product and content embeddings for semantic search per site.';

    /**
     * De taak in het takenicoon van dashed-core. Pas aangemaakt bij de eerste
     * site die echt indexeert: op een site zonder embedding-zoekstrategie
     * doet deze dagelijkse ronde niets en hoort er ook geen taak te staan.
     */
    private ?BackgroundTask $task = null;

    private int $indexed = 0;

    public function handle(EmbeddingService $embeddings): int
    {
        try {
            $result = $this->indexSites($embeddings);
        } catch (\Throwable $e) {
            $this->task?->fail($e);

            throw $e;
        }

        $this->task?->progress($this->indexed);
        $this->task?->finish(__(':aantal items geïndexeerd.', ['aantal' => $this->indexed]));

        return $result;
    }

    private function indexSites(EmbeddingService $embeddings): int
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

            // Achter class_exists, want een oudere dashed-core kent
            // BackgroundTask nog niet. Zonder starter en zonder melding: dit
            // draait elke nacht vanuit de planner.
            if (! $this->task && class_exists(BackgroundTask::class)) {
                $this->task = BackgroundTask::begin('chat-embeddings', __('Kennisindex van de chat bijwerken'), notify: false);
            }

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

            // Geleerde antwoorden van medewerkers meenemen, zodat de bot bij
            // vergelijkbare vragen het echte collega-antwoord ophaalt.
            \Dashed\DashedLivechat\Models\ChatLearning::where('site_id', $siteId)->where('is_active', true)
                ->each(function ($l) use ($embeddings, $siteId) {
                    $text = trim(($l->question ?? '') . ' ' . ($l->answer ?? ''));
                    if ($text !== '') {
                        $embeddings->upsertFor($l, $text, $siteId);
                        $this->indexedOne();
                    }
                });

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
            $this->indexedOne();
        });
    }

    /**
     * Elke 25 items naar de taak, niet bij elk item: een shop heeft er
     * duizenden.
     */
    private function indexedOne(): void
    {
        $this->indexed++;

        if ($this->indexed % 25 === 0) {
            $this->task?->progress($this->indexed);
        }
    }
}
