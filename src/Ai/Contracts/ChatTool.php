<?php

namespace Dashed\DashedLivechat\Ai\Contracts;

use Dashed\DashedLivechat\Models\ChatConversation;

interface ChatTool
{
    public function name(): string;

    public function description(): string;

    public function inputSchema(): array;

    public function handle(array $input, ChatConversation $conversation): array;
}
