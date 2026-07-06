<?php

// src/Services/ConversationManager.php

namespace Dashed\DashedLivechat\Services;

use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedLivechat\Models\ChatAgent;
use Dashed\DashedLivechat\Enums\MessageRole;
use Dashed\DashedLivechat\Models\ChatMessage;
use Dashed\DashedLivechat\Models\ChatLearning;
use Dashed\DashedLivechat\Models\ChatConversation;

class ConversationManager
{
    public function findOrCreate(string $siteId, ?string $publicToken, array $attributes = []): ChatConversation
    {
        if ($publicToken) {
            $query = ChatConversation::where('site_id', $siteId)->where('public_token', $publicToken);
            // Sandbox-conversaties (agent-testomgeving) zitten achter de
            // global scope; zonder deze hole zou elke turn een nieuwe, wees
            // sandbox-conversatie aanmaken in plaats van de bestaande te
            // hervinden, en verliest de AI zijn gespreksgeschiedenis.
            if (! empty($attributes['is_sandbox'])) {
                $query->withSandbox();
            }
            $existing = $query->first();
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

        // Sandbox-gesprekken (agent-testomgeving) mogen nooit een echte
        // push-notificatie naar medewerkers sturen.
        if (! $c->is_sandbox) {
            $this->notifyNewVisitorMessage($c, $content, $message->attachments ?? []);
        }

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

        // Sandbox-gesprekken mogen nooit een echte mail naar een bezoeker
        // sturen (er is geen echte bezoeker).
        if (! $c->is_sandbox) {
            $this->maybeEmailOfflineReply($c, $message);
        }

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

        $this->learnFromHumanReply($c, $message);
        $this->maybeEmailOfflineReply($c, $message);

        return $message;
    }

    /**
     * Stuurt een agent-/AI-antwoord als e-mail naar de bezoeker wanneer die niet
     * meer actief is (tab dicht/weg). Online bezoekers zien het antwoord live, dus
     * dan mailen we niet. Vereist een vastgelegd e-mailadres; interne berichten en
     * lege antwoorden worden overgeslagen. Queued, zodat het antwoord niet wacht.
     */
    private function maybeEmailOfflineReply(ChatConversation $c, ChatMessage $message): void
    {
        if ($message->is_internal) {
            return;
        }

        $email = trim((string) $c->visitor_email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        if (trim((string) $message->content) === '') {
            return;
        }

        // Online? Dan ziet de bezoeker het antwoord live → niet mailen.
        $window = (int) config('dashed-livechat.offline_reply_after_seconds', 30);
        $active = $c->visitor_last_active_at;
        if ($active && $active->gt(now()->subSeconds($window))) {
            return;
        }

        try {
            \Illuminate\Support\Facades\Mail::to($email)
                ->queue(new \Dashed\DashedLivechat\Mail\OfflineReplyMail($c, $message));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Triviale antwoorden die geen leerwaarde hebben. */
    private const LEARN_STOP_WORDS = ['momentje', 'moment', 'hoi', 'hallo', 'hey', 'ok', 'oke', 'oké', 'dank', 'dankje', 'bedankt', 'ja', 'nee', '1 sec', 'sec', 'even kijken'];

    /**
     * Leer van ELK inhoudelijk mens-antwoord: maak een actieve ChatLearning met
     * de voorafgaande bezoekersvraag → het mens-antwoord. Slaat triviale/interne
     * berichten over en dedupliceert op (vraag, antwoord).
     */
    private function learnFromHumanReply(ChatConversation $c, ChatMessage $message): void
    {
        if ($message->is_internal) {
            return;
        }
        $answer = trim((string) $message->content);
        $normalized = mb_strtolower($answer);
        if (mb_strlen($answer) < 15 || in_array($normalized, self::LEARN_STOP_WORDS, true)) {
            return;
        }

        $prior = $c->messages()
            ->where('id', '<', $message->id)
            ->where('is_internal', false)
            ->orderByDesc('id')
            ->get();
        $questionParts = [];
        foreach ($prior as $m) {
            if ($m->role === MessageRole::Visitor->value) {
                $questionParts[] = trim((string) $m->content);
            } else {
                break;
            }
        }
        if (empty($questionParts)) {
            return;
        }
        $question = trim(implode("\n", array_reverse($questionParts)));
        if ($question === '') {
            return;
        }

        $exists = ChatLearning::where('site_id', $c->site_id)
            ->where('question', $question)
            ->where('answer', $answer)
            ->exists();
        if ($exists) {
            return;
        }

        ChatLearning::create([
            'site_id' => $c->site_id,
            'question' => $question,
            'answer' => $answer,
            'source' => 'human',
            'source_message_id' => $message->id,
            'is_active' => true,
        ]);
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
                Log::warning('livechat: bijlage overgeslagen (geen UploadedFile)', ['type' => get_debug_type($file)]);

                continue;
            }
            // Move onto the 'dashed' disk so MediaHelper can addMediaFromDisk it.
            $path = $file->store('chat-tmp', 'dashed');
            if (! $path) {
                Log::warning('livechat: bijlage opslaan op disk mislukt', ['name' => $file->getClientOriginalName()]);

                continue;
            }
            $id = mediaHelper()->uploadFromPath($path, 'chat');
            Storage::disk('dashed')->delete($path);
            if ($id) {
                $ids[] = (int) $id;
            } else {
                Log::warning('livechat: media-upload gaf geen id terug', ['path' => $path]);
            }
        }

        return $ids;
    }
}
