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
use Filament\Forms\Components\FileUpload;
use Dashed\DashedLivechat\Ai\ToolRegistry;
use Dashed\DashedLivechat\Models\ChatAgent;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedLivechat\Filament\Resources\ChatAgentResource\Pages;

class ChatAgentResource extends Resource
{
    protected static ?string $model = ChatAgent::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static string | UnitEnum | null $navigationGroup = 'Chat';

    protected static ?string $navigationLabel = 'Medewerkers';

    protected static ?string $label = 'Medewerker';

    protected static ?string $pluralLabel = 'Medewerkers';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        $toolNames = (new ToolRegistry())->toolNames();
        $toolOptions = array_combine($toolNames, $toolNames);

        return $schema->schema([
            Section::make('Algemeen')->columnSpanFull()
                ->schema([
                    Select::make('type')
                        ->label('Type')
                        ->options(['ai' => 'AI', 'human' => 'Mens'])
                        ->default('ai')
                        ->live()
                        ->required(),
                    TextInput::make('name')
                        ->label('Naam')
                        ->required(),
                    TextInput::make('site_id')
                        ->label('Site')
                        ->required(),
                    Toggle::make('is_active')
                        ->label('Actief')
                        ->default(true),
                    FileUpload::make('avatar')
                        ->label('Avatar')
                        ->image()
                        ->directory('chat-avatars'),
                ])
                ->columns(2),

            Section::make('AI-instellingen')->columnSpanFull()
                ->schema([
                    Textarea::make('persona')
                        ->label('Persona / Systeem-prompt')
                        ->rows(4),
                    TextInput::make('tone')
                        ->label('Toon'),
                    TagsInput::make('languages')
                        ->label('Talen'),
                    Textarea::make('allowed_topics')
                        ->label('Toegestane onderwerpen'),
                    Textarea::make('disallowed_topics')
                        ->label('Verboden onderwerpen'),
                    Textarea::make('escalation_rules')
                        ->label('Escalatieregels'),
                    Textarea::make('greeting')
                        ->label('Begroeting'),
                    TextInput::make('model')
                        ->label('Model')
                        ->default('claude-sonnet-4-6'),
                    TextInput::make('temperature')
                        ->label('Temperatuur')
                        ->numeric()
                        ->default(0.5),
                    Select::make('guardrail_mode')
                        ->label('Guardrail-modus')
                        ->options(['standard' => 'Standaard', 'strict' => 'Streng'])
                        ->default('standard'),
                    CheckboxList::make('enabled_tools')
                        ->label('Ingeschakelde tools')
                        ->options($toolOptions)
                        ->columns(2),
                ])
                ->columns(2)
                ->visible(fn (Get $get) => $get('type') === 'ai'),

            Section::make('Mens-instellingen')->columnSpanFull()
                ->schema([
                    TextInput::make('email')
                        ->label('E-mailadres')
                        ->email(),
                ])
                ->visible(fn (Get $get) => $get('type') === 'human'),
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
                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('site_id')
                    ->label('Site'),
                IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChatAgents::route('/'),
            'create' => Pages\CreateChatAgent::route('/create'),
            'edit' => Pages\EditChatAgent::route('/{record}/edit'),
        ];
    }
}
