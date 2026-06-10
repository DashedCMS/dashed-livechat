<?php

namespace Dashed\DashedLivechat\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Dashed\DashedLivechat\Models\ChatAgent;
use Dashed\DashedLivechat\Models\ChatTrigger;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Schema as DbSchema;
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
                            'models' => 'Specifieke modellen',
                        ])
                        ->default('all_pages')
                        ->live()
                        ->required(),
                    TagsInput::make('url_rules')
                        ->label('URL-regels')
                        ->helperText('Voer de URL\'s of patronen in die van toepassing zijn.')
                        ->visible(fn (Get $get) => in_array($get('placement'), ['include_urls', 'url_pattern'], true)),
                    Repeater::make('model_links')
                        ->label('Gekoppelde modellen')
                        ->helperText('Selecteer de specifieke modellen waarop deze trigger actief is.')
                        ->schema([
                            Select::make('type')
                                ->label('Type')
                                ->options(self::routeModelOptions())
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (callable $set) => $set('id', null)),
                            Select::make('id')
                                ->label('Model')
                                ->searchable()
                                ->required()
                                ->getSearchResultsUsing(function (string $search, callable $get) {
                                    $class = $get('type');
                                    if (! $class || ! class_exists($class)) {
                                        return [];
                                    }
                                    $model = new $class();

                                    return $class::query()
                                        ->where(function ($q) use ($search, $model) {
                                            foreach (['name', 'title'] as $col) {
                                                if (DbSchema::hasColumn($model->getTable(), $col)) {
                                                    $q->orWhere($col, 'like', "%{$search}%");
                                                }
                                            }
                                        })
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn ($m) => [$m->getKey() => $m->name ?? $m->title ?? "#{$m->getKey()}"])
                                        ->toArray();
                                })
                                ->getOptionLabelUsing(function ($value, callable $get) {
                                    $class = $get('type');
                                    if (! $value || ! $class || ! class_exists($class)) {
                                        return null;
                                    }
                                    $item = $class::find($value);

                                    return $item ? ($item->name ?? $item->title ?? "#{$item->getKey()}") : null;
                                }),
                        ])
                        ->columns(2)
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => $get('placement') === 'models'),
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

    /**
     * @return array<string, string>
     */
    private static function routeModelOptions(): array
    {
        $options = [];
        foreach (cms()->builder('routeModels') ?? [] as $modelConfig) {
            $class = $modelConfig['class'] ?? null;
            if ($class && class_exists($class)) {
                $options[$class] = $modelConfig['name'] ?? class_basename($class);
            }
        }

        return $options;
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
