<?php

namespace Dashed\DashedLivechat\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\BulkAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Filament\Concerns\HiddenWhenChatDisabled;
use Dashed\DashedLivechat\Filament\Resources\ChatConversationResource\Pages;

class ChatConversationResource extends Resource
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesResourceLabels;
    use HiddenWhenChatDisabled;

    protected static ?string $model = ChatConversation::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string | UnitEnum | null $navigationGroup = 'Chat';

    protected static ?string $navigationLabel = 'Gesprekken';

    protected static ?string $label = 'Gesprek';

    protected static ?string $pluralLabel = 'Gesprekken';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ChatConversation::query()->where('status', '!=', 'closed')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('visitor_name')
                    ->label(__('Bezoeker'))
                    ->state(fn ($record) => $record->visitor_name ?: ($record->visitor_email ?: __('Anoniem')))
                    ->weight(fn ($record) => $record->awaiting === 'agent' && $record->status !== 'closed' ? 'bold' : null)
                    ->description(fn ($record) => static::messagePreview($record))
                    ->icon('heroicon-m-signal')
                    ->iconColor(fn ($record) => ['active' => 'success', 'idle' => 'warning'][$record->visitorPresence()] ?? 'gray')
                    ->tooltip(fn ($record) => ['active' => __('Bezoeker is nu actief'), 'idle' => __('Bezoeker is nog op de site')][$record->visitorPresence()] ?? __('Bezoeker is weg'))
                    ->searchable(['visitor_name', 'visitor_email'])
                    ->wrap(),
                TextColumn::make('last_message_at')
                    ->label(__('Laatste bericht'))
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('awaiting')
                    ->label(__('Aan zet'))
                    ->badge()
                    ->state(fn ($record): string => $record->status === 'closed' ? 'closed' : $record->awaiting)
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'agent' => __('Jij'),
                        'closed' => __('Afgerond'),
                        default => __('Bezoeker'),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'agent' => 'danger',
                        'closed' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('mode')
                    ->label(__('Modus'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ai' => __('AI'),
                        'waiting_human' => __('Wacht op medewerker'),
                        'human' => __('Medewerker'),
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'waiting_human' => 'warning',
                        'human' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('tags.name')
                    ->label(__('Tags'))
                    ->badge()
                    ->separator(','),
                SelectColumn::make('status')
                    ->label(__('Status'))
                    ->options([
                        'active' => __('Actief'),
                        'inactive' => __('Inactief'),
                        'closed' => __('Afgerond'),
                    ])
                    ->selectablePlaceholder(false)
                    ->toggleable(),
                TextColumn::make('rating')
                    ->label(__('Beoordeling'))
                    ->tooltip(fn ($record) => $record->rating_comment ?: null)
                    ->state(fn ($record) => match (true) {
                        $record->rating === null => '—',
                        $record->rating >= 4 => '👍 ' . $record->rating,
                        $record->rating <= 2 => '👎 ' . $record->rating,
                        default => '★ ' . $record->rating,
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('messages_count')
                    ->counts('messages')
                    ->label(__('Berichten'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->poll('15s')
            ->recordActions([
                // Status wisselen gebeurt nu inline via de Status-keuzelijst hierboven.
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markClosed')
                        ->label(__('Afronden'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records): void {
                            $records->each(fn ($record) => $record->markClosed());

                            Notification::make()->success()->title(__('Geselecteerde gesprekken afgerond'))->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->filters([
                SelectFilter::make('mode')
                    ->label(__('Modus'))
                    ->options([
                        'ai' => __('AI'),
                        'waiting_human' => __('Wacht op medewerker'),
                        'human' => __('Medewerker'),
                    ]),
                SelectFilter::make('rating')
                    ->label(__('Beoordeling'))
                    ->options(['rated' => __('Beoordeeld'), 'positive' => __('Positief (≥4)'), 'negative' => __('Negatief (≤2)')])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'rated' => $query->whereNotNull('rating'),
                        'positive' => $query->where('rating', '>=', 4),
                        'negative' => $query->where('rating', '<=', 2)->whereNotNull('rating'),
                        default => $query,
                    }),
                SelectFilter::make('tag')
                    ->label(__('Tag'))
                    ->options(fn () => \Dashed\DashedLivechat\Models\ChatTag::query()
                        ->where('site_id', (string) \Dashed\DashedCore\Classes\Sites::getActive())
                        ->orderBy('sort')->orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('tags', fn ($q) => $q->where('dashed__chat_tags.id', $data['value']))
                        : $query),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChatConversations::route('/'),
            'view' => Pages\ViewChatConversation::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // visitorSession en latestMessage eager-loaden zodat presence en preview geen N+1 doen.
        return parent::getEloquentQuery()->with(['visitorSession', 'latestMessage']);
    }

    /** Eerste regel van het laatste bericht, met wie het stuurde ervoor. */
    public static function messagePreview(ChatConversation $record): ?string
    {
        $message = $record->latestMessage;

        if (! $message) {
            return null;
        }

        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $message->content)));

        if ($text === '' && filled($message->attachments)) {
            $text = __('Bijlage');
        }

        $prefix = match ($message->role) {
            'ai' => __('AI') . ': ',
            'human' => __('Medewerker') . ': ',
            default => '',
        };

        return $prefix . \Illuminate\Support\Str::limit($text, 90);
    }
}
