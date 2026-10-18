<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('preference_sms.enabled', false);
        $this->migrator->add('preference_sms.default_gateway', 'aliyun');
        $this->migrator->add('preference_sms.timeout', 5.0);
        $this->migrator->addEncrypted('preference_sms.providers', []);
    }

    public function down(): void
    {
        foreach (['enabled', 'default_gateway', 'timeout', 'providers'] as $name) {
            $this->migrator->delete('preference_sms.'.$name);
        }
    }
};
