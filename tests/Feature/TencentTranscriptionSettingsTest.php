<?php

declare(strict_types=1);

namespace Moonweft\Preference\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use MoonWeft\Iam\BootstrapDeveloper;
use MoonWeft\Iam\Models\Admin;
use MoonWeft\Iam\PermissionSynchronizer;
use Moonweft\Preference\Ai\AiSettings;
use Moonweft\Preference\Ai\ProviderCatalog;
use Moonweft\Preference\Filament\Pages\AiSettingsPage;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
final class TencentTranscriptionSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Full Host fixtures and repeated rendering of the provider tabs exceed 128 MiB.
        ini_set('memory_limit', '256M');
        parent::setUp();
    }

    public function test_tencent_is_only_available_for_transcription(): void
    {
        $catalog = app(ProviderCatalog::class);
        self::assertArrayHasKey('tencent', $catalog->options('transcription'));
        self::assertArrayNotHasKey('tencent', $catalog->options('text'));
        self::assertArrayNotHasKey('tencent', $catalog->options('audio'));
        self::assertSame(['secret_id', 'key'], $catalog->secretFields('tencent'));
    }

    public function test_credentials_are_encrypted_hidden_and_retained_on_blank_save(): void
    {
        app(PermissionSynchronizer::class)->sync();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = Admin::factory()->create();
        app(BootstrapDeveloper::class)->initialize($admin, 'Synthetic ASR settings test');
        $page = Livewire::actingAs($admin, 'admin')->test(AiSettingsPage::class)
            ->set(['data.defaults.transcription' => 'tencent',
                'data.providers.tencent.secret_id' => 'AKIDsynthetic',
                'data.providers.tencent.key' => 'synthetic-secret',
                'data.providers.tencent.models.transcription.default' => '16k_zh'])
            ->call('save')->assertHasNoErrors()
            ->assertSet('data.providers.tencent.secret_id', null)
            ->assertSet('data.providers.tencent.key', null);
        $page->call('save')->assertHasNoErrors();
        self::assertSame('AKIDsynthetic', (new AiSettings)->providers['tencent']['secret_id']);
        self::assertSame('synthetic-secret', (new AiSettings)->providers['tencent']['key']);
        self::assertSame('tencent', (new AiSettings)->defaults['transcription']);
        $stored = (string) DB::table('settings')->where('group', 'preference_ai')->where('name', 'providers')->value('payload');
        self::assertStringNotContainsString('synthetic-secret', $stored);
        self::assertStringNotContainsString('AKIDsynthetic', $stored);
        $page->set('data.providers.tencent.remove_key', true)->call('save')->assertHasNoErrors();
        self::assertArrayNotHasKey('key', (new AiSettings)->providers['tencent']);
        self::assertArrayNotHasKey('secret_id', (new AiSettings)->providers['tencent']);
    }

    public function test_account_without_system_configuration_authority_cannot_open_credentials(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::actingAs(Admin::factory()->create(), 'admin')->test(AiSettingsPage::class)->assertForbidden();
    }
}
