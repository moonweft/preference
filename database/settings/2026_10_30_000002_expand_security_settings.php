<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('preference_security.password_history_count', 0);
        $this->migrator->add('preference_security.password_max_age_days', 0);
        $this->migrator->add('preference_security.force_initial_password_change', false);
        $this->migrator->add('preference_security.login_max_attempts', 5);
        $this->migrator->add('preference_security.login_window_seconds', 60);
        $this->migrator->add('preference_security.login_lockout_seconds', 60);
        $this->migrator->add('preference_security.session_idle_minutes', 120);
        $this->migrator->add('preference_security.session_absolute_minutes', 0);
        $this->migrator->add('preference_security.remember_login_enabled', true);
        $this->migrator->add('preference_security.remember_login_days', 400);
        $this->migrator->add('preference_security.notify_login', false);
        $this->migrator->add('preference_security.notify_password_changed', false);
    }

    public function down(): void
    {
        foreach ([
            'password_history_count',
            'password_max_age_days',
            'force_initial_password_change',
            'login_max_attempts',
            'login_window_seconds',
            'login_lockout_seconds',
            'session_idle_minutes',
            'session_absolute_minutes',
            'remember_login_enabled',
            'remember_login_days',
            'notify_login',
            'notify_password_changed',
        ] as $name) {
            $this->migrator->delete('preference_security.'.$name);
        }
    }
};
