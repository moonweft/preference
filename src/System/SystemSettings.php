<?php

declare(strict_types=1);

namespace Moonweft\Preference\System;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
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
        'Y-m-d' => '2026-10-14（年-月-日）',
        'Y/m/d' => '2026/10/14（年/月/日）',
        'd/m/Y' => '14/10/2026（日/月/年）',
        'm/d/Y' => '10/14/2026（月/日/年）',
        'd.m.Y' => '14.10.2026（日.月.年）',
    ];

    public const TIME_FORMATS = [
        'H:i:s' => '14:30:00（24 小时，含秒）',
        'H:i' => '14:30（24 小时）',
        'h:i:s A' => '02:30:00 PM（12 小时，含秒）',
        'h:i A' => '02:30 PM（12 小时）',
    ];

    public string $timezone = 'UTC';

    public string $date_format = 'Y-m-d';

    public string $time_format = 'H:i:s';

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
        ])->validate();

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
