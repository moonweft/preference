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
use MoonWeft\Iam\SecurityConfirmation;
use Moonweft\Preference\Filament\Pages\SecuritySettingsPage;
use Moonweft\Preference\PreferencePermissionContributor;
use Moonweft\Preference\Security\SecuritySettings;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class AccountSecuritySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ini_set('memory_limit', '256M');
        app(PermissionSynchronizer::class)->sync();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_saved_password_policy_enforces_each_selected_laravel_password_requirement(): void
    {
        $admin = Admin::factory()->create();
        $this->grant($admin);
        $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        Livewire::actingAs($admin, 'admin')->test(SecuritySettingsPage::class)
            ->fillForm(['password_min_length' => 16, 'password_letters' => true, 'password_mixed_case' => true,
                'password_numbers' => true, 'password_symbols' => true, 'mfa_required' => true,
                'confirmation' => (new Google2FA)->getCurrentOtp('JBSWY3DPEHPK3PXP')])
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
            $html = $page->html();
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
        app(SecurityConfirmation::class)->verifyCurrentCredential('password', null);
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

    public function test_security_save_requires_recent_identity_proof_in_the_page_and_backend(): void
    {
        $admin = Admin::factory()->create();
        $this->grant($admin);
        $page = Livewire::actingAs($admin, 'admin')->test(SecuritySettingsPage::class)
            ->assertFormFieldIsVisible('confirmation_password')->assertFormFieldIsHidden('confirmation');
        $page->fillForm(['password_min_length' => 16])->call('save')->assertHasErrors(['data.confirmation_password']);
        try {
            $settings = new SecuritySettings;
            $settings->password_min_length = 18;
            $settings->save();
            self::fail('Calling the settings service must not bypass recent identity proof.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('data.confirmation', $exception->errors());
        }
        $page->fillForm(['confirmation_password' => 'incorrect'])->call('save')->assertHasErrors(['data.confirmation_password']);
        $page->fillForm(['confirmation_password' => 'password'])->set('data.confirmation', 'stale-code')
            ->call('save')->assertHasNoErrors();
        self::assertSame(16, (new SecuritySettings)->password_min_length);
        self::assertArrayNotHasKey('confirmation_password', (new SecuritySettings)->toArray());
        self::assertNull($page->get('data.confirmation_password'));
        self::assertNull($page->get('data.confirmation'));
    }

    public function test_enabling_mfa_without_an_authenticator_reports_the_policy_field(): void
    {
        $admin = Admin::factory()->create();
        $this->grant($admin);
        Livewire::actingAs($admin, 'admin')->test(SecuritySettingsPage::class)
            ->fillForm(['mfa_required' => true, 'confirmation_password' => 'password'])
            ->call('save')->assertHasErrors(['data.mfa_required'])->assertFormFieldIsHidden('confirmation');
        self::assertFalse((new SecuritySettings)->mfa_required);
    }

    public function test_sso_only_accounts_need_an_enrolled_authenticator_for_confirmation(): void
    {
        $admin = Admin::factory()->create();
        $this->grant($admin);
        $admin->forceFill(['password_login_enabled' => false])->save();
        Livewire::actingAs($admin, 'admin')->test(SecuritySettingsPage::class)
            ->assertFormFieldIsHidden('confirmation_password')->assertFormFieldIsHidden('confirmation')
            ->assertSee(__('moonweft-preference::security.confirmation.unavailable_description'))
            ->fillForm(['password_min_length' => 16])->set('data.confirmation_password', 'password')
            ->call('save')->assertHasErrors(['data.mfa_required']);
        self::assertSame(12, (new SecuritySettings)->password_min_length);

        $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        Livewire::test(SecuritySettingsPage::class)
            ->assertFormFieldIsHidden('confirmation_password')->assertFormFieldIsVisible('confirmation')
            ->fillForm(['password_min_length' => 16, 'confirmation' => (new Google2FA)->getCurrentOtp('JBSWY3DPEHPK3PXP')])
            ->call('save')->assertHasNoErrors();
        self::assertSame(16, (new SecuritySettings)->password_min_length);
    }

    public function test_direct_backend_write_requires_recent_identity_confirmation(): void
    {
        $admin = Admin::factory()->create();
        $this->grant($admin);
        $this->actingAs($admin, 'admin');
        $this->expectException(ValidationException::class);
        (new SecuritySettings)->save();
    }

    public function test_all_security_settings_reach_the_runtime_policy_and_reject_invalid_values(): void
    {
        $admin = Admin::factory()->create();
        $this->grant($admin);
        $page = Livewire::actingAs($admin, 'admin')->test(SecuritySettingsPage::class)->fillForm([
            'password_history_count' => 6, 'password_max_age_days' => 90, 'force_initial_password_change' => true,
            'login_max_attempts' => 4, 'login_window_seconds' => 300, 'login_lockout_seconds' => 600,
            'session_idle_minutes' => 30, 'session_absolute_minutes' => 480,
            'remember_login_enabled' => false, 'remember_login_days' => 7,
            'notify_login' => true, 'notify_password_changed' => true, 'confirmation_password' => 'password',
        ])->call('save')->assertHasNoErrors();
        app()->forgetInstance(SecuritySettings::class);
        $policy = app(AccountSecurityPolicy::class);
        self::assertSame([6, 90, true, 4, 300, 600, 30, 480, false, 7, true, true], [
            $policy->passwordHistoryCount(), $policy->passwordMaxAgeDays(), $policy->forceInitialPasswordChange(),
            $policy->loginMaxAttempts(), $policy->loginWindowSeconds(), $policy->loginLockoutSeconds(),
            $policy->sessionIdleMinutes(), $policy->sessionAbsoluteMinutes(), $policy->rememberLoginEnabled(),
            $policy->rememberLoginDays(), $policy->notifyLogin(), $policy->notifyPasswordChanged(),
        ]);
        $page->fillForm(['password_history_count' => 0, 'password_max_age_days' => 0,
            'session_absolute_minutes' => 0, 'confirmation_password' => 'password'])
            ->call('save')->assertHasNoErrors();
        app()->forgetInstance(SecuritySettings::class);
        self::assertSame([0, 0, 0], [
            $policy->passwordHistoryCount(), $policy->passwordMaxAgeDays(), $policy->sessionAbsoluteMinutes(),
        ]);
        foreach (['password_history_count' => 25, 'password_max_age_days' => -1, 'login_max_attempts' => 0,
            'login_window_seconds' => 0, 'login_lockout_seconds' => 86401, 'session_idle_minutes' => 0,
            'session_absolute_minutes' => -1, 'remember_login_days' => 401] as $field => $value) {
            $settings = new SecuritySettings;
            $settings->{$field} = $value;
            try {
                $settings->save();
                self::fail('Invalid security limits must not be stored.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey($field, $exception->errors());
            }
        }
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
