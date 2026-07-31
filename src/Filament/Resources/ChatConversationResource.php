<?php

namespace Dashed\DashedLivechat\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\BulkAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Tables\Filters\Filter;
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
                    ->label('Bezoeker')
                    ->state(fn ($record) => $record->visitor_name ?: ($record->visitor_email ?: 'Anoniem'))
                    ->searchable(['visitor_name', 'visitor_email']),
                TextColumn::make('visitor_presence')
                    ->label('Bezoeker online')
                    ->badge()
                    ->state(fn ($record) => ['active' => 'Actief', 'idle' => 'Niet actief', 'away' => 'Weg'][$record->visitorPresence()] ?? 'Weg')
                    ->color(fn ($record) => ['active' => 'success', 'idle' => 'warning', 'away' => 'gray'][$record->visitorPresence()] ?? 'gray')
                    ->icon(fn ($record) => $record->visitorPresence() === 'active' ? 'heroicon-m-signal' : ($record->visitorPresence() === 'idle' ? 'heroicon-m-signal-slash' : 'heroicon-m-no-symbol')),
                TextColumn::make('mode')
                    ->label('Modus')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ai' => 'AI',
                        'waiting_human' => 'Wacht op medewerker',
                        'human' => 'Medewerker',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'waiting_human' => 'warning',
                        'human' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('awaiting')
                    ->label('Beurt')
                    ->badge()
                    ->state(fn ($record): string => $record->awaiting)
                    ->formatStateUsing(fn (string $state): string => $state === 'agent' ? 'Jij' : 'Wachten op bezoeker')
                    ->color(fn (string $state): string => $state === 'agent' ? 'danger' : 'gray'),
                SelectColumn::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Actief',
                        'closed' => 'Afgerond',
                    ])
                    ->selectablePlaceholder(false),
                TextColumn::make('rating')
                    ->label('Beoordeling')
                    ->tooltip(fn ($record) => $record->rating_comment ?: null)
                    ->state(fn ($record) => match (true) {
                        $record->rating === null => '—',
                        $record->rating >= 4 => '👍 ' . $record->rating,
                        $record->rating <= 2 => '👎 ' . $record->rating,
                        default => '★ ' . $record->rating,
                    }),
                TextColumn::make('tags.name')
                    ->label('Tags')
                    ->badge()
                    ->separator(','),
                TextColumn::make('messages_count')
                    ->counts('messages')
                    ->label('Berichten'),
                TextColumn::make('last_message_at')
                    ->label('Laatste bericht')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw("CASE WHEN last_message_role = 'visitor' THEN 0 ELSE 1 END")
                ->orderByDesc('last_message_at'))
            ->recordActions([
                // Status wisselen gebeurt nu inline via de Status-keuzelijst hierboven.
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markClosed')
                        ->label('Afronden')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records): void {
                            $records->each(fn ($record) => $record->markClosed());

                            Notification::make()->success()->title('Geselecteerde gesprekken afgerond')->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->filters([
                Filter::make('awaiting_agent')
                    ->label('Wacht op mijn antwoord')
                    ->query(fn (Builder $query): Builder => $query->awaitingAgent()->where('status', '!=', 'closed')),
                SelectFilter::make('mode')
                    ->label('Modus')
                    ->options([
                        'ai' => 'AI',
                        'waiting_human' => 'Wacht op medewerker',
                        'human' => 'Medewerker',
                    ]),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Actief',
                        'inactive' => 'Inactief',
                        'closed' => 'Afgerond',
                    ]),
                SelectFilter::make('rating')
                    ->label('Beoordeling')
                    ->options(['rated' => 'Beoordeeld', 'positive' => 'Positief (≥4)', 'negative' => 'Negatief (≤2)'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'rated' => $query->whereNotNull('rating'),
                        'positive' => $query->where('rating', '>=', 4),
                        'negative' => $query->where('rating', '<=', 2)->whereNotNull('rating'),
                        default => $query,
                    }),
                SelectFilter::make('tag')
                    ->label('Tag')
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
        // visitorSession eager-loaden zodat de presence-badge geen N+1 doet.
        return parent::getEloquentQuery()->with('visitorSession');
    }
}
