<?php

// src/Models/Scopes/ExcludeSandboxScope.php

namespace Dashed\DashedLivechat\Models\Scopes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sluit sandbox-conversaties (ChatAgentPlayground-testgesprekken) standaard
 * uit van elke "gewone" query op ChatConversation. Zo kunnen sandbox-rijen
 * niet per ongeluk lekken naar echte medewerker-/app-views, notificaties of
 * mails — ze zijn er simpelweg niet, tenzij je expliciet
 * `ChatConversation::withSandbox()` gebruikt.
 *
 * Elk bestaand/echt gesprek heeft `is_sandbox = false` (kolom-default), dus
 * deze scope is een no-op voor alle echte data.
 */
class ExcludeSandboxScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->getTable() . '.is_sandbox', false);
    }
}
