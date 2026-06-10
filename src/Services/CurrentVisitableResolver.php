<?php

namespace Dashed\DashedLivechat\Services;

use Illuminate\Database\Eloquent\Model;

class CurrentVisitableResolver
{
    public function resolve(): ?Model
    {
        $model = app('view')->getShared()['model'] ?? null;

        return $model instanceof Model ? $model : null;
    }
}
