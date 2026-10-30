<?php

declare(strict_types=1);

namespace Moonweft\Preference\Tests\Feature;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use MoonWeft\Iam\IamPermission;
use MoonWeft\Iam\Models\AccountAuthorization;
use MoonWeft\Iam\Models\Admin;
use MoonWeft\Iam\PermissionSynchronizer;
use Moonweft\Preference\Filament\Pages\ThemeSettingsPage;
use Moonweft\Preference\PreferencePermissionContributor;
use Moonweft\Preference\System\SystemSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class SystemTypographyTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_scale_is_available_on_public_login(): void
    {
        $this->assertSame(125, app(SystemSettings::class)->font_scale);
        $this->withoutVite()->get('/admin/login')->assertSuccessful()
            ->assertSee('--moonweft-font-scale: 1.25', false);
    }

    public static function scales(): array
    {
        return [
            ['sharp', 100], ['sharp', 110], ['sharp', 125], ['sharp', 150],
            ['orbit', 100], ['orbit', 110], ['orbit', 125], ['orbit', 150],
        ];
    }

    #[DataProvider('scales')]
    public function test_authorised_admin_can_preview_and_save_font_scale(string $theme, int $scale): void
    {
        $this->authorise();
        Livewire::test(ThemeSettingsPage::class)
            ->assertSee('data-font-preview', false)
            ->fillForm(['panel_theme' => $theme, 'font_scale' => $scale])
            ->call('save')->assertHasNoFormErrors()->assertRedirect(ThemeSettingsPage::getUrl());
        $this->assertSame($scale, (new SystemSettings)->font_scale);
    }

    public function test_reset_only_changes_the_form_and_backend_enforces_validation_and_permission(): void
    {
        $role = $this->authorise();
        $settings = new SystemSettings;
        $settings->font_scale = 150;
        $settings->save();
        Livewire::test(ThemeSettingsPage::class)->callAction(TestAction::make('resetTypography')->schemaComponent('typography', schema: 'form'))
            ->assertSet('data.font_scale', 125);
        $this->assertSame(150, (new SystemSettings)->font_scale);

        $settings = new SystemSettings;
        $settings->font_scale = 999;
        try {
            $settings->save();
            $this->fail('Invalid scale was saved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('font_scale', $exception->errors());
        }
        $page = Livewire::test(ThemeSettingsPage::class)->fillForm(['font_scale' => 100]);
        $role->revokePermissionTo(PreferencePermissionContributor::MANAGE_SYSTEM);
        $page->call('save')->assertForbidden();
        $this->assertSame(150, (new SystemSettings)->font_scale);
    }

    private function authorise(): Role
    {
        app(PermissionSynchronizer::class)->sync();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = Admin::factory()->create();
        $role = Role::create(['name' => 'typography', 'guard_name' => 'admin']);
        $role->givePermissionTo(IamPermission::PanelAccess->value, PreferencePermissionContributor::MANAGE_SYSTEM);
        AccountAuthorization::create([
            'principal_provider' => 'admins', 'principal_id' => (string) $admin->id,
            'role_ids' => [$role->id], 'organization_ids' => [],
        ]);
        $this->actingAs($admin, 'admin');

        return $role;
    }
}
