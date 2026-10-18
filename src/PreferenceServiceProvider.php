<?php

declare(strict_types=1);

namespace Moonweft\Preference;

use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;
use MoonWeft\Ai\Contracts\ExecutionMiddleware;
use MoonWeft\Ai\Services\RequestLimits;
use MoonWeft\Iam\Contracts\PermissionContributor;
use Moonweft\Preference\Ai\AiConfiguration;
use Moonweft\Preference\Ai\AiSettings;
use Moonweft\Preference\Ai\ConfigureAiExecution;
use Moonweft\Preference\Ai\SystemAiLimits;
use Moonweft\Preference\Sms\SmsClient;
use Moonweft\Preference\Sms\SmsSettings;
use Moonweft\Preference\System\SystemSettings;
use Overtrue\EasySms\EasySms;

class PreferenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiConfiguration::class);
        $this->app->scoped(AiSettings::class);
        $this->app->scoped(SystemSettings::class);
        $this->app->scoped(SmsSettings::class);
        $this->app->bind(EasySms::class, static fn (): EasySms => new SmsClient((new SmsSettings)->configuration()));
        $this->app->bind(RequestLimits::class, SystemAiLimits::class);
        $this->app->tag([PreferencePermissionContributor::class], PermissionContributor::class);
        $this->app->tag([ConfigureAiExecution::class], ExecutionMiddleware::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'moonweft-preference');
        $this->loadMigrationsFrom(__DIR__.'/../database/settings');

        FilamentTimezone::set(static fn (): string => app(SystemSettings::class)->timezone);

        foreach ([Table::class, Schema::class] as $component) {
            $component::configureUsing(static function (Table|Schema $component): void {
                $component->defaultDateDisplayFormat(static fn (): string => app(SystemSettings::class)->date_format)
                    ->defaultTimeDisplayFormat(static fn (): string => app(SystemSettings::class)->time_format)
                    ->defaultDateTimeDisplayFormat(static fn (): string => app(SystemSettings::class)->dateTimeFormat());
            });
        }

        DateTimePicker::configureUsing(static function (DateTimePicker $picker): void {
            $picker->native(false)
                ->timezone(static fn (DateTimePicker $component): string => $component->hasDate() && $component->hasTime()
                    ? app(SystemSettings::class)->timezone : config('app.timezone'))
                ->defaultDateDisplayFormat(static fn (): string => app(SystemSettings::class)->date_format)
                ->defaultDateTimeDisplayFormat(static fn (): string => str_replace(':s', '', app(SystemSettings::class)->dateTimeFormat()))
                ->defaultDateTimeWithSecondsDisplayFormat(static fn (): string => app(SystemSettings::class)->dateTimeFormat())
                ->defaultTimeDisplayFormat(static fn (): string => str_replace(':s', '', app(SystemSettings::class)->time_format))
                ->defaultTimeWithSecondsDisplayFormat(static fn (): string => app(SystemSettings::class)->time_format);
        });

        $this->app->booted(fn () => $this->app->make(AiConfiguration::class)->baseline());
    }
}
