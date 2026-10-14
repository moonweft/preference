<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('preference_system.timezone', 'UTC');
        $this->migrator->add('preference_system.date_format', 'Y-m-d');
        $this->migrator->add('preference_system.time_format', 'H:i:s');
    }

    public function down(): void
    {
        $this->migrator->delete('preference_system.timezone');
        $this->migrator->delete('preference_system.date_format');
        $this->migrator->delete('preference_system.time_format');
    }
};
