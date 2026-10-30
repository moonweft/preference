<?php

declare(strict_types=1);

namespace Moonweft\Preference\Security;

use Illuminate\Validation\Rules\Password;
use MoonWeft\Iam\AccountSecurityPolicy;

/** Supplies current platform preferences to IAM without retaining request state. */
final class ConfiguredAccountSecurityPolicy extends AccountSecurityPolicy
{
    public function passwordRule(): Password
    {
        $settings = app(SecuritySettings::class);

        return Password::min($settings->password_min_length)
            ->when($settings->password_letters, static fn (Password $rule): Password => $rule->letters())
            ->when($settings->password_mixed_case, static fn (Password $rule): Password => $rule->mixedCase())
            ->when($settings->password_numbers, static fn (Password $rule): Password => $rule->numbers())
            ->when($settings->password_symbols, static fn (Password $rule): Password => $rule->symbols());
    }

    public function requiresMultiFactorAuthentication(): bool
    {
        return app(SecuritySettings::class)->mfa_required;
    }

    public function passwordHistoryCount(): int
    {
        return app(SecuritySettings::class)->password_history_count;
    }

    public function passwordMaxAgeDays(): int
    {
        return app(SecuritySettings::class)->password_max_age_days;
    }

    public function forceInitialPasswordChange(): bool
    {
        return app(SecuritySettings::class)->force_initial_password_change;
    }

    public function loginMaxAttempts(): int
    {
        return app(SecuritySettings::class)->login_max_attempts;
    }

    public function loginWindowSeconds(): int
    {
        return app(SecuritySettings::class)->login_window_seconds;
    }

    public function loginLockoutSeconds(): int
    {
        return app(SecuritySettings::class)->login_lockout_seconds;
    }

    public function sessionIdleMinutes(): int
    {
        return app(SecuritySettings::class)->session_idle_minutes;
    }

    public function sessionAbsoluteMinutes(): int
    {
        return app(SecuritySettings::class)->session_absolute_minutes;
    }

    public function rememberLoginEnabled(): bool
    {
        return app(SecuritySettings::class)->remember_login_enabled;
    }

    public function rememberLoginDays(): int
    {
        return app(SecuritySettings::class)->remember_login_days;
    }

    public function notifyLogin(): bool
    {
        return app(SecuritySettings::class)->notify_login;
    }

    public function notifyPasswordChanged(): bool
    {
        return app(SecuritySettings::class)->notify_password_changed;
    }
}
