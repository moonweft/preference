<?php

declare(strict_types=1);

namespace Moonweft\Preference\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\Contracts\Client;
use Laravel\Octane\OctaneResponse;
use Laravel\Octane\RequestContext;
use Laravel\Octane\Worker;
use MoonWeft\Iam\AccountSecurityPolicy;
use MoonWeft\Iam\AdminMfaSession;
use MoonWeft\Iam\IamPermission;
use MoonWeft\Iam\Models\AccountAuthorization;
use MoonWeft\Iam\Models\Admin;
use MoonWeft\Iam\PermissionSynchronizer;
use MoonWeft\Iam\SecurityConfirmation;
use Moonweft\Preference\Filament\Pages\SecuritySettingsPage;
use Moonweft\Preference\PreferencePermissionContributor;
use Moonweft\Preference\Security\SecuritySettings;
use PHPUnit\Framework\TestCase;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AccountSecurityOctaneTest extends TestCase
{
    public function test_policy_refreshes_and_authority_does_not_leak_between_requests_in_one_worker(): void
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
        $worker->boot();

        try {
            self::assertSame(0, Artisan::call('migrate', ['--force' => true]));
            app(PermissionSynchronizer::class)->sync();
            $actors = Admin::factory()->count(2)->create();
            $role = Role::create(['name' => 'security-octane', 'guard_name' => 'admin']);
            $role->givePermissionTo(IamPermission::PanelAccess->value, PreferencePermissionContributor::MANAGE_SECURITY);
            foreach ($actors as $actor) {
                $actor->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
                AccountAuthorization::create(['principal_provider' => 'admins', 'principal_id' => (string) $actor->id,
                    'role_ids' => [$role->id], 'organization_ids' => []]);
            }
            self::assertFalse($worker->application()->make(AccountSecurityPolicy::class)->requiresMultiFactorAuthentication());
            $worker->application()->make('router')->match(['GET', 'POST'], '/octane-test/account-security', static function (Request $request): array {
                $beforeActor = auth('admin')->id();
                Filament::setCurrentPanel(Filament::getPanel('admin'));
                if ($actor = $request->attributes->get('security_test_actor')) {
                    auth('admin')->setUser($actor);
                }
                $request->setLaravelSession(app('session.store'));
                $settings = app(SecuritySettings::class);
                $before = [$settings->password_min_length, $settings->mfa_required];
                if ($request->isMethod('POST')) {
                    $settings->password_min_length = $request->integer('length');
                    $settings->mfa_required = $request->boolean('mfa');
                    abort_if($request->boolean('fail'), 500, 'Synthetic account security failure');
                    if ($actor) {
                        app(SecurityConfirmation::class)->verifyCurrentCredential('password', null);
                        app(AdminMfaSession::class)->confirm($actor);
                    }
                    $settings->save();
                }
                $policy = app(AccountSecurityPolicy::class);

                return ['previous_actor' => $beforeActor, 'before' => $before,
                    'minimum' => $settings->password_min_length, 'mfa' => $policy->requiresMultiFactorAuthentication(),
                    'short_password_passes' => Validator::make(['password' => 'twelvechars!'], ['password' => $policy->passwordRule()])->passes(),
                    'can_edit' => SecuritySettingsPage::canAccess()];
            });

            $previous = [12, false];
            foreach ([[0, 16, true, false, 200], [1, 12, false, false, 200], [0, 20, true, false, 200],
                [null, 12, false, false, 403], [1, 18, false, true, 500], [0, 12, false, false, 200]] as [$index, $length, $mfa, $fail, $status]) {
                $request = Request::create('/octane-test/account-security', 'POST', ['length' => $length, 'mfa' => $mfa, 'fail' => $fail], server: ['HTTP_ACCEPT' => 'application/json']);
                $request->attributes->set('security_test_actor', $index === null ? null : $actors[$index]);
                $worker->handle($request, new RequestContext);
                $response = array_pop($responses);
                self::assertSame($status, $response->getStatusCode(), $response->getContent());
                if ($status === 200) {
                    self::assertSame(['previous_actor' => null, 'before' => $previous, 'minimum' => $length,
                        'mfa' => $mfa, 'short_password_passes' => $length === 12, 'can_edit' => true], json_decode($response->getContent(), true));
                    $previous = [$length, $mfa];
                }
            }
            $worker->handle(Request::create('/octane-test/account-security', server: ['HTTP_ACCEPT' => 'application/json']), new RequestContext);
            $response = array_pop($responses);
            self::assertSame(['previous_actor' => null, 'before' => [12, false], 'minimum' => 12,
                'mfa' => false, 'short_password_passes' => true, 'can_edit' => false], json_decode($response->getContent(), true));
        } finally {
            $worker->terminate();
            unset($_ENV['APP_RUNNING_IN_CONSOLE']);
        }
    }
}
