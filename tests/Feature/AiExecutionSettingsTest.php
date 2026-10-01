<?php

declare(strict_types=1);

namespace Moonweft\Preference\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use MoonWeft\Ai\Services\RequestLimits;
use MoonWeft\Iam\Models\Admin;
use MoonWeft\Iam\PermissionSynchronizer;
use MoonWeft\Iam\RoleNames;
use Moonweft\Preference\Ai\AiConfiguration;
use Moonweft\Preference\Ai\AiSettings;
use Moonweft\Preference\Filament\Pages\AiSettingsPage;
use Tests\TestCase;

final class AiExecutionSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_execution_budgets_and_model_profiles_round_trip_through_the_authorised_page(): void
    {
        app(PermissionSynchronizer::class)->sync();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = Admin::factory()->create();
        $admin->assignRole(RoleNames::SuperAdmin);
        $limits = ['max_attachment_files' => 2, 'max_attachment_kb' => 5120,
            'max_attachment_context_kb' => 16384, 'max_pdf_pages' => 6, 'max_attachment_images' => 12,
            'max_steps' => 5, 'max_tool_calls' => 10, 'max_tool_calls_per_tool' => 2,
            'max_tool_result_bytes' => 32768, 'max_run_seconds' => 90, 'max_run_tokens' => 32000];
        Livewire::actingAs($admin, 'admin')->test(AiSettingsPage::class)
            ->set('data.limits', $limits)
            ->set('data.providers.deepseek.models.text.simple', 'evaluated-small')
            ->set('data.providers.deepseek.models.text.complex', 'evaluated-large')
            ->call('save')->assertHasNoErrors();
        self::assertSame($limits, (new AiSettings)->limits);
        self::assertSame(5, app(RequestLimits::class)->all()['max_steps']);
        $before = config('ai.providers.deepseek.models.text.complex');
        app(AiConfiguration::class)->run(function (): void {
            self::assertSame('evaluated-small', config('ai.providers.deepseek.models.text.simple'));
            self::assertSame('evaluated-large', config('ai.providers.deepseek.models.text.complex'));
        });
        self::assertSame($before, config('ai.providers.deepseek.models.text.complex'));
    }
}
