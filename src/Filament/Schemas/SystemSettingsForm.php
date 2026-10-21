<?php

declare(strict_types=1);

namespace Moonweft\Preference\Filament\Schemas;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Moonweft\Preference\System\SystemSettings;

final class SystemSettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('moonweft-preference::system.datetime.title'))
                ->description(__('moonweft-preference::system.datetime.description'))
                ->schema([
                    Select::make('timezone')->label(__('moonweft-preference::system.fields.timezone'))
                        ->options(SystemSettings::timezones())->searchable()->required()
                        ->helperText(__('moonweft-preference::system.datetime.timezone_help')),
                    Select::make('date_format')->label(__('moonweft-preference::system.fields.date_format'))
                        ->options(__('moonweft-preference::system.date_formats'))->required(),
                    Select::make('time_format')->label(__('moonweft-preference::system.fields.time_format'))
                        ->options(__('moonweft-preference::system.time_formats'))->required(),
                ])->columns(3)->columnSpanFull(),
            Section::make(__('moonweft-preference::system.scope.title'))
                ->description(__('moonweft-preference::system.scope.description'))
                ->columnSpanFull(),
        ]);
    }
}
