<?php

namespace Dashed\DashedLivechat\Commands;

use Minishlink\WebPush\VAPID;
use Illuminate\Console\Command;

class GenerateWebPushKeysCommand extends Command
{
    protected $signature = 'chat:generate-web-push-keys';

    protected $description = 'Genereer een VAPID-sleutelpaar voor livechat bureaubladmeldingen (Web Push).';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->info('Zet deze twee regels in je .env en herstart de app:');
        $this->newLine();
        $this->line('LIVECHAT_WEBPUSH_PUBLIC_KEY=' . $keys['publicKey']);
        $this->line('LIVECHAT_WEBPUSH_PRIVATE_KEY=' . $keys['privateKey']);

        return self::SUCCESS;
    }
}
