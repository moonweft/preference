<?php

declare(strict_types=1);

namespace Moonweft\Preference\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use MoonWeft\Iam\BootstrapDeveloper;
use MoonWeft\Iam\Models\Admin;
use MoonWeft\Iam\PermissionSynchronizer;
use Moonweft\Preference\Ai\AiConfiguration;
use Moonweft\Preference\Ai\AiSettings;
use Moonweft\Preference\Filament\Pages\AiSettingsPage;
use Tests\TestCase;

final class AiSettingsTranslationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_provider_tabs_and_limits_follow_the_current_language(): void
    {
        ini_set('memory_limit', '256M');
        Http::preventStrayRequests();
        app(PermissionSynchronizer::class)->sync();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = Admin::factory()->create();
        app(BootstrapDeveloper::class)->initialize($admin, 'AI language test');

        foreach (['en', 'zh_CN', 'en'] as $locale) {
            app()->setLocale($locale);
            $page = Livewire::actingAs($admin, 'admin')->test(AiSettingsPage::class)->assertSuccessful();
            $html = $page->instance()->form->toHtml();
            foreach ($locale === 'en' ? [
                'Default settings', 'Usage limits', 'Development debugging', 'Requests per minute',
                'Estimated tokens', 'OpenAI-compatible API', 'Text generation model',
                'Text generation deployment', 'Transcription model', 'Reasoning effort', 'Remove saved API key',
            ] : ['默认配置', '使用限制', '开发调试', '每分钟请求次数', '预估 token', 'OpenAI 兼容接口',
                '文本生成模型', '文本生成部署名称', '语音转文字模型', '推理强度', '移除已保存的密钥'] as $label) {
                self::assertStringContainsString($label, $html);
            }
            self::assertStringNotContainsString('moonweft-preference::', $html);
            if ($locale === 'en') {
                self::assertDoesNotMatchRegularExpression('/\p{Han}/u', html_entity_decode($html));
            }

            $settings = new AiSettings;
            $settings->limits = ['requests_per_day' => $locale === 'en' ? 200 : 300];
            $settings->save();
            $page->call('save')->assertHasErrors(['data']);
            self::assertSame($locale === 'en'
                ? 'AI settings were changed by another administrator. Refresh the page and try again.'
                : 'AI 配置已被其他管理员修改，请刷新页面后重试。', $page->instance()->getErrorBag()->first('data'));
        }
    }

    public function test_missing_default_provider_details_use_localised_validation_messages(): void
    {
        config(['ai.providers.openai-compatible.url' => null, 'ai.providers.openai-compatible.models' => []]);
        app()->forgetInstance(AiConfiguration::class);

        foreach (['en', 'zh_CN', 'en'] as $locale) {
            app()->setLocale($locale);
            $settings = new AiSettings;
            $settings->defaults = ['text' => 'openai-compatible'];
            try {
                $settings->save();
                self::fail('Incomplete default provider configuration must be rejected.');
            } catch (ValidationException $exception) {
                self::assertSame($locale === 'en'
                    ? 'A default provider requires an endpoint URL and the model for the selected capability.'
                    : '作为默认服务商时，必须填写接口地址及对应模型。', $exception->errors()['providers.openai-compatible.url'][0]);
            }
        }
    }
}
