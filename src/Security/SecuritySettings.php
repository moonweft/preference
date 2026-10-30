<?php

declare(strict_types=1);

namespace Moonweft\Preference\Security;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use MoonWeft\Iam\SecurityConfirmation;
use Moonweft\Preference\PreferencePermissionContributor;
use Moonweft\Preference\SettingsAccess;
use Spatie\LaravelSettings\Settings;

/** Public, platform-wide policy for backend accounts; only platform-authorised administrators may change it. */
final class SecuritySettings extends Settings
{
    public int $password_min_length = 12;

    public bool $password_letters = false;

    public bool $password_mixed_case = false;

    public bool $password_numbers = false;

    public bool $password_symbols = false;

    public bool $mfa_required = false;

    public int $password_history_count = 0;

    public int $password_max_age_days = 0;

    public bool $force_initial_password_change = false;

    public int $login_max_attempts = 5;

    public int $login_window_seconds = 60;

    public int $login_lockout_seconds = 60;

    public int $session_idle_minutes = 120;

    public int $session_absolute_minutes = 0;

    public bool $remember_login_enabled = true;

    public int $remember_login_days = 400;

    public bool $notify_login = false;

    public bool $notify_password_changed = false;

    public static function group(): string
    {
        return 'preference_security';
    }

    public function save(): self
    {
        $principal = app(SettingsAccess::class)->principal(PreferencePermissionContributor::MANAGE_SECURITY);
        abort_if($principal === null, 403);

        $values = Validator::make($this->toArray(), [
            'password_min_length' => ['required', 'integer', 'min:12', 'max:72'],
            'password_letters' => ['required', 'boolean'],
            'password_mixed_case' => ['required', 'boolean'],
            'password_numbers' => ['required', 'boolean'],
            'password_symbols' => ['required', 'boolean'],
            'mfa_required' => ['required', 'boolean'],
            'password_history_count' => ['required', 'integer', 'min:0', 'max:24'],
            'password_max_age_days' => ['required', 'integer', 'min:0', 'max:365'],
            'login_max_attempts' => ['required', 'integer', 'min:1', 'max:20'],
            'login_window_seconds' => ['required', 'integer', 'min:60', 'max:3600'],
            'login_lockout_seconds' => ['required', 'integer', 'min:60', 'max:86400'],
            'session_idle_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'session_absolute_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'remember_login_days' => ['required', 'integer', 'min:1', 'max:400'],
            'force_initial_password_change' => ['required', 'boolean'],
            'remember_login_enabled' => ['required', 'boolean'],
            'notify_login' => ['required', 'boolean'],
            'notify_password_changed' => ['required', 'boolean'],
        ], attributes: __('moonweft-preference::security.fields'))->validate();

        app(SecurityConfirmation::class)->assertRecentlyConfirmed();
        if ($this->mfa_required) {
            app(SecurityConfirmation::class)->assertCanRequireMultiFactorAuthentication();
        }

        return DB::transaction(function () use ($principal, $values): self {
            $before = $this->originalValues?->all();
            parent::save();
            DB::afterCommit(static fn () => Log::info('preference.security.updated', [
                'principal' => $principal->identity(), 'scope' => 'host', 'before' => $before, 'after' => $values,
            ]));

            return $this;
        });
    }
}
