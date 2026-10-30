<?php

declare(strict_types=1);

namespace Moonweft\Preference\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use MoonWeft\Employee\Modules\Organization\Domain\Models\Organization;
use MoonWeft\Iam\AccountSecurityPolicy;
use MoonWeft\Iam\IamPermission;
use MoonWeft\Iam\Models\AccountAuthorization;
use MoonWeft\Iam\Models\Admin;
use MoonWeft\Iam\Models\User;
use MoonWeft\Iam\PermissionSynchronizer;
use Moonweft\Preference\Filament\Pages\SecuritySettingsPage;
use Moonweft\Preference\PreferencePermissionContributor;
use Moonweft\Preference\Security\SecuritySettings;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class AccountSecuritySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionSynchronizer::class)->sync();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_saved_password_policy_enforces_each_selected_laravel_password_requirement(): void
    {
        $admin = Admin::factory()->create();
        $this->grant($admin);
        Livewire::actingAs($admin, 'admin')->test(SecuritySettingsPage::class)
            ->fillForm(['password_min_length' => 16, 'password_letters' => true, 'password_mixed_case' => true,
                'password_numbers' => true, 'password_symbols' => true, 'mfa_required' => true])
            ->call('save')->assertHasNoFormErrors();
        app()->forgetInstance(SecuritySettings::class);

        $policy = app(AccountSecurityPolicy::class);
        $results = [];
        foreach (['Abc123!short', '123456789012345!', 'alllowercase1234!', 'ALLUPPERCASE1234!',
            'NoNumbersAtAll!!!!', 'NoSymbolsAtAll123', 'CorrectHorse42!!'] as $password) {
            $results[] = Validator::make(['password' => $password], ['password' => $policy->passwordRule()])->passes();
        }
        self::assertSame([false, false, false, false, false, false, true, true], [...$results, $policy->requiresMultiFactorAuthentication()]);
    }

    public function test_account_security_copy_follows_the_current_language(): void
    {
        $admin = Admin::factory()->create();
        $this->grant($admin);
        foreach (['en', 'zh_CN', 'en'] as $locale) {
            app()->setLocale($locale);
            $page = Livewire::actingAs($admin, 'admin')->test(SecuritySettingsPage::class)->assertSuccessful();
            $html = $page->instance()->form->toHtml();
            self::assertStringContainsString(__('moonweft-preference::security.password.title'), $html);
            self::assertStringContainsString(__('moonweft-preference::security.fields.mfa_required'), $html);
            self::assertStringNotContainsString('moonweft-preference::', $html);
            if ($locale === 'en') {
                self::assertDoesNotMatchRegularExpression('/\p{Han}/u', html_entity_decode($html));
            }
        }
    }

    public function test_platform_policy_cannot_be_changed_by_organisation_members_or_the_web_guard(): void
    {
        $before = (new SecuritySettings)->toArray();
        foreach (['organisation-a', 'organisation-b'] as $code) {
            $organisation = Organization::create(['name' => $code, 'code' => $code, 'is_active' => true]);
            $admin = Admin::factory()->create();
            $this->grant($admin, PreferencePermissionContributor::MANAGE_SYSTEM, [$organisation->id]);
            Livewire::actingAs($admin, 'admin')->test(SecuritySettingsPage::class)->assertForbidden();
            $settings = new SecuritySettings;
            $settings->mfa_required = true;
            try {
                $settings->save();
                self::fail('Organisation membership must not grant platform security management.');
            } catch (HttpException $exception) {
                self::assertSame(403, $exception->getStatusCode());
            }
        }
        auth('admin')->logout();
        $this->actingAs(User::factory()->create(), 'web');
        try {
            (new SecuritySettings)->save();
            self::fail('The web guard must not grant backend security management.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }
        self::assertSame($before, (new SecuritySettings)->toArray());
    }

    public function test_missing_identity_cannot_write_policy_directly(): void
    {
        $this->expectException(HttpException::class);
        (new SecuritySettings)->save();
    }

    public function test_backend_rejects_password_lengths_outside_the_supported_range(): void
    {
        $admin = Admin::factory()->create();
        $this->grant($admin);
        $this->actingAs($admin, 'admin');
        foreach ([11, 73] as $length) {
            $settings = new SecuritySettings;
            $settings->password_min_length = $length;
            try {
                $settings->save();
                self::fail('An unsupported password length was saved.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('password_min_length', $exception->errors());
            }
        }
        self::assertSame(12, (new SecuritySettings)->password_min_length);
    }

    public function test_stale_page_does_not_overwrite_a_newer_security_policy(): void
    {
        $admin = Admin::factory()->create();
        $this->grant($admin);
        $page = Livewire::actingAs($admin, 'admin')->test(SecuritySettingsPage::class);
        $settings = new SecuritySettings;
        $settings->password_min_length = 20;
        $settings->save();

        $page->fillForm(['password_min_length' => 16])->call('save')->assertHasErrors(['data']);
        self::assertSame(20, (new SecuritySettings)->password_min_length);
    }

    public function test_open_page_cannot_be_saved_after_permission_revocation_or_identity_change(): void
    {
        $first = Admin::factory()->create();
        $second = Admin::factory()->create();
        $role = $this->grant($first);
        $this->grant($second);
        $page = Livewire::actingAs($first, 'admin')->test(SecuritySettingsPage::class);
        $this->actingAs($second, 'admin');
        $page->call('save')->assertForbidden();

        $page = Livewire::actingAs($first, 'admin')->test(SecuritySettingsPage::class);
        $role->revokePermissionTo(PreferencePermissionContributor::MANAGE_SECURITY);
        $page->call('save')->assertForbidden();
        $this->expectException(HttpException::class);
        (new SecuritySettings)->save();
    }

    private function grant(Admin $admin, string $permission = PreferencePermissionContributor::MANAGE_SECURITY, array $organisations = []): Role
    {
        $role = Role::create(['name' => 'account-security-'.$admin->id, 'guard_name' => 'admin']);
        $role->givePermissionTo(IamPermission::PanelAccess->value, $permission);
        AccountAuthorization::create(['principal_provider' => 'admins', 'principal_id' => (string) $admin->id,
            'role_ids' => [$role->id], 'organization_ids' => $organisations]);

        return $role;
    }
}
