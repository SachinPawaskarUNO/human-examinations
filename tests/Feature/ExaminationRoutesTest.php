<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Tests\Feature;

use HumanExaminations\HumanExaminations\Models\Examination;
use HumanExaminations\HumanExaminations\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\DefineEnvironment;

class ExaminationRoutesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', str_repeat('r', 32));
        $app['config']->set('session.driver', 'array');
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }

    protected function defineRoutes($router): void
    {
        $router->get('/host/examinations/{examination}', fn (): string => 'Host workspace')
            ->name('host.examinations.show');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => realpath(__DIR__.'/../../database/migrations'),
            '--realpath' => true,
        ])->assertSuccessful();
    }

    public function test_routes_are_disabled_by_default(): void
    {
        $this->assertFalse(config('human-examinations.routes.enabled'));
        $this->assertFalse(Route::has('human-examinations.examinations.store'));
        $this->assertFalse(Route::has('human-examinations.examinations.update'));

        $this->postJson('/human-examinations/examinations')->assertNotFound();
        $this->patchJson('/human-examinations/examinations/1')->assertNotFound();
        foreach (['store', 'update', 'destroy'] as $action) {
            $this->assertFalse(Route::has('human-examinations.observations.'.$action));
        }
        $this->postJson('/human-examinations/examinations/1/observations')->assertNotFound();
        $this->patchJson('/human-examinations/examinations/1/observations/1')->assertNotFound();
        $this->deleteJson('/human-examinations/examinations/1/observations/1')->assertNotFound();
        $this->assertDatabaseCount('human_examinations', 0);
    }

    #[DefineEnvironment('enableRoutes')]
    public function test_default_middleware_blocks_guest_writes(): void
    {
        $this->postJson('/human-examinations/examinations', ['reference' => 'GUEST'])
            ->assertUnauthorized();
        $this->assertDatabaseCount('human_examinations', 0);

        $examination = Examination::create(['reference' => 'BEFORE']);
        $this->patchJson('/human-examinations/examinations/'.$examination->id, ['reference' => 'AFTER'])
            ->assertUnauthorized();
        $this->assertSame('BEFORE', $examination->fresh()->reference);
    }

    #[DefineEnvironment('enableRoutes')]
    public function test_an_authenticated_user_without_a_host_policy_is_denied(): void
    {
        $this->signIn();
        $this->postJson('/human-examinations/examinations')->assertForbidden();
        $this->assertDatabaseCount('human_examinations', 0);

        $examination = Examination::create(['reference' => 'BEFORE']);
        $this->patchJson('/human-examinations/examinations/'.$examination->id, ['reference' => 'AFTER'])
            ->assertForbidden();
        $this->assertSame('BEFORE', $examination->fresh()->reference);
    }

    #[DefineEnvironment('enableRoutes')]
    public function test_host_policy_denials_happen_before_validation_or_writes(): void
    {
        Gate::policy(Examination::class, ExaminationRoutePolicy::class);
        $this->signIn(2);

        $this->postJson('/human-examinations/examinations', ['reference' => ['invalid']])
            ->assertForbidden();
        $this->assertDatabaseCount('human_examinations', 0);

        $examination = Examination::create(['reference' => 'BEFORE']);
        $this->patchJson('/human-examinations/examinations/'.$examination->id, ['reference' => ['invalid']])
            ->assertForbidden();
        $this->assertSame('BEFORE', $examination->fresh()->reference);
    }

    #[DefineEnvironment('enableRoutes')]
    public function test_an_authorized_user_can_create_an_examination_and_return_to_the_host_page(): void
    {
        Gate::policy(Examination::class, ExaminationRoutePolicy::class);
        $this->signIn();

        $this->from('/host')->post('/human-examinations/examinations', [
            'reference' => 'EX-001',
            'examiner' => 'Examiner',
            'date' => '2026-11-14',
            'notes' => 'Draft notes',
        ])->assertRedirect('/host');

        $this->assertDatabaseHas('human_examinations', [
            'reference' => 'EX-001',
            'examiner' => 'Examiner',
            'date' => '2026-11-14',
            'notes' => 'Draft notes',
        ]);
    }

    #[DefineEnvironment('enableRoutes')]
    public function test_updates_respect_the_host_policy_for_each_examination(): void
    {
        Gate::policy(Examination::class, ExaminationRoutePolicy::class);
        $this->signIn();
        $allowed = Examination::create(['reference' => 'ALLOWED']);
        $denied = Examination::create(['reference' => 'DENIED']);

        $this->from('/host')->patch('/human-examinations/examinations/'.$allowed->id, ['notes' => 'Updated'])
            ->assertRedirect('/host');
        $this->assertSame('ALLOWED', $allowed->fresh()->reference);
        $this->assertSame('Updated', $allowed->fresh()->notes);

        $this->patchJson('/human-examinations/examinations/'.$denied->id, ['reference' => 'CHANGED'])
            ->assertForbidden();
        $this->assertSame('DENIED', $denied->fresh()->reference);
    }

    #[DefineEnvironment('customRoutes')]
    public function test_url_and_name_prefixes_can_be_configured(): void
    {
        Gate::policy(Examination::class, ExaminationRoutePolicy::class);
        $this->signIn();

        $this->assertFalse(Route::has('human-examinations.examinations.store'));
        $this->assertSame('/tools/examinations/examinations', route('forensics.examinations.store', [], false));
        $this->assertSame('/tools/examinations/examinations/1', route('forensics.examinations.update', 1, false));

        $this->postJson('/human-examinations/examinations')->assertNotFound();
        $this->postJson(route('forensics.examinations.store'), ['reference' => 'CUSTOM'])
            ->assertRedirect();
        $this->patchJson(route('forensics.examinations.update', 1), ['notes' => 'Custom URL'])
            ->assertRedirect();
        $this->assertDatabaseHas('human_examinations', ['reference' => 'CUSTOM', 'notes' => 'Custom URL']);
    }

    #[DefineEnvironment('emptyPrefixes')]
    public function test_empty_url_and_name_prefixes_are_supported(): void
    {
        Gate::policy(Examination::class, ExaminationRoutePolicy::class);
        $this->signIn();

        $this->assertSame('/examinations', route('examinations.store', [], false));
        $this->postJson(route('examinations.store'), ['reference' => 'ROOT'])->assertRedirect();
        $this->assertDatabaseHas('human_examinations', ['reference' => 'ROOT']);
    }

    #[DefineEnvironment('customMiddleware')]
    public function test_configured_middleware_applies_to_both_write_routes(): void
    {
        Gate::policy(Examination::class, ExaminationRoutePolicy::class);
        Gate::define('enter-examinations', fn (User $user): bool => false);
        $this->signIn();

        $this->postJson('/human-examinations/examinations')->assertForbidden();
        $this->assertDatabaseCount('human_examinations', 0);

        $examination = Examination::create(['reference' => 'BEFORE']);
        $this->patchJson('/human-examinations/examinations/'.$examination->id, ['reference' => 'AFTER'])
            ->assertForbidden();
        $this->assertSame('BEFORE', $examination->fresh()->reference);
    }

    #[DefineEnvironment('webMiddlewareOnly')]
    public function test_removing_auth_middleware_does_not_bypass_authorization(): void
    {
        Gate::policy(Examination::class, ExaminationRoutePolicy::class);

        $this->postJson('/human-examinations/examinations')->assertForbidden();
        $this->assertDatabaseCount('human_examinations', 0);

        $examination = Examination::create(['reference' => 'BEFORE']);
        $this->patchJson('/human-examinations/examinations/'.$examination->id, ['reference' => 'AFTER'])
            ->assertForbidden();
        $this->assertSame('BEFORE', $examination->fresh()->reference);
    }

    #[DefineEnvironment('enableRoutes')]
    public function test_an_authorized_create_uses_the_configured_workspace_redirect(): void
    {
        Gate::policy(Examination::class, ExaminationRoutePolicy::class);
        $this->signIn();
        config()->set('human-examinations.workspace_route', 'host.examinations.show');

        $this->post('/human-examinations/examinations', ['reference' => 'REDIRECT'])
            ->assertRedirect(route('host.examinations.show', 1));
        $this->assertDatabaseHas('human_examinations', ['reference' => 'REDIRECT']);
    }

    #[DefineEnvironment('noMiddleware')]
    public function test_empty_middleware_keeps_model_binding_and_per_record_authorization(): void
    {
        Gate::policy(Examination::class, ExaminationRoutePolicy::class);
        $this->signIn();
        $allowed = Examination::create(['reference' => 'ALLOWED']);
        $denied = Examination::create(['reference' => 'DENIED']);

        $this->from('/host')->patch('/human-examinations/examinations/'.$allowed->id, ['notes' => 'Updated'])
            ->assertRedirect('/host');
        $this->assertSame('Updated', $allowed->fresh()->notes);

        $this->patchJson('/human-examinations/examinations/'.$denied->id, ['reference' => 'CHANGED'])
            ->assertForbidden();
        $this->assertSame('DENIED', $denied->fresh()->reference);
    }

    #[DefineEnvironment('enableRoutes')]
    public function test_authorized_requests_still_validate_their_payloads(): void
    {
        Gate::policy(Examination::class, ExaminationRoutePolicy::class);
        $this->signIn();

        $this->postJson('/human-examinations/examinations', ['reference' => ['invalid']])
            ->assertUnprocessable()->assertJsonValidationErrors('reference');
        $this->assertDatabaseCount('human_examinations', 0);

        $examination = Examination::create(['reference' => 'BEFORE']);
        $this->patchJson('/human-examinations/examinations/'.$examination->id, ['reference' => ['invalid']])
            ->assertUnprocessable()->assertJsonValidationErrors('reference');
        $this->assertSame('BEFORE', $examination->fresh()->reference);
    }

    protected function enableRoutes($app): void
    {
        $app['config']->set('human-examinations.routes.enabled', true);
    }

    protected function customRoutes($app): void
    {
        $this->enableRoutes($app);
        $app['config']->set('human-examinations.routes.prefix', 'tools/examinations');
        $app['config']->set('human-examinations.routes.name_prefix', 'forensics.');
    }

    protected function emptyPrefixes($app): void
    {
        $this->enableRoutes($app);
        $app['config']->set('human-examinations.routes.prefix', '');
        $app['config']->set('human-examinations.routes.name_prefix', '');
    }

    protected function customMiddleware($app): void
    {
        $this->enableRoutes($app);
        $app['config']->set('human-examinations.routes.middleware', ['web', 'auth', 'can:enter-examinations']);
    }

    protected function webMiddlewareOnly($app): void
    {
        $this->enableRoutes($app);
        $app['config']->set('human-examinations.routes.middleware', ['web']);
    }

    protected function noMiddleware($app): void
    {
        $this->enableRoutes($app);
        $app['config']->set('human-examinations.routes.middleware', []);
    }

    private function signIn(int $id = 1): void
    {
        $this->actingAs((new User)->forceFill(['id' => $id]));
    }
}

class ExaminationRoutePolicy
{
    public function create(User $user): bool
    {
        return $user->getAuthIdentifier() === 1;
    }

    public function update(User $user, Examination $examination): bool
    {
        return $user->getAuthIdentifier() === 1 && $examination->id === 1;
    }
}
