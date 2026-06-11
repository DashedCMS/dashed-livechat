<?php

// src/Services/TriggerMatcher.php

namespace Dashed\DashedLivechat\Services;

use Dashed\DashedLivechat\Models\ChatTrigger;

class TriggerMatcher
{
    public function matches(string $siteId, string $path): bool
    {
        // Site speelt geen rol bij triggers; alle actieve triggers tellen mee.
        $triggers = ChatTrigger::where('is_active', true)->get();

        // Geen triggers ingesteld -> standaard overal tonen.
        if ($triggers->isEmpty()) {
            return true;
        }

        return $this->matchingTrigger($siteId, $path) !== null;
    }

    public function matchingTrigger(string $siteId, string $path): ?ChatTrigger
    {
        $path = '/' . ltrim($path, '/');
        $triggers = ChatTrigger::where('is_active', true)->orderByDesc('sort_order')->get();

        $lineage = [];
        if ($triggers->contains(fn ($t) => $t->placement === 'models')) {
            $model = app(CurrentVisitableResolver::class)->resolve();
            if ($model) {
                $lineage = app(VisitableLineage::class)->for($model);
            }
        }

        foreach ($triggers as $trigger) {
            // Check exclude_urls first — if path is excluded, skip this trigger.
            $excluded = false;
            foreach (($trigger->exclude_urls ?? []) as $exclude) {
                if (str_starts_with($path, '/' . ltrim($exclude, '/'))) {
                    $excluded = true;

                    break;
                }
            }
            if ($excluded) {
                continue;
            }

            if ($trigger->placement === 'all_pages') {
                return $trigger;
            }
            if ($trigger->placement === 'include_urls') {
                foreach (($trigger->url_rules ?? []) as $rule) {
                    if (str_starts_with($path, '/' . ltrim($rule, '/'))) {
                        return $trigger;
                    }
                }
            }
            if ($trigger->placement === 'url_pattern') {
                foreach (($trigger->url_rules ?? []) as $rule) {
                    if (@preg_match('#' . $rule . '#', $path)) {
                        return $trigger;
                    }
                }
            }
            if ($trigger->placement === 'models') {
                foreach (($trigger->model_links ?? []) as $link) {
                    $type = $link['type'] ?? null;
                    $id = (int) ($link['id'] ?? 0);
                    foreach ($lineage as $entry) {
                        if ($entry['type'] === $type && $entry['id'] === $id) {
                            return $trigger;
                        }
                    }
                }
            }
        }

        return null;
    }
}
