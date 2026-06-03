<?php

// src/Ai/ToolRegistry.php

namespace Dashed\DashedLivechat\Ai;

use Dashed\DashedLivechat\Models\ChatAgent;
use Dashed\DashedLivechat\Ai\Tools\GetPageTool;
use Dashed\DashedLivechat\Ai\Contracts\ChatTool;
use Dashed\DashedLivechat\Ai\Tools\SearchFaqTool;
use Dashed\DashedLivechat\Ai\Tools\GetProductTool;
use Dashed\DashedLivechat\Ai\Tools\SearchContentTool;
use Dashed\DashedLivechat\Ai\Tools\GetOrderStatusTool;
use Dashed\DashedLivechat\Ai\Tools\SearchProductsTool;
use Dashed\DashedLivechat\Ai\Tools\GetOpeningHoursTool;
use Dashed\DashedLivechat\Ai\Tools\RequestHumanHandoffTool;

class ToolRegistry
{
    /** @return array<string, class-string<ChatTool>> */
    protected function map(): array
    {
        return [
            'searchProducts' => SearchProductsTool::class,
            'searchContent' => SearchContentTool::class,
            'getProduct' => GetProductTool::class,
            'getPage' => GetPageTool::class,
            'searchFaq' => SearchFaqTool::class,
            'getOrderStatus' => GetOrderStatusTool::class,
            'getOpeningHours' => GetOpeningHoursTool::class,
            'requestHumanHandoff' => RequestHumanHandoffTool::class,
        ];
    }

    /** @return ChatTool[] */
    public function forAgent(ChatAgent $agent): array
    {
        $enabled = is_array($agent->enabled_tools) ? $agent->enabled_tools : array_keys($this->map());

        return collect($this->map())
            ->only($enabled)
            ->map(fn (string $class) => app($class))
            ->values()
            ->all();
    }

    public function get(string $name): ?ChatTool
    {
        $class = $this->map()[$name] ?? null;

        return $class ? app($class) : null;
    }

    public function toolNames(): array
    {
        return array_keys($this->map());
    }

    /** @param ChatTool[] $tools */
    public function anthropicSchema(array $tools): array
    {
        return collect($tools)->map(fn (ChatTool $t) => [
            'name' => $t->name(),
            'description' => $t->description(),
            'input_schema' => $t->inputSchema(),
        ])->all();
    }
}
