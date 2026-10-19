<?php

declare(strict_types=1);

namespace Moonweft\Preference\Tests\Feature;

use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Laravel\Ai\AiManager;
use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\Contracts\Client;
use Laravel\Octane\Events\WorkerErrorOccurred;
use Laravel\Octane\Exceptions\TaskExceptionResult;
use Laravel\Octane\Listeners\ReportException;
use Laravel\Octane\OctaneResponse;
use Laravel\Octane\RequestContext;
use Laravel\Octane\Worker;
use MoonWeft\Iam\IamPermission;
use MoonWeft\Iam\Models\AccountAuthorization;
use MoonWeft\Iam\Models\Admin;
use MoonWeft\Iam\PermissionSynchronizer;
use Moonweft\Preference\Ai\AiConfiguration;
use Moonweft\Preference\Ai\AiSettings;
use Moonweft\Preference\Filament\Pages\SystemSettingsPage;
use Moonweft\Preference\PreferencePermissionContributor;
use Moonweft\Preference\SettingsAccess;
use Moonweft\Preference\System\SystemSettings;
use PHPUnit\Framework\TestCase;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class SystemSettingsOctaneTest extends TestCase
{
    private Worker $worker;

    private array $responses = [];

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['APP_RUNNING_IN_CONSOLE'] = false;
        $client = $this->createMock(Client::class);
        $client->method('respond')->willReturnCallback(function (RequestContext $context, OctaneResponse $response): void {
            $this->responses[] = $response->response;
        });
        $client->method('error')->willReturnCallback(function (Throwable $error, Application $app, Request $request, RequestContext $context): void {
            $this->responses[] = new Response($error->getMessage(), 500);
        });
        $this->worker = new Worker(new ApplicationFactory(getcwd()), $client);
        $this->worker->boot();
    }

    protected function tearDown(): void
    {
        $this->worker->terminate();
        unset($_ENV['APP_RUNNING_IN_CONSOLE']);
        parent::tearDown();
    }

    public function test_shared_settings_refresh_and_authority_does_not_leak_between_requests_and_tasks(): void
    {
        self::assertSame(0, Artisan::call('migrate', ['--force' => true]));
        app(PermissionSynchronizer::class)->sync();
        $admins = Admin::factory()->count(2)->create();
        $role = Role::create(['name' => 'system-settings-octane', 'guard_name' => 'admin']);
        $role->givePermissionTo(IamPermission::PanelAccess->value, PreferencePermissionContributor::MANAGE_SYSTEM);
        foreach ($admins as $admin) {
            if (! class_exists(AccountAuthorization::class)) {
                $admin->assignRole($role);

                continue;
            }
            AccountAuthorization::create(['principal_provider' => 'admins', 'principal_id' => (string) $admin->id,
                'role_ids' => [$role->id], 'organization_ids' => []]);
        }

        // Warm the worker instance to catch callbacks retaining its initial settings.
        self::assertSame('UTC', $this->worker->application()->make(SystemSettings::class)->timezone);
        $this->worker->application()->make('router')->match(['GET', 'POST'], '/octane-test/system-settings', static function (Request $request): array {
            $before = auth('admin')->id();
            Filament::setCurrentPanel(Filament::getPanel('admin'));
            if ($actor = $request->attributes->get('octane_test_actor')) {
                auth('admin')->setUser($actor);
            }
            $settings = app(SystemSettings::class);
            $previousTimezone = $settings->timezone;
            if ($request->isMethod('POST')) {
                $settings->timezone = $request->input('timezone');
                $settings->date_format = 'd/m/Y';
                $settings->time_format = 'H:i';
                abort_if($request->boolean('fail'), 500, 'Synthetic settings failure');
                $settings->save();
            }

            return [$before, $previousTimezone, FilamentTimezone::get(),
                $settings->formatDateTime('2026-10-14 01:30:00'),
                Schema::make()->getDefaultDateTimeDisplayFormat(), DateTimePicker::make('instant')->getTimezone(),
                config('app.timezone'), date_default_timezone_get(), SystemSettingsPage::canAccess()];
        });

        $previous = 'UTC';
        foreach ([[0, 'Asia/Shanghai', '09:30'], [1, 'Europe/London', '02:30'], [0, 'Asia/Shanghai', '09:30']] as [$index, $timezone, $time]) {
            self::assertSame([null, $previous, $timezone, '14/10/2026 '.$time, 'd/m/Y H:i', $timezone, 'UTC', 'UTC', true],
                $this->request($admins[$index], ['timezone' => $timezone]));
            $previous = $timezone;
        }

        $this->request($admins[0], ['timezone' => 'America/New_York', 'fail' => true], 500);
        $this->request(null, ['timezone' => 'America/New_York'], 403);
        $admins[1]->update(['is_active' => false]);
        $this->request($admins[1], ['timezone' => 'America/New_York'], 403);
        self::assertSame([null, 'Asia/Shanghai', 'Asia/Shanghai', '14/10/2026 09:30', 'd/m/Y H:i', 'Asia/Shanghai', 'UTC', 'UTC', false],
            $this->request());
        self::assertTrue($this->request($admins[0])[8]);

        self::assertSame(['Asia/Shanghai', false], $this->worker->handleTask(static fn (): array => [
            app(SystemSettings::class)->timezone, SystemSettingsPage::canAccess(),
        ])->result);
        // Capture the expected worker error instead of writing to PHPUnit's child-process stderr.
        $reporter = $this->createMock(ReportException::class);
        $reporter->expects(self::once())->method('handle')->with(self::callback(
            static fn (WorkerErrorOccurred $event): bool => $event->exception->getMessage() === 'Synthetic task failure',
        ));
        $this->worker->application()->instance(ReportException::class, $reporter);
        self::assertInstanceOf(TaskExceptionResult::class, $this->worker->handleTask(static function (): void {
            app(SystemSettings::class)->timezone = 'Europe/London';
            throw new \RuntimeException('Synthetic task failure');
        }));
        self::assertSame('Asia/Shanghai', $this->worker->handleTask(static fn (): string => app(SystemSettings::class)->timezone)->result);
        self::assertSame('UTC', $this->worker->application()->make('config')->get('app.timezone'));
    }

    public function test_ai_reasoning_settings_refresh_and_restore_in_one_worker_after_failure(): void
    {
        self::assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $actors = Admin::factory()->count(2)->create();
        $baseline = config('ai.providers.deepseek.reasoning_effort');
        $this->worker->application()->make('router')->get('/octane-test/ai-effort', static function (Request $request): array {
            abort_unless($request->attributes->get('octane_test_actor') instanceof Admin, 403);
            $effort = $request->attributes->get('octane_test_effort');
            $settings = new AiSettings;
            $settings->providers = $effort === null ? [] : ['deepseek' => ['reasoning_effort' => $effort]];
            $settings->save();
            $before = config('ai.providers.deepseek.reasoning_effort');
            try {
                $effort = app(AiConfiguration::class)->run(static function () use ($request): mixed {
                    abort_if($request->boolean('fail'), 500, 'Synthetic AI configuration failure');

                    return config('ai.providers.deepseek.reasoning_effort');
                });
            } finally {
                if (config('ai.providers.deepseek.reasoning_effort') !== $before) {
                    throw new \LogicException('AI configuration leaked.');
                }
            }

            return ['effort' => $effort, 'restored' => $before];
        });
        foreach ([[0, 'low', false, 200], [1, 'high', false, 200], [0, 'low', false, 200],
            [null, 'low', false, 403], [1, 'none', true, 500], [0, null, false, 200]] as [$actor, $effort, $fail, $status]) {
            $request = Request::create('/octane-test/ai-effort'.($fail ? '?fail=1' : ''), server: ['HTTP_ACCEPT' => 'application/json']);
            $request->attributes->set('octane_test_actor', $actor === null ? null : $actors[$actor]);
            $request->attributes->set('octane_test_effort', $effort);
            $this->worker->handle($request, new RequestContext);
            $response = array_pop($this->responses);
            self::assertSame($status, $response->getStatusCode(), $response->getContent());
            if ($status === 200) {
                self::assertSame(['effort' => $effort ?? $baseline, 'restored' => $baseline], json_decode($response->getContent(), true));
            }
            self::assertSame($baseline, $this->worker->application()->make('config')->get('ai.providers.deepseek.reasoning_effort'));
        }
    }

    public function test_transcription_credentials_refresh_and_restore_across_authorised_requests(): void
    {
        self::assertSame(0, Artisan::call('migrate', ['--force' => true]));
        app(PermissionSynchronizer::class)->sync();
        $actors = Admin::factory()->count(2)->create();
        $role = Role::create(['name' => 'asr-settings-test', 'guard_name' => 'admin']);
        $role->givePermissionTo(IamPermission::PanelAccess->value, PreferencePermissionContributor::MANAGE_AI);
        foreach ($actors as $actor) {
            AccountAuthorization::create(['principal_provider' => 'admins', 'principal_id' => (string) $actor->id,
                'role_ids' => [$role->id], 'organization_ids' => []]);
        }
        $baseline = config('ai.providers.tencent');
        $this->worker->application()->make('router')->post('/octane-test/asr', static function (Request $request): array {
            Filament::setCurrentPanel(Filament::getPanel('admin'));
            if ($actor = $request->attributes->get('octane_test_actor')) {
                auth('admin')->setUser($actor);
            }
            abort_if(app(SettingsAccess::class)->principal(PreferencePermissionContributor::MANAGE_AI) === null, 403);
            $settings = new AiSettings;
            $settings->providers = ['tencent' => ['secret_id' => 'AKIDsynthetic'.$actor->id, 'key' => $request->input('key')]];
            $settings->save();
            $before = config('ai.providers.tencent');
            app(AiManager::class)->transcriptionProvider('tencent');
            try {
                $credentialHash = app(AiConfiguration::class)->run(static function () use ($request): string {
                    $credentials = app(AiManager::class)->transcriptionProvider('tencent')->providerCredentials();
                    abort_if($request->boolean('fail'), 500, 'Synthetic ASR configuration failure');

                    return hash('sha256', $credentials['key']);
                });
            } finally {
                if (config('ai.providers.tencent') !== $before
                    || app(AiManager::class)->transcriptionProvider('tencent')->providerCredentials()['key'] !== $before['key']) {
                    throw new \LogicException('Transcription credentials leaked.');
                }
            }

            return ['credential_hash' => $credentialHash];
        });
        foreach ([[0, 'synthetic-a', false, 200], [1, 'synthetic-b', false, 200], [0, 'synthetic-a', false, 200],
            [null, 'missing', false, 403], [1, 'synthetic-failure', true, 500], [0, 'synthetic-after', false, 200]] as [$index, $key, $fail, $status]) {
            $request = Request::create('/octane-test/asr', 'POST', ['key' => $key, 'fail' => $fail], server: ['HTTP_ACCEPT' => 'application/json']);
            $request->attributes->set('octane_test_actor', $index === null ? null : $actors[$index]);
            $this->worker->handle($request, new RequestContext);
            $response = array_pop($this->responses);
            self::assertSame($status, $response->getStatusCode(), $response->getContent());
            if ($status === 200) {
                self::assertSame(['credential_hash' => hash('sha256', $key)], json_decode($response->getContent(), true));
            }
            self::assertSame($baseline, $this->worker->application()->make('config')->get('ai.providers.tencent'));
        }
    }

    private function request(?Admin $actor = null, array $data = [], int $status = 200): array
    {
        $request = Request::create('/octane-test/system-settings', $data === [] ? 'GET' : 'POST', $data, server: ['HTTP_ACCEPT' => 'application/json']);
        $request->attributes->set('octane_test_actor', $actor);
        $this->worker->handle($request, new RequestContext);
        $response = array_pop($this->responses);
        self::assertNotNull($response);
        self::assertSame($status, $response->getStatusCode(), $response->getContent());

        return json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
