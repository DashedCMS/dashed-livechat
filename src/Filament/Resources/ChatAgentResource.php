<?php

namespace Dashed\DashedLivechat\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Dashed\DashedCore\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Dashed\DashedCore\Classes\Locales;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Dashed\DashedLivechat\Ai\ToolRegistry;
use Dashed\DashedLivechat\Models\ChatAgent;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedLivechat\Filament\Concerns\HiddenWhenChatDisabled;
use Dashed\DashedLivechat\Filament\Resources\ChatAgentResource\Pages;

class ChatAgentResource extends Resource
{
    use HiddenWhenChatDisabled;

    protected static ?string $model = ChatAgent::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static string | UnitEnum | null $navigationGroup = 'Chat';

    protected static ?string $navigationLabel = 'Medewerkers';

    protected static ?string $label = 'Medewerker';

    protected static ?string $pluralLabel = 'Medewerkers';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        $registry = new ToolRegistry();
        $registeredNames = array_flip($registry->toolNames());
        $toolOptions = array_filter(
            $registry->toolLabels(),
            fn (string $key) => isset($registeredNames[$key]),
            ARRAY_FILTER_USE_KEY,
        );

        return $schema->schema([
            Section::make('Algemeen')->columnSpanFull()
                ->schema([
                    Select::make('type')
                        ->label('Type')
                        ->options(['ai' => 'AI', 'human' => 'Mens'])
                        ->default('ai')
                        ->live()
                        ->required()
                        ->helperText('AI = chatbot. Mens = medewerker die gesprekken kan overnemen.'),
                    Select::make('user_id')
                        ->label('Gekoppelde gebruiker')
                        ->options(
                            User::whereIn('role', ['admin', 'superadmin'])->orderBy('name')->get()
                                ->mapWithKeys(fn ($u) => [$u->id => $u->name ?: ($u->email ?: 'Gebruiker #' . $u->id)])
                                ->all()
                        )
                        ->searchable()
                        ->visible(fn (Get $get) => $get('type') === 'human')
                        ->required(fn (Get $get) => $get('type') === 'human')
                        ->helperText('De medewerker (admin of superadmin) die deze gesprekken voert. Naam en e-mail komen automatisch van deze gebruiker.'),
                    TextInput::make('name')
                        ->label('Naam')
                        ->visible(fn (Get $get) => $get('type') === 'ai')
                        ->required(fn (Get $get) => $get('type') === 'ai')
                        ->helperText('Naam die de bezoeker in de chat ziet.'),
                    Toggle::make('is_active')
                        ->label('Actief')
                        ->default(true)
                        ->helperText('Alleen actieve medewerkers worden ingezet.'),
                    Toggle::make('receive_outside_hours')
                        ->label('Ook buiten openingstijden ontvangen')
                        ->default(false)
                        ->visible(fn (Get $get) => $get('type') === 'human')
                        ->helperText('Wanneer aan: deze medewerker krijgt ook buiten de openingstijden chats/handoffs (en notificaties).'),
                    mediaHelper()->field('avatar', 'Profielfoto', isImage: true)
                        ->helperText('Profielfoto die in de chat wordt getoond.'),
                ])
                ->columns(2),

            Section::make('AI-instellingen')->columnSpanFull()
                ->schema([
                    Textarea::make('persona')
                        ->label('Persona / Systeem-prompt')
                        ->rows(4)
                        ->helperText('Korte beschrijving van karakter en rol, bv. "Vriendelijke webshop-assistent".'),
                    TextInput::make('tone')
                        ->label('Toon')
                        ->helperText('Toon van de antwoorden, bv. informeel, zakelijk of behulpzaam.'),
                    Select::make('languages')
                        ->label('Talen')
                        ->multiple()
                        ->options(Locales::getLocalesArray())
                        ->helperText('Talen waarin de bot mag antwoorden. Kies uit de in het CMS geactiveerde talen.'),
                    Textarea::make('allowed_topics')
                        ->label('Toegestane onderwerpen')
                        ->helperText('Onderwerpen waarover de bot wel mag praten.'),
                    Textarea::make('disallowed_topics')
                        ->label('Verboden onderwerpen')
                        ->helperText('Onderwerpen die de bot moet weigeren of doorverwijzen.'),
                    Toggle::make('escalate_on_request')
                        ->label('Escaleer als de bezoeker om een mens vraagt')
                        ->default(true),
                    Toggle::make('escalate_on_negative')
                        ->label('Escaleer bij een boze/ontevreden bezoeker')
                        ->default(true),
                    Toggle::make('escalate_on_tool_failure')
                        ->label('Escaleer als tools herhaald geen antwoord geven')
                        ->default(true),
                    Toggle::make('escalate_off_topic')
                        ->label('Escaleer bij vragen buiten de onderwerpen')
                        ->default(false),
                    Textarea::make('escalation_rules')
                        ->label('Extra escalatieregels (optioneel)')
                        ->helperText('Aanvullende gevallen die niet door de knoppen hierboven worden gedekt.'),
                    Textarea::make('greeting')
                        ->label('Begroeting')
                        ->helperText('Openingsbericht dat de bot als eerste bericht plaatst bij een nieuw gesprek.'),
                    Select::make('model')
                        ->label('Model')
                        ->options([
                            'claude-sonnet-5' => 'Claude Sonnet 5 (aanbevolen)',
                            'claude-sonnet-4-6' => 'Claude Sonnet 4.6 (standaard, gebalanceerd)',
                            'claude-opus-4-8' => 'Claude Opus 4.8 (krachtigst)',
                            'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (snel, goedkoop)',
                        ])
                        ->default('claude-sonnet-4-6')
                        ->helperText('Welk Claude-model de bot gebruikt. Sonnet is een goede standaard.'),
                    TextInput::make('temperature')
                        ->label('Temperatuur')
                        ->numeric()
                        ->default(0.5)
                        ->helperText('Creativiteit: 0 = feitelijk en consistent, 1 = creatiever.'),
                    TextInput::make('ai_reply_delay_seconds')
                        ->label('Reactievertraging (seconden)')
                        ->numeric()
                        ->default(8)
                        ->minValue(0)
                        ->helperText('Seconden wachten voordat de AI reageert. Binnen dit venster kan een medewerker het overnemen of kan de bezoeker nog typen (de AI antwoordt dan op het hele blok). 0 = direct.'),
                    TextInput::make('max_tokens')
                        ->label('Max. antwoordlengte (tokens)')
                        ->numeric()
                        ->default(1536)
                        ->minValue(256)
                        ->helperText('Maximale lengte van een AI-antwoord per beurt. Hoger = langere antwoorden mogelijk.'),
                    Select::make('guardrail_mode')
                        ->label('Guardrail-modus')
                        ->options(['standard' => 'Standaard', 'strict' => 'Streng'])
                        ->default('standard')
                        ->helperText('Streng voegt een extra controle toe die off-topic vragen harder afvangt (iets duurder).'),
                    CheckboxList::make('enabled_tools')
                        ->label('Ingeschakelde tools')
                        ->options($toolOptions)
                        ->columns(2)
                        ->bulkToggleable()
                        ->helperText('Welke gegevens en acties de bot mag gebruiken om te antwoorden.'),
                ])
                ->columns(2)
                ->visible(fn (Get $get) => $get('type') === 'ai'),
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
