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
use Filament\Forms\Components\Textarea;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedLivechat\Models\ChatQuickReply;
use Dashed\DashedLivechat\Filament\Resources\ChatQuickReplyResource\Pages;

class ChatQuickReplyResource extends Resource
{
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
                ->helperText('De tekst die wordt ingevoegd wanneer dit antwoord wordt gekozen.'),

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
                TextColumn::make('content')
                    ->label('Antwoord')
                    ->limit(80)
                    ->searchable(),
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
            ->where('site_id', (string) Sites::getActive());
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
