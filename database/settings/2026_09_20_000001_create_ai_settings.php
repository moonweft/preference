<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('preference_ai.defaults', []);
        $this->migrator->addEncrypted('preference_ai.providers', []);
    }

    public function down(): void
    {
        $this->migrator->delete('preference_ai.defaults');
        $this->migrator->delete('preference_ai.providers');
    }
};
