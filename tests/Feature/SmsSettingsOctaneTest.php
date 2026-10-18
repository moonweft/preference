<?php

declare(strict_types=1);

namespace Moonweft\Preference\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\Contracts\Client;
use Laravel\Octane\OctaneResponse;
use Laravel\Octane\RequestContext;
use Laravel\Octane\Worker;
use MoonWeft\Iam\IamPermission;
use MoonWeft\Iam\Models\AccountAuthorization;
use MoonWeft\Iam\Models\Admin;
use MoonWeft\Iam\PermissionSynchronizer;
use Moonweft\Preference\Filament\Pages\SmsSettingsPage;
use Moonweft\Preference\PreferencePermissionContributor;
use Moonweft\Preference\Sms\SmsClient;
use Moonweft\Preference\Sms\SmsSettings;
use Overtrue\EasySms\EasySms;
use PHPUnit\Framework\TestCase;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class SmsSettingsOctaneTest extends TestCase
{
    public function test_credentials_and_permissions_refresh_in_one_worker_after_actor_changes_and_failure(): void
    {
        $_ENV['APP_RUNNING_IN_CONSOLE'] = false;
        $responses = [];
        $client = $this->createMock(Client::class);
        $client->method('respond')->willReturnCallback(static function (RequestContext $context, OctaneResponse $response) use (&$responses): void {
            $responses[] = $response->response;
        });
        $client->method('error')->willReturnCallback(static function (Throwable $error, Application $app, Request $request, RequestContext $context) use (&$responses): void {
            $responses[] = new Response($error->getMessage(), 500);
        });
        $worker = new Worker(new ApplicationFactory(getcwd()), $client);

        try {
            $worker->boot();
            self::assertSame(0, Artisan::call('migrate', ['--force' => true]));
            app(PermissionSynchronizer::class)->sync();
            $actors = Admin::factory()->count(2)->create();
            foreach ($actors as $actor) {
                $role = Role::create(['name' => 'sms-worker-'.$actor->id, 'guard_name' => 'admin']);
                $role->givePermissionTo(IamPermission::PanelAccess->value, PreferencePermissionContributor::MANAGE_SMS);
                if (class_exists(AccountAuthorization::class)) {
                    AccountAuthorization::create(['principal_provider' => 'admins', 'principal_id' => (string) $actor->id,
                        'role_ids' => [$role->id], 'organization_ids' => []]);
                } else {
                    $actor->assignRole($role);
                }
            }
            self::assertFalse($worker->application()->make(SmsSettings::class)->enabled);
            $defaultLocale = $worker->application()->getLocale();
            $worker->application()->make('router')->post('/octane-test/sms-settings', static function (Request $request): array {
                $before = auth('admin')->id();
                $localeBefore = app()->getLocale();
                app()->setLocale($request->input('locale'));
                Filament::setCurrentPanel(Filament::getPanel('admin'));
                if ($actor = $request->attributes->get('sms_test_actor')) {
                    auth('admin')->setUser($actor);
                }
                abort_unless(SmsSettingsPage::canAccess(), 403);
                $settings = app(SmsSettings::class);
                $previous = $settings->providers['yunpian']['api_key'] ?? null;
                $settings->enabled = true;
                $settings->default_gateway = 'yunpian';
                $settings->backup_gateway = 'aliyun';
                $settings->failover_enabled = $request->boolean('failover');
                $settings->providers = [
                    'yunpian' => ['api_key' => $request->input('key')],
                    'aliyun' => ['access_key_id' => 'backup-id', 'access_key_secret' => $request->input('key').'-backup', 'sign_name' => 'MoonWeft'],
                ];
                abort_if($request->boolean('fail'), 500, 'Synthetic SMS configuration failure');
                $settings->save();
                $sms = app(EasySms::class);
                $key = $sms->getConfig()->get('gateways.yunpian.api_key');
                $gateways = $sms->getConfig()->get('default.gateways');
                $backup = $sms->getConfig()->get('gateways.aliyun.access_key_secret');
                $sms->getConfig()['gateways'] = ['yunpian' => ['api_key' => 'mutated-client']];

                return [$before, $previous, $key, app(EasySms::class)->getConfig()->get('gateways.yunpian.api_key'), $gateways, $backup, $sms instanceof SmsClient, $localeBefore, SmsSettingsPage::getNavigationLabel(), __(SmsSettings::GATEWAYS['aliyun']['label'])];
            });

            $previous = null;
            foreach ([[0, 'key-A', false, 200], [1, 'key-B', false, 200], [0, 'key-A', false, 200],
                [null, 'missing-identity', false, 403], [1, 'failed-key', true, 500], [0, 'recovered-key', false, 200]] as [$actor, $key, $fail, $status]) {
                $request = Request::create('/octane-test/sms-settings', 'POST', ['key' => $key, 'fail' => $fail, 'failover' => $actor === 0, 'locale' => $actor === 0 ? 'zh_CN' : 'en'], server: ['HTTP_ACCEPT' => 'application/json']);
                $request->attributes->set('sms_test_actor', $actor === null ? null : $actors[$actor]);
                $worker->handle($request, new RequestContext);
                $response = array_pop($responses);
                self::assertSame($status, $response->getStatusCode(), $response->getContent());
                if ($status === 200) {
                    self::assertSame([null, $previous, $key, $key, $actor === 0 ? ['yunpian', 'aliyun'] : ['yunpian'], $actor === 0 ? $key.'-backup' : null, true, $defaultLocale, $actor === 0 ? '短信' : 'SMS', $actor === 0 ? '阿里云' : 'Alibaba Cloud'], json_decode($response->getContent(), true));
                    $previous = $key;
                }
            }

            $worker->handleTask(static function () use ($actors): void {
                Role::where('name', 'sms-worker-'.$actors[1]->id)->firstOrFail()->revokePermissionTo(PreferencePermissionContributor::MANAGE_SMS);
            });
            $request = Request::create('/octane-test/sms-settings', 'POST', ['key' => 'revoked', 'locale' => 'en'], server: ['HTTP_ACCEPT' => 'application/json']);
            $request->attributes->set('sms_test_actor', $actors[1]);
            $worker->handle($request, new RequestContext);
            self::assertSame(403, array_pop($responses)->getStatusCode());

            self::assertSame(['recovered-key', false, $defaultLocale], $worker->handleTask(static fn (): array => [
                app(EasySms::class)->getConfig()->get('gateways.yunpian.api_key'), SmsSettingsPage::canAccess(), app()->getLocale(),
            ])->result);
            $worker->handleTask(static function (): void {
                app(SmsSettings::class)->failover_enabled = false;
                app(SmsSettings::class)->providers = ['yunpian' => ['api_key' => 'unsaved-task-key']];
                app(EasySms::class)->getConfig()['gateways'] = ['yunpian' => ['api_key' => 'mutated-task-client']];
            });
            self::assertSame(['recovered-key', 'recovered-key-backup', ['yunpian', 'aliyun']], $worker->handleTask(static fn (): array => [
                app(EasySms::class)->getConfig()->get('gateways.yunpian.api_key'),
                app(EasySms::class)->getConfig()->get('gateways.aliyun.access_key_secret'),
                app(EasySms::class)->getConfig()->get('default.gateways'),
            ])->result);
        } finally {
            $worker->terminate();
            unset($_ENV['APP_RUNNING_IN_CONSOLE']);
        }
    }
}
