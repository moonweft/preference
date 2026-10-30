<?php

declare(strict_types=1);

namespace Moonweft\Preference\Filament\Schemas;

use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use MoonWeft\Components\Text\Forms\AdvancedTextInput;

final class SecuritySettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('moonweft-preference::security.password.title'))
                ->description(__('moonweft-preference::security.password.description'))
                ->schema([
                    AdvancedTextInput::make('password_min_length')
                        ->label(__('moonweft-preference::security.fields.password_min_length'))
                        ->integer()->required()->minValue(12)->maxValue(72)
                        ->suffix(__('moonweft-preference::security.password.characters'))
                        ->helperText(__('moonweft-preference::security.password.length_help')),
                    ...array_map(static fn (string $field): Toggle => Toggle::make($field)
                        ->label(__('moonweft-preference::security.fields.'.$field))
                        ->helperText(__('moonweft-preference::security.help.'.$field)),
                        ['password_letters', 'password_mixed_case', 'password_numbers', 'password_symbols']),
                ])->columnSpanFull(),
            Section::make(__('moonweft-preference::security.mfa.title'))
                ->description(__('moonweft-preference::security.mfa.description'))
                ->schema([
                    Toggle::make('mfa_required')
                        ->label(__('moonweft-preference::security.fields.mfa_required'))
                        ->helperText(__('moonweft-preference::security.mfa.help')),
                ])->columnSpanFull(),
        ]);
    }
}
