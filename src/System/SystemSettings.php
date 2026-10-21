<?php

declare(strict_types=1);

namespace Moonweft\Preference\System;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Moonweft\Preference\PreferencePermissionContributor;
use Moonweft\Preference\SettingsAccess;
use Spatie\LaravelSettings\Settings;

/** Host-wide, non-secret display preferences, shared by all users and organisations. */
final class SystemSettings extends Settings
{
    public const DATE_FORMATS = [
        'Y-m-d' => '2026-10-14',
        'Y/m/d' => '2026/10/14',
        'd/m/Y' => '14/10/2026',
        'm/d/Y' => '10/14/2026',
        'd.m.Y' => '14.10.2026',
    ];

    public const TIME_FORMATS = [
        'H:i:s' => '14:30:00',
        'H:i' => '14:30',
        'h:i:s A' => '02:30:00 PM',
        'h:i A' => '02:30 PM',
    ];

    public const THEME_MODES = ['system', 'light', 'dark'];

    public const PRIMARY_COLORS = ['blue', 'indigo', 'violet', 'purple', 'rose', 'orange', 'amber', 'emerald', 'teal', 'cyan'];

    public const GRAY_COLORS = ['slate', 'gray', 'zinc', 'neutral', 'stone'];

    public string $timezone = 'UTC';

    public string $date_format = 'Y-m-d';

    public string $time_format = 'H:i:s';

    public string $theme_mode = 'system';

    public string $primary_color = 'blue';

    public string $gray_color = 'slate';

    public static function group(): string
    {
        return 'preference_system';
    }

    /** @return array<string, string> IANA identifiers, with UTC explicitly available. */
    public static function timezones(): array
    {
        $timezones = DateTimeZone::listIdentifiers();

        return array_combine($timezones, $timezones);
    }

    public function dateTimeFormat(): string
    {
        return $this->date_format.' '.$this->time_format;
    }

    /** Native colour scales shared by panels using the preference plugin. */
    public function colors(): array
    {
        return [
            'primary' => Color::all()[$this->primary_color] ?? Color::Blue,
            'gray' => Color::all()[$this->gray_color] ?? Color::Slate,
        ];
    }

    /** Format an instant for UI, notifications or exports without changing its stored value. */
    public function formatDateTime(DateTimeInterface|string|null $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse($value, config('app.timezone'))
            ->setTimezone($this->timezone)->translatedFormat($this->dateTimeFormat());
    }

    /** All writes require the current IAM administrator's platform permission. */
    public function save(): self
    {
        $principal = app(SettingsAccess::class)->principal(PreferencePermissionContributor::MANAGE_SYSTEM);
        abort_if($principal === null, 403);

        $values = Validator::make($this->toArray(), [
            'timezone' => ['required', Rule::in(array_keys(self::timezones()))],
            'date_format' => ['required', Rule::in(array_keys(self::DATE_FORMATS))],
            'time_format' => ['required', Rule::in(array_keys(self::TIME_FORMATS))],
            'theme_mode' => ['required', Rule::in(self::THEME_MODES)],
            'primary_color' => ['required', Rule::in(self::PRIMARY_COLORS)],
            'gray_color' => ['required', Rule::in(self::GRAY_COLORS)],
        ], attributes: __('moonweft-preference::system.fields'))->validate();

        return DB::transaction(function () use ($principal, $values): self {
            $before = $this->originalValues?->all();
            parent::save();
            DB::afterCommit(static fn () => Log::info('preference.system.updated', [
                'principal' => $principal->identity(), 'scope' => 'host', 'before' => $before, 'after' => $values,
            ]));

            return $this;
        });
    }
}
