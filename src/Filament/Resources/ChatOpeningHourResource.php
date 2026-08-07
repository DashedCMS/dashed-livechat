<?php

namespace Dashed\DashedLivechat\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Dashed\DashedLivechat\Models\ChatOpeningHour;
use Dashed\DashedLivechat\Filament\Concerns\HiddenWhenChatDisabled;
use Dashed\DashedLivechat\Filament\Resources\ChatOpeningHourResource\Pages;

class ChatOpeningHourResource extends Resource
{
    use HiddenWhenChatDisabled;

    protected static ?string $model = ChatOpeningHour::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    protected static string | UnitEnum | null $navigationGroup = 'Chat';

    protected static ?string $navigationLabel = 'Openingstijden';

    protected static ?string $label = 'Openingstijd';

    protected static ?string $pluralLabel = 'Openingstijden';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make(__('Openingstijd'))->columnSpanFull()
                ->schema([
                    Select::make('day_of_week')
                        ->label(__('Dag van de week'))
                        ->options([
                            0 => __('Zondag'),
                            1 => __('Maandag'),
                            2 => __('Dinsdag'),
                            3 => __('Woensdag'),
                            4 => __('Donderdag'),
                            5 => __('Vrijdag'),
                            6 => __('Zaterdag'),
                        ])
                        ->nullable(),
                    DatePicker::make('date')
                        ->label(__('Datum (uitzondering)'))
                        ->nullable(),
                    Toggle::make('is_closed')
                        ->label(__('Gesloten')),
                    TimePicker::make('opens_at')
                        ->label(__('Opent om'))
                        ->seconds(false)
                        ->nullable(),
                    TimePicker::make('closes_at')
                        ->label(__('Sluit om'))
                        ->seconds(false)
                        ->nullable(),
                    TextInput::make('label')
                        ->label(__('Label'))
                        ->nullable(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('day_of_week')
                    ->label(__('Dag / Datum'))
                    ->formatStateUsing(function ($record) {
                        if ($record->date) {
                            return $record->date->format('d-m-Y');
                        }

                        $days = [
                            0 => 'Zondag',
                            1 => 'Maandag',
                            2 => 'Dinsdag',
                            3 => 'Woensdag',
                            4 => 'Donderdag',
                            5 => 'Vrijdag',
                            6 => 'Zaterdag',
                        ];

                        return $days[$record->day_of_week] ?? '-';
                    }),
                TextColumn::make('opens_at')
                    ->label(__('Opent om')),
                TextColumn::make('closes_at')
                    ->label(__('Sluit om')),
                IconColumn::make('is_closed')
                    ->label(__('Gesloten'))
                    ->boolean(),
            ])
            ->defaultSort('day_of_week');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChatOpeningHours::route('/'),
            'create' => Pages\CreateChatOpeningHour::route('/create'),
            'edit' => Pages\EditChatOpeningHour::route('/{record}/edit'),
        ];
    }
}
