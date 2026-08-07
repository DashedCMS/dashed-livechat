<?php

namespace Dashed\DashedLivechat\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Dashed\DashedLivechat\Models\ChatLearning;
use Dashed\DashedLivechat\Filament\Concerns\HiddenWhenChatDisabled;
use Dashed\DashedLivechat\Filament\Resources\ChatLearningResource\Pages;

class ChatLearningResource extends Resource
{
    use HiddenWhenChatDisabled;

    protected static ?string $model = ChatLearning::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string | UnitEnum | null $navigationGroup = 'Chat';

    protected static ?string $navigationLabel = 'Geleerd';

    protected static ?string $label = 'Geleerde correctie';

    protected static ?string $pluralLabel = 'Geleerde correcties';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Textarea::make('question')
                ->label(__('Vraag / aanleiding'))
                ->rows(3)
                ->columnSpanFull()
                ->helperText(__('De vraag of aanleiding voor dit leervoorbeeld.')),

            Textarea::make('answer')
                ->label(__('Gewenst antwoord'))
                ->rows(5)
                ->columnSpanFull()
                ->required()
                ->helperText(__('Het antwoord dat de bot voortaan moet geven.')),

            Select::make('source')
                ->label(__('Bron'))
                ->options([
                    'feedback' => __('Feedback (slecht AI-antwoord)'),
                    'human' => __('Mens (menselijk antwoord)'),
                    'ai' => __('AI (goed AI-antwoord)'),
                ])
                ->default('feedback')
                ->required(),

            Toggle::make('is_active')
                ->label(__('Actief'))
                ->default(true)
                ->helperText(__('Alleen actieve voorbeelden worden aan de bot meegegeven.')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')
                    ->label(__('Vraag'))
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('answer')
                    ->label(__('Antwoord'))
                    ->limit(80)
                    ->searchable(),
                TextColumn::make('source')
                    ->label(__('Bron'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'feedback' => 'danger',
                        'human' => 'info',
                        'ai' => 'success',
                        default => 'gray',
                    }),
                IconColumn::make('is_active')
                    ->label(__('Actief'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('Aangemaakt'))
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChatLearnings::route('/'),
            'create' => Pages\CreateChatLearning::route('/create'),
            'edit' => Pages\EditChatLearning::route('/{record}/edit'),
        ];
    }
}
