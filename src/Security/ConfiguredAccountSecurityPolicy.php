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
}
