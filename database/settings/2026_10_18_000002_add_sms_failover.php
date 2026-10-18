<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('preference_sms.backup_gateway', null);
        $this->migrator->add('preference_sms.failover_enabled', false);
    }

    public function down(): void
    {
        $this->migrator->delete('preference_sms.backup_gateway');
        $this->migrator->delete('preference_sms.failover_enabled');
    }
};
