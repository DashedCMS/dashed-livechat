<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\PersonalData;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Dashed\DashedCore\PersonalData\PersonalDataExport;
use Dashed\DashedCore\PersonalData\PersonalDataFinding;
use Dashed\DashedCore\PersonalData\PersonalDataSubject;
use Dashed\DashedCore\PersonalData\PersonalDataProvider;
use Dashed\DashedCore\Security\Uploads\PrivateUploads;

/**
 * Chatgesprekken van een bezoeker. Een gesprek kent geen user_id: het wordt
 * gevonden op het e-mailadres dat de bezoeker in de chat opgaf. Alles wat aan
 * zo'n gesprek hangt (berichten, bijlagen, notities, gebeurtenissen, tags,
 * onbeantwoorde vragen, de bezoekerssessie) gaat bij verwijderen mee.
 *
 * Bewust niet: medewerkers (dashed__chat_agents) en hun web-push-abonnementen
 * zijn beheerders, geen bezoekers. Geleerde antwoorden
 * (dashed__chat_learnings) zijn kennis van de winkel; alleen de verwijzing
 * naar het bericht van de bezoeker gaat eraf. Oude bijlagen die nog als
 * media-id in de mediabibliotheek staan worden niet weggehaald: die
 * bibliotheek is gedeeld en een id zegt niet van wie het bestand is.
 */
class LivechatPersonalDataProvider extends PersonalDataProvider
{
    public function key(): string
    {
        return 'dashed-livechat';
    }

    public function label(): string
    {
        return __('Livechat');
    }

    public function priority(): int
    {
        return 300;
    }

    public function find(PersonalDataSubject $subject): array
    {
        $ids = $this->conversationIds($subject);
        if ($ids === []) {
            return [];
        }

        $findings = [PersonalDataFinding::delete('conversations', __('Chatgesprekken'), count($ids))];

        foreach ($this->children($ids) as $key => $child) {
            $count = (clone $child['query'])->count();
            if ($count > 0) {
                $findings[] = PersonalDataFinding::delete($key, $child['label'], $count);
            }
        }

        $files = count($this->privateAttachments($ids));
        if ($files > 0) {
            $findings[] = PersonalDataFinding::delete('attachments', __('Bijlagen in de chat'), $files);
        }

        $learnings = $this->learningsQuery($ids)?->count() ?? 0;
        if ($learnings > 0) {
            $findings[] = PersonalDataFinding::unlink('learnings', __('Geleerde antwoorden'), $learnings, __('Kennis van de winkel; alleen de verwijzing naar het bericht van de bezoeker gaat eraf.'));
        }

        return $findings;
    }

    public function export(PersonalDataSubject $subject, PersonalDataExport $export): void
    {
        $ids = $this->conversationIds($subject);
        if ($ids === []) {
            return;
        }

        $export->add('conversations', $this->rows(DB::table('dashed__chat_conversations')->whereIn('id', $ids)->orderBy('id'), ['public_token', 'visitor_session_token', 'ip_hash']));

        foreach ($this->children($ids) as $key => $child) {
            if ($child['exportable'] ?? true) {
                $export->add($key, $this->rows(clone $child['query'], $child['without'] ?? []));
            }
        }

        foreach ($this->privateAttachments($ids) as $path) {
            $export->addFile('attachments', PrivateUploads::diskName(), $path);
        }
    }

