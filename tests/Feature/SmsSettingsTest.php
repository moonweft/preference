<?php

declare(strict_types=1);

namespace Moonweft\Preference\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use MoonWeft\Iam\IamPermission;
use MoonWeft\Iam\Models\AccountAuthorization;
use MoonWeft\Iam\Models\Admin;
use MoonWeft\Iam\Models\User;
use MoonWeft\Iam\PermissionSynchronizer;
use Moonweft\Preference\Filament\Pages\SmsSettingsPage;
use Moonweft\Preference\PreferencePermissionContributor;
use Moonweft\Preference\Sms\SmsClient;
use Moonweft\Preference\Sms\SmsSettings;
use Overtrue\EasySms\EasySms;
use Overtrue\EasySms\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class SmsSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionSynchronizer::class)->sync();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_sms_is_disabled_until_an_administrator_configures_it(): void
    {
        $this->assertFalse((new SmsSettings)->enabled);
        $this->assertFalse((new SmsSettings)->failover_enabled);
        $this->assertNull((new SmsSettings)->backup_gateway);
        $this->expectException(\LogicException::class);
        app(EasySms::class);
    }

    public function test_credentials_are_encrypted_hidden_and_preserved_when_left_blank(): void
    {
        $page = Livewire::actingAs($this->admin(), 'admin')->test(SmsSettingsPage::class)
            ->assertSuccessful()->fillForm([
                'enabled' => true, 'default_gateway' => 'aliyun', 'timeout' => 8,
                'providers.aliyun.access_key_id' => 'saved-id',
                'providers.aliyun.access_key_secret' => 'saved-secret',
                'providers.aliyun.sign_name' => 'MoonWeft',
            ])->call('save')->assertHasNoFormErrors()
            ->assertSet('data.providers.aliyun.access_key_id', null)
            ->assertSet('data.providers.aliyun.access_key_secret', null)
            ->assertDontSee('saved-secret');
        $payload = DB::table('settings')->where('group', SmsSettings::group())->where('name', 'providers')->value('payload');
        $this->assertStringNotContainsString('saved-secret', $payload);
        $this->assertStringNotContainsString('saved-id', $payload);
        $page->fillForm(['timeout' => 9])->call('save')->assertHasNoFormErrors();
        $this->assertSame('saved-secret', (new SmsSettings)->providers['aliyun']['access_key_secret']);
        $this->assertSame(9.0, app(EasySms::class)->getConfig()->get('timeout'));
    }

    public function test_configuration_requires_its_own_permission_and_revocation_is_rechecked(): void
    {
        $admin = $this->admin(PreferencePermissionContributor::MANAGE_SYSTEM);
        Livewire::actingAs($admin, 'admin')->test(SmsSettingsPage::class)->assertForbidden();
        $role = Role::where('name', 'sms-settings-'.$admin->id)->firstOrFail();
        $role->givePermissionTo(PreferencePermissionContributor::MANAGE_SMS);
        $page = Livewire::actingAs($admin, 'admin')->test(SmsSettingsPage::class);
        $role->revokePermissionTo(PreferencePermissionContributor::MANAGE_SMS);
        $page->call('save')->assertForbidden();
    }

    public function test_switching_administrators_invalidates_the_open_page(): void
    {
        $page = Livewire::actingAs($this->admin(), 'admin')->test(SmsSettingsPage::class);
        Livewire::actingAs($this->admin(), 'admin');
        $page->call('save')->assertForbidden();
    }

    public function test_web_identity_cannot_bypass_backend_authorisation(): void
    {
        $this->actingAs(User::factory()->create(), 'web');
        $this->expectException(HttpException::class);
        (new SmsSettings)->save();
    }

    public function test_a_stale_page_cannot_overwrite_updated_credentials(): void
    {
        $admin = $this->admin();
        $page = Livewire::actingAs($admin, 'admin')->test(SmsSettingsPage::class);
        $settings = new SmsSettings;
        $settings->providers = ['yunpian' => ['api_key' => 'newer-key']];
        $settings->save();
        $page->fillForm(['timeout' => 10])->call('save')->assertHasErrors(['data']);
        $this->assertSame('newer-key', (new SmsSettings)->providers['yunpian']['api_key']);
    }

    public function test_unreadable_credentials_are_not_overwritten(): void
    {
        DB::table('settings')->where('group', SmsSettings::group())->where('name', 'providers')
            ->update(['payload' => json_encode('invalid-ciphertext')]);
        Livewire::actingAs($this->admin(), 'admin')->test(SmsSettingsPage::class)
            ->assertSee('短信配置暂时无法读取')->assertSet('data', [])
            ->call('save')->assertForbidden();
        $this->assertSame(json_encode('invalid-ciphertext'), DB::table('settings')->where('group', SmsSettings::group())->where('name', 'providers')->value('payload'));
    }

    public function test_enabling_requires_complete_credentials_and_removal_requires_disabling(): void
    {
        $page = Livewire::actingAs($this->admin(), 'admin')->test(SmsSettingsPage::class)
            ->fillForm(['enabled' => true, 'default_gateway' => 'yunpian'])
            ->call('save')->assertHasFormErrors(['providers.yunpian.api_key']);
        $page->fillForm(['providers.yunpian.api_key' => 'key-to-remove'])
            ->call('save')->assertHasNoFormErrors();
        $page->fillForm(['providers.yunpian.remove_credentials' => true])
            ->call('save')->assertHasFormErrors(['providers.yunpian.api_key']);
        $page->fillForm(['enabled' => false, 'providers.yunpian.remove_credentials' => true])
            ->call('save')->assertHasNoFormErrors();
        $this->assertArrayNotHasKey('api_key', (new SmsSettings)->providers['yunpian'] ?? []);
        $this->expectException(\LogicException::class);
        app(EasySms::class);
    }

    public function test_backup_requires_a_different_provider_and_complete_credentials(): void
    {
        $page = Livewire::actingAs($this->admin(), 'admin')->test(SmsSettingsPage::class)
            ->fillForm([
                'enabled' => true, 'default_gateway' => 'yunpian', 'failover_enabled' => true,
                'providers.yunpian.api_key' => 'primary-secret',
            ])->call('save')->assertHasFormErrors(['backup_gateway']);
        $page->fillForm(['backup_gateway' => 'yunpian'])->call('save')->assertHasFormErrors(['backup_gateway']);
        $page->fillForm(['backup_gateway' => 'aliyun'])->call('save')
            ->assertHasFormErrors(['providers.aliyun.access_key_id', 'providers.aliyun.access_key_secret', 'providers.aliyun.sign_name']);
        $page->fillForm([
            'providers.aliyun.access_key_id' => 'backup-id',
            'providers.aliyun.access_key_secret' => 'backup-secret',
            'providers.aliyun.sign_name' => 'MoonWeft',
        ])->call('save')->assertHasNoFormErrors()->assertDontSee('backup-secret');
        $this->assertSame(['yunpian', 'aliyun'], app(EasySms::class)->getConfig()->get('default.gateways'));
        $page->fillForm(['timeout' => 7])->call('save')->assertHasNoFormErrors();
        $this->assertSame('backup-secret', (new SmsSettings)->providers['aliyun']['access_key_secret']);
        $page->fillForm(['providers.aliyun.remove_credentials' => true])->call('save')
            ->assertHasFormErrors(['providers.aliyun.access_key_secret']);
        $page->fillForm(['failover_enabled' => false, 'providers.aliyun.remove_credentials' => true])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame(['yunpian'], app(EasySms::class)->getConfig()->get('default.gateways'));
    }

    public function test_a_stale_page_cannot_overwrite_the_backup_configuration(): void
    {
        $page = Livewire::actingAs($this->admin(), 'admin')->test(SmsSettingsPage::class);
        (new SmsSettings)->fill(['backup_gateway' => 'qiniu', 'failover_enabled' => true])->save();
        $page->fillForm(['backup_gateway' => 'yunpian'])->call('save')->assertHasErrors(['data']);
        $this->assertSame('qiniu', (new SmsSettings)->backup_gateway);
    }

    #[DataProvider('invalidConfigurations')]
    public function test_backend_rejects_invalid_configuration(array $values): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->expectException(ValidationException::class);
        (new SmsSettings)->fill($values)->save();
    }

    public static function invalidConfigurations(): array
    {
        return [
            'unknown gateway' => [['default_gateway' => 'errorlog']],
            'unknown backup' => [['backup_gateway' => 'errorlog']],
            'same backup' => [['backup_gateway' => 'aliyun']],
            'missing backup' => [['failover_enabled' => true]],
            'unsafe timeout' => [['timeout' => 0]],
            'custom endpoint' => [['providers' => ['aliyun' => ['endpoint' => 'http://127.0.0.1']]]],
        ];
    }

    #[DataProvider('gateways')]
    public function test_installed_gateway_receives_the_saved_configuration(string $gateway, array $credentials): void
    {
        $this->actingAs($this->admin(), 'admin');
        (new SmsSettings)->fill(['enabled' => true, 'default_gateway' => $gateway, 'providers' => [$gateway => $credentials]])->save();
        $client = app(EasySms::class);
        foreach ($credentials as $field => $value) {
            $this->assertSame($value, $client->gateway($gateway)->getConfig()->get($field));
        }
        $this->assertSame([$gateway], $client->getConfig()->get('default.gateways'));
    }

    public static function gateways(): array
    {
        return [
            ['aliyun', ['access_key_id' => 'id', 'access_key_secret' => 'secret', 'sign_name' => 'MoonWeft']],
            ['qcloud', ['sdk_app_id' => '1400000001', 'secret_id' => 'id', 'secret_key' => 'secret', 'sign_name' => 'MoonWeft']],
            ['qiniu', ['access_key' => 'id', 'secret_key' => 'secret']],
            ['yunpian', ['api_key' => 'key']],
        ];
    }

    public function test_sms_copy_and_validation_follow_locale_changes(): void
    {
        $admin = $this->admin();
        foreach (['zh_CN', 'en', 'zh_CN'] as $locale) {
            app()->setLocale($locale);
            $english = $locale === 'en';
            $page = Livewire::actingAs($admin, 'admin')->test(SmsSettingsPage::class)
                ->assertSee($english ? 'Primary provider' : '主服务商')
                ->assertSee($english ? 'Backup provider' : '备用服务商')
                ->assertSee($english ? 'Automatic failover' : '自动切换')
                ->assertSee($english ? 'Alibaba Cloud' : '阿里云')
                ->assertSee($english ? 'SMS signature' : '短信签名')
                ->fillForm(['enabled' => true])->call('save')
                ->assertHasFormErrors(['providers.aliyun.sign_name']);
            self::assertSame($english ? 'SMS settings' : '短信配置', $page->instance()->getTitle());
            self::assertSame($english ? 'SMS' : '短信', SmsSettingsPage::getNavigationLabel());
            self::assertSame('preferences', SmsSettingsPage::getNavigationGroup());
            self::assertStringContainsString($english ? 'Alibaba Cloud SMS signature' : '阿里云 短信签名', $page->instance()->getErrorBag()->first('data.providers.aliyun.sign_name'));
            $settings = new SmsSettings;
            $settings->timeout += 1;
            $settings->save();
            $page->fillForm(['enabled' => false])->call('save')->assertHasErrors(['data']);
            self::assertSame(__('moonweft-preference::sms.errors.stale'), $page->instance()->getErrorBag()->first('data'));
        }
    }

    public function test_sms_errors_and_translation_catalogues_cover_both_locales(): void
    {
        $english = Arr::dot(require __DIR__.'/../../resources/lang/en/sms.php');
        $chinese = Arr::dot(require __DIR__.'/../../resources/lang/zh_CN/sms.php');
        self::assertSame(array_keys($english), array_keys($chinese));
        foreach (['en', 'zh_CN'] as $locale) {
            app()->setLocale($locale);
            foreach ($english as $key => $value) {
                self::assertTrue(app('translator')->hasForLocale('moonweft-preference::sms.'.$key, $locale), $key);
            }
            try {
                (new SmsSettings)->configuration();
                self::fail('Disabled SMS must reject sending.');
            } catch (\LogicException $exception) {
                self::assertSame(__('moonweft-preference::sms.errors.disabled'), $exception->getMessage());
            }
            try {
                (new SmsClient(['default' => ['gateways' => ['aliyun', 'qcloud', 'qiniu']]]))->send('13800138000', []);
                self::fail('More than two gateways must be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame(__('moonweft-preference::sms.errors.invalid_gateways'), $exception->getMessage());
            }
        }
        DB::table('settings')->where('group', SmsSettings::group())->where('name', 'providers')
            ->update(['payload' => json_encode('invalid-ciphertext')]);
        app()->setLocale('en');
        Livewire::actingAs($this->admin(), 'admin')->test(SmsSettingsPage::class)
            ->assertSee('SMS settings are temporarily unavailable')
            ->assertSee('Saved credentials could not be decrypted.')->call('save')->assertForbidden();
    }

    private function admin(string $permission = PreferencePermissionContributor::MANAGE_SMS): Admin
    {
        $admin = Admin::factory()->create();
        $role = Role::create(['name' => 'sms-settings-'.$admin->id, 'guard_name' => 'admin']);
        $role->givePermissionTo(IamPermission::PanelAccess->value, $permission);
        if (class_exists(AccountAuthorization::class)) {
            AccountAuthorization::create(['principal_provider' => 'admins', 'principal_id' => (string) $admin->id,
                'role_ids' => [$role->id], 'organization_ids' => []]);
        } else {
            $admin->assignRole($role);
        }

        return $admin;
    }
}
