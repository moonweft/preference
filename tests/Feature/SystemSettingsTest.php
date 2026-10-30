<?php

declare(strict_types=1);

namespace Moonweft\Preference\Tests\Feature;

use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use MoonWeft\Iam\IamPermission;
use MoonWeft\Iam\Models\AccountAuthorization;
use MoonWeft\Iam\Models\Admin;
use MoonWeft\Iam\Models\User;
use MoonWeft\Iam\PermissionSynchronizer;
use Moonweft\Preference\Filament\Pages\AiSettingsPage;
use Moonweft\Preference\Filament\Pages\SystemSettingsPage;
use Moonweft\Preference\Filament\Pages\ThemeSettingsPage;
use Moonweft\Preference\PreferencePermissionContributor;
use Moonweft\Preference\System\Appearance;
use Moonweft\Preference\System\SystemSettings;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionSynchronizer::class)->sync();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_administrator_can_save_system_time_preferences(): void
    {
        $admin = Admin::factory()->create();
        $this->grant($admin);

        Livewire::actingAs($admin, 'admin')->test(SystemSettingsPage::class)
            ->assertSuccessful()
            ->fillForm(['timezone' => 'Asia/Shanghai', 'date_format' => 'd/m/Y', 'time_format' => 'h:i A'])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame([
            'timezone' => 'Asia/Shanghai', 'date_format' => 'd/m/Y', 'time_format' => 'h:i A',
            'primary_color' => 'blue', 'gray_color' => 'slate', 'panel_theme' => 'sharp', 'font_scale' => 125,
        ], (new SystemSettings)->toArray());
    }

    public function test_reading_and_saving_require_current_system_permission(): void
    {
        $admin = Admin::factory()->create();
        $role = $this->grant($admin, PreferencePermissionContributor::MANAGE_AI);
        Livewire::actingAs($admin, 'admin')->test(SystemSettingsPage::class)->assertForbidden();

        $role->givePermissionTo(PreferencePermissionContributor::MANAGE_SYSTEM);
        $page = Livewire::actingAs($admin, 'admin')->test(SystemSettingsPage::class);
        $role->revokePermissionTo(PreferencePermissionContributor::MANAGE_SYSTEM);
        $page->call('save')->assertForbidden();
    }

    public function test_administrator_can_save_theme_and_colours_and_reload_the_page(): void
    {
        $admin = Admin::factory()->create();
        $this->grant($admin);

        foreach (Appearance::COLOR_SCHEMES as $scheme => $colors) {
            Livewire::actingAs($admin, 'admin')->test(ThemeSettingsPage::class)
                ->fillForm(['panel_theme' => 'orbit', 'color_scheme' => $scheme])
                ->call('save')->assertHasNoFormErrors()->assertRedirect(ThemeSettingsPage::getUrl());

            $settings = new SystemSettings;
            $this->assertSame(['orbit', $colors['primary_color'], $colors['gray_color'], 'UTC', Color::all()[$colors['primary_color']]], [
                $settings->panel_theme, $settings->primary_color, $settings->gray_color, $settings->timezone, $settings->colors()['primary'],
            ]);
            Livewire::test(ThemeSettingsPage::class)->assertSet('data.color_scheme', $scheme);
        }
    }

    public function test_backend_rejects_invalid_theme_and_colour_values(): void
    {
        $admin = Admin::factory()->create();
        $this->grant($admin);
        $this->actingAs($admin, 'admin');
        $settings = new SystemSettings;
        $settings->primary_color = '</style><script>alert(1)</script>';
        $settings->gray_color = 'blue';
        $settings->panel_theme = '../unknown.css';

        try {
            $settings->save();
            $this->fail('Invalid appearance was saved.');
        } catch (ValidationException $exception) {
            $this->assertSame(['primary_color', 'gray_color', 'panel_theme'], array_keys($exception->errors()));
        }
    }

    public function test_saved_appearance_is_used_by_the_panel_and_public_login_page(): void
    {
        foreach (['primary_color' => 'violet', 'gray_color' => 'stone'] as $name => $value) {
            DB::table('settings')->where('group', 'preference_system')->where('name', $name)->update(['payload' => json_encode($value)]);
        }
        $panel = Filament::getPanel('admin');
        $this->assertSame([Color::Violet, Color::Stone, true, false, true], [
            $panel->getColors()['primary'], $panel->getColors()['gray'], $panel->hasDarkMode(),
            $panel->hasDarkModeForced(), $panel->hasThemeSwitcher(),
        ]);
        $this->withoutVite()->get('/admin/login')->assertSuccessful()
            ->assertDontSee('data-system-theme', false)
            ->assertSee('--primary-500:'.Color::Violet[500], false)
            ->assertSee('--gray-500:'.Color::Stone[500], false);
    }

    public function test_system_settings_copy_follows_the_current_language(): void
    {
        $admin = Admin::factory()->create();
        $this->grant($admin);
        foreach (['en' => ['System settings', 'System', 'preferences', 'Date and time'],
            'zh_CN' => ['系统设置', '系统', 'preferences', '日期与时间']] as $locale => $labels) {
            app()->setLocale($locale);
            $page = Livewire::actingAs($admin, 'admin')->test(SystemSettingsPage::class)->assertSee($labels[3]);
            $this->assertSame(array_slice($labels, 0, 3), [
                $page->instance()->getTitle(), SystemSettingsPage::getNavigationLabel(), SystemSettingsPage::getNavigationGroup(),
            ]);
        }
    }

    public function test_theme_navigation_shows_theme_cards_and_twenty_colour_schemes_without_selects(): void
    {
        $this->assertCount(20, Appearance::COLOR_SCHEMES);
        $admin = Admin::factory()->create();
        $this->grant($admin);
        foreach (['en' => 'Theme', 'zh_CN' => '主题'] as $locale => $label) {
            app()->setLocale($locale);
            $this->assertSame($label, ThemeSettingsPage::getNavigationLabel());
            $page = Livewire::actingAs($admin, 'admin')->test(ThemeSettingsPage::class)
                ->assertSee('Sharp')->assertSee('Orbit')->assertDontSee('<select', false);
            $labels = __('moonweft-preference::theme.schemes');
            foreach (array_keys(Appearance::COLOR_SCHEMES) as $key) {
                $this->assertArrayHasKey($key, $labels);
                $page->assertSee('data-appearance-option="palette:'.$key.'"', false)->assertSee($labels[$key]);
            }
        }
        Livewire::test(SystemSettingsPage::class)->assertDontSee('data-appearance-option', false);
    }

    public function test_theme_page_uses_the_native_personal_mode_switcher(): void
    {
        $this->assertTrue(Filament::getPanel('admin')->hasThemeSwitcher());
        $admin = Admin::factory()->create();
        $this->grant($admin);
        $this->withoutVite()->actingAs($admin, 'admin')->get(ThemeSettingsPage::getUrl())
            ->assertSuccessful()->assertSee('class="fi-theme-switcher"', false)
            ->assertDontSee('data.theme_mode', false)->assertDontSee('data-system-theme', false);
        $this->assertDatabaseMissing('settings', ['group' => 'preference_system', 'name' => 'theme_mode']);
    }

    public function test_theme_page_preserves_existing_colour_pairings_until_a_scheme_is_selected(): void
    {
        $admin = Admin::factory()->create();
        $this->grant($admin);
        $this->actingAs($admin, 'admin');
        $settings = app(SystemSettings::class);
        $settings->primary_color = 'violet';
        $settings->gray_color = 'stone';
        $settings->save();
        Livewire::test(ThemeSettingsPage::class)->assertSet('data.color_scheme', 'current')
            ->fillForm(['panel_theme' => 'orbit'])->call('save')->assertHasNoFormErrors();
        Livewire::test(SystemSettingsPage::class)->fillForm(['timezone' => 'Asia/Shanghai'])
            ->call('save')->assertHasNoFormErrors();
        $saved = new SystemSettings;
        $this->assertSame(['orbit', 'violet', 'stone', 'Asia/Shanghai'], [
            $saved->panel_theme, $saved->primary_color, $saved->gray_color, $saved->timezone,
        ]);
    }

    public function test_theme_page_rejects_stale_edits_invalid_schemes_and_revoked_permission(): void
    {
        $admin = Admin::factory()->create();
        $role = $this->grant($admin);
        $page = Livewire::actingAs($admin, 'admin')->test(ThemeSettingsPage::class);
        $page->fillForm(['color_scheme' => 'unknown'])->call('save')->assertHasFormErrors(['color_scheme']);
        $settings = new SystemSettings;
        $settings->timezone = 'Europe/London';
        $settings->save();
        $page->fillForm(['color_scheme' => 'emerald'])->call('save')->assertHasErrors(['data']);
        $role->revokePermissionTo(PreferencePermissionContributor::MANAGE_SYSTEM);
        $page->call('save')->assertForbidden();
        $this->assertSame('blue', (new SystemSettings)->primary_color);
    }

    public function test_switching_administrators_invalidates_the_editing_session(): void
    {
        $admins = Admin::factory()->count(2)->create();
        foreach ($admins as $admin) {
            $this->grant($admin);
        }
        $page = Livewire::actingAs($admins[0], 'admin')->test(SystemSettingsPage::class);
        Livewire::actingAs($admins[1], 'admin');
        $page->call('save')->assertForbidden();
    }

    public function test_web_identity_cannot_write_shared_settings(): void
    {
        $this->actingAs(User::factory()->create(), 'web');
        $this->expectException(HttpException::class);
        app(SystemSettings::class)->save();
    }

    public function test_stale_page_cannot_overwrite_new_settings_or_other_groups(): void
    {
        $admin = Admin::factory()->create();
        $this->grant($admin);
        $page = Livewire::actingAs($admin, 'admin')->test(SystemSettingsPage::class);
        $settings = new SystemSettings;
        $settings->timezone = 'Europe/London';
        $settings->save();

        $page->fillForm(['timezone' => 'Asia/Shanghai'])->call('save')->assertHasErrors(['data']);
        $this->assertSame('Europe/London', (new SystemSettings)->timezone);
        $this->assertDatabaseHas('settings', ['group' => 'preference_ai', 'name' => 'defaults', 'payload' => '[]']);
    }

    public function test_backend_rejects_invalid_timezone_and_formats(): void
    {
        $admin = Admin::factory()->create();
        $this->grant($admin);
        $this->actingAs($admin, 'admin');
        $settings = new SystemSettings;
        $settings->timezone = 'Invalid/Zone';
        $settings->date_format = '<script>';
        $settings->time_format = 'invalid';

        try {
            $settings->save();
            $this->fail('Invalid settings were saved.');
        } catch (ValidationException $exception) {
            $this->assertSame(['timezone', 'date_format', 'time_format'], array_keys($exception->errors()));
        }
    }

    public function test_filament_defaults_convert_instants_but_preserve_calendar_dates_and_storage_timezone(): void
    {
        app()->setLocale('en');
        foreach (['timezone' => 'America/New_York', 'date_format' => 'd/m/Y', 'time_format' => 'h:i A'] as $name => $value) {
            DB::table('settings')->where('group', 'preference_system')->where('name', $name)->update(['payload' => json_encode($value)]);
        }
        $schema = Schema::make()->components([
            TextEntry::make('instant')->dateTime(),
            TextEntry::make('date')->date(),
            DateTimePicker::make('input'),
            DatePicker::make('birthday'),
            TimePicker::make('opening_time'),
        ]);
        [$instant, $date, $input, $birthday, $time] = $schema->getComponents();

        $this->assertSame([
            'America/New_York', '13/10/2026 09:30 PM', '14/10/2026', 'd/m/Y h:i A',
            'America/New_York', 'UTC', 'UTC', 'UTC', false,
        ], [
            FilamentTimezone::get(), $instant->formatState('2026-10-14 01:30:00'), $date->formatState('2026-10-14'),
            $input->getDisplayFormat(), $input->getTimezone(), $birthday->getTimezone(), $time->getTimezone(), config('app.timezone'), $input->isNative(),
        ]);
    }

    public function test_missing_identity_cannot_write_shared_settings(): void
    {
        $this->expectException(HttpException::class);
        app(SystemSettings::class)->save();
    }

    public function test_disabled_administrator_cannot_save_an_open_page(): void
    {
        $admin = Admin::factory()->create();
        $this->grant($admin);
        $page = Livewire::actingAs($admin, 'admin')->test(SystemSettingsPage::class);
        $admin->update(['is_active' => false]);
        $page->call('save')->assertForbidden();
    }

    public function test_ai_page_still_uses_its_own_permission_with_the_backend_admin_provider(): void
    {
        $admin = Admin::factory()->create();
        $this->grant($admin, PreferencePermissionContributor::MANAGE_AI);
        Livewire::actingAs($admin, 'admin')->test(AiSettingsPage::class)->assertSuccessful();
    }

    public function test_table_defaults_and_picker_round_trip_preserve_utc_instants_across_daylight_saving(): void
    {
        DB::table('settings')->where('group', 'preference_system')->where('name', 'timezone')
            ->update(['payload' => json_encode('America/New_York')]);
        $column = TextColumn::make('created_at')->dateTime()->table(Table::make($this->createMock(HasTable::class)));
        $cast = DateTimePicker::make('instant')->getDefaultStateCasts()[0];
        foreach ([['2026-01-14 01:30:00', '2026-01-13 20:30:00'], ['2026-07-14 01:30:00', '2026-07-13 21:30:00']] as [$utc, $local]) {
            $this->assertSame([$local, $local, $utc], [$column->formatState($utc), $cast->set($utc), $cast->get($local)]);
        }
    }

    public function test_minute_display_format_does_not_discard_stored_seconds(): void
    {
        DB::table('settings')->where('group', 'preference_system')->where('name', 'time_format')
            ->update(['payload' => json_encode('H:i')]);
        $picker = DateTimePicker::make('instant');
        $cast = $picker->getDefaultStateCasts()[0];

        $this->assertSame(['Y-m-d H:i', '2026-10-14 01:30:45'], [
            $picker->getDisplayFormat(), $cast->get($cast->set('2026-10-14 01:30:45')),
        ]);
    }

    private function grant(Admin $admin, string $permission = PreferencePermissionContributor::MANAGE_SYSTEM): Role
    {
        $role = Role::create(['name' => 'system-settings-'.$admin->id, 'guard_name' => 'admin']);
        $role->givePermissionTo(IamPermission::PanelAccess->value, $permission);
        if (! class_exists(AccountAuthorization::class)) {
            $admin->assignRole($role);

            return $role;
        }
        AccountAuthorization::create([
            'principal_provider' => 'admins', 'principal_id' => (string) $admin->id,
            'role_ids' => [$role->id], 'organization_ids' => [],
        ]);

        return $role;
    }
}
