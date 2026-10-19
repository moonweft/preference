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
use Spatie\LaravelSettings\Support\Crypto;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
final class TranscriptionSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Full Host fixtures and repeated rendering of the provider tabs exceed 128 MiB.
        ini_set('memory_limit', '256M');
        parent::setUp();
    }

    public function test_transcription_settings_only_offer_registered_supported_providers(): void
    {
        $catalog = app(ProviderCatalog::class);
        self::assertArrayNotHasKey('tencent', config('ai.providers'));
        self::assertArrayNotHasKey('tencent', $catalog->options());
        self::assertArrayNotHasKey('tencent', $catalog->options('transcription'));
        self::assertArrayHasKey('openai', $catalog->options('transcription'));
        self::assertArrayNotHasKey('deepseek', $catalog->options('transcription'));
    }

    public function test_credentials_are_encrypted_hidden_and_retained_on_blank_save(): void
    {
        app(PermissionSynchronizer::class)->sync();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = Admin::factory()->create();
        app(BootstrapDeveloper::class)->initialize($admin, 'Synthetic ASR settings test');
        $page = Livewire::actingAs($admin, 'admin')->test(AiSettingsPage::class)
            ->assertDontSee('Tencent ASR')
            ->set(['data.defaults.transcription' => 'openai',
                'data.providers.openai.key' => 'synthetic-secret',
                'data.providers.openai.models.transcription.default' => 'whisper-1'])
            ->call('save')->assertHasNoErrors()
            ->assertSet('data.providers.openai.key', null);
        $page->call('save')->assertHasNoErrors();
        self::assertSame('synthetic-secret', (new AiSettings)->providers['openai']['key']);
        self::assertSame('openai', (new AiSettings)->defaults['transcription']);
        $stored = (string) DB::table('settings')->where('group', 'preference_ai')->where('name', 'providers')->value('payload');
        self::assertStringNotContainsString('synthetic-secret', $stored);
        $page->set('data.providers.openai.remove_key', true)->call('save')->assertHasNoErrors();
        self::assertArrayNotHasKey('key', (new AiSettings)->providers['openai']);
    }

    public function test_account_without_system_configuration_authority_cannot_open_credentials(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::actingAs(Admin::factory()->create(), 'admin')->test(AiSettingsPage::class)->assertForbidden();
    }

    public function test_removed_provider_credentials_are_not_hydrated_into_the_page(): void
    {
        (new AiSettings)->getRepository()->updatePropertiesPayload(AiSettings::group(), [
            'providers' => Crypto::encrypt(['tencent' => ['secret_id' => 'retired-account', 'key' => 'retired-secret']]),
        ]);
        app(PermissionSynchronizer::class)->sync();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = Admin::factory()->create();
        app(BootstrapDeveloper::class)->initialize($admin, 'Removed provider settings test');
        Livewire::actingAs($admin, 'admin')->test(AiSettingsPage::class)
            ->assertSet('data.providers.tencent', null);
    }
}
