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
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ColorPicker;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedLivechat\Models\ChatTag;
use Dashed\DashedLivechat\Filament\Concerns\HiddenWhenChatDisabled;
use Dashed\DashedLivechat\Filament\Resources\ChatTagResource\Pages;

class ChatTagResource extends Resource
{
    use HiddenWhenChatDisabled;

    protected static ?string $model = ChatTag::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-tag';

    protected static string | UnitEnum | null $navigationGroup = 'Chat';

    protected static ?string $navigationLabel = 'Tags';

    protected static ?string $label = 'Tag';

    protected static ?string $pluralLabel = 'Tags';

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->label('Naam')
                ->required()
                ->maxLength(60)
                ->helperText('Bijv. Verkoop, Support of Klacht.'),

            ColorPicker::make('color')
                ->label('Kleur')
                ->default('#64748b'),

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
                TextColumn::make('name')
                    ->label('Naam')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('color')
                    ->label('Kleur'),
                TextColumn::make('conversations_count')
                    ->label('Gesprekken')
                    ->counts('conversations')
                    ->sortable(),
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
            'index' => Pages\ListChatTags::route('/'),
            'create' => Pages\CreateChatTag::route('/create'),
            'edit' => Pages\EditChatTag::route('/{record}/edit'),
        ];
    }
}
