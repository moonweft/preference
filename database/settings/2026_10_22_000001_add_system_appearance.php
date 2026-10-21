<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('preference_system.theme_mode', 'system');
        $this->migrator->add('preference_system.primary_color', 'blue');
        $this->migrator->add('preference_system.gray_color', 'slate');
    }

    public function down(): void
    {
        $this->migrator->delete('preference_system.theme_mode');
        $this->migrator->delete('preference_system.primary_color');
        $this->migrator->delete('preference_system.gray_color');
    }
};