    public function erase(PersonalDataSubject $subject): array
    {
        $ids = $this->conversationIds($subject);
        if ($ids === []) {
            return [];
        }

        $findings = [];

        // Eerst de bestanden: daarna weten we niet meer welke er waren.
        $files = 0;
        foreach ($this->privateAttachments($ids) as $path) {
            rescue(fn () => PrivateUploads::disk()->delete($path), null, false);
            $files++;
        }

        $learnings = $this->learningsQuery($ids)?->update(['source_message_id' => null]) ?? 0;

        // Sessietokens vóór het verwijderen van de gesprekken ophalen.
        $tokens = Schema::hasColumn('dashed__chat_conversations', 'visitor_session_token')
            ? DB::table('dashed__chat_conversations')->whereIn('id', $ids)->whereNotNull('visitor_session_token')->pluck('visitor_session_token')->all()
            : [];

        foreach ($this->children($ids) as $key => $child) {
            $count = (clone $child['query'])->delete();
            if ($count > 0) {
                $findings[] = PersonalDataFinding::delete($key, $child['label'], $count);
            }
        }

        $sessions = $tokens !== [] && $this->hasTable('dashed__chat_visitor_sessions')
            ? DB::table('dashed__chat_visitor_sessions')->whereIn('token', $tokens)->delete()
            : 0;

        $conversations = DB::table('dashed__chat_conversations')->whereIn('id', $ids)->delete();

        array_unshift($findings, PersonalDataFinding::delete('conversations', __('Chatgesprekken'), $conversations));
        if ($files > 0) {
            $findings[] = PersonalDataFinding::delete('attachments', __('Bijlagen in de chat'), $files);
        }
        if ($sessions > 0) {
            $findings[] = PersonalDataFinding::delete('visitor_sessions', __('Bezoekerssessies'), $sessions);
        }
        if ($learnings > 0) {
            $findings[] = PersonalDataFinding::unlink('learnings', __('Geleerde antwoorden'), $learnings, __('Kennis van de winkel; alleen de verwijzing naar het bericht van de bezoeker gaat eraf.'));
        }

        return $findings;
    }

    /** @return array<int, int> */
    protected function conversationIds(PersonalDataSubject $subject): array
    {
        if (! $this->hasTable('dashed__chat_conversations')) {
            return [];
        }

        return $subject->whereEmail(DB::table('dashed__chat_conversations'), 'visitor_email')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<string, array{label: string, query: \Illuminate\Database\Query\Builder, without?: array<int, string>, exportable?: bool}>
     */
    protected function children(array $ids): array
    {
        $children = [];
        $tables = [
            'messages' => ['dashed__chat_messages', __('Chatberichten'), ['tokens_in', 'tokens_out', 'tool_calls'], true],
            'notes' => ['dashed__chat_notes', __('Notities bij het gesprek'), [], true],
            'events' => ['dashed__chat_events', __('Gebeurtenissen in het gesprek'), [], true],
            'unanswered_questions' => ['dashed__chat_unanswered_questions', __('Onbeantwoorde vragen'), [], true],
            'tags' => ['dashed__chat_conversation_tag', __('Labels van het gesprek'), [], false],
        ];

        foreach ($tables as $key => [$table, $label, $without, $exportable]) {
            if ($this->hasTable($table)) {
                $children[$key] = [
                    'label' => $label,
                    'query' => DB::table($table)->whereIn('chat_conversation_id', $ids),
                    'without' => $without,
                    'exportable' => $exportable,
                ];
            }
        }

        return $children;
    }

    /**
     * Bijlagen in de privé opslag. Oude media-id's vallen erbuiten.
     *
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    protected function privateAttachments(array $ids): array
    {
        if (! $this->hasTable('dashed__chat_messages') || ! Schema::hasColumn('dashed__chat_messages', 'attachments') || ! class_exists(PrivateUploads::class)) {
            return [];
        }

        return DB::table('dashed__chat_messages')
            ->whereIn('chat_conversation_id', $ids)
            ->whereNotNull('attachments')
            ->pluck('attachments')
            ->flatMap(fn ($json) => (array) json_decode((string) $json, true))
            ->filter(fn ($reference) => is_string($reference) && PrivateUploads::isPrivate($reference))
            ->unique()
            ->values()
            ->all();
    }

    /** @param  array<int, int>  $ids */
    protected function learningsQuery(array $ids): ?\Illuminate\Database\Query\Builder
    {
        if (! $this->hasTable('dashed__chat_learnings') || ! $this->hasTable('dashed__chat_messages')) {
            return null;
        }

        return DB::table('dashed__chat_learnings')->whereIn(
            'source_message_id',
            DB::table('dashed__chat_messages')->whereIn('chat_conversation_id', $ids)->select('id')
        );
    }
}
