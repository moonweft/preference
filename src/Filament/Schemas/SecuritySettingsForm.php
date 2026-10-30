<?php

declare(strict_types=1);

namespace Moonweft\Preference\Filament\Schemas;

use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use MoonWeft\Components\Slider\Forms\Slider;
use MoonWeft\Components\Text\Forms\AdvancedTextInput;

final class SecuritySettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('moonweft-preference::security.password.title'))
                ->description(__('moonweft-preference::security.password.description'))
                ->schema([
                    ...self::numbers(['password_min_length' => [12, 72, [12, 16, 24, 32]]]),
                    ...self::toggles(['password_letters', 'password_mixed_case', 'password_numbers', 'password_symbols']),
                    ...self::numbers(['password_history_count' => [0, 24, [0, 5, 12, 24]],
                        'password_max_age_days' => [0, 365, [0, 30, 90, 180]]]),
                    ...self::toggles(['force_initial_password_change']),
                ])->columns(2)->columnSpanFull(),
            Section::make(__('moonweft-preference::security.login.title'))
                ->description(__('moonweft-preference::security.login.description'))
                ->schema(self::numbers(['login_max_attempts' => [1, 20, [3, 5, 10, 20]],
                    'login_window_seconds' => [60, 3600, [60, 300, 900, 3600]],
                    'login_lockout_seconds' => [60, 86400, [60, 300, 3600, 86400]]]))->columns(3)->columnSpanFull(),
            Section::make(__('moonweft-preference::security.session.title'))
                ->description(__('moonweft-preference::security.session.description'))
                ->schema([
                    ...self::numbers(['session_idle_minutes' => [5, 1440, [15, 30, 60, 120]],
                        'session_absolute_minutes' => [0, 10080, [0, 480, 1440, 10080]]]),
                    ...self::toggles(['remember_login_enabled']),
                    ...self::numbers(['remember_login_days' => [1, 400, [7, 30, 90, 400]]]),
                ])->columns(2)->columnSpanFull(),
            Section::make(__('moonweft-preference::security.mfa.title'))
                ->description(__('moonweft-preference::security.mfa.description'))
                ->schema([
                    Toggle::make('mfa_required')
                        ->label(__('moonweft-preference::security.fields.mfa_required'))
                        ->helperText(__('moonweft-preference::security.mfa.help')),
                ])->columnSpanFull(),
            Section::make(__('moonweft-preference::security.notifications.title'))
                ->description(__('moonweft-preference::security.notifications.description'))
                ->schema(self::toggles(['notify_login', 'notify_password_changed']))->columns(2)->columnSpanFull(),
            Section::make(__('moonweft-preference::security.confirmation.title'))
                ->description(__('moonweft-preference::security.confirmation.description'))
                ->schema([
                    AdvancedTextInput::make('confirmation_password')->password()->autocomplete('current-password')
                        ->label(__('moonweft-preference::security.fields.confirmation_password'))->dehydrated(false),
                    AdvancedTextInput::make('confirmation')->autocomplete('one-time-code')
                        ->label(__('moonweft-preference::security.fields.confirmation'))->dehydrated(false)
                        ->helperText(__('moonweft-preference::security.confirmation.help')),
                ])->columns(2)->columnSpanFull(),
        ]);
    }

    /** @return array<Toggle> */
    private static function toggles(array $fields): array
    {
        return array_map(static fn (string $field): Toggle => Toggle::make($field)
            ->label(__('moonweft-preference::security.fields.'.$field))
            ->helperText(__('moonweft-preference::security.help.'.$field)), $fields);
    }

    /**
     * @param  array<string, array{int, int, list<int>}>  $ranges
     * @return array<Slider>
     */
    private static function numbers(array $ranges): array
    {
        $fields = [];
        foreach ($ranges as $name => [$minimum, $maximum, $presets]) {
            $fields[] = Slider::make($name)->range($minimum, $maximum)->step(1)->decimalPlaces(0)->required()
                ->currency('')->rulerLabel(false)->accent('var(--primary-500)')
                ->presets(array_map(static fn (int $value): array => [
                    'amount' => $value,
                    'label' => $value === 0 ? __('moonweft-preference::security.zero_options.'.$name) : (string) $value,
                ], $presets))
                ->label(__('moonweft-preference::security.fields.'.$name))
                ->helperText(__('moonweft-preference::security.help.'.$name));
        }

        return $fields;
    }
}
