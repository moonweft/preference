<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('preference_system.font_scale', 125);
    }

    public function down(): void
    {
        $this->migrator->delete('preference_system.font_scale');
    }
};
