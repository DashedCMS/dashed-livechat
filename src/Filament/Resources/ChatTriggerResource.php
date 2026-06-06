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
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Dashed\DashedLivechat\Models\ChatAgent;
use Dashed\DashedLivechat\Models\ChatTrigger;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedLivechat\Filament\Resources\ChatTriggerResource\Pages;

class ChatTriggerResource extends Resource
{
    protected static ?string $model = ChatTrigger::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-bolt';

    protected static string | UnitEnum | null $navigationGroup = 'Chat';

    protected static ?string $navigationLabel = 'Triggers';

    protected static ?string $label = 'Trigger';

    protected static ?string $pluralLabel = 'Triggers';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Algemeen')->columnSpanFull()
                ->schema([
                    TextInput::make('site_id')
                        ->label('Site')
                        ->required(),
                    TextInput::make('name')
                        ->label('Naam')
                        ->required(),
                    Toggle::make('is_active')
                        ->label('Actief')
                        ->default(true),
                    TextInput::make('sort_order')
                        ->label('Volgorde')
                        ->numeric()
                        ->default(0),
                ])
                ->columns(2),

            Section::make('Plaatsing')->columnSpanFull()
                ->schema([
                    Select::make('placement')
                        ->label('Plaatsing')
                        ->options([
                            'all_pages' => 'Alle pagina\'s',
                            'include_urls' => 'Specifieke URL\'s',
                            'url_pattern' => 'URL-patroon',
                        ])
                        ->default('all_pages')
                        ->live()
                        ->required(),
                    TagsInput::make('url_rules')
                        ->label('URL-regels')
                        ->helperText('Voer de URL\'s of patronen in die van toepassing zijn.')
                        ->visible(fn (Get $get) => $get('placement') !== 'all_pages'),
                    TagsInput::make('exclude_urls')
                        ->label('Uitgesloten URL\'s')
                        ->helperText('URL\'s waarop deze trigger niet actief is.'),
                ])
                ->columns(2),

            Section::make('Trigger')->columnSpanFull()
                ->schema([
                    Select::make('trigger_type')
                        ->label('Trigger-type')
                        ->options([
                            'none' => 'Geen',
                            'immediate' => 'Direct',
                            'time_on_page' => 'Tijd op pagina',
                            'scroll_depth' => 'Scroll-diepte',
                            'exit_intent' => 'Vertrekintentie',
                        ])
                        ->default('none')
                        ->live()
                        ->required(),
                    TextInput::make('trigger_value')
                        ->label('Triggerwaarde')
                        ->numeric()
                        ->suffix(fn (Get $get) => match ($get('trigger_type')) {
                            'time_on_page' => 'sec',
                            'scroll_depth' => '%',
                            default => null,
                        })
                        ->visible(fn (Get $get) => in_array($get('trigger_type'), ['time_on_page', 'scroll_depth'])),
                    Textarea::make('proactive_message')
                        ->label('Proactief bericht')
                        ->rows(3)
                        ->columnSpanFull(),
                    Select::make('ai_agent_id')
                        ->label('AI-medewerker')
                        ->options(ChatAgent::where('type', 'ai')->pluck('name', 'id')->toArray())
                        ->nullable()
                        ->searchable(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('placement')
                    ->label('Plaatsing')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'all_pages' => 'Alle pagina\'s',
                        'include_urls' => 'Specifieke URL\'s',
                        'url_pattern' => 'URL-patroon',
                        default => $state,
                    }),
                TextColumn::make('trigger_type')
                    ->label('Trigger-type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'none' => 'Geen',
                        'immediate' => 'Direct',
                        'time_on_page' => 'Tijd op pagina',
                        'scroll_depth' => 'Scroll-diepte',
                        'exit_intent' => 'Vertrekintentie',
                        default => $state,
                    }),
                IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChatTriggers::route('/'),
            'create' => Pages\CreateChatTrigger::route('/create'),
            'edit' => Pages\EditChatTrigger::route('/{record}/edit'),
        ];
    }
}
