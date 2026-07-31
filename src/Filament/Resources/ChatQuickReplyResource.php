<?php

namespace Dashed\DashedLivechat\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Dashed\DashedCore\Classes\Sites;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedLivechat\Models\ChatQuickReply;
use Dashed\DashedLivechat\Filament\Concerns\HiddenWhenChatDisabled;
use Dashed\DashedLivechat\Filament\Resources\ChatQuickReplyResource\Pages;

class ChatQuickReplyResource extends Resource
{
    use HiddenWhenChatDisabled;

    protected static ?string $model = ChatQuickReply::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    protected static string | UnitEnum | null $navigationGroup = 'Chat';

    protected static ?string $navigationLabel = 'Snelle antwoorden';

    protected static ?string $label = 'Snel antwoord';

    protected static ?string $pluralLabel = 'Snelle antwoorden';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('title')
                ->label('Titel')
                ->required()
                ->columnSpanFull()
                ->helperText('Korte herkenbare titel voor dit antwoord.'),

            Textarea::make('content')
                ->label('Antwoord')
                ->rows(5)
                ->required()
                ->columnSpanFull()
                ->helperText('De tekst die wordt ingevoegd wanneer dit antwoord wordt gekozen. Ondersteunt variabelen: {naam}, {shop}, {agent}, {email}.'),

            TextInput::make('shortcut')
                ->label('Shortcut')
                ->nullable()
                ->prefix('/')
                ->helperText('Typ /shortcut in de reply-box om dit antwoord direct in te voegen.'),

            Select::make('owner_id')
                ->label('Zichtbaarheid')
                ->options(fn () => [
                    '' => 'Gedeeld (team)',
                    (string) auth()->id() => 'Alleen ik',
                ])
                ->default('')
                ->dehydrateStateUsing(fn ($state) => $state === '' || $state === null ? null : (int) $state)
                ->formatStateUsing(fn ($state) => $state === null ? '' : (string) $state)
                ->helperText('Gedeelde antwoorden zijn zichtbaar voor alle medewerkers van deze site.'),

            TextInput::make('sort')
                ->label('Volgorde')
                ->numeric()
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titel')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('shortcut')
                    ->label('Shortcut')
                    ->formatStateUsing(fn (?string $state) => $state ? '/'.$state : '—')
                    ->searchable(),
                TextColumn::make('content')
                    ->label('Antwoord')
                    ->limit(80)
                    ->searchable(),
                TextColumn::make('owner_id')
                    ->label('Zichtbaarheid')
                    ->badge()
                    ->formatStateUsing(fn (?int $state) => $state === null ? 'Gedeeld' : 'Persoonlijk')
                    ->color(fn (?int $state) => $state === null ? 'info' : 'gray'),
                TextColumn::make('sort')
                    ->label('Volgorde')
                    ->sortable(),
            ])
            ->defaultSort('sort')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->visibleTo(auth()->id(), (string) Sites::getActive());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChatQuickReplies::route('/'),
            'create' => Pages\CreateChatQuickReply::route('/create'),
            'edit' => Pages\EditChatQuickReply::route('/{record}/edit'),
        ];
    }
}
