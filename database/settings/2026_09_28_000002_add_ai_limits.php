<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('preference_ai.limits', []);
    }

    public function down(): void
    {
        $this->migrator->delete('preference_ai.limits');
    }
};
