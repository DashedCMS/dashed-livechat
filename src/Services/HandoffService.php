<?php

// src/Services/HandoffService.php

namespace Dashed\DashedLivechat\Services;

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedLivechat\Enums\AgentType;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedLivechat\Models\ChatAgent;
use Dashed\DashedLivechat\Enums\ConversationMode;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Mail\HandoffNotificationMail;

class HandoffService
{
    public function __construct(private OpeningHoursService $hours)
    {
    }

    public function requestHandoff(ChatConversation $c, ?string $reason = null): array
    {
        if (! $this->hours->isOpen($c->site_id)) {
            $behavior = \Dashed\DashedCore\Models\Customsetting::get('chat_out_of_hours_behavior', $c->site_id, 'ai_only');
            $next = $this->hours->nextOpening($c->site_id);

            return [
                'status' => 'closed',
                'behavior' => $behavior,
                'next_opening' => $next?->format('Y-m-d H:i'),
                'message' => 'We zijn nu niet bemand. Ik help je zelf graag verder'
                    . ($next ? '; een collega is er weer vanaf ' . $next->format('d-m H:i') . '.' : '.'),
            ];
        }

        $c->forceFill(['mode' => ConversationMode::WaitingHuman->value])->save();
        $c->events()->create(['type' => 'handoff_requested', 'payload' => ['reason' => $reason]]);
        $this->notifyAgents($c, $reason);

        return ['status' => 'requested', 'message' => 'Ik haal er een collega bij, een moment geduld.'];
    }

    public function takeOver(ChatConversation $c, ChatAgent $humanAgent): void
    {
        $c->forceFill(['mode' => ConversationMode::Human->value, 'assigned_agent_id' => $humanAgent->id])->save();
        $c->events()->create(['type' => 'handoff_taken', 'agent_id' => $humanAgent->id]);
    }

    public function release(ChatConversation $c): void
    {
        $c->events()->create(['type' => 'handoff_released', 'agent_id' => $c->assigned_agent_id]);
        $c->forceFill(['mode' => ConversationMode::Ai->value, 'assigned_agent_id' => null])->save();
    }

    public function humanAgentForUser(User $user, string $siteId): ChatAgent
    {
        return ChatAgent::firstOrCreate(
            ['site_id' => $siteId, 'type' => AgentType::Human->value, 'user_id' => $user->id],
            [
                'name' => $user->name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'Medewerker',
                'email' => $user->email,
                'is_active' => true,
            ],
        );
    }

    protected function notifyAgents(ChatConversation $c, ?string $reason): void
    {
        if (! Customsetting::get('chat_handoff_notifications', $c->site_id, true)) {
            return;
        }

        $emails = ChatAgent::where('site_id', $c->site_id)
            ->where('type', AgentType::Human->value)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->pluck('email')->filter()->unique()->all();

        foreach ($emails as $email) {
            Mail::to($email)->send(new HandoffNotificationMail($c, $reason));
        }
    }
}
