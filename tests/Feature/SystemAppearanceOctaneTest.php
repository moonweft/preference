<?php

declare(strict_types=1);

namespace Moonweft\Preference\Tests\Feature;

use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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
use Moonweft\Preference\Filament\Pages\SystemSettingsPage;
use Moonweft\Preference\PreferencePermissionContributor;
use Moonweft\Preference\System\Appearance;
use Moonweft\Preference\System\SystemSettings;
use PHPUnit\Framework\TestCase;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class SystemAppearanceOctaneTest extends TestCase
{
    public function test_appearance_refreshes_in_one_worker_without_leaking_failed_changes_or_authority(): void
    {
        $_ENV['APP_RUNNING_IN_CONSOLE'] = false;
        $response = null;
        $client = $this->createMock(Client::class);
        $client->method('respond')->willReturnCallback(static function (RequestContext $context, OctaneResponse $result) use (&$response): void {
            $response = $result->response;
        });
        $client->method('error')->willReturnCallback(static function (Throwable $error, Application $app, Request $request, RequestContext $context) use (&$response): void {
            $response = new Response($error->getMessage(), 500);
        });
        $worker = new Worker(new ApplicationFactory(getcwd()), $client);
        $worker->boot();

        try {
            self::assertSame(0, Artisan::call('migrate', ['--force' => true]));
            app(PermissionSynchronizer::class)->sync();
            $actors = Admin::factory()->count(2)->create();
            $role = Role::create(['name' => 'appearance-octane', 'guard_name' => 'admin']);
            $role->givePermissionTo(IamPermission::PanelAccess->value, PreferencePermissionContributor::MANAGE_SYSTEM);
            foreach ($actors as $actor) {
                AccountAuthorization::create([
                    'principal_provider' => 'admins', 'principal_id' => (string) $actor->id,
                    'role_ids' => [$role->id], 'organization_ids' => [],
                ]);
            }

            self::assertSame('blue', $worker->application()->make(SystemSettings::class)->primary_color);
            FilamentColor::getColors();
            $worker->application()->make('router')->match(['GET', 'POST'], '/octane-test/appearance', static function (Request $request): array {
                $previousActor = auth('admin')->id();
                Filament::setCurrentPanel($panel = Filament::getPanel('admin'));
                if ($actor = $request->attributes->get('test_actor')) {
                    auth('admin')->setUser($actor);
                }
                $settings = app(SystemSettings::class);
                if ($request->isMethod('POST')) {
                    $settings->fill($request->only(['primary_color', 'gray_color', 'panel_theme']));
                    if ($request->boolean('fail')) {
                        Filament::bootCurrentPanel();
                        FilamentColor::getColors();
                        abort(500, 'Synthetic appearance failure');
                    }
                    $settings->save();

                    return [];
                }
                Filament::bootCurrentPanel();

                return [
                    $previousActor, SystemSettingsPage::canAccess(),
                    FilamentColor::getColor('primary'), FilamentColor::getColor('gray'),
                    $panel->hasDarkMode(), $panel->hasDarkModeForced(), $panel->hasThemeSwitcher(),
                    str_contains(FilamentView::renderHook(PanelsRenderHook::HEAD_START)->toHtml(), 'data-system-theme'),
                    str_contains($panel->getTheme()->getHtml()->toHtml(), app(Vite::class)->asset(Appearance::THEMES[$settings->panel_theme])),
                    $panel->hasTopbar(),
                ];
            });

            foreach ([[0, 'violet', 'stone', 'sharp'], [1, 'emerald', 'zinc', 'orbit'], [0, 'blue', 'slate', 'sharp']] as [$actor, $primary, $gray, $theme]) {
                $request = Request::create('/octane-test/appearance', 'POST', ['primary_color' => $primary, 'gray_color' => $gray, 'panel_theme' => $theme], server: ['HTTP_ACCEPT' => 'application/json']);
                $request->attributes->set('test_actor', $actors[$actor]);
                $worker->handle($request, new RequestContext);
                self::assertSame(200, $response->getStatusCode(), $response->getContent());

                $worker->handle(Request::create('/octane-test/appearance'), new RequestContext);
                self::assertSame([null, false, Color::all()[$primary], Color::all()[$gray], true, false, true, false, true, $theme !== 'sharp'], json_decode($response->getContent(), true));
            }

            // Both missing authority and an exception must leave the shared appearance intact.
            foreach ([[null, false, 403], [$actors[1], true, 500]] as [$actor, $fail, $status]) {
                $request = Request::create('/octane-test/appearance', 'POST', ['primary_color' => 'rose', 'panel_theme' => 'orbit', 'fail' => $fail], server: ['HTTP_ACCEPT' => 'application/json']);
                $request->attributes->set('test_actor', $actor);
                $worker->handle($request, new RequestContext);
                self::assertSame($status, $response->getStatusCode());
                $worker->handle(Request::create('/octane-test/appearance'), new RequestContext);
                self::assertSame([null, false, Color::Blue, Color::Slate, true, false, true, false, true, false], json_decode($response->getContent(), true));
            }

            $worker->handleTask(static function () use ($role): void {
                $role->revokePermissionTo(PreferencePermissionContributor::MANAGE_SYSTEM);
            });
            $request = Request::create('/octane-test/appearance', 'POST', ['primary_color' => 'rose'], server: ['HTTP_ACCEPT' => 'application/json']);
            $request->attributes->set('test_actor', $actors[0]);
            $worker->handle($request, new RequestContext);
            self::assertSame(403, $response->getStatusCode());

            $reporter = $this->createMock(ReportException::class);
            $reporter->expects(self::once())->method('handle')->with(self::callback(
                static fn (WorkerErrorOccurred $event): bool => $event->exception->getMessage() === 'Synthetic appearance task failure',
            ));
            $worker->application()->instance(ReportException::class, $reporter);
            self::assertInstanceOf(TaskExceptionResult::class, $worker->handleTask(static function (): void {
                app(SystemSettings::class)->primary_color = 'rose';
                app(SystemSettings::class)->panel_theme = 'orbit';
                throw new \RuntimeException('Synthetic appearance task failure');
            }));
            self::assertSame(['blue', 'sharp', false], $worker->handleTask(static fn (): array => [
                app(SystemSettings::class)->primary_color, app(SystemSettings::class)->panel_theme, SystemSettingsPage::canAccess(),
            ])->result);
        } finally {
            $worker->terminate();
            unset($_ENV['APP_RUNNING_IN_CONSOLE']);
        }
    }
}
