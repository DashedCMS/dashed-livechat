<?php

namespace Dashed\DashedLivechat\Commands;

use Illuminate\Console\Command;
use Dashed\DashedLivechat\Jobs\PrivatizeChatAttachments;

class PrivatizeChatAttachmentsCommand extends Command
{
    protected $signature = 'dashed-livechat:privatize-attachments {--now : Direct uitvoeren in plaats van via de queue}';

    protected $description = 'Verhuist oude chatbijlagen uit de publieke mediabibliotheek naar de privé opslag';

    public function handle(): int
    {
        $this->option('now') ? PrivatizeChatAttachments::dispatchSync() : PrivatizeChatAttachments::dispatch();
        $this->info($this->option('now') ? 'Klaar; zie de log voor de aantallen.' : 'In de queue gezet.');

        return self::SUCCESS;
    }
}
