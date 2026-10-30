<?php

declare(strict_types=1);

namespace Moonweft\Preference\Security;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
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
        ], attributes: __('moonweft-preference::security.fields'))->validate();

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
