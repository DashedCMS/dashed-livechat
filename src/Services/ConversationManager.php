<?php

// src/Services/ConversationManager.php

namespace Dashed\DashedLivechat\Services;

use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedLivechat\Models\ChatAgent;
use Dashed\DashedLivechat\Enums\MessageRole;
use Dashed\DashedLivechat\Models\ChatMessage;
use Dashed\DashedLivechat\Models\ChatConversation;

class ConversationManager
{
    public function findOrCreate(string $siteId, ?string $publicToken, array $attributes = []): ChatConversation
    {
        if ($publicToken) {
            $existing = ChatConversation::where('site_id', $siteId)->where('public_token', $publicToken)->first();
            if ($existing) {
                return $existing;
            }
        }

        return ChatConversation::create(array_merge([
            'site_id' => $siteId,
            'public_token' => (string) Str::uuid(),
            'status' => 'active',
            'mode' => 'ai',
        ], $attributes));
    }

    public function addVisitorMessage(ChatConversation $c, string $content, array $attachments = []): ChatMessage
    {
        $message = $c->messages()->create([
            'role' => MessageRole::Visitor->value,
            'content' => $content,
            'attachments' => $this->storeAttachments($attachments) ?: null,
        ]);
        // Begint de bezoeker weer te chatten, dan is het gesprek weer actief/open.
        $c->forceFill(['last_message_at' => now(), 'status' => 'active'])->save();

        $this->notifyNewVisitorMessage($c, $content, $message->attachments ?? []);

        return $message;
    }

    /**
     * Push naar app-medewerkers bij elk nieuw bezoekersbericht — zowel in AI- als
     * mens-gesprekken. Per gebruiker te toggelen via het type 'chat.message'.
     *
     * @param  array<int, int>  $attachmentIds
     */
    private function notifyNewVisitorMessage(ChatConversation $c, string $content, array $attachmentIds = []): void
    {
        $center = '\Dashed\DashedMobileApi\Support\NotificationCenter';
        if (! class_exists($center)) {
            return;
        }

        try {
            $body = Str::limit(trim($content), 120);
            if ($body === '') {
                $body = ! empty($attachmentIds) ? '📷 Afbeelding' : 'Nieuw bericht in de chat';
            }
            app($center)->push()
                ->type('chat.message')
                ->site((string) $c->site_id)
                ->title($c->visitor_name ?: 'Nieuw chatbericht')
                ->body($body)
                ->route("/conversation/{$c->id}")
                ->data(['type' => 'conversation', 'id' => $c->id])
                ->send();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function addAiMessage(ChatConversation $c, ChatAgent $agent, string $content, array $toolCalls = [], ?int $tokensIn = null, ?int $tokensOut = null, array $attachments = []): ChatMessage
    {
        $message = $c->messages()->create([
            'role' => MessageRole::Ai->value,
            'agent_id' => $agent->id,
            'content' => $content,
            'tool_calls' => $toolCalls ?: null,
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'attachments' => $this->storeAttachments($attachments) ?: null,
        ]);
        $c->forceFill(['last_message_at' => now()])->save();

        return $message;
    }

    public function addHumanMessage(ChatConversation $c, ChatAgent $agent, string $content, array $attachments = []): ChatMessage
    {
        $message = $c->messages()->create([
            'role' => \Dashed\DashedLivechat\Enums\MessageRole::Human->value,
            'agent_id' => $agent->id,
            'content' => $content,
            'attachments' => $this->storeAttachments($attachments) ?: null,
        ]);
        $c->forceFill(['last_message_at' => now()])->save();

        return $message;
    }

    /**
     * Persist uploaded files into the chat media folder and return their media ids.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, int>
     */
    private function storeAttachments(array $files): array
    {
        $ids = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            // Move onto the 'dashed' disk so MediaHelper can addMediaFromDisk it.
            $path = $file->store('chat-tmp', 'dashed');
            if (! $path) {
                continue;
            }
            $id = mediaHelper()->uploadFromPath($path, 'chat');
            Storage::disk('dashed')->delete($path);
            if ($id) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }
}
