<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('preference_security.password_min_length', 12);
        foreach (['password_letters', 'password_mixed_case', 'password_numbers', 'password_symbols'] as $name) {
            $this->migrator->add('preference_security.'.$name, false);
        }
        $this->migrator->add('preference_security.mfa_required', (bool) config('iam.mfa_required', false));
    }

    public function down(): void
    {
        foreach (['password_min_length', 'password_letters', 'password_mixed_case', 'password_numbers', 'password_symbols', 'mfa_required'] as $name) {
            $this->migrator->delete('preference_security.'.$name);
        }
    }
};
