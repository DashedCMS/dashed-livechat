<?php

namespace Dashed\DashedLivechat\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Dashed\DashedCore\Classes\Sites;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedLivechat\Models\ChatLearning;
use Dashed\DashedLivechat\Models\ChatUnansweredQuestion;
use Dashed\DashedLivechat\Filament\Concerns\HiddenWhenChatDisabled;
use Dashed\DashedLivechat\Filament\Resources\ChatUnansweredQuestionResource\Pages;

class ChatUnansweredQuestionResource extends Resource
{
    use HiddenWhenChatDisabled;

    protected static ?string $model = ChatUnansweredQuestion::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static string | UnitEnum | null $navigationGroup = 'Chat';

    protected static ?string $navigationLabel = 'Onbeantwoord';

    protected static ?string $label = 'Onbeantwoorde vraag';

    protected static ?string $pluralLabel = 'Onbeantwoorde vragen';

    protected static ?int $navigationSort = 8;

    private const REASONS = [
        'handoff' => 'Overgedragen',
        'negative_feedback' => 'Negatieve feedback',
        'no_match' => 'Geen match',
    ];

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')
                    ->label('Vraag')
                    ->wrap()
                    ->limit(120)
                    ->searchable(),
                TextColumn::make('reason')
                    ->label('Reden')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => self::REASONS[$state] ?? $state)
                    ->color(fn (?string $state) => $state === 'negative_feedback' ? 'danger' : 'warning'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state === 'resolved' ? 'Opgelost' : 'Open')
                    ->color(fn (?string $state) => $state === 'resolved' ? 'success' : 'gray'),
                TextColumn::make('created_at')
                    ->label('Wanneer')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(['open' => 'Open', 'resolved' => 'Opgelost'])
                    ->default('open'),
                SelectFilter::make('reason')
                    ->label('Reden')
                    ->options(self::REASONS),
            ])
            ->recordActions([
                Action::make('learn')
                    ->label('Maak leerpunt')
                    ->icon('heroicon-m-academic-cap')
                    ->color('primary')
                    ->visible(fn (ChatUnansweredQuestion $record) => $record->status !== 'resolved')
                    ->requiresConfirmation()
                    ->modalDescription('Maakt een concept-leerpunt aan met deze vraag. Vul het antwoord aan en activeer het bij Leerpunten.')
                    ->action(function (ChatUnansweredQuestion $record): void {
                        ChatLearning::create([
                            'site_id' => $record->site_id,
                            'question' => $record->question,
                            'answer' => '',
                            'source' => 'human',
                            'is_active' => false,
                        ]);
                        $record->update(['status' => 'resolved']);

                        Notification::make()
                            ->success()
                            ->title('Concept-leerpunt aangemaakt')
                            ->body('Vul het antwoord aan en activeer het bij Leerpunten.')
                            ->send();
                    }),
                Action::make('resolve')
                    ->label('Opgelost')
                    ->icon('heroicon-m-check')
                    ->color('gray')
                    ->visible(fn (ChatUnansweredQuestion $record) => $record->status !== 'resolved')
                    ->action(fn (ChatUnansweredQuestion $record) => $record->update(['status' => 'resolved'])),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('site_id', (string) Sites::getActive());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChatUnansweredQuestions::route('/'),
        ];
    }
}
